<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\CsvCellSanitizer;
use OpenEMR\Modules\ProviderTeams\Domain\DateSpan;
use OpenEMR\Modules\ProviderTeams\Domain\VisitCsv;
use OpenEMR\Modules\ProviderTeams\Domain\VisitExportFormat;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSaveJson;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSaveMode;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSaveScope;
use OpenEMR\Modules\ProviderTeams\Http\Access;
use OpenEMR\Modules\ProviderTeams\Http\SessionUserId;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Persistence\VisitStore;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSync;
use OpenEMR\Modules\ProviderTeams\Service\TeamAdmin;
use OpenEMR\Modules\ProviderTeams\Service\TeamJoinDateConfirmationRequired;
use OpenEMR\Modules\ProviderTeams\Service\TeamRuleException;
use OpenEMR\Modules\ProviderTeams\Service\VisitBoard;
use PHPUnit\Framework\TestCase;

final class VisitAuditFixesTest extends TestCase
{
    private string $previousTimezone;

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Chicago');
        unset($GLOBALS['local_timezone'], $GLOBALS['gbl']);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
        unset($_SESSION['authUserID'], $GLOBALS['local_timezone'], $GLOBALS['gbl']);
        parent::tearDown();
    }

    public function testAccessUserIdReadsLegacySessionKey(): void
    {
        $_SESSION['authUserID'] = '42';
        self::assertSame(42, (new Access())->userId());
    }

    public function testSessionUserIdReadsOpenEmr85SessionWrapper(): void
    {
        $session = new class () {
            public function get(string $key): ?string
            {
                return $key === 'authUserID' ? '99' : null;
            }
        };
        $factory = new class ($session) {
            public function __construct(private object $session)
            {
            }

            public function getActiveSession(): object
            {
                return $this->session;
            }
        };
        self::assertSame(99, SessionUserId::current($factory));
    }

    public function testVisitSaveScopeKeepsOnlyDirtyPatients(): void
    {
        $raw = [
            1 => ['morning' => ['in' => '06:00']],
            2 => ['morning' => ['in' => '07:00']],
        ];
        $filtered = VisitSaveScope::filterPatientSlots($raw, ['1' => '1', '2' => '0']);
        self::assertSame([1], array_keys($filtered));
    }

    public function testStaffDirtyFlag(): void
    {
        self::assertFalse(VisitSaveScope::shouldUpdateStaff('0'));
        self::assertTrue(VisitSaveScope::shouldUpdateStaff('1'));
    }

    public function testAssignedAtUsesClinicTimezone(): void
    {
        $GLOBALS['local_timezone'] = 'America/Chicago';
        self::assertSame('2026-03-04 09:30:00', DateSpan::assignedAt('2026-03-04T09:30'));
        self::assertSame('2026-03-04T09:30', DateSpan::assignedAtInputValue('2026-03-04 09:30:00'));
    }

    public function testAssignedAtRejectsInvalidInput(): void
    {
        self::assertNull(DateSpan::assignedAt('not-a-date'));
    }

    public function testTeamJoinDateRejectsFutureDates(): void
    {
        $admin = $this->teamAdminWithAssignment('2026-01-01 00:00:00', 0);
        $this->expectException(TeamRuleException::class);
        $this->expectExceptionMessage('future');
        $admin->updatePatientAssignedAt(1, 5, '2099-12-31T23:59', false);
    }

    public function testTeamJoinDateRequiresConfirmationWhenVisitsWouldBeHidden(): void
    {
        $admin = $this->teamAdminWithAssignment('2026-01-01 00:00:00', 2);
        try {
            $admin->updatePatientAssignedAt(1, 5, '2026-06-01T00:00', false);
            self::fail('Expected confirmation requirement.');
        } catch (TeamJoinDateConfirmationRequired $exception) {
            self::assertSame(2, $exception->affectedVisitCount);
            self::assertSame(5, $exception->pid);
        }
    }

    public function testTeamJoinDateSavesWhenConfirmed(): void
    {
        $teams = new class () extends TeamStore {
            public ?string $saved = null;

            public function find(int $teamId): ?array
            {
                return ['id' => $teamId, 'name' => 'A', 'active' => 1, 'notes' => ''];
            }

            public function findActivePatientAssignment(int $teamId, int $pid): ?array
            {
                return ['assigned_at' => '2026-01-01 00:00:00'];
            }

            public function updateAssignedAt(int $teamId, int $pid, string $assignedAt): void
            {
                $this->saved = $assignedAt;
            }
        };
        $visits = new class () extends VisitStore {
            public function countVisitsExcludedByJoin(int $teamId, int $pid, string $assignedAt): int
            {
                return 1;
            }
        };
        $admin = new TeamAdmin(
            $teams,
            new \OpenEMR\Modules\ProviderTeams\Persistence\DirectoryStore(),
            new CareTeamSync(),
            $visits
        );
        $admin->updatePatientAssignedAt(1, 5, '2026-06-01T00:00', true, 1);
        self::assertSame('2026-06-01 00:00:00', $teams->saved);
    }

    public function testTeamJoinDateRejectsPatientNotOnTeam(): void
    {
        $teams = new class () extends TeamStore {
            public function find(int $teamId): ?array
            {
                return ['id' => $teamId, 'name' => 'A', 'active' => 1, 'notes' => ''];
            }

            public function findActivePatientAssignment(int $teamId, int $pid): ?array
            {
                return null;
            }
        };
        $admin = new TeamAdmin(
            $teams,
            new \OpenEMR\Modules\ProviderTeams\Persistence\DirectoryStore(),
            new CareTeamSync(),
            new VisitStore()
        );
        $this->expectException(TeamRuleException::class);
        $this->expectExceptionMessage('not on this team');
        $admin->updatePatientAssignedAt(1, 999, '2026-03-01T00:00', false);
    }

    public function testTeamJoinDateRejectsStaleConfirmCount(): void
    {
        $admin = $this->teamAdminWithAssignment('2026-01-01 00:00:00', 3);
        $this->expectException(TeamRuleException::class);
        $this->expectExceptionMessage('count changed');
        $admin->updatePatientAssignedAt(1, 5, '2026-06-01T00:00', true, 2);
    }

    public function testCsvSanitizerPrefixesSpreadsheetFormulaCells(): void
    {
        self::assertSame("'=1+1", CsvCellSanitizer::sanitize('=1+1'));
        self::assertSame("'+cmd", CsvCellSanitizer::sanitize('+cmd'));
        $rows = (new \OpenEMR\Modules\ProviderTeams\Domain\VisitMatrixBuilder())->build(
            [['pid' => 1, 'name' => '=HYPERLINK("x")', 'position' => 1]],
            ['2026-03-30'],
            [],
            []
        );
        $csv = (new VisitCsv())->render($rows);
        self::assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function testPartialSavePersistsOnlyDirtyPatientRows(): void
    {
        $teams = new class () extends TeamStore {
            public function find(int $teamId): ?array
            {
                return ['id' => $teamId, 'name' => 'A', 'active' => 1, 'notes' => ''];
            }

            public function activeMemberIds(int $teamId): array
            {
                return [10];
            }
        };
        $visits = new class () extends VisitStore {
            /** @var list<array<string, mixed>> */
            public array $savedDays = [];

            /**
             * @return list<array{pid: int, name: string, position: int}>
             */
            public function patientsForRange(int $teamId, string $from, string $to): array
            {
                return [
                    ['pid' => 1, 'name' => 'One', 'position' => 1],
                    ['pid' => 2, 'name' => 'Two', 'position' => 2],
                ];
            }

            public function staffIdsForDate(int $teamId, string $date): array
            {
                return [10];
            }

            public function saveDay(
                int $teamId,
                string $date,
                array $staffIds,
                array $slots,
                int $recordedBy,
                bool $updateStaff = true,
            ): void {
                $this->savedDays[] = [
                    'teamId' => $teamId,
                    'date' => $date,
                    'staffIds' => $staffIds,
                    'slots' => $slots,
                    'recordedBy' => $recordedBy,
                    'updateStaff' => $updateStaff,
                ];
            }
        };
        $board = new VisitBoard($teams, $visits);
        $errors = $board->saveDay(
            1,
            '2026-03-01',
            [10],
            [
                1 => [
                    'morning' => ['in' => '06:00', 'out' => '06:30', 'missed' => ''],
                    'midday' => ['in' => '', 'out' => '', 'missed' => ''],
                    'afternoon' => ['in' => '', 'out' => '', 'missed' => ''],
                    'evening' => ['in' => '', 'out' => '', 'missed' => ''],
                ],
                2 => [
                    'morning' => ['in' => '', 'out' => '', 'missed' => ''],
                    'midday' => ['in' => '', 'out' => '', 'missed' => ''],
                    'afternoon' => ['in' => '', 'out' => '', 'missed' => ''],
                    'evening' => ['in' => '', 'out' => '', 'missed' => ''],
                ],
            ],
            7,
            ['1' => '1', '2' => '0'],
            false
        );
        self::assertSame([], $errors);
        self::assertCount(1, $visits->savedDays);
        $pids = array_values(array_unique(array_column($visits->savedDays[0]['slots'], 'pid')));
        self::assertSame([1], $pids);
        self::assertSame(7, $visits->savedDays[0]['recordedBy']);
        self::assertTrue($visits->savedDays[0]['updateStaff']);
    }

    public function testCsvExportUsesUtf8BomAndAsciiHyphenInRanges(): void
    {
        self::assertSame("\xEF\xBB\xBF", VisitExportFormat::BOM);
        self::assertSame('text/csv; charset=utf-8', VisitExportFormat::CONTENT_TYPE);
        self::assertSame('06:00-06:30', VisitExportFormat::timeRange('06:00', '06:30'));
        self::assertStringNotContainsString('–', VisitExportFormat::timeRange('06:00', '06:30'));
    }

    public function testVisitSaveModeDetectsRowSavePost(): void
    {
        self::assertTrue(VisitSaveMode::isRowSave(['save_mode' => 'row']));
        self::assertFalse(VisitSaveMode::isRowSave(['save_mode' => 'all']));
        self::assertFalse(VisitSaveMode::isRowSave([]));
    }

    public function testVisitSaveJsonFailurePayloadListsTranslatedErrors(): void
    {
        $payload = VisitSaveJson::failure(['Choose the staff who attended this day.']);
        self::assertFalse($payload['ok']);
        self::assertSame(['Choose the staff who attended this day.'], $payload['errors']);
    }

    public function testVisitSaveJsonSuccessPayload(): void
    {
        self::assertSame(['ok' => true], VisitSaveJson::success());
    }

    private function teamAdminWithAssignment(string $assignedAt, int $excludedVisits): TeamAdmin
    {
        $teams = new class ($assignedAt) extends TeamStore {
            public function __construct(private string $assignedAt)
            {
            }

            public function find(int $teamId): ?array
            {
                return ['id' => $teamId, 'name' => 'A', 'active' => 1, 'notes' => ''];
            }

            public function findActivePatientAssignment(int $teamId, int $pid): ?array
            {
                return ['assigned_at' => $this->assignedAt];
            }
        };
        $visits = new class ($excludedVisits) extends VisitStore {
            public function __construct(private int $excludedVisits)
            {
            }

            public function countVisitsExcludedByJoin(int $teamId, int $pid, string $assignedAt): int
            {
                return $this->excludedVisits;
            }
        };
        return new TeamAdmin(
            $teams,
            new \OpenEMR\Modules\ProviderTeams\Persistence\DirectoryStore(),
            new CareTeamSync(),
            $visits
        );
    }
}
