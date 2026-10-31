<?php

/**
 * Inclusive visit dates, capped so an audit page stays readable.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class DateSpan
{
    public const MAX_DAYS = 31;

    public static function day(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        if ($date->format('Y-m-d') !== $value) {
            return null;
        }
        return $date;
    }

    /**
     * @return list<string>
     */
    public static function inclusive(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($to < $from) {
            throw new \InvalidArgumentException('The end date must be on or after the start date.');
        }
        $start = new \DateTimeImmutable($from->format('Y-m-d') . ' 00:00:00', new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable($to->format('Y-m-d') . ' 00:00:00', new \DateTimeZone('UTC'));
        $days = $start->diff($end)->days;
        if ($days === false || $days > self::MAX_DAYS - 1) {
            throw new \InvalidArgumentException('Choose 31 days or fewer.');
        }
        $dates = [];
        $cursor = $start;
        while ($cursor <= $to) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }
        return $dates;
    }

    public static function display(string $isoDate): string
    {
        $date = self::day($isoDate);
        if ($date === null) {
            return $isoDate;
        }
        return $date->format('d/m/Y');
    }

    /**
     * Normalizes a team join timestamp from a date or datetime-local field.
     */
    public static function assignedAt(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_contains($value, 'T')) {
            $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();
            if (
                $dateTime === false
                || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            ) {
                return null;
            }
            return $dateTime->format('Y-m-d H:i:s');
        }
        $day = self::day($value);
        if ($day === null) {
            return null;
        }
        return $day->format('Y-m-d') . ' 00:00:00';
    }

    /**
     * Value for an HTML datetime-local input from a stored datetime.
     */
    public static function assignedAtInputValue(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $stored, new \DateTimeZone('UTC'));
        if ($dateTime === false) {
            $day = self::day(substr($stored, 0, 10));
            if ($day === null) {
                return '';
            }
            return $day->format('Y-m-d') . 'T00:00';
        }
        return $dateTime->format('Y-m-d\TH:i');
    }
}
