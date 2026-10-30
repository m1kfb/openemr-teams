<?php

/**
 * Whether a call was recorded, missed, or left blank.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitStatus
{
    public const BLANK = 'blank';
    public const RECORDED = 'recorded';
    public const MISSED = 'missed';

    public static function label(string $status): string
    {
        return match ($status) {
            self::BLANK => 'Blank',
            self::RECORDED => 'Recorded',
            self::MISSED => 'Not required',
            default => throw new \InvalidArgumentException('Unknown visit status: ' . $status),
        };
    }
}
