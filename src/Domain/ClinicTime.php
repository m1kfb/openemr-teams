<?php

/**
 * Clinic-local clock used for team join timestamps (assigned_at).
 *
 * Team join datetimes are stored as naive Y-m-d H:i:s values in the same
 * convention as OpenEMR core: PHP's default timezone after globals.php runs
 * (from the OpenEMR local timezone setting). They are not UTC.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class ClinicTime
{
    public static function timezone(): \DateTimeZone
    {
        $name = null;
        if (isset($GLOBALS['local_timezone']) && is_string($GLOBALS['local_timezone']) && $GLOBALS['local_timezone'] !== '') {
            $name = $GLOBALS['local_timezone'];
        } elseif (isset($GLOBALS['gbl']['local_timezone']) && is_string($GLOBALS['gbl']['local_timezone'])) {
            $name = $GLOBALS['gbl']['local_timezone'];
        }
        if ($name !== null && $name !== '') {
            try {
                return new \DateTimeZone($name);
            } catch (\Exception) {
                // Fall through to PHP default.
            }
        }
        return new \DateTimeZone(date_default_timezone_get());
    }

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', self::timezone());
    }

    public static function parseStored(string $stored): ?\DateTimeImmutable
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }
        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $stored, self::timezone());
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $dateTime !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        ) {
            return $dateTime;
        }
        $day = DateSpan::day(substr($stored, 0, 10));
        if ($day === null) {
            return null;
        }
        return new \DateTimeImmutable($day->format('Y-m-d') . ' 00:00:00', self::timezone());
    }

    public static function formatStored(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(self::timezone())->format('Y-m-d H:i:s');
    }
}
