<?php

/**
 * HTTP and byte-order details for the visit CSV download.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitExportFormat
{
    public const CONTENT_TYPE = 'text/csv; charset=utf-8';

    public const BOM = "\xEF\xBB\xBF";

    /**
     * Plain ASCII hyphen for copied time ranges (never an en dash).
     */
    public static function timeRange(string $timeIn, string $timeOut): string
    {
        if ($timeIn === '' && $timeOut === '') {
            return '';
        }
        return $timeIn . '-' . $timeOut;
    }
}
