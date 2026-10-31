<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamReconciler;
use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamBridge;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSync;
use OpenEMR\Modules\ProviderTeams\Tests\Support\InMemoryCareTeamLinkStore;
use PHPUnit\Framework\TestCase;

final class CareTeamDeactivationExclusionTest extends TestCase
{
    public function testDeactivationCleanupClearsOnlyNonExcludedLinks(): void
    {
        $links = new class extends InMemoryCareTeamLinkStore {
            public int $forgetActiveCalls = 0;

            public function forgetActiveLinksForPatientTeam(int $providerTeamId, int $pid): void
            {
                $this->forgetActiveCalls++;
                parent::forgetActiveLinksForPatientTeam($providerTeamId, $pid);
            }
        };
        $links->rows = [
            ['provider_team_id' => 1, 'pid' => 5, 'user_id' => 10, 'excluded' => 0],
            ['provider_team_id' => 1, 'pid' => 5, 'user_id' => 42, 'excluded' => 1],
        ];

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);

        $teams = $this->createMock(TeamStore::class);

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());
        $sync->clearModuleCareTeamForPatient(1, 5);

        self::assertSame(1, $links->forgetActiveCalls);
        self::assertSame([42], $links->excludedUserIds(1, 5));
        self::assertSame([], $links->activeTrackedUserIds(1, 5));
    }

    public function testExcludedLinksSurviveDeactivateReactivateAndResync(): void
    {
        $teamActive = 0;
        $links = new InMemoryCareTeamLinkStore();
        $links->rows = [
            ['provider_team_id' => 1, 'pid' => 5, 'user_id' => 10, 'excluded' => 0],
            ['provider_team_id' => 1, 'pid' => 5, 'user_id' => 42, 'excluded' => 1],
        ];

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);

        $teams = $this->createMock(TeamStore::class);
        $teams->method('find')->willReturnCallback(
            static function () use (&$teamActive): array {
                return ['id' => 1, 'active' => $teamActive, 'name' => 'T', 'notes' => ''];
            }
        );
        $teams->method('activePatientIds')->willReturn([5]);
        $teams->method('activePatients')->willReturn([
            ['pid' => 5, 'name' => 'Patient 5', 'pubpid' => '', 'position' => 0],
        ]);
        $teams->method('activeMemberIds')->willReturn([10, 20]);
        $teams->method('patientDisplay')->willReturn([
            'pid' => 5,
            'name' => 'Patient 5',
            'pubpid' => '',
        ]);

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());
        $batch = $sync->clearModuleCareTeamForAllPatients(1);

        self::assertTrue($batch->isFullSuccess());
        self::assertSame([42], $links->excludedUserIds(1, 5));
        self::assertSame([], $links->activeTrackedUserIds(1, 5));

        $teamActive = 1;
        $bridge->method('activeUserIdsForPatient')->willReturn([]);
        $bridge->method('presentUserIdsForPatient')->willReturn([]);
        $bridge->method('findMemberId')->willReturn(999);

        $sync->syncTeamToPatient(1, 5);

        self::assertSame([42], $links->excludedUserIds(1, 5));
        self::assertContains(10, $links->activeTrackedUserIds(1, 5));
        self::assertContains(20, $links->activeTrackedUserIds(1, 5));
    }
}
