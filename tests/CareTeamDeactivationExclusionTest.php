<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamReconciler;
use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamBridge;
use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamLinkStore;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSync;
use PHPUnit\Framework\TestCase;

final class CareTeamDeactivationExclusionTest extends TestCase
{
    public function testDeactivationCleanupClearsOnlyNonExcludedLinks(): void
    {
        $links = new class extends CareTeamLinkStore {
            public int $forgetActiveCalls = 0;
            public int $forgetAllCalls = 0;

            public function withPatientTeamLock(int $providerTeamId, int $pid, callable $action): mixed
            {
                return $action();
            }

            public function activeTrackedUserIds(int $providerTeamId, int $pid): array
            {
                return [10];
            }

            public function forgetActiveLinksForPatientTeam(int $providerTeamId, int $pid): void
            {
                $this->forgetActiveCalls++;
            }

            public function forgetAllForPatientTeam(int $providerTeamId, int $pid): void
            {
                $this->forgetAllCalls++;
            }

            public function excludedUserIds(int $providerTeamId, int $pid): array
            {
                return [42];
            }
        };

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);

        $teams = $this->createMock(TeamStore::class);

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());
        $sync->clearModuleCareTeamForPatient(1, 5);

        self::assertSame(1, $links->forgetActiveCalls);
        self::assertSame(0, $links->forgetAllCalls);
        self::assertSame([42], $links->excludedUserIds(1, 5));
    }

    public function testExcludedLinksSurviveDeactivateAndReactivateCycle(): void
    {
        $links = new class extends CareTeamLinkStore {
            public int $forgetActiveCalls = 0;

            /** @var list<int> */
            public array $excluded = [42];

            public function withPatientTeamLock(int $providerTeamId, int $pid, callable $action): mixed
            {
                return $action();
            }

            public function activeTrackedUserIds(int $providerTeamId, int $pid): array
            {
                return [10];
            }

            public function forgetActiveLinksForPatientTeam(int $providerTeamId, int $pid): void
            {
                $this->forgetActiveCalls++;
            }

            public function excludedUserIds(int $providerTeamId, int $pid): array
            {
                return $this->excluded;
            }
        };

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);

        $teams = $this->createMock(TeamStore::class);
        $teams->method('find')->willReturn(['id' => 1, 'active' => 1, 'name' => 'T', 'notes' => '']);
        $teams->method('activePatientIds')->willReturn([5]);
        $teams->method('activePatients')->willReturn([
            ['pid' => 5, 'name' => 'Patient 5', 'pubpid' => '', 'position' => 0],
        ]);

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());
        $batch = $sync->clearModuleCareTeamForAllPatients(1);

        self::assertTrue($batch->isFullSuccess());
        self::assertSame(1, $links->forgetActiveCalls);
        self::assertSame([42], $links->excludedUserIds(1, 5));
    }
}
