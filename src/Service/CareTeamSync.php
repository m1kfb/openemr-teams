<?php

/**
 * Keeps provider teams and patient care teams aligned (team → patient automatic; patient → team opt-in).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Service;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamReconciler;
use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamBridge;
use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamLinkStore;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;

final class CareTeamSync
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
    public function syncTeamToAllPatients(int $teamId): void
    {
        if (!$this->careTeams->isSupported() || CareTeamSyncGuard::shouldSkipTeamToPatient()) {
            return;
        }
        $team = $this->teams->find($teamId);
        if ($team === null || (int) $team['active'] !== 1) {
            return;
        }
        CareTeamSyncGuard::runTeamToPatient(function () use ($teamId): void {
            $memberIds = $this->teams->activeMemberIds($teamId);
            foreach ($this->teams->activePatientIds($teamId) as $pid) {
                $this->applyTeamToPatient($teamId, $pid, $memberIds);
            }
        });
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
    public function clearModuleCareTeamForPatient(int $teamId, int $pid): void
    {
        if (!$this->careTeams->isSupported()) {
            return;
        }
        CareTeamSyncGuard::runTeamToPatient(function () use ($teamId, $pid): void {
            $tracked = $this->links->trackedUserIds($teamId, $pid);
            if ($tracked === []) {
                return;
            }
            $this->careTeams->applyUserChanges($pid, [], $tracked);
            $this->links->forgetAllForPatientTeam($teamId, $pid);
        });
    }

    /**
     * Explicit opt-in: apply one patient's care team to provider-team staff, then refresh all patients.
     *
     * @return array{team_id: int, team_name: string, patient_count: int, added: int, removed: int}|null
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
            $moduleLinked = $this->links->trackedUserIds($teamId, $pid);

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

        $this->syncTeamToAllPatients($teamId);

        return $summary;
    }

    /**
     * @param list<int> $teamMemberUserIds
     */
    private function applyTeamToPatient(int $teamId, int $pid, array $teamMemberUserIds): void
    {
        $careUsers = $this->careTeams->activeUserIdsForPatient($pid);
        $tracked = $this->links->trackedUserIds($teamId, $pid);
        $plan = $this->reconciler->planTeamToPatient($teamMemberUserIds, $careUsers, $tracked);

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
}
