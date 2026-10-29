<?php

/**
 * Display names taken from OpenEMR user and patient rows.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class PersonName
{
    public static function lastCommaFirst(?string $last, ?string $first, string $fallback): string
    {
        $last = trim((string) $last);
        $first = trim((string) $first);
        if ($last === '' && $first === '') {
            return $fallback;
        }
        if ($last === '') {
            return $first;
        }
        if ($first === '') {
            return $last;
        }
        return $last . ', ' . $first;
    }
}
