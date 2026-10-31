<?php

/**
 * Wires the Provider Teams module pages and services.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams;

use OpenEMR\Modules\ProviderTeams\Http\Access;
use OpenEMR\Modules\ProviderTeams\Http\Csrf;
use OpenEMR\Modules\ProviderTeams\Http\PatientCareTeamExclusionUi;
use OpenEMR\Modules\ProviderTeams\Http\TeamController;
use OpenEMR\Modules\ProviderTeams\Http\Templates;
use OpenEMR\Modules\ProviderTeams\Http\VisitController;
use OpenEMR\Modules\ProviderTeams\Persistence\DirectoryStore;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Persistence\VisitStore;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSync;
use OpenEMR\Modules\ProviderTeams\Service\TeamAdmin;
use OpenEMR\Modules\ProviderTeams\Service\VisitBoard;

final class Module
{
    public static function teamAdmin(): TeamAdmin
    {
        return new TeamAdmin(new TeamStore(), new DirectoryStore(), new CareTeamSync());
    }

    public static function teamController(): TeamController
    {
        return new TeamController(
            self::teamAdmin(),
            new Access(),
            new Csrf(),
            new Templates(),
        );
    }

    public static function visitController(): VisitController
    {
        return new VisitController(
            self::teamAdmin(),
            new VisitBoard(new TeamStore(), new VisitStore()),
            new Access(),
            new Csrf(),
            new Templates(),
        );
    }

    public static function patientCareTeamExclusionUi(): PatientCareTeamExclusionUi
    {
        return new PatientCareTeamExclusionUi(new CareTeamSync());
    }

    public static function stylesheetPath(): string
    {
        return '/interface/modules/custom_modules/' . basename(dirname(__DIR__)) . '/public/assets/provider-teams.css';
    }
}
