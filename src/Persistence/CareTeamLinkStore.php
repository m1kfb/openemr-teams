<?php

/**
 * Tracks care-team members this module added so removals stay scoped.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Persistence;

use OpenEMR\Common\Database\QueryUtils;

final class CareTeamLinkStore
{
    /**
     * @return list<int> User ids the module added to this patient's care team for a provider team
     */
    public function trackedUserIds(int $providerTeamId, int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT user_id FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ?',
            [$providerTeamId, $pid]
        );
        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /**
     * @return array<int, int> care_team_member.id keyed by user_id for this patient/team
     */
    public function memberIdsByUser(int $providerTeamId, int $pid): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT user_id, care_team_member_id FROM provider_team_care_team_links
             WHERE provider_team_id = ? AND pid = ?',
            [$providerTeamId, $pid]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']] = (int) $row['care_team_member_id'];
        }
        return $map;
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
                'UPDATE provider_team_care_team_links SET care_team_member_id = ? WHERE id = ?',
                [$careTeamMemberId, (int) $existing['id']]
            );
            return;
        }
        QueryUtils::sqlInsert(
            'INSERT INTO provider_team_care_team_links
             (provider_team_id, pid, user_id, care_team_member_id, created_at)
             VALUES (?, ?, ?, ?, ?)',
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
}
