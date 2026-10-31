<?php

/**
 * Tracks care-team members this module added so removals stay scoped.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Persistence;

use OpenEMR\Common\Database\QueryUtils;

class CareTeamLinkStore
{
    /**
     * @return list<int> Module-tracked users that are not excluded for this patient/team
     */
    public function activeTrackedUserIds(int $providerTeamId, int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT user_id FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ? AND excluded = 0',
            [$providerTeamId, $pid]
        );
        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /**
     * @return list<int> User ids the module added to this patient's care team for a provider team
     *
     * @deprecated Use activeTrackedUserIds() for sync; excludedUserIds() for opt-outs.
     */
    public function trackedUserIds(int $providerTeamId, int $pid): array
    {
        return $this->activeTrackedUserIds($providerTeamId, $pid);
    }

    /**
     * @return list<int> Per-patient opt-outs: still on the provider team but removed from this care team
     */
    public function excludedUserIds(int $providerTeamId, int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT user_id FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ? AND excluded = 1',
            [$providerTeamId, $pid]
        );
        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /**
     * @return list<array{user_id: int, name: string, username: string}>
     */
    public function excludedMembers(int $providerTeamId, int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT l.user_id, u.fname, u.lname, u.username
             FROM provider_team_care_team_links l
             INNER JOIN users u ON u.id = l.user_id
             WHERE l.provider_team_id = ? AND l.pid = ? AND l.excluded = 1
             ORDER BY u.lname, u.fname, u.id',
            [$providerTeamId, $pid]
        );
        return array_map(static function (array $row): array {
            $userId = (int) $row['user_id'];
            $lname = (string) ($row['lname'] ?? '');
            $fname = (string) ($row['fname'] ?? '');
            $name = trim($lname . ', ' . $fname);
            if ($name === ',') {
                $name = 'User ' . $userId;
            }
            return [
                'user_id' => $userId,
                'name' => $name,
                'username' => (string) ($row['username'] ?? ''),
            ];
        }, $rows);
    }

    /**
     * @return array<int, int> care_team_member.id keyed by user_id for this patient/team
     */
    public function memberIdsByUser(int $providerTeamId, int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT user_id, care_team_member_id FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ? AND excluded = 0',
            [$providerTeamId, $pid]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']] = (int) $row['care_team_member_id'];
        }
        return $map;
    }

    /**
     * When a tracked user is no longer on the care team but still on the provider team, record an opt-out.
     *
     * @param list<int> $careTeamUserIds
     * @param list<int> $providerTeamMemberUserIds
     */
    public function recordManualRemovals(
        int $providerTeamId,
        int $pid,
        array $careTeamUserIds,
        array $providerTeamMemberUserIds,
    ): void {
        $careSet = array_fill_keys($careTeamUserIds, true);
        $teamSet = array_fill_keys($providerTeamMemberUserIds, true);
        foreach ($this->activeTrackedUserIds($providerTeamId, $pid) as $userId) {
            if (!isset($careSet[$userId]) && isset($teamSet[$userId])) {
                $this->markExcluded($providerTeamId, $pid, $userId);
            }
        }
    }

    public function markExcluded(int $providerTeamId, int $pid, int $userId): void
    {
        $existing = QueryUtils::querySingleRow(
            'SELECT id, excluded FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ? AND user_id = ?',
            [$providerTeamId, $pid, $userId]
        );
        $now = date('Y-m-d H:i:s');
        if (is_array($existing)) {
            if ((int) ($existing['excluded'] ?? 0) === 1) {
                return;
            }
            QueryUtils::sqlStatementThrowException(
                'UPDATE provider_team_care_team_links
                 SET excluded = 1, excluded_at = ?, care_team_member_id = 0
                 WHERE id = ?',
                [$now, (int) $existing['id']]
            );
            return;
        }
        QueryUtils::sqlInsert(
            'INSERT INTO provider_team_care_team_links
             (provider_team_id, pid, user_id, care_team_member_id, excluded, excluded_at, created_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)',
            [$providerTeamId, $pid, $userId, 0, $now, $now]
        );
    }

    public function clearExclusion(int $providerTeamId, int $pid, int $userId): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE provider_team_care_team_links
             SET excluded = 0, excluded_at = NULL
             WHERE provider_team_id = ? AND pid = ? AND user_id = ? AND excluded = 1',
            [$providerTeamId, $pid, $userId]
        );
    }

    public function isExcluded(int $providerTeamId, int $pid, int $userId): bool
    {
        $row = QueryUtils::querySingleRow(
            'SELECT id FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ? AND user_id = ? AND excluded = 1',
            [$providerTeamId, $pid, $userId]
        );
        return is_array($row);
    }

    public function remember(int $providerTeamId, int $pid, int $userId, int $careTeamMemberId): void
    {
        $existing = QueryUtils::querySingleRow(
            'SELECT id FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ? AND user_id = ?',
            [$providerTeamId, $pid, $userId]
        );
        if (is_array($existing)) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE provider_team_care_team_links
                 SET care_team_member_id = ?, excluded = 0, excluded_at = NULL
                 WHERE id = ?',
                [$careTeamMemberId, (int) $existing['id']]
            );
            return;
        }
        QueryUtils::sqlInsert(
            'INSERT INTO provider_team_care_team_links
             (provider_team_id, pid, user_id, care_team_member_id, excluded, excluded_at, created_at)
             VALUES (?, ?, ?, ?, 0, NULL, ?)',
            [$providerTeamId, $pid, $userId, $careTeamMemberId, date('Y-m-d H:i:s')]
        );
    }

    public function forgetUser(int $providerTeamId, int $pid, int $userId): void
    {
        QueryUtils::sqlStatementThrowException(
            'DELETE FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ? AND user_id = ?',
            [$providerTeamId, $pid, $userId]
        );
    }

    public function forgetAllForPatientTeam(int $providerTeamId, int $pid): void
    {
        QueryUtils::sqlStatementThrowException(
            'DELETE FROM provider_team_care_team_links WHERE provider_team_id = ? AND pid = ?',
            [$providerTeamId, $pid]
        );
    }

    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    public function withPatientTeamLock(int $providerTeamId, int $pid, callable $action): mixed
    {
        return TransactionRunner::run(function () use ($providerTeamId, $pid, $action): mixed {
            QueryUtils::fetchRecords(
                'SELECT id FROM provider_team_care_team_links
                 WHERE provider_team_id = ? AND pid = ?
                 FOR UPDATE',
                [$providerTeamId, $pid]
            );
            QueryUtils::querySingleRow(
                'SELECT id FROM care_teams WHERE pid = ? FOR UPDATE',
                [$pid]
            );
            return $action();
        });
    }
}
