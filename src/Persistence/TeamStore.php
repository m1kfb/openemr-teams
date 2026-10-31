<?php

/**
 * Team, member, and patient assignment records.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Persistence;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\ProviderTeams\Domain\DateSpan;
use OpenEMR\Modules\ProviderTeams\Domain\PersonName;
use OpenEMR\Modules\ProviderTeams\Service\TeamRuleException;

final class TeamStore
{
    /**
     * @return list<array{id: int, name: string, active: int, notes: string, member_count: int, patient_count: int}>
     */
    public function listTeams(): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT t.id, t.name, t.active, t.notes,
                (SELECT COUNT(*) FROM provider_team_members m WHERE m.team_id = t.id AND m.active = 1) AS member_count,
                (SELECT COUNT(*) FROM provider_team_patients p WHERE p.team_id = t.id AND p.active = 1) AS patient_count
             FROM provider_teams t
             ORDER BY t.active DESC, t.name, t.id'
        );
        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'active' => (int) $row['active'],
            'notes' => (string) ($row['notes'] ?? ''),
            'member_count' => (int) $row['member_count'],
            'patient_count' => (int) $row['patient_count'],
        ], $rows);
    }

    /**
     * @return array{id: int, name: string, active: int, notes: string}|null
     */
    public function find(int $teamId): ?array
    {
        $row = QueryUtils::querySingleRow(
            'SELECT id, name, active, notes FROM provider_teams WHERE id = ?',
            [$teamId]
        );
        if (!is_array($row)) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'active' => (int) $row['active'],
            'notes' => (string) ($row['notes'] ?? ''),
        ];
    }

    public function nameTaken(string $name, ?int $exceptId): bool
    {
        $sql = 'SELECT id FROM provider_teams WHERE LOWER(name) = LOWER(?)';
        $binds = [$name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $binds[] = $exceptId;
        }
        return is_array(QueryUtils::querySingleRow($sql, $binds));
    }

    public function create(string $name, string $notes, bool $active): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) QueryUtils::sqlInsert(
            'INSERT INTO provider_teams (name, active, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$name, $active ? 1 : 0, $notes, $now, $now]
        );
    }

    public function update(int $teamId, string $name, string $notes, bool $active): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE provider_teams SET name = ?, active = ?, notes = ?, updated_at = ? WHERE id = ?',
            [$name, $active ? 1 : 0, $notes, date('Y-m-d H:i:s'), $teamId]
        );
    }

    /**
     * @return list<array{user_id: int, name: string, username: string}>
     */
    public function activeMembers(int $teamId): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT m.user_id, u.fname, u.lname, u.username
             FROM provider_team_members m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.team_id = ? AND m.active = 1
             ORDER BY u.lname, u.fname, u.id',
            [$teamId]
        );
        return array_map(fn (array $row): array => [
            'user_id' => (int) $row['user_id'],
            'name' => PersonName::lastCommaFirst($row['lname'] ?? '', $row['fname'] ?? '', 'User ' . (int) $row['user_id']),
            'username' => (string) ($row['username'] ?? ''),
        ], $rows);
    }

    /**
     * @return list<int>
     */
    public function activeMemberIds(int $teamId): array
    {
        return array_map(
            static fn (array $member): int => $member['user_id'],
            $this->activeMembers($teamId)
        );
    }

    /**
     * @return list<int>
     */
    public function activePatientIds(int $teamId): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT pid FROM provider_team_patients WHERE team_id = ? AND active = 1 ORDER BY position, pid',
            [$teamId]
        );
        return array_map(static fn (array $row): int => (int) $row['pid'], $rows);
    }

    public function activePatientCount(int $teamId): int
    {
        $row = QueryUtils::querySingleRow(
            'SELECT COUNT(*) AS cnt FROM provider_team_patients WHERE team_id = ? AND active = 1',
            [$teamId]
        );
        return is_array($row) ? (int) $row['cnt'] : 0;
    }

    /**
     * @return array{team_id: int, team_name: string}|null
     */
    public function activeTeamForPatient(int $pid): ?array
    {
        $row = QueryUtils::querySingleRow(
            'SELECT a.team_id, t.name AS team_name
             FROM provider_team_patients a
             INNER JOIN provider_teams t ON t.id = a.team_id
             WHERE a.pid = ? AND a.active = 1 AND t.active = 1
             LIMIT 1',
            [$pid]
        );
        if (!is_array($row)) {
            return null;
        }
        return [
            'team_id' => (int) $row['team_id'],
            'team_name' => (string) $row['team_name'],
        ];
    }

    public function addMember(int $teamId, int $userId): void
    {
        $existing = QueryUtils::querySingleRow(
            'SELECT id, active FROM provider_team_members WHERE team_id = ? AND user_id = ?',
            [$teamId, $userId]
        );
        $now = date('Y-m-d H:i:s');
        if (is_array($existing)) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE provider_team_members SET active = 1, updated_at = ? WHERE id = ?',
                [$now, (int) $existing['id']]
            );
            return;
        }
        QueryUtils::sqlInsert(
            'INSERT INTO provider_team_members (team_id, user_id, active, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            [$teamId, $userId, $now, $now]
        );
    }

    public function deactivateMember(int $teamId, int $userId): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE provider_team_members SET active = 0, updated_at = ? WHERE team_id = ? AND user_id = ? AND active = 1',
            [date('Y-m-d H:i:s'), $teamId, $userId]
        );
    }

    /**
     * @return list<array{pid: int, name: string, pubpid: string, position: int, assigned_at: string, assigned_at_input: string}>
     */
    public function activePatients(int $teamId): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT a.pid, a.position, a.assigned_at, p.fname, p.lname, p.pubpid
             FROM provider_team_patients a
             INNER JOIN patient_data p ON p.pid = a.pid
             WHERE a.team_id = ? AND a.active = 1
             ORDER BY a.position, p.lname, p.fname, a.pid',
            [$teamId]
        );
        return array_map(fn (array $row): array => $this->mapPatient($row), $rows);
    }

    public function assignPatient(int $teamId, int $pid): string
    {
        return $this->transaction(function () use ($teamId, $pid): string {
            $patient = QueryUtils::querySingleRow(
                'SELECT pid FROM patient_data WHERE pid = ? FOR UPDATE',
                [$pid]
            );
            if (!is_array($patient)) {
                throw new TeamRuleException('That patient was not found.');
            }
            $active = QueryUtils::querySingleRow(
                'SELECT a.team_id, t.name AS team_name
                 FROM provider_team_patients a
                 INNER JOIN provider_teams t ON t.id = a.team_id
                 WHERE a.pid = ? AND a.active = 1
                 FOR UPDATE',
                [$pid]
            );
            if (is_array($active) && (int) $active['team_id'] !== $teamId) {
                throw new TeamRuleException(
                    'This patient is already assigned to ' . $active['team_name'] . '. Unassign them there first.'
                );
            }
            $here = QueryUtils::querySingleRow(
                'SELECT id, active FROM provider_team_patients WHERE team_id = ? AND pid = ? FOR UPDATE',
                [$teamId, $pid]
            );
            if (is_array($here) && (int) $here['active'] === 1) {
                return 'already';
            }
            $position = $this->nextPosition($teamId);
            $now = date('Y-m-d H:i:s');
            if (is_array($here)) {
                QueryUtils::sqlStatementThrowException(
                    'UPDATE provider_team_patients
                     SET active = 1, unassigned_at = NULL, assigned_at = ?, position = ?
                     WHERE id = ?',
                    [$now, $position, (int) $here['id']]
                );
                return 'assigned';
            }
            QueryUtils::sqlInsert(
                'INSERT INTO provider_team_patients (team_id, pid, position, active, assigned_at, unassigned_at)
                 VALUES (?, ?, ?, 1, ?, NULL)',
                [$teamId, $pid, $position, $now]
            );
            return 'assigned';
        });
    }

    public function unassignPatient(int $teamId, int $pid): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE provider_team_patients
             SET active = 0, unassigned_at = ?
             WHERE team_id = ? AND pid = ? AND active = 1',
            [date('Y-m-d H:i:s'), $teamId, $pid]
        );
    }

    /**
     * @param array<int, int> $positions
     */
    public function savePositions(int $teamId, array $positions): void
    {
        foreach ($positions as $pid => $position) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE provider_team_patients SET position = ? WHERE team_id = ? AND pid = ? AND active = 1',
                [max(0, $position), $teamId, $pid]
            );
        }
    }

    public function updateAssignedAt(int $teamId, int $pid, string $assignedAt): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE provider_team_patients SET assigned_at = ? WHERE team_id = ? AND pid = ? AND active = 1',
            [$assignedAt, $teamId, $pid]
        );
    }

    private function nextPosition(int $teamId): int
    {
        $row = QueryUtils::querySingleRow(
            'SELECT COALESCE(MAX(position), 0) AS max_position
             FROM provider_team_patients WHERE team_id = ? AND active = 1',
            [$teamId]
        );
        $max = is_array($row) ? (int) $row['max_position'] : 0;
        return $max + 1;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{pid: int, name: string, pubpid: string, position: int, assigned_at: string, assigned_at_input: string}
     */
    private function mapPatient(array $row): array
    {
        $pid = (int) $row['pid'];
        $assignedAt = (string) ($row['assigned_at'] ?? '');
        return [
            'pid' => $pid,
            'name' => PersonName::lastCommaFirst($row['lname'] ?? '', $row['fname'] ?? '', 'Patient ' . $pid),
            'pubpid' => (string) ($row['pubpid'] ?? ''),
            'position' => (int) ($row['position'] ?? 0),
            'assigned_at' => $assignedAt,
            'assigned_at_input' => DateSpan::assignedAtInputValue($assignedAt),
        ];
    }

    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    private function transaction(callable $action): mixed
    {
        if (method_exists(QueryUtils::class, 'inTransaction')) {
            return QueryUtils::inTransaction($action);
        }
        sqlBeginTrans();
        try {
            $result = $action();
            sqlCommitTrans();
            return $result;
        } catch (\Throwable $exception) {
            sqlRollbackTrans();
            throw $exception;
        }
    }
}
