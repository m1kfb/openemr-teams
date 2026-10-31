<?php

/**
 * Neutralises spreadsheet formula injection in CSV cell values.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class CsvCellSanitizer
{
    public static function sanitize(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $first = $value[0];
        if (
            $first === '='
            || $first === '+'
            || $first === '-'
            || $first === '@'
            || $first === "\t"
            || $first === "\r"
        ) {
            return "'" . $value;
        }
        return $value;
    }
}
