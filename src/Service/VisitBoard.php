<?php

/**
 * Loads and saves the daily visit matrix.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Service;

use OpenEMR\Modules\ProviderTeams\Domain\DateSpan;
use OpenEMR\Modules\ProviderTeams\Domain\SlotDraft;
use OpenEMR\Modules\ProviderTeams\Domain\TimeOfDay;
use OpenEMR\Modules\ProviderTeams\Domain\VisitCsv;
use OpenEMR\Modules\ProviderTeams\Domain\VisitMatrixBuilder;
use OpenEMR\Modules\ProviderTeams\Domain\VisitPeriod;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSaveScope;
use OpenEMR\Modules\ProviderTeams\Domain\VisitStatus;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Persistence\VisitStore;

final class VisitBoard
{
    public function __construct(
        private readonly TeamStore $teams,
        private readonly VisitStore $visits,
        private readonly VisitMatrixBuilder $matrix = new VisitMatrixBuilder(),
        private readonly VisitCsv $csv = new VisitCsv(),
    ) {
    }

    /**
     * @param array<int|string, mixed>|null $rawSlots
     * @param list<int>|null $staffOverride
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   members: list<array{user_id: int, name: string, username: string}>,
     *   selectedStaff: list<int>
     * }
     */
    public function day(int $teamId, string $date, ?array $staffOverride = null, ?array $rawSlots = null): array
    {
        $rows = $this->matrix($teamId, $date, $date);
        if ($rawSlots !== null) {
            $rows = $this->overlay($rows, $rawSlots);
        }
        $selected = $staffOverride ?? $this->visits->staffIdsForDate($teamId, $date);
        return [
            'rows' => $rows,
            'members' => $this->teams->activeMembers($teamId),
            'selectedStaff' => array_values(array_map('intval', $selected)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function range(int $teamId, string $from, string $to): array
    {
        return $this->matrix($teamId, $from, $to);
    }

    /**
     * @param list<int> $staffIds
     * @param array<int|string, mixed> $rawSlots
     * @param array<int|string, mixed> $dirtyPatients
     * @return list<string>
     */
    public function saveDay(
        int $teamId,
        string $date,
        array $staffIds,
        array $rawSlots,
        int $recordedBy,
        array $dirtyPatients = [],
        bool $staffDirty = false,
    ): array {
        $team = $this->teams->find($teamId);
        if ($team === null) {
            return ['That team was not found.'];
        }
        if ($team['active'] !== 1) {
            return ['This team is inactive. You can still review visits, but new times cannot be saved.'];
        }
        $parsed = DateSpan::day($date);
        if ($parsed === null) {
            return ['Enter a valid date.'];
        }
        $date = $parsed->format('Y-m-d');
        $rawSlots = VisitSaveScope::filterPatientSlots($rawSlots, $dirtyPatients);
        if ($rawSlots === [] && !$staffDirty) {
            return ['Choose at least one patient row to save, or change staff on duty.'];
        }
        $allowed = [];
        foreach ($this->visits->patientsForRange($teamId, $date, $date) as $patient) {
            $allowed[$patient['pid']] = $patient['name'];
        }
        $memberIds = $this->teams->activeMemberIds($teamId);
        $staffIds = $this->idList($staffIds);
        $errors = [];
        foreach ($staffIds as $staffId) {
            if ($staffId <= 0 || !in_array($staffId, $memberIds, true)) {
                $errors[] = 'A selected staff member is not on this team.';
                break;
            }
        }

        $normalized = [];
        $anyReal = false;
        foreach ($rawSlots as $pidRaw => $periods) {
            if (!is_scalar($pidRaw) || !is_array($periods)) {
                continue;
            }
            $pid = (int) $pidRaw;
            if (!isset($allowed[$pid])) {
                $errors[] = 'A patient in the form is not on this team for this date.';
                continue;
            }
            foreach (VisitPeriod::all() as $period) {
                $fields = $periods[$period] ?? [];
                if (!is_array($fields)) {
                    $fields = [];
                }
                $draft = SlotDraft::fromInput(
                    $this->textField($fields['missed'] ?? ''),
                    $this->textField($fields['in'] ?? ''),
                    $this->textField($fields['out'] ?? '')
                );
                foreach ($draft->errors as $error) {
                    $errors[] = $allowed[$pid] . ', ' . VisitPeriod::label($period) . ': ' . $error;
                }
                if ($draft->status !== VisitStatus::BLANK) {
                    $anyReal = true;
                }
                $normalized[] = [
                    'pid' => $pid,
                    'period' => $period,
                    'status' => $draft->status,
                    'timeIn' => $draft->timeIn,
                    'timeOut' => $draft->timeOut,
                ];
            }
        }
        if ($anyReal && $staffIds === []) {
            if (!$staffDirty) {
                $staffIds = $this->visits->staffIdsForDate($teamId, $date);
            }
            if ($staffIds === []) {
                $errors[] = 'Choose the staff who attended this day.';
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        $updateStaff = $staffDirty || $anyReal;
        if (!$updateStaff && $normalized === []) {
            return ['Choose at least one patient row to save, or change staff on duty.'];
        }
        $this->visits->saveDay(
            $teamId,
            $date,
            $staffIds,
            $normalized,
            $recordedBy,
            $updateStaff
        );
        return [];
    }

    /**
     * @return null|'none'|'joined_later'
     */
    public function dayEmptyReason(int $teamId, string $date): ?string
    {
        if ($this->teams->activePatientCount($teamId) === 0) {
            return 'none';
        }
        $onDate = $this->visits->patientsForRange($teamId, $date, $date);
        if ($onDate === []) {
            return 'joined_later';
        }
        return null;
    }

    public function exportCsv(int $teamId, string $from, string $to): string
    {
        $dates = $this->dates($from, $to);
        $rows = $this->matrix($teamId, $dates[0], $dates[array_key_last($dates)]);
        return $this->csv->render($rows);
    }

    /**
     * @return list<string>
     */
    public function dates(string $from, string $to): array
    {
        $start = DateSpan::day($from);
        $end = DateSpan::day($to);
        if ($start === null || $end === null) {
            throw new \InvalidArgumentException('Enter valid start and end dates.');
        }
        return DateSpan::inclusive($start, $end);
    }

    private function draftDisplay(string $status, ?int $minutes, string $raw): string
    {
        if ($status === VisitStatus::MISSED) {
            return '';
        }
        if ($minutes !== null) {
            return TimeOfDay::format($minutes);
        }
        if ($status !== VisitStatus::RECORDED) {
            return trim($raw);
        }
        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matrix(int $teamId, string $from, string $to): array
    {
        return $this->matrix->build(
            $this->visits->patientsForRange($teamId, $from, $to),
            $this->dates($from, $to),
            $this->visits->visitsForRange($teamId, $from, $to),
            $this->visits->staffNamesByDate($teamId, $from, $to)
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<int|string, mixed> $rawSlots
     * @return list<array<string, mixed>>
     */
    private function overlay(array $rows, array $rawSlots): array
    {
        foreach ($rows as $index => $row) {
            $fieldsByPeriod = $rawSlots[$row['pid']] ?? $rawSlots[(string) $row['pid']] ?? null;
            if (!is_array($fieldsByPeriod)) {
                continue;
            }
            foreach (VisitPeriod::all() as $period) {
                $fields = $fieldsByPeriod[$period] ?? [];
                if (!is_array($fields)) {
                    continue;
                }
                $draft = SlotDraft::fromInput(
                    $this->textField($fields['missed'] ?? ''),
                    $this->textField($fields['in'] ?? ''),
                    $this->textField($fields['out'] ?? '')
                );
                $row['slots'][$period]['status'] = $draft->status;
                $timeInRaw = $this->textField($fields['in'] ?? '');
                $timeOutRaw = $this->textField($fields['out'] ?? '');
                $row['slots'][$period]['timeInDisplay'] = $this->draftDisplay($draft->status, $draft->timeIn, $timeInRaw);
                $row['slots'][$period]['timeOutDisplay'] = $this->draftDisplay($draft->status, $draft->timeOut, $timeOutRaw);
                if ($draft->status === VisitStatus::RECORDED && $draft->errors !== []) {
                    $row['slots'][$period]['timeInDisplay'] = trim($timeInRaw);
                    $row['slots'][$period]['timeOutDisplay'] = trim($timeOutRaw);
                }
                $duration = TimeOfDay::durationMinutes($draft->timeIn, $draft->timeOut);
                $row['slots'][$period]['durationMinutes'] = $duration;
                $row['slots'][$period]['durationDisplay'] = $duration === null ? '' : TimeOfDay::formatDuration($duration);
            }
            $rows[$index] = $row;
        }
        return $rows;
    }

    /**
     * @param list<mixed> $ids
     * @return list<int>
     */
    private function idList(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            if (is_scalar($id)) {
                $clean[] = (int) $id;
            }
        }
        return array_values(array_unique($clean));
    }

    private function textField(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
