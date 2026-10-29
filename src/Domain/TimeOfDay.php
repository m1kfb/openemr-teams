<?php

/**
 * Visit times stored as minutes from the visit date's midnight.
 * A call that finishes after midnight uses 24:00 and later, so 24:10 is 1450.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class TimeOfDay
{
    public const MAX_MINUTES = (47 * 60) + 59;

    public static function parse(string $raw): ?int
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '.')) {
            $value = substr($value, 1);
        }
        if (preg_match('/^(\d{1,2})[:.](\d{2})$/', $value, $matches) !== 1) {
            return null;
        }
        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($hours > 47 || $minutes > 59) {
            return null;
        }
        return ($hours * 60) + $minutes;
    }

    public static function isMissedToken(string $raw): bool
    {
        $value = strtoupper(trim($raw));
        if ($value === 'MISSED') {
            return true;
        }
        return $value !== '' && preg_match('/^X+$/', $value) === 1;
    }

    public static function format(int $minutes): string
    {
        if ($minutes < 0 || $minutes > self::MAX_MINUTES) {
            throw new \InvalidArgumentException('Minutes are outside the visit clock.');
        }
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function durationMinutes(?int $timeIn, ?int $timeOut): ?int
    {
        if ($timeIn === null || $timeOut === null || $timeOut <= $timeIn) {
            return null;
        }
        return $timeOut - $timeIn;
    }

    public static function formatDuration(int $minutes): string
    {
        if ($minutes < 0) {
            throw new \InvalidArgumentException('Duration cannot be negative.');
        }
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;
        if ($hours === 0) {
            return $remainder . ' min';
        }
        return $hours . ' h ' . sprintf('%02d', $remainder) . ' min';
    }
}
