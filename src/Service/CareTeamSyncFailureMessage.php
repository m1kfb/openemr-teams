<?php

/**
 * Admin-safe care team sync failure text (details go to the error log).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Service;

final class CareTeamSyncFailureMessage
{
    public static function forPatient(int $teamId, int $pid, \Throwable $exception): string
    {
        error_log(sprintf(
            'Provider Teams: care team sync failed for pid %d on team %d: %s',
            $pid,
            $teamId,
            $exception->getMessage()
        ));

        $text = 'Care team could not be updated for this patient.';
        return function_exists('xl') ? xl($text) : $text;
    }
}
