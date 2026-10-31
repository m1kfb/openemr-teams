<?php

/**
 * Which visit-day fields a save request is allowed to touch.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitSaveScope
{
    /**
     * @param array<int|string, mixed> $dirtyFlags keyed by patient id
     */
    public static function shouldPersistPatient(array $dirtyFlags, int $pid): bool
    {
        if ($dirtyFlags === []) {
            return false;
        }
        $key = (string) $pid;
        if (!array_key_exists($key, $dirtyFlags) && !array_key_exists($pid, $dirtyFlags)) {
            return false;
        }
        $value = $dirtyFlags[$key] ?? $dirtyFlags[$pid] ?? null;
        if ($value === null || $value === '' || $value === '0' || $value === 0 || $value === false) {
            return false;
        }
        return true;
    }

    public static function shouldUpdateStaff(mixed $staffDirty): bool
    {
        if ($staffDirty === null || $staffDirty === '' || $staffDirty === '0' || $staffDirty === 0 || $staffDirty === false) {
            return false;
        }
        return true;
    }

    /**
     * @param array<int|string, mixed> $rawSlots
     * @param array<int|string, mixed> $dirtyFlags
     * @return array<int|string, mixed>
     */
    public static function filterPatientSlots(array $rawSlots, array $dirtyFlags): array
    {
        $filtered = [];
        foreach ($rawSlots as $pidRaw => $periods) {
            if (!is_scalar($pidRaw)) {
                continue;
            }
            $pid = (int) $pidRaw;
            if (!self::shouldPersistPatient($dirtyFlags, $pid)) {
                continue;
            }
            $filtered[$pidRaw] = $periods;
        }
        return $filtered;
    }
}
