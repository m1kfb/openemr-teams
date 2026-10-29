<?php

/**
 * Stored calls and the staff who worked a team on a date.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Persistence;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\ProviderTeams\Domain\PersonName;
use OpenEMR\Modules\ProviderTeams\Domain\VisitStatus;

final class VisitStore
{
    /**
     * Patients assigned on any date in the range, plus anyone who already has a call in the range.
     *
     * @return list<array{pid: int, name: string, position: int}>
     */
    public function patientsForRange(int $teamId, string $from, string $to): array
    {
        $assigned = QueryUtils::fetchRecords(
            'SELECT a.pid, a.position, p.fname, p.lname
             FROM provider_team_patients a
             INNER JOIN patient_data p ON p.pid = a.pid
             WHERE a.team_id = ?
               AND a.assigned_at < DATE_ADD(?, INTERVAL 1 DAY)
               AND (a.unassigned_at IS NULL OR a.unassigned_at >= ?)',
            [$teamId, $to, $from]
        );
        $visited = QueryUtils::fetchRecords(
            'SELECT DISTINCT v.pid, p.fname, p.lname
             FROM provider_team_visits v
             INNER JOIN patient_data p ON p.pid = v.pid
             WHERE v.team_id = ? AND v.visit_date BETWEEN ? AND ?',
            [$teamId, $from, $to]
        );
        $byPid = [];
        foreach ($assigned as $row) {
            $pid = (int) $row['pid'];
            $byPid[$pid] = [
                'pid' => $pid,
                'name' => PersonName::lastCommaFirst($row['lname'] ?? '', $row['fname'] ?? '', 'Patient ' . $pid),
                'position' => (int) $row['position'],
            ];
        }
        foreach ($visited as $row) {
            $pid = (int) $row['pid'];
            if (isset($byPid[$pid])) {
                continue;
            }
            $byPid[$pid] = [
                'pid' => $pid,
                'name' => PersonName::lastCommaFirst($row['lname'] ?? '', $row['fname'] ?? '', 'Patient ' . $pid),
                'position' => 1000000 + $pid,
            ];
        }
        return array_values($byPid);
    }

    /**
     * @return list<array{pid: int, date: string, period: string, status: string, timeIn: ?int, timeOut: ?int}>
     */
    public function visitsForRange(int $teamId, string $from, string $to): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT pid, visit_date, period, status, time_in, time_out
             FROM provider_team_visits
             WHERE team_id = ? AND visit_date BETWEEN ? AND ?',
            [$teamId, $from, $to]
        );
        return array_map(fn (array $row): array => [
            'pid' => (int) $row['pid'],
            'date' => (string) $row['visit_date'],
            'period' => (string) $row['period'],
            'status' => (string) $row['status'],
            'timeIn' => $this->nullableInt($row['time_in']),
            'timeOut' => $this->nullableInt($row['time_out']),
        ], $rows);
    }

    /**
     * @return array<string, list<string>>
     */
    public function staffNamesByDate(int $teamId, string $from, string $to): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT s.visit_date, s.user_id, u.fname, u.lname
             FROM provider_team_day_staff s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.team_id = ? AND s.visit_date BETWEEN ? AND ?
             ORDER BY s.visit_date, u.lname, u.fname, s.user_id',
            [$teamId, $from, $to]
        );
        $byDate = [];
        foreach ($rows as $row) {
            $date = (string) $row['visit_date'];
            $byDate[$date][] = PersonName::lastCommaFirst(
                $row['lname'] ?? '',
                $row['fname'] ?? '',
                'User ' . (int) $row['user_id']
            );
        }
        return $byDate;
    }

    /**
     * @return list<int>
     */
    public function staffIdsForDate(int $teamId, string $date): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT user_id FROM provider_team_day_staff WHERE team_id = ? AND visit_date = ? ORDER BY user_id',
            [$teamId, $date]
        );
        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /**
     * @param list<int> $staffIds
     * @param list<array{pid: int, period: string, status: string, timeIn: ?int, timeOut: ?int}> $slots
     */
    public function saveDay(int $teamId, string $date, array $staffIds, array $slots, int $recordedBy): void
    {
        $this->transaction(function () use ($teamId, $date, $staffIds, $slots, $recordedBy): void {
            QueryUtils::sqlStatementThrowException(
                'DELETE FROM provider_team_day_staff WHERE team_id = ? AND visit_date = ?',
                [$teamId, $date]
            );
            foreach ($staffIds as $userId) {
                QueryUtils::sqlInsert(
                    'INSERT INTO provider_team_day_staff (team_id, visit_date, user_id) VALUES (?, ?, ?)',
                    [$teamId, $date, $userId]
                );
            }
            foreach ($slots as $slot) {
                if ($slot['status'] === VisitStatus::BLANK) {
                    QueryUtils::sqlStatementThrowException(
                        'DELETE FROM provider_team_visits
                         WHERE team_id = ? AND pid = ? AND visit_date = ? AND period = ?',
                        [$teamId, $slot['pid'], $date, $slot['period']]
                    );
                    continue;
                }
                QueryUtils::sqlStatementThrowException(
                    'INSERT INTO provider_team_visits
                        (team_id, pid, visit_date, period, time_in, time_out, status, recorded_by, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        time_in = VALUES(time_in),
                        time_out = VALUES(time_out),
                        status = VALUES(status),
                        recorded_by = VALUES(recorded_by),
                        updated_at = VALUES(updated_at)',
                    [
                        $teamId,
                        $slot['pid'],
                        $date,
                        $slot['period'],
                        $slot['timeIn'],
                        $slot['timeOut'],
                        $slot['status'],
                        $recordedBy > 0 ? $recordedBy : null,
                        date('Y-m-d H:i:s'),
                    ]
                );
            }
        });
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
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
