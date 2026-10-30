<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Domain\SlotDraft;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSlotValidator;
use OpenEMR\Modules\ProviderTeams\Domain\VisitStatus;
use PHPUnit\Framework\TestCase;

final class VisitSlotTest extends TestCase
{
    public function testEmptyInputIsBlank(): void
    {
        $draft = SlotDraft::fromInput('', '', '');
        self::assertSame(VisitStatus::BLANK, $draft->status);
        self::assertNull($draft->timeIn);
        self::assertNull($draft->timeOut);
        self::assertSame([], $draft->errors);
    }

    public function testMissedCheckboxDropsAnyTimes(): void
    {
        $draft = SlotDraft::fromInput('1', '17:45', '18:15');
        self::assertSame(VisitStatus::MISSED, $draft->status);
        self::assertNull($draft->timeIn);
        self::assertNull($draft->timeOut);
        self::assertSame([], $draft->errors);
    }

    public function testPaperMissedMarksBecomeAMissedSlot(): void
    {
        $draft = SlotDraft::fromInput('', 'XXXXXX', 'XXX');
        self::assertSame(VisitStatus::MISSED, $draft->status);
        self::assertNull($draft->timeIn);
        self::assertNull($draft->timeOut);
    }

    public function testRecordedSlotKeepsMinutes(): void
    {
        $draft = SlotDraft::fromInput('', '06.00', '06.50');
        self::assertSame(VisitStatus::RECORDED, $draft->status);
        self::assertSame(360, $draft->timeIn);
        self::assertSame(410, $draft->timeOut);
        self::assertSame([], $draft->errors);
    }

    public function testOneSidedOrBackwardsTimesAreRejected(): void
    {
        $missing = SlotDraft::fromInput('', '06:00', '');
        self::assertSame(['Enter both time in and time out.'], $missing->errors);

        $backwards = SlotDraft::fromInput('', '18:15', '17:45');
        self::assertSame(['Time out must be after time in.'], $backwards->errors);

        $equal = SlotDraft::fromInput('', '12:00', '12:00');
        self::assertSame(['Time out must be after time in.'], $equal->errors);

        $invalid = SlotDraft::fromInput('', 'nope', '07:00');
        self::assertSame(['Time in is not a valid time.'], $invalid->errors);
    }

    public function testValidatorProtectsStoredInvariants(): void
    {
        $validator = new VisitSlotValidator();
        self::assertSame([], $validator->validate(VisitStatus::RECORDED, 360, 410));
        self::assertSame(
            ['A call marked not required cannot have times.'],
            $validator->validate(VisitStatus::MISSED, 360, null)
        );
        self::assertSame(
            ['A blank visit cannot have times.'],
            $validator->validate(VisitStatus::BLANK, null, 410)
        );
        self::assertSame(['Unknown visit status.'], $validator->validate('other', null, null));
    }
}
