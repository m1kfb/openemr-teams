<?php

/**
 * Builds the patient-by-period grid used by the dashboard, print view, and CSV.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitMatrixBuilder
{
    /**
     * @param list<array{pid: int, name: string, position: int}> $patients
     * @param list<string> $dates
     * @param list<array{pid: int, date: string, period: string, status: string, timeIn: ?int, timeOut: ?int}> $visits
     * @param array<string, list<string>> $staffNamesByDate
     * @return list<array<string, mixed>>
     */
    public function build(array $patients, array $dates, array $visits, array $staffNamesByDate): array
    {
        $patients = $this->sortPatients($patients);
        $dates = $this->sortDates($dates);
        $indexed = [];
        foreach ($visits as $visit) {
            if (!VisitPeriod::isKnown($visit['period'])) {
                continue;
            }
            $indexed[$visit['pid'] . '|' . $visit['date'] . '|' . $visit['period']] = $visit;
        }

        $rows = [];
        foreach ($dates as $date) {
            $staffLabel = StaffLabel::format($staffNamesByDate[$date] ?? []);
            foreach ($patients as $patient) {
                $slots = [];
                foreach (VisitPeriod::all() as $period) {
                    $visit = $indexed[$patient['pid'] . '|' . $date . '|' . $period] ?? null;
                    $slots[$period] = $this->slot($period, $visit);
                }
                $rows[] = [
                    'pid' => $patient['pid'],
                    'patientName' => $patient['name'],
                    'date' => $date,
                    'dateDisplay' => DateSpan::display($date),
                    'staffLabel' => $staffLabel,
                    'slots' => $slots,
                ];
            }
        }
        return $rows;
    }

    /**
     * @param list<array{pid: int, name: string, position: int}> $patients
     * @return list<array{pid: int, name: string, position: int}>
     */
    private function sortPatients(array $patients): array
    {
        usort($patients, function (array $left, array $right): int {
            $byPosition = $left['position'] <=> $right['position'];
            if ($byPosition !== 0) {
                return $byPosition;
            }
            $byName = strcasecmp($left['name'], $right['name']);
            if ($byName !== 0) {
                return $byName;
            }
            return $left['pid'] <=> $right['pid'];
        });
        return $patients;
    }

    /**
     * @param list<string> $dates
     * @return list<string>
     */
    private function sortDates(array $dates): array
    {
        $dates = array_values(array_unique($dates));
        sort($dates);
        return $dates;
    }

    /**
     * @param array{pid: int, date: string, period: string, status: string, timeIn: ?int, timeOut: ?int}|null $visit
     * @return array<string, mixed>
     */
    private function slot(string $period, ?array $visit): array
    {
        $status = $visit['status'] ?? VisitStatus::BLANK;
        if (!in_array($status, [VisitStatus::BLANK, VisitStatus::RECORDED, VisitStatus::MISSED], true)) {
            $status = VisitStatus::BLANK;
        }
        $timeIn = $status === VisitStatus::RECORDED ? $visit['timeIn'] : null;
        $timeOut = $status === VisitStatus::RECORDED ? $visit['timeOut'] : null;
        $duration = TimeOfDay::durationMinutes($timeIn, $timeOut);
        return [
            'period' => $period,
            'label' => VisitPeriod::label($period),
            'status' => $status,
            'timeIn' => $timeIn,
            'timeOut' => $timeOut,
            'timeInDisplay' => $timeIn === null ? '' : TimeOfDay::format($timeIn),
            'timeOutDisplay' => $timeOut === null ? '' : TimeOfDay::format($timeOut),
            'durationMinutes' => $duration,
            'durationDisplay' => $duration === null ? '' : TimeOfDay::formatDuration($duration),
        ];
    }
}
