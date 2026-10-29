<?php

/**
 * A visit slot as typed on the dashboard, before it is stored.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class SlotDraft
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly string $status,
        public readonly ?int $timeIn,
        public readonly ?int $timeOut,
        public readonly array $errors,
    ) {
    }

    public static function fromInput(string $missed, string $timeInRaw, string $timeOutRaw): self
    {
        $markedMissed = in_array(strtolower(trim($missed)), ['1', 'on', 'true', 'yes'], true);
        $inIsMissed = TimeOfDay::isMissedToken($timeInRaw);
        $outIsMissed = TimeOfDay::isMissedToken($timeOutRaw);
        if ($markedMissed || $inIsMissed || $outIsMissed) {
            return new self(VisitStatus::MISSED, null, null, []);
        }

        $timeInRaw = trim($timeInRaw);
        $timeOutRaw = trim($timeOutRaw);
        if ($timeInRaw === '' && $timeOutRaw === '') {
            return new self(VisitStatus::BLANK, null, null, []);
        }

        $timeIn = $timeInRaw === '' ? null : TimeOfDay::parse($timeInRaw);
        $timeOut = $timeOutRaw === '' ? null : TimeOfDay::parse($timeOutRaw);
        $errors = [];
        if ($timeInRaw !== '' && $timeIn === null) {
            $errors[] = 'Time in is not a valid time.';
        }
        if ($timeOutRaw !== '' && $timeOut === null) {
            $errors[] = 'Time out is not a valid time.';
        }
        if ($errors === []) {
            $errors = (new VisitSlotValidator())->validate(VisitStatus::RECORDED, $timeIn, $timeOut);
        }
        return new self(VisitStatus::RECORDED, $timeIn, $timeOut, $errors);
    }
}
