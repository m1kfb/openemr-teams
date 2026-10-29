<?php

/**
 * Searches OpenEMR users and patients. This module does not create either.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Persistence;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\ProviderTeams\Domain\PersonName;

final class DirectoryStore
{
    /**
     * @return list<array{id: int, name: string, username: string}>
     */
    public function searchUsers(string $query): array
    {
        $like = $this->contains($query);
        $rows = QueryUtils::fetchRecords(
            'SELECT id, fname, lname, username
             FROM users
             WHERE active = 1
               AND username IS NOT NULL
               AND username != \'\'
               AND (fname LIKE ? OR lname LIKE ? OR username LIKE ?)
             ORDER BY lname, fname, id
             LIMIT 25',
            [$like, $like, $like]
        );
        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => PersonName::lastCommaFirst($row['lname'] ?? '', $row['fname'] ?? '', 'User ' . (int) $row['id']),
            'username' => (string) $row['username'],
        ], $rows);
    }

    public function userCanJoinTeam(int $userId): bool
    {
        $row = QueryUtils::querySingleRow(
            'SELECT id FROM users WHERE id = ? AND active = 1 AND username IS NOT NULL AND username != \'\'',
            [$userId]
        );
        return is_array($row);
    }

    /**
     * @return list<array{pid: int, name: string, pubpid: string}>
     */
    public function searchPatients(string $query): array
    {
        $like = $this->contains($query);
        $rows = QueryUtils::fetchRecords(
            'SELECT pid, fname, lname, pubpid
             FROM patient_data
             WHERE fname LIKE ? OR lname LIKE ? OR pubpid LIKE ? OR CONCAT(fname, \' \', lname) LIKE ?
             ORDER BY lname, fname, pid
             LIMIT 25',
            [$like, $like, $like, $like]
        );
        return array_map(function (array $row): array {
            $pid = (int) $row['pid'];
            return [
                'pid' => $pid,
                'name' => PersonName::lastCommaFirst($row['lname'] ?? '', $row['fname'] ?? '', 'Patient ' . $pid),
                'pubpid' => (string) ($row['pubpid'] ?? ''),
            ];
        }, $rows);
    }

    private function contains(string $value): string
    {
        return '%' . addcslashes(trim($value), '%_\\') . '%';
    }
}
