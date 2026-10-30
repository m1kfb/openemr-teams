<?php

/**
 * Rules for one call: blank, missed, or a time in that is earlier than time out.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitSlotValidator
{
    /**
     * @return list<string>
     */
    public function validate(string $status, ?int $timeIn, ?int $timeOut): array
    {
        $errors = [];
        switch ($status) {
            case VisitStatus::BLANK:
                if ($timeIn !== null || $timeOut !== null) {
                    $errors[] = 'A blank visit cannot have times.';
                }
                break;
            case VisitStatus::MISSED:
                if ($timeIn !== null || $timeOut !== null) {
                    $errors[] = 'A call marked not required cannot have times.';
                }
                break;
            case VisitStatus::RECORDED:
                if ($timeIn === null || $timeOut === null) {
                    $errors[] = 'Enter both time in and time out.';
                } elseif ($timeOut <= $timeIn) {
                    $errors[] = 'Time out must be after time in.';
                }
                break;
            default:
                $errors[] = 'Unknown visit status.';
                break;
        }
        return $errors;
    }
}
