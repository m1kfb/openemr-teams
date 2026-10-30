<?php

/**
 * Prevents recursive sync when team and care-team updates trigger each other.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Service;

final class CareTeamSyncGuard
{
    private static int $teamToPatientDepth = 0;

    private static int $patientToTeamDepth = 0;

    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    public static function runTeamToPatient(callable $action): mixed
    {
        ++self::$teamToPatientDepth;
        try {
            return $action();
        } finally {
            --self::$teamToPatientDepth;
        }
    }

    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    public static function runPatientToTeam(callable $action): mixed
    {
        ++self::$patientToTeamDepth;
        try {
            return $action();
        } finally {
            --self::$patientToTeamDepth;
        }
    }

    public static function isApplyingTeamToPatient(): bool
    {
        return self::$teamToPatientDepth > 0;
    }

    public static function isApplyingPatientToTeam(): bool
    {
        return self::$patientToTeamDepth > 0;
    }

    public static function shouldSkipPatientToTeam(): bool
    {
        return self::isApplyingTeamToPatient();
    }

    public static function shouldSkipTeamToPatient(): bool
    {
        return self::isApplyingPatientToTeam();
    }
}
