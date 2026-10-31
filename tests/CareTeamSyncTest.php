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
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSyncFailureMessage;
use PHPUnit\Framework\TestCase;

final class CareTeamSyncTest extends TestCase
{
    public function testManualRemovalIsRecordedBeforePlanning(): void
    {
        $links = $this->createMock(CareTeamLinkStore::class);
        $links->expects(self::once())
            ->method('withPatientTeamLock')
            ->willReturnCallback(static fn (int $teamId, int $pid, callable $action): mixed => $action());
        $links->expects(self::once())
            ->method('recordManualRemovals')
            ->with(3, 42, [10], [10, 20]);
        $links->method('activeTrackedUserIds')->willReturn([10]);
        $links->method('excludedUserIds')->willReturn([20]);

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);
        $bridge->method('activeUserIdsForPatient')->willReturn([10]);
        $bridge->method('presentUserIdsForPatient')->willReturn([10]);

        $teams = $this->createMock(TeamStore::class);
        $teams->method('find')->willReturn(['id' => 3, 'active' => 1, 'name' => 'T', 'notes' => '']);
        $teams->method('activeMemberIds')->willReturn([10, 20]);
        $teams->method('activePatientIds')->willReturn([42]);

        $bridge->expects(self::never())->method('applyUserChanges');

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());
        $sync->syncTeamToPatient(3, 42);
    }

    public function testApplyUserChangesFailureDoesNotUpdateLinks(): void
    {
        $links = $this->createMock(CareTeamLinkStore::class);
        $links->method('withPatientTeamLock')
            ->willReturnCallback(static fn (int $teamId, int $pid, callable $action): mixed => $action());
        $links->method('recordManualRemovals');
        $links->method('activeTrackedUserIds')->willReturn([20]);
        $links->method('excludedUserIds')->willReturn([]);

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);
        $bridge->method('activeUserIdsForPatient')->willReturn([20]);
        $bridge->method('applyUserChanges')
            ->willThrowException(new \RuntimeException('save failed'));

        $teams = $this->createMock(TeamStore::class);

        $links->expects(self::never())->method('forgetUser');
        $links->expects(self::never())->method('remember');

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());

        $this->expectException(\RuntimeException::class);
        $sync->syncTeamToPatient(1, 9);
    }

    public function testRestoreExcludedMemberClearsExclusionAndAddsUser(): void
    {
        $links = $this->createMock(CareTeamLinkStore::class);
        $links->method('isExcluded')->with(2, 5, 7)->willReturn(true);
        $links->method('withPatientTeamLock')
            ->willReturnCallback(static fn (int $teamId, int $pid, callable $action): mixed => $action());
        $links->expects(self::once())->method('clearExclusion')->with(2, 5, 7);
        $links->method('recordManualRemovals');
        $links->method('activeTrackedUserIds')->willReturn([]);
        $links->method('excludedUserIds')->willReturn([]);
        $links->expects(self::once())->method('remember')->with(2, 5, 7, 99);

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);
        $bridge->method('activeUserIdsForPatient')->willReturn([]);
        $bridge->method('applyUserChanges')->with(5, [7], []);
        $bridge->method('findMemberId')->with(5, 7)->willReturn(99);

        $teams = $this->createMock(TeamStore::class);
        $teams->method('activeMemberIds')->with(2)->willReturn([7]);

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());
        $sync->restoreExcludedMember(2, 5, 7);
    }

    public function testSyncTeamToPatientsCollectsPartialFailures(): void
    {
        $links = $this->createMock(CareTeamLinkStore::class);
        $links->method('withPatientTeamLock')
            ->willReturnCallback(static fn (int $teamId, int $pid, callable $action): mixed => $action());
        $links->method('recordManualRemovals');
        $links->method('activeTrackedUserIds')->willReturn([]);
        $links->method('excludedUserIds')->willReturn([]);

        $bridge = $this->createMock(CareTeamBridge::class);
        $bridge->method('isSupported')->willReturn(true);
        $bridge->method('activeUserIdsForPatient')->willReturn([]);
        $bridge->method('presentUserIdsForPatient')->willReturn([]);
        $bridge->method('applyUserChanges')->willReturnCallback(
            static function (int $pid): void {
                if ($pid === 2) {
                    throw new \RuntimeException('save failed for pid 2');
                }
            }
        );

        $teams = $this->createMock(TeamStore::class);
        $teams->method('find')->willReturn(['id' => 1, 'active' => 1, 'name' => 'North', 'notes' => '']);
        $teams->method('activeMemberIds')->willReturn([9]);
        $teams->method('activePatients')->willReturn([
            ['pid' => 1, 'name' => 'Alpha, One', 'pubpid' => 'A1', 'position' => 0],
            ['pid' => 2, 'name' => 'Beta, Two', 'pubpid' => 'B2', 'position' => 1],
        ]);
        $teams->method('patientDisplay')->willReturnCallback(static fn (int $pid): array => [
            'pid' => $pid,
            'name' => 'Patient ' . $pid,
            'pubpid' => '',
        ]);

        $sync = new CareTeamSync($teams, $bridge, $links, new CareTeamReconciler());
        $result = $sync->syncTeamToPatients(1, [1, 2]);

        self::assertSame(2, $result->attempted);
        self::assertSame(1, $result->succeeded);
        self::assertCount(1, $result->failed);
        self::assertSame(2, $result->failed[0]['pid']);
        self::assertSame(
            CareTeamSyncFailureMessage::forPatient(1, 2, new \RuntimeException('save failed for pid 2')),
            $result->failed[0]['reason']
        );
    }
}
