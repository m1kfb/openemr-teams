<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamReconciler;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSyncGuard;
use PHPUnit\Framework\TestCase;

final class CareTeamReconcilerTest extends TestCase
{
    private CareTeamReconciler $reconciler;

    protected function setUp(): void
    {
        $this->reconciler = new CareTeamReconciler();
    }

    public function testTeamToPatientAddsMissingStaffWithoutDuplicates(): void
    {
        $plan = $this->reconciler->planTeamToPatient(
            teamMemberUserIds: [10, 20, 30],
            careTeamUserIds: [20, 99],
            moduleTrackedUserIds: [20],
        );
        self::assertSame([10, 30], $plan->ensureUserIds);
        self::assertSame([], $plan->removeModuleUserIds);
    }

    public function testTeamToPatientRemovesOnlyModuleTrackedMembers(): void
    {
        $plan = $this->reconciler->planTeamToPatient(
            teamMemberUserIds: [10],
            careTeamUserIds: [10, 20, 99],
            moduleTrackedUserIds: [10, 20],
        );
        self::assertSame([], $plan->ensureUserIds);
        self::assertSame([20], $plan->removeModuleUserIds);
    }

    public function testTeamToPatientPreservesManualCareTeamMembers(): void
    {
        $plan = $this->reconciler->planTeamToPatient(
            teamMemberUserIds: [10],
            careTeamUserIds: [10, 99],
            moduleTrackedUserIds: [10],
        );
        self::assertSame([], $plan->ensureUserIds);
        self::assertSame([], $plan->removeModuleUserIds);
    }

    public function testPatientToTeamAddsUsersNotOnProviderTeam(): void
    {
        $plan = $this->reconciler->planPatientToTeam(
            patientCareTeamUserIds: [5, 6],
            providerTeamMemberUserIds: [5],
            moduleLinkedOnPatientUserIds: [5],
        );
        self::assertSame([6], $plan->addToTeamUserIds);
        self::assertSame([], $plan->removeFromTeamUserIds);
    }

    public function testPatientToTeamRemovesOnlyModuleLinkedUsersMissingFromPatientCareTeam(): void
    {
        $plan = $this->reconciler->planPatientToTeam(
            patientCareTeamUserIds: [5],
            providerTeamMemberUserIds: [5, 7],
            moduleLinkedOnPatientUserIds: [5, 7],
        );
        self::assertSame([], $plan->addToTeamUserIds);
        self::assertSame([7], $plan->removeFromTeamUserIds);
    }

    public function testPatientToTeamDoesNotRemoveManualCareTeamOnlyStaff(): void
    {
        $plan = $this->reconciler->planPatientToTeam(
            patientCareTeamUserIds: [5],
            providerTeamMemberUserIds: [5, 8],
            moduleLinkedOnPatientUserIds: [5],
        );
        self::assertSame([], $plan->addToTeamUserIds);
        self::assertSame([], $plan->removeFromTeamUserIds);
    }

    public function testSyncGuardPreventsNestedDirections(): void
    {
        self::assertFalse(CareTeamSyncGuard::isApplyingTeamToPatient());
        CareTeamSyncGuard::runTeamToPatient(function (): void {
            self::assertTrue(CareTeamSyncGuard::isApplyingTeamToPatient());
            self::assertTrue(CareTeamSyncGuard::shouldSkipPatientToTeam());
            self::assertFalse(CareTeamSyncGuard::shouldSkipTeamToPatient());
        });
        CareTeamSyncGuard::runPatientToTeam(function (): void {
            self::assertTrue(CareTeamSyncGuard::shouldSkipTeamToPatient());
        });
    }
}
