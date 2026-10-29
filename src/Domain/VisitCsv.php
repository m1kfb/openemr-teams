<?php

/**
 * CSV in the same column order as the staff log: patient, date, four in/out pairs, staff.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitCsv
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function render(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to build the visit export.');
        }
        fputcsv($handle, [
            'Patient name',
            'DATE',
            'MORNING in',
            'MORNING out',
            'MIDDAY in',
            'MIDDAY out',
            'AFTERNOON in',
            'AFTERNOON out',
            'EVENING in',
            'EVENING out',
            'STAFF',
        ]);
        foreach ($rows as $row) {
            $line = [
                (string) $row['patientName'],
                (string) $row['dateDisplay'],
            ];
            foreach (VisitPeriod::all() as $period) {
                $slot = $row['slots'][$period];
                $line[] = $this->cell($slot, 'timeInDisplay');
                $line[] = $this->cell($slot, 'timeOutDisplay');
            }
            $line[] = (string) $row['staffLabel'];
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        if ($csv === false) {
            throw new \RuntimeException('Unable to read the visit export.');
        }
        return $csv;
    }

    /**
     * @param array<string, mixed> $slot
     */
    private function cell(array $slot, string $field): string
    {
        if ($slot['status'] === VisitStatus::MISSED) {
            return $field === 'timeInDisplay' ? 'XXXXXX' : 'XXX';
        }
        return (string) $slot[$field];
    }
}
