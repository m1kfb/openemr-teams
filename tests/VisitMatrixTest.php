<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\DateSpan;
use OpenEMR\Modules\ProviderTeams\Domain\VisitCsv;
use OpenEMR\Modules\ProviderTeams\Domain\VisitMatrixBuilder;
use OpenEMR\Modules\ProviderTeams\Domain\VisitStatus;
use PHPUnit\Framework\TestCase;

final class VisitMatrixTest extends TestCase
{
    public function testBuildsTheDailyLogInRoundOrder(): void
    {
        $rows = (new VisitMatrixBuilder())->build(
            [
                ['pid' => 2, 'name' => 'Watt, Vera', 'position' => 2],
                ['pid' => 1, 'name' => 'Patel, Rita', 'position' => 1],
            ],
            ['2026-03-31', '2026-03-30'],
            [
                [
                    'pid' => 1,
                    'date' => '2026-03-30',
                    'period' => 'morning',
                    'status' => VisitStatus::RECORDED,
                    'timeIn' => 360,
                    'timeOut' => 410,
                ],
                [
                    'pid' => 1,
                    'date' => '2026-03-30',
                    'period' => 'evening',
                    'status' => VisitStatus::RECORDED,
                    'timeIn' => 1400,
                    'timeOut' => 1450,
                ],
                [
                    'pid' => 2,
                    'date' => '2026-03-30',
                    'period' => 'afternoon',
                    'status' => VisitStatus::MISSED,
                    'timeIn' => null,
                    'timeOut' => null,
                ],
                [
                    'pid' => 1,
                    'date' => '2026-03-30',
                    'period' => 'not-a-call',
                    'status' => VisitStatus::RECORDED,
                    'timeIn' => 1,
                    'timeOut' => 2,
                ],
            ],
            ['2026-03-30' => ['Eddie Houghton', 'Lesley Hill']]
        );

        self::assertSame(['2026-03-30', '2026-03-30', '2026-03-31', '2026-03-31'], array_column($rows, 'date'));
        self::assertSame(['Patel, Rita', 'Watt, Vera', 'Patel, Rita', 'Watt, Vera'], array_column($rows, 'patientName'));
        self::assertSame('30/03/2026', $rows[0]['dateDisplay']);
        self::assertSame('Eddie Houghton & Lesley Hill', $rows[0]['staffLabel']);
        self::assertSame('', $rows[2]['staffLabel']);

        $morning = $rows[0]['slots']['morning'];
        self::assertSame(VisitStatus::RECORDED, $morning['status']);
        self::assertSame('06:00', $morning['timeInDisplay']);
        self::assertSame('06:50', $morning['timeOutDisplay']);
        self::assertSame(50, $morning['durationMinutes']);
        self::assertSame('50 min', $morning['durationDisplay']);

        $evening = $rows[0]['slots']['evening'];
        self::assertSame('23:20', $evening['timeInDisplay']);
        self::assertSame('24:10', $evening['timeOutDisplay']);
        self::assertSame(50, $evening['durationMinutes']);

        $afternoon = $rows[1]['slots']['afternoon'];
        self::assertSame(VisitStatus::MISSED, $afternoon['status']);
        self::assertSame('', $afternoon['timeInDisplay']);
        self::assertNull($afternoon['durationMinutes']);

        self::assertSame(VisitStatus::BLANK, $rows[0]['slots']['midday']['status']);
        self::assertArrayNotHasKey('not-a-call', $rows[0]['slots']);
    }

    public function testCsvUsesThePaperColumnOrderAndMissedMarks(): void
    {
        $rows = (new VisitMatrixBuilder())->build(
            [['pid' => 5, 'name' => 'Jones, Robin', 'position' => 1]],
            ['2026-03-30'],
            [[
                'pid' => 5,
                'date' => '2026-03-30',
                'period' => 'afternoon',
                'status' => VisitStatus::MISSED,
                'timeIn' => null,
                'timeOut' => null,
            ]],
            ['2026-03-30' => ['Joanne Hargreaves', 'Nicola Hargreaves']]
        );
        $csv = (new VisitCsv())->render($rows);
        $lines = $this->csvLines($csv);

        self::assertSame([
            'Patient name',
            'DATE',
            'MORNING in',
            'MORNING out',
            'MIDDAY in',
            'MIDDAY out',
            'AFTERNOON in',
            'AFTERNOON out',
            'EVENING in',
            'EVENING out',
            'STAFF',
        ], $lines[0]);
        self::assertCount(11, $lines[1]);
        self::assertSame('Jones, Robin', $lines[1][0]);
        self::assertSame('30/03/2026', $lines[1][1]);
        self::assertSame('', $lines[1][2]);
        self::assertSame('XXXXXX', $lines[1][6]);
        self::assertSame('XXX', $lines[1][7]);
        self::assertSame('Joanne Hargreaves & Nicola Hargreaves', $lines[1][10]);
    }

    /**
     * @return list<list<string|null>>
     */
    private function csvLines(string $csv): array
    {
        $lines = [];
        $handle = fopen('php://temp', 'r+');
        self::assertNotFalse($handle);
        fwrite($handle, $csv);
        rewind($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $lines[] = $row;
        }
        fclose($handle);
        return $lines;
    }

    public function testDateSpanRejectsReversedAndOverlongRanges(): void
    {
        $from = DateSpan::day('2026-03-01');
        $to = DateSpan::day('2026-03-31');
        self::assertNotNull($from);
        self::assertNotNull($to);
        self::assertCount(31, DateSpan::inclusive($from, $to));
        self::assertSame('01/03/2026', DateSpan::display('2026-03-01'));

        $this->expectException(\InvalidArgumentException::class);
        DateSpan::inclusive($to, $from);
    }

    public function testDateSpanCapsARangeAt31Days(): void
    {
        $from = DateSpan::day('2026-03-01');
        $tooFar = DateSpan::day('2026-04-01');
        self::assertNotNull($from);
        self::assertNotNull($tooFar);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('31 days');
        DateSpan::inclusive($from, $tooFar);
    }

    public function testDateSpanRejectsImpossibleDates(): void
    {
        self::assertNull(DateSpan::day('2026-02-31'));
        self::assertNull(DateSpan::day('30/03/2026'));
        self::assertNull(DateSpan::day(''));
    }
}
