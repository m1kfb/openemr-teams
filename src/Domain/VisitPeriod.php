<?php

/**
 * The four calls on a team day, in the order used by the visit log.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitPeriod
{
    public const MORNING = 'morning';
    public const MIDDAY = 'midday';
    public const AFTERNOON = 'afternoon';
    public const EVENING = 'evening';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::MORNING, self::MIDDAY, self::AFTERNOON, self::EVENING];
    }

    public static function label(string $period): string
    {
        return match ($period) {
            self::MORNING => 'Morning',
            self::MIDDAY => 'Midday',
            self::AFTERNOON => 'Afternoon',
            self::EVENING => 'Evening',
            default => throw new \InvalidArgumentException('Unknown visit period: ' . $period),
        };
    }

    public static function isKnown(string $period): bool
    {
        return in_array($period, self::all(), true);
    }
}
