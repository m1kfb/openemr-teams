<?php

/**
 * Keeps provider teams and patient care teams aligned (team → patient automatic; patient → team opt-in).
 *
 * Intentionally not `final` so unit tests can substitute collaborators. Related test seams live on
 * {@see CareTeamBridge} (`fetchCareTeamData`, `saveCareTeamPayload`, `detectModernSchema`).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Service;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamReconciler;
use OpenEMR\Modules\ProviderTeams\Domain\CareTeamSyncBatchResult;
use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamBridge;
use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamLinkStore;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;

class CareTeamSync
{
    public function __construct(
        private readonly TeamStore $teams = new TeamStore(),
        private readonly CareTeamBridge $careTeams = new CareTeamBridge(),
        private readonly CareTeamLinkStore $links = new CareTeamLinkStore(),
        private readonly CareTeamReconciler $reconciler = new CareTeamReconciler(),
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->careTeams->isSupported();
    }

    /**
     * After staff or roster changes on a provider team, refresh care teams for all assigned patients.
     */
    public function syncTeamToAllPatients(int $teamId): CareTeamSyncBatchResult
    {
        return $this->syncTeamToPatients($teamId, $this->teams->activePatientIds($teamId));
    }

    /**
     * @param list<int> $patientIds
     */
    public function syncTeamToPatients(int $teamId, array $patientIds): CareTeamSyncBatchResult
    {
        if (!$this->careTeams->isSupported() || CareTeamSyncGuard::shouldSkipTeamToPatient()) {
            return new CareTeamSyncBatchResult(0, 0, []);
        }
        $team = $this->teams->find($teamId);
        if ($team === null || (int) $team['active'] !== 1) {
            return new CareTeamSyncBatchResult(0, 0, []);
        }

        $patientIds = $this->uniquePositive($patientIds);
        if ($patientIds === []) {
            return new CareTeamSyncBatchResult(0, 0, []);
        }

        $labels = $this->patientLabelsByPid($teamId);
        $failed = [];
        $succeeded = 0;

        CareTeamSyncGuard::runTeamToPatient(function () use ($teamId, $patientIds, $labels, &$failed, &$succeeded): void {
            $memberIds = $this->teams->activeMemberIds($teamId);
            foreach ($patientIds as $pid) {
                try {
                    $this->applyTeamToPatient($teamId, $pid, $memberIds);
                    ++$succeeded;
                } catch (\Throwable $exception) {
                    $label = $labels[$pid] ?? $this->teams->patientDisplay($pid);
                    $failed[] = [
                        'pid' => $pid,
                        'name' => $label['name'],
                        'pubpid' => $label['pubpid'],
                        'reason' => CareTeamSyncFailureMessage::forPatient($teamId, $pid, $exception),
                    ];
                }
            }
        });

        return new CareTeamSyncBatchResult(count($patientIds), $succeeded, $failed);
    }

    /**
     * After assigning or changing membership for one patient on a team.
     */
    public function syncTeamToPatient(int $teamId, int $pid): void
    {
        if (!$this->careTeams->isSupported() || CareTeamSyncGuard::shouldSkipTeamToPatient()) {
            return;
        }
        CareTeamSyncGuard::runTeamToPatient(function () use ($teamId, $pid): void {
            $this->applyTeamToPatient($teamId, $pid, $this->teams->activeMemberIds($teamId));
        });
    }

    /**
     * Remove module-added care-team members when a patient leaves the provider team.
     */
    public function clearModuleCareTeamForPatient(int $teamId, int $pid, bool $requireInactiveTeam = false): void
    {
        if (!$this->careTeams->isSupported()) {
            return;
        }
        CareTeamSyncGuard::runTeamToPatient(function () use ($teamId, $pid, $requireInactiveTeam): void {
            $this->links->withPatientTeamLock($teamId, $pid, function () use ($teamId, $pid, $requireInactiveTeam): void {
                if ($requireInactiveTeam) {
                    $this->ensureTeamInactiveForDeactivationCleanup($teamId);
                }
                $tracked = $this->links->activeTrackedUserIds($teamId, $pid);
                if ($tracked === []) {
                    return;
                }
                $this->careTeams->applyUserChanges($pid, [], $tracked);
                $this->links->forgetActiveLinksForPatientTeam($teamId, $pid);
            });
        });
    }

    /**
     * @param list<int> $patientIds
     */
    public function clearModuleCareTeamForPatients(
        int $teamId,
        array $patientIds,
        bool $requireInactiveTeam = false,
    ): CareTeamSyncBatchResult {
        if (!$this->careTeams->isSupported()) {
            return new CareTeamSyncBatchResult(0, 0, []);
        }

        $patientIds = $this->uniquePositive($patientIds);
        if ($patientIds === []) {
            return new CareTeamSyncBatchResult(0, 0, []);
        }

        $labels = $this->patientLabelsByPid($teamId);
        $failed = [];
        $succeeded = 0;

        CareTeamSyncGuard::runTeamToPatient(function () use (
            $teamId,
            $patientIds,
            $labels,
            $requireInactiveTeam,
            &$failed,
            &$succeeded,
        ): void {
            foreach ($patientIds as $pid) {
                try {
                    $this->clearModuleCareTeamForPatient($teamId, $pid, $requireInactiveTeam);
                    ++$succeeded;
                } catch (TeamActiveAgainException) {
                    $label = $labels[$pid] ?? $this->teams->patientDisplay($pid);
                    $failed[] = [
                        'pid' => $pid,
                        'name' => $label['name'],
                        'pubpid' => $label['pubpid'],
                        'reason' => CareTeamSyncFailureMessage::teamActiveAgain(),
                    ];
                } catch (\Throwable $exception) {
                    $label = $labels[$pid] ?? $this->teams->patientDisplay($pid);
                    $failed[] = [
                        'pid' => $pid,
                        'name' => $label['name'],
                        'pubpid' => $label['pubpid'],
                        'reason' => CareTeamSyncFailureMessage::forPatient($teamId, $pid, $exception),
                    ];
                }
            }
        });

        return new CareTeamSyncBatchResult(count($patientIds), $succeeded, $failed);
    }

    /**
     * Clear module-added care-team members for every patient still assigned to a team.
     */
    public function clearModuleCareTeamForAllPatients(int $teamId): CareTeamSyncBatchResult
    {
        return $this->clearModuleCareTeamForPatients($teamId, $this->teams->activePatientIds($teamId), false);
    }

    private function ensureTeamInactiveForDeactivationCleanup(int $teamId): void
    {
        $team = $this->teams->find($teamId);
        if ($team === null || (int) $team['active'] === 1) {
            throw new TeamActiveAgainException();
        }
    }

    /**
     * Clear a per-patient exclusion and add the provider-team member back to the care team.
     */
    public function restoreExcludedMember(int $teamId, int $pid, int $userId): void
    {
        if (!$this->careTeams->isSupported()) {
            return;
        }
        if ($userId <= 0 || !$this->links->isExcluded($teamId, $pid, $userId)) {
            return;
        }
        if (!in_array($userId, $this->teams->activeMemberIds($teamId), true)) {
            return;
        }
        CareTeamSyncGuard::runTeamToPatient(function () use ($teamId, $pid, $userId): void {
            $this->links->withPatientTeamLock($teamId, $pid, function () use ($teamId, $pid, $userId): void {
                $this->links->clearExclusion($teamId, $pid, $userId);
                $this->applyTeamToPatientLocked($teamId, $pid, $this->teams->activeMemberIds($teamId));
            });
        });
    }

    /**
     * Explicit opt-in: apply one patient's care team to provider-team staff, then refresh all patients.
     *
     * @return array{
     *   team_id: int,
     *   team_name: string,
     *   patient_count: int,
     *   added: int,
     *   removed: int,
     *   care_team_sync: CareTeamSyncBatchResult
     * }|null
     */
    public function applyPatientCareTeamToProviderTeam(int $pid): ?array
    {
        if (!$this->careTeams->isSupported() || CareTeamSyncGuard::shouldSkipPatientToTeam()) {
            return null;
        }
        $assignment = $this->teams->activeTeamForPatient($pid);
        if ($assignment === null) {
            return null;
        }
        $teamId = (int) $assignment['team_id'];
        $teamName = (string) $assignment['team_name'];

        $summary = CareTeamSyncGuard::runPatientToTeam(function () use ($pid, $teamId, $teamName): array {
            $patientUsers = $this->careTeams->activeUserIdsForPatient($pid);
            $teamMembers = $this->teams->activeMemberIds($teamId);
            $moduleLinked = $this->links->activeTrackedUserIds($teamId, $pid);

            $plan = $this->reconciler->planPatientToTeam($patientUsers, $teamMembers, $moduleLinked);

            foreach ($plan->addToTeamUserIds as $userId) {
                $this->teams->addMember($teamId, $userId);
            }
            foreach ($plan->removeFromTeamUserIds as $userId) {
                $this->teams->deactivateMember($teamId, $userId);
            }

            return [
                'team_id' => $teamId,
                'team_name' => $teamName,
                'patient_count' => $this->teams->activePatientCount($teamId),
                'added' => count($plan->addToTeamUserIds),
                'removed' => count($plan->removeFromTeamUserIds),
            ];
        });

        $batch = $this->syncTeamToAllPatients($teamId);

        return array_merge($summary, ['care_team_sync' => $batch]);
    }

    /**
     * @param list<int> $teamMemberUserIds
     */
    private function applyTeamToPatient(int $teamId, int $pid, array $teamMemberUserIds): void
    {
        $this->links->withPatientTeamLock($teamId, $pid, function () use ($teamId, $pid, $teamMemberUserIds): void {
            $this->applyTeamToPatientLocked($teamId, $pid, $teamMemberUserIds);
        });
    }

    /**
     * @param list<int> $teamMemberUserIds
     */
    private function applyTeamToPatientLocked(int $teamId, int $pid, array $teamMemberUserIds): void
    {
        $careUsers = $this->careTeams->activeUserIdsForPatient($pid);
        $presentUserIds = $this->careTeams->presentUserIdsForPatient($pid);
        $this->links->recordManualRemovals($teamId, $pid, $presentUserIds, $teamMemberUserIds);

        $tracked = $this->links->activeTrackedUserIds($teamId, $pid);
        $excluded = $this->links->excludedUserIds($teamId, $pid);
        $plan = $this->reconciler->planTeamToPatient($teamMemberUserIds, $careUsers, $tracked, $excluded);

        if ($plan->ensureUserIds === [] && $plan->removeModuleUserIds === []) {
            return;
        }

        $this->careTeams->applyUserChanges($pid, $plan->ensureUserIds, $plan->removeModuleUserIds);

        foreach ($plan->removeModuleUserIds as $userId) {
            $this->links->forgetUser($teamId, $pid, $userId);
        }

        foreach ($plan->ensureUserIds as $userId) {
            $memberId = $this->careTeams->findMemberId($pid, $userId);
            if ($memberId !== null) {
                $this->links->remember($teamId, $pid, $userId, $memberId);
            }
        }
    }

    /**
     * @return list<array{user_id: int, name: string, username: string}>
     */
    public function excludedMembersForPatient(int $pid): array
    {
        $assignment = $this->teams->activeTeamForPatient($pid);
        if ($assignment === null) {
            return [];
        }
        return $this->links->excludedMembers((int) $assignment['team_id'], $pid);
    }

    /**
     * @return array<int, array{pid: int, name: string, pubpid: string}>
     */
    private function patientLabelsByPid(int $teamId): array
    {
        $map = [];
        foreach ($this->teams->activePatients($teamId) as $patient) {
            $pid = (int) $patient['pid'];
            $map[$pid] = [
                'pid' => $pid,
                'name' => (string) $patient['name'],
                'pubpid' => (string) $patient['pubpid'],
            ];
        }
        return $map;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function uniquePositive(array $ids): array
    {
        $seen = [];
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
        }
        return $out;
    }
}
