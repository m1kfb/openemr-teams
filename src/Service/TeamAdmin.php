<?php

/**
 * Creating teams and assigning OpenEMR users and patients.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Service;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamSyncBatchResult;
use OpenEMR\Modules\ProviderTeams\Domain\ClinicTime;
use OpenEMR\Modules\ProviderTeams\Domain\DateSpan;
use OpenEMR\Modules\ProviderTeams\Http\CareTeamSyncFlash;
use OpenEMR\Modules\ProviderTeams\Persistence\DirectoryStore;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Persistence\VisitStore;

final class TeamAdmin
{
    public function __construct(
        private readonly TeamStore $teams,
        private readonly DirectoryStore $directory,
        private readonly CareTeamSync $careTeamSync,
        private readonly VisitStore $visits,
    ) {
    }

    /**
     * @return list<array{id: int, name: string, active: int, notes: string, member_count: int, patient_count: int}>
     */
    public function listTeams(): array
    {
        return $this->teams->listTeams();
    }

    /**
     * @return array{id: int, name: string, active: int, notes: string}|null
     */
    public function find(int $teamId): ?array
    {
        return $this->teams->find($teamId);
    }

    /**
     * @return list<array{user_id: int, name: string, username: string}>
     */
    public function members(int $teamId): array
    {
        return $this->teams->activeMembers($teamId);
    }

    /**
     * @return list<array{pid: int, name: string, pubpid: string, position: int, assigned_at: string, assigned_at_input: string}>
     */
    public function patients(int $teamId): array
    {
        return $this->teams->activePatients($teamId);
    }

    public function create(string $name, string $notes, bool $active): int
    {
        $name = $this->cleanName($name);
        $notes = $this->cleanNotes($notes);
        if ($this->teams->nameTaken($name, null)) {
            throw new TeamRuleException('A team with that name already exists.');
        }
        return $this->teams->create($name, $notes, $active);
    }

    public function update(int $teamId, string $name, string $notes, bool $active): ?CareTeamSyncBatchResult
    {
        $existing = $this->teams->find($teamId);
        if ($existing === null) {
            throw new TeamRuleException('That team was not found.');
        }
        $name = $this->cleanName($name);
        $notes = $this->cleanNotes($notes);
        if ($this->teams->nameTaken($name, $teamId)) {
            throw new TeamRuleException('A team with that name already exists.');
        }
        $wasActive = (int) $existing['active'] === 1;
        $this->teams->update($teamId, $name, $notes, $active);
        if ($wasActive && !$active) {
            return $this->careTeamSync->clearModuleCareTeamForAllPatients($teamId);
        }
        return null;
    }

    public function addMember(int $teamId, int $userId): void
    {
        $this->requireTeam($teamId);
        if ($userId <= 0 || !$this->directory->userCanJoinTeam($userId)) {
            throw new TeamRuleException('That staff member was not found.');
        }
        $this->teams->addMember($teamId, $userId);
        $this->careTeamSync->syncTeamToAllPatients($teamId);
    }

    public function removeMember(int $teamId, int $userId): void
    {
        $this->requireTeam($teamId);
        if (!in_array($userId, $this->teams->activeMemberIds($teamId), true)) {
            throw new TeamRuleException('That staff member is not on this team.');
        }
        $this->teams->deactivateMember($teamId, $userId);
        $this->careTeamSync->syncTeamToAllPatients($teamId);
    }

    public function assignPatient(int $teamId, int $pid): string
    {
        $this->requireTeam($teamId);
        if ($pid <= 0) {
            throw new TeamRuleException('That patient was not found.');
        }
        $result = $this->teams->assignPatient($teamId, $pid);
        if ($result === 'assigned') {
            $this->careTeamSync->syncTeamToPatient($teamId, $pid);
        }
        return $result;
    }

    public function unassignPatient(int $teamId, int $pid): void
    {
        $this->requireTeam($teamId);
        $this->careTeamSync->clearModuleCareTeamForPatient($teamId, $pid);
        $this->teams->unassignPatient($teamId, $pid);
    }

    /**
     * @return array{
     *   team_id: int,
     *   team_name: string,
     *   patient_count: int,
     *   added: int,
     *   removed: int,
     *   care_team_sync: CareTeamSyncBatchResult
     * }|null
     */
    public function pullCareTeamFromPatient(int $teamId, int $pid): ?array
    {
        $this->requireTeam($teamId);
        $assignment = $this->teams->activeTeamForPatient($pid);
        if ($assignment === null || (int) $assignment['team_id'] !== $teamId) {
            throw new TeamRuleException('That patient is not on this team.');
        }
        return $this->careTeamSync->applyPatientCareTeamToProviderTeam($pid);
    }

    /**
     * @param list<int|string> $patientIds
     */
    public function retryCareTeamSync(int $teamId, array $patientIds, string $context): CareTeamSyncBatchResult
    {
        $this->requireTeam($teamId);
        $clean = [];
        foreach ($this->parseStrictPatientIds($patientIds) as $pid) {
            $assignment = $this->teams->activeTeamForPatient($pid);
            if ($assignment === null || (int) $assignment['team_id'] !== $teamId) {
                continue;
            }
            $clean[] = $pid;
        }
        if ($context === CareTeamSyncFlash::CONTEXT_DEACTIVATION_CLEANUP) {
            return $this->careTeamSync->clearModuleCareTeamForPatients($teamId, $clean, true);
        }
        $team = $this->teams->find($teamId);
        if ($team !== null && (int) $team['active'] !== 1) {
            return $this->careTeamSync->clearModuleCareTeamForPatients($teamId, $clean, true);
        }
        return $this->careTeamSync->syncTeamToPatients($teamId, $clean);
    }

    public function careTeamSyncAvailable(): bool
    {
        return $this->careTeamSync->isAvailable();
    }

    public function activePatientCount(int $teamId): int
    {
        return $this->teams->activePatientCount($teamId);
    }

    /**
     * @param array<int|string, mixed> $positions
     */
    public function saveOrder(int $teamId, array $positions): void
    {
        $this->requireTeam($teamId);
        $clean = [];
        foreach ($positions as $pid => $position) {
            if (!is_scalar($pid) || !is_scalar($position) || !is_numeric($position)) {
                continue;
            }
            $pid = (int) $pid;
            if ($pid <= 0) {
                continue;
            }
            $clean[$pid] = max(0, (int) $position);
        }
        $this->teams->savePositions($teamId, $clean);
    }

    public function updatePatientAssignedAt(
        int $teamId,
        int $pid,
        string $rawAssignedAt,
        bool $confirmed,
        ?int $confirmAffectedCount = null,
    ): void {
        $this->requireTeam($teamId);
        if ($pid <= 0) {
            throw new TeamRuleException('That patient was not found.');
        }
        $assignment = $this->teams->findActivePatientAssignment($teamId, $pid);
        if ($assignment === null) {
            throw new TeamRuleException('That patient is not on this team.');
        }
        $assignedAt = DateSpan::assignedAt($rawAssignedAt);
        if ($assignedAt === null) {
            throw new TeamRuleException('Enter a valid team join date and time.');
        }
        $assignedAtDt = ClinicTime::parseStored($assignedAt);
        if ($assignedAtDt === null) {
            throw new TeamRuleException('Enter a valid team join date and time.');
        }
        if ($assignedAtDt > ClinicTime::now()) {
            throw new TeamRuleException('Team join date cannot be in the future.');
        }
        $stored = ClinicTime::formatStored($assignedAtDt);
        $currentDt = ClinicTime::parseStored($assignment['assigned_at']);
        if ($currentDt !== null && $assignedAtDt > $currentDt) {
            $affected = $this->visits->countVisitsExcludedByJoin($teamId, $pid, $stored);
            if ($affected > 0 && !$confirmed) {
                throw new TeamJoinDateConfirmationRequired($affected, $pid, $rawAssignedAt);
            }
            if ($confirmed && $affected > 0) {
                if ($confirmAffectedCount === null || $confirmAffectedCount !== $affected) {
                    throw new TeamRuleException(
                        'The affected visit count changed. Save the join date again and confirm.'
                    );
                }
            }
        }
        $this->teams->updateAssignedAt($teamId, $pid, $stored);
    }

    /**
     * @return list<array{id: int, name: string, username: string}>
     */
    public function searchUsers(string $query): array
    {
        if (mb_strlen(trim($query)) < 2) {
            return [];
        }
        return $this->directory->searchUsers($query);
    }

    /**
     * @return list<array{pid: int, name: string, pubpid: string}>
     */
    public function searchPatients(string $query): array
    {
        if (mb_strlen(trim($query)) < 2) {
            return [];
        }
        return $this->directory->searchPatients($query);
    }

    private function requireTeam(int $teamId): void
    {
        if ($this->teams->find($teamId) === null) {
            throw new TeamRuleException('That team was not found.');
        }
    }

    private function cleanName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new TeamRuleException('Enter a team name.');
        }
        if (mb_strlen($name) > 100) {
            throw new TeamRuleException('Team name must be 100 characters or fewer.');
        }
        return $name;
    }

    private function cleanNotes(string $notes): string
    {
        $notes = trim($notes);
        if (mb_strlen($notes) > 2000) {
            throw new TeamRuleException('Notes must be 2000 characters or fewer.');
        }
        return $notes;
    }

    /**
     * @param list<int|string> $patientIds
     * @return list<int>
     */
    private function parseStrictPatientIds(array $patientIds): array
    {
        $clean = [];
        foreach ($patientIds as $patientId) {
            if (!is_scalar($patientId)) {
                continue;
            }
            $raw = trim((string) $patientId);
            if ($raw === '' || !ctype_digit($raw)) {
                continue;
            }
            $pid = (int) $raw;
            if ($pid <= 0) {
                continue;
            }
            $clean[$pid] = $pid;
        }
        return array_values($clean);
    }
}
