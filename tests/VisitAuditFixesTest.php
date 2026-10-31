<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\DateSpan;
use OpenEMR\Modules\ProviderTeams\Domain\VisitExportFormat;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSaveScope;
use OpenEMR\Modules\ProviderTeams\Http\Access;
use PHPUnit\Framework\TestCase;

final class VisitAuditFixesTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['authUserID']);
        parent::tearDown();
    }

    public function testAccessUserIdReadsLegacySessionKey(): void
    {
        $_SESSION['authUserID'] = '42';
        self::assertSame(42, (new Access())->userId());
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

    public function testAssignedAtAcceptsDatetimeLocalInput(): void
    {
        self::assertSame('2026-03-04 09:30:00', DateSpan::assignedAt('2026-03-04T09:30'));
        self::assertSame('2026-03-04 00:00:00', DateSpan::assignedAt('2026-03-04'));
    }

    public function testAssignedAtInputValueForStoredDatetime(): void
    {
        self::assertSame('2026-03-04T09:30', DateSpan::assignedAtInputValue('2026-03-04 09:30:00'));
    }

    public function testCsvExportUsesUtf8BomAndAsciiHyphenInRanges(): void
    {
        self::assertSame("\xEF\xBB\xBF", VisitExportFormat::BOM);
        self::assertSame('text/csv; charset=utf-8', VisitExportFormat::CONTENT_TYPE);
        self::assertSame('06:00-06:30', VisitExportFormat::timeRange('06:00', '06:30'));
        self::assertStringNotContainsString('–', VisitExportFormat::timeRange('06:00', '06:30'));
    }
}
