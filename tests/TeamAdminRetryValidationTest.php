<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamSyncBatchResult;
use OpenEMR\Modules\ProviderTeams\Http\CareTeamSyncFlash;
use OpenEMR\Modules\ProviderTeams\Persistence\DirectoryStore;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Persistence\VisitStore;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSync;
use OpenEMR\Modules\ProviderTeams\Service\TeamAdmin;
use PHPUnit\Framework\TestCase;

final class TeamAdminRetryValidationTest extends TestCase
{
    public function testRetryCareTeamSyncRejectsNonDigitPatientIds(): void
    {
        $sync = $this->createMock(CareTeamSync::class);
        $sync->expects(self::once())
            ->method('syncTeamToPatients')
            ->with(1, [5])
            ->willReturn(new CareTeamSyncBatchResult(1, 1, []));

        $teams = $this->createMock(TeamStore::class);
        $teams->method('find')->willReturn(['id' => 1, 'name' => 'T', 'active' => 1, 'notes' => '']);
        $teams->method('activeTeamForPatient')->willReturn(['team_id' => 1, 'team_name' => 'T']);

        $visits = $this->createMock(VisitStore::class);

        $admin = new TeamAdmin($teams, new DirectoryStore(), $sync, $visits);
        $admin->retryCareTeamSync(1, ['5abc', '5', '00', '-3'], CareTeamSyncFlash::CONTEXT_TEAM_SYNC);
    }
}
