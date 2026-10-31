<?php

/**
 * OpenEMR care-team reads/writes with schema detection (8.x care_teams / care_team_member).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Persistence;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\CareTeamService;

class CareTeamBridge
{
    private ?bool $modernSchema = null;

    public function isSupported(): bool
    {
        return $this->detectModernSchema();
    }

    /**
     * @return list<int> Active OpenEMR user ids on the patient's care team (not contacts)
     */
    public function activeUserIdsForPatient(int $pid): array
    {
        if (!$this->detectModernSchema()) {
            return [];
        }
        $data = $this->fetchCareTeamData($pid);
        $ids = [];
        foreach ($data['members'] ?? [] as $member) {
            if (($member['member_type'] ?? 'user') !== 'user') {
                continue;
            }
            if (($member['status'] ?? 'active') !== 'active') {
                continue;
            }
            $userId = (int) ($member['user_id'] ?? 0);
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }
        $ids = array_values(array_unique($ids));
        return $this->filterActiveUsers($ids);
    }

    /**
     * User ids still listed on the care team payload (any member status).
     *
     * @return list<int>
     */
    public function presentUserIdsForPatient(int $pid): array
    {
        if (!$this->detectModernSchema()) {
            return [];
        }
        $data = $this->fetchCareTeamData($pid);
        $ids = [];
        foreach ($data['members'] ?? [] as $member) {
            if (($member['member_type'] ?? 'user') !== 'user') {
                continue;
            }
            $userId = (int) ($member['user_id'] ?? 0);
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param list<int> $ensureUserIds
     * @param list<int> $removeUserIds
     */
    public function applyUserChanges(int $pid, array $ensureUserIds, array $removeUserIds): void
    {
        if (!$this->detectModernSchema()) {
            return;
        }
        $ensureUserIds = $this->uniquePositive($ensureUserIds);
        $removeUserIds = $this->uniquePositive($removeUserIds);
        if ($ensureUserIds === [] && $removeUserIds === []) {
            return;
        }

        $ensureUserIds = $this->filterActiveUsers($ensureUserIds);

        $data = $this->fetchCareTeamData($pid);
        $teamId = isset($data['team_id']) ? (int) $data['team_id'] : null;
        $teamName = (string) ($data['team_name'] ?? '');
        if ($teamName === '') {
            $teamName = 'Care Team';
        }
        $teamStatus = (string) ($data['team_status'] ?? 'active');
        if ($teamStatus === '') {
            $teamStatus = 'active';
        }

        $removeSet = array_fill_keys($removeUserIds, true);

        $payload = [];
        foreach ($data['members'] ?? [] as $member) {
            $userId = (int) ($member['user_id'] ?? 0);
            $contactId = (int) ($member['contact_id'] ?? 0);
            if ($userId > 0) {
                if (isset($removeSet[$userId])) {
                    continue;
                }
                $payload[] = $this->memberPayload($member);
                continue;
            }
            if ($contactId > 0) {
                $payload[] = $this->memberPayload($member);
            }
        }

        foreach ($ensureUserIds as $userId) {
            $already = false;
            foreach ($payload as $entry) {
                if ((int) ($entry['user_id'] ?? 0) === $userId) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                $payload[] = [
                    'user_id' => $userId,
                    'contact_id' => null,
                    'role' => 'healthcare_professional',
                    'facility_id' => null,
                    'provider_since' => null,
                    'status' => 'active',
                    'note' => '',
                ];
            }
        }

        $this->saveCareTeamPayload($pid, $teamId ?: null, $teamName, $payload, $teamStatus);
    }

    public function findMemberId(int $pid, int $userId): ?int
    {
        if (!$this->detectModernSchema()) {
            return null;
        }
        $row = QueryUtils::querySingleRow(
            'SELECT ctm.id
             FROM care_teams ct
             INNER JOIN care_team_member ctm ON ctm.care_team_id = ct.id
             WHERE ct.pid = ? AND ctm.user_id = ? AND ctm.status = ?
             ORDER BY ctm.id DESC
             LIMIT 1',
            [$pid, $userId, 'active']
        );
        if (!is_array($row)) {
            return null;
        }
        return (int) $row['id'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchCareTeamData(int $pid): array
    {
        $service = new CareTeamService();
        return $service->getCareTeamData($pid);
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    protected function saveCareTeamPayload(
        int $pid,
        ?int $teamId,
        string $teamName,
        array $payload,
        string $teamStatus,
    ): void {
        $service = new CareTeamService();
        $service->saveCareTeam($pid, $teamId, $teamName, $payload, $teamStatus);
    }

    protected function detectModernSchema(): bool
    {
        if ($this->modernSchema !== null) {
            return $this->modernSchema;
        }
        $row = QueryUtils::fetchRecords(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name IN ('care_teams', 'care_team_member')"
        );
        $this->modernSchema = count($row) === 2;
        if (!$this->modernSchema) {
            error_log('Provider Teams: care team sync disabled (care_teams / care_team_member schema not found).');
        }
        return $this->modernSchema;
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    protected function memberPayload(array $member): array
    {
        return [
            'user_id' => $member['user_id'] ?? null,
            'contact_id' => $member['contact_id'] ?? null,
            'role' => $member['role'] ?? 'healthcare_professional',
            'facility_id' => $member['facility_id'] ?? null,
            'provider_since' => $member['provider_since'] ?? null,
            'status' => $member['status'] ?? 'active',
            'note' => $member['note'] ?? '',
        ];
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

    /**
     * @param list<int> $userIds
     * @return list<int>
     */
    private function filterActiveUsers(array $userIds): array
    {
        $userIds = $this->uniquePositive($userIds);
        if ($userIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = QueryUtils::fetchRecords(
            "SELECT id FROM users
             WHERE id IN ($placeholders)
               AND active = 1
               AND username IS NOT NULL
               AND username != ''",
            $userIds
        );
        $allowed = [];
        foreach ($rows as $row) {
            $allowed[(int) $row['id']] = true;
        }
        $out = [];
        foreach ($userIds as $id) {
            if (isset($allowed[$id])) {
                $out[] = $id;
            }
        }
        return $out;
    }
}
