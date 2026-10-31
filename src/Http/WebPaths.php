<?php

/**
 * OpenEMR webroot-aware URLs for this module.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

final class WebPaths
{
    public static function webroot(): string
    {
        return (string) ($GLOBALS['webroot'] ?? '');
    }

    public static function modulePublic(string $script): string
    {
        $moduleDirectoryName = basename(dirname(__DIR__, 2));
        return self::webroot()
            . '/interface/modules/custom_modules/'
            . $moduleDirectoryName
            . '/public/'
            . ltrim($script, '/');
    }

    public static function patientDemographics(int $pid): string
    {
        return self::webroot()
            . '/interface/patient_file/summary/demographics.php?'
            . http_build_query(['set_pid' => $pid]);
    }

    public static function moduleTeams(): string
    {
        return self::modulePublic('teams.php');
    }
}
