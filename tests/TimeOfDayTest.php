<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\TimeOfDay;
use PHPUnit\Framework\TestCase;

final class TimeOfDayTest extends TestCase
{
    public function testParsesDotAndColonTimes(): void
    {
        self::assertSame(360, TimeOfDay::parse('06.00'));
        self::assertSame(410, TimeOfDay::parse('06:50'));
        self::assertSame(390, TimeOfDay::parse('6:30'));
    }

    public function testParsesTimesAfterMidnight(): void
    {
        self::assertSame(1450, TimeOfDay::parse('24.10'));
        self::assertSame(1450, TimeOfDay::parse('24:10'));
        self::assertSame((47 * 60) + 59, TimeOfDay::parse('47:59'));
    }

    public function testStripsASingleLeadingDot(): void
    {
        self::assertSame((22 * 60) + 40, TimeOfDay::parse('.22.40'));
    }

    public function testRejectsInvalidTimes(): void
    {
        self::assertNull(TimeOfDay::parse(''));
        self::assertNull(TimeOfDay::parse('   '));
        self::assertNull(TimeOfDay::parse('24:70'));
        self::assertNull(TimeOfDay::parse('48:00'));
        self::assertNull(TimeOfDay::parse('6:5'));
        self::assertNull(TimeOfDay::parse('abc'));
        self::assertNull(TimeOfDay::parse('XXXXXX'));
    }

    public function testFormatsMinutesOnA24HourPlusClock(): void
    {
        self::assertSame('06:00', TimeOfDay::format(360));
        self::assertSame('24:10', TimeOfDay::format(1450));
    }

    public function testRecognisesMissedTokensFromThePaperLog(): void
    {
        self::assertTrue(TimeOfDay::isMissedToken('XXXXXX'));
        self::assertTrue(TimeOfDay::isMissedToken('XXX'));
        self::assertTrue(TimeOfDay::isMissedToken('xxx'));
        self::assertTrue(TimeOfDay::isMissedToken('x'));
        self::assertTrue(TimeOfDay::isMissedToken('missed'));
        self::assertFalse(TimeOfDay::isMissedToken('06:00'));
        self::assertFalse(TimeOfDay::isMissedToken(''));
    }

    public function testDurationAndItsDisplay(): void
    {
        self::assertSame(50, TimeOfDay::durationMinutes(360, 410));
        self::assertSame(50, TimeOfDay::durationMinutes(1400, 1450));
        self::assertNull(TimeOfDay::durationMinutes(410, 360));
        self::assertNull(TimeOfDay::durationMinutes(360, 360));
        self::assertNull(TimeOfDay::durationMinutes(null, 410));
        self::assertSame('50 min', TimeOfDay::formatDuration(50));
        self::assertSame('1 h 10 min', TimeOfDay::formatDuration(70));
    }
}
