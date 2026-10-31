<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamReconciler;
use OpenEMR\Modules\ProviderTeams\Domain\CareTeamSyncBatchResult;
use OpenEMR\Modules\ProviderTeams\Http\CareTeamSyncFlash;
use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamBridge;
use OpenEMR\Modules\ProviderTeams\Persistence\DirectoryStore;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Persistence\VisitStore;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSync;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSyncFailureMessage;
use OpenEMR\Modules\ProviderTeams\Service\TeamAdmin;
use PHPUnit\Framework\TestCase;

final class TeamAdminRetryDeactivationTest extends TestCase
{
    public function testRetryWithDeactivationContextRunsCleanupNotSync(): void
    {
        $sync = $this->createMock(CareTeamSync::class);
        $sync->expects(self::once())
            ->method('clearModuleCareTeamForPatients')
            ->with(1, [5, 6], true)
            ->willReturn(new CareTeamSyncBatchResult(2, 2, []));
        $sync->expects(self::never())->method('syncTeamToPatients');

        $teams = $this->createMock(TeamStore::class);
        $teams->method('find')->willReturn(['id' => 1, 'name' => 'T', 'active' => 0, 'notes' => '']);
        $teams->method('activeTeamForPatient')->willReturn(['team_id' => 1, 'team_name' => 'T']);

        $visits = $this->createMock(VisitStore::class);

        $admin = new TeamAdmin($teams, new DirectoryStore(), $sync, $visits);
        $admin->retryCareTeamSync(1, [5, 6], CareTeamSyncFlash::CONTEXT_DEACTIVATION_CLEANUP);
    }

    public function testRetryOnInactiveTeamUsesCleanupEvenWithoutDeactivationContext(): void
    {
        $sync = $this->createMock(CareTeamSync::class);
        $sync->expects(self::once())
            ->method('clearModuleCareTeamForPatients')
            ->with(1, [5], true)
            ->willReturn(new CareTeamSyncBatchResult(1, 1, []));

        $teams = $this->createMock(TeamStore::class);
        $teams->method('find')->willReturn(['id' => 1, 'name' => 'T', 'active' => 0, 'notes' => '']);
        $teams->method('activeTeamForPatient')->willReturn(['team_id' => 1, 'team_name' => 'T']);

        $visits = $this->createMock(VisitStore::class);

        $admin = new TeamAdmin($teams, new DirectoryStore(), $sync, $visits);
        $admin->retryCareTeamSync(1, [5], CareTeamSyncFlash::CONTEXT_TEAM_SYNC);
    }
}

final class CareTeamRetryTeamActiveAgainTest extends TestCase
{
    public function testDeactivationRetrySkipsCleanupWhenTeamIsActiveAgain(): void
    {
        $teams = $this->createMock(TeamStore::class);
        $teams->method('find')->willReturn(['id' => 1, 'name' => 'T', 'active' => 1, 'notes' => '']);
        $teams->method('activePatients')->willReturn([
            ['pid' => 5, 'name' => 'Patient 5', 'pubpid' => '', 'position' => 0],
        ]);
        $teams->method('patientDisplay')->willReturn([
            'pid' => 5,
            'name' => 'Patient 5',
            'pubpid' => '',
        ]);

        $links = $this->createMock(\OpenEMR\Modules\ProviderTeams\Persistence\CareTeamLinkStore::class);
        $links->expects(self::never())->method('forgetActiveLinksForPatientTeam');

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());
        $batch = $sync->clearModuleCareTeamForPatients(1, [5], true);

        self::assertSame(1, $batch->attempted);
        self::assertSame(0, $batch->succeeded);
        self::assertCount(1, $batch->failed);
        self::assertSame(CareTeamSyncFailureMessage::teamActiveAgain(), $batch->failed[0]['reason']);
    }
}
