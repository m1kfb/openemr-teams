<?php

/**
 * The staff column on the visit log, for example "Joanne Hargreaves & Lesley Hill".
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class StaffLabel
{
    /**
     * @param list<string> $names
     */
    public static function format(array $names): string
    {
        $clean = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name !== '') {
                $clean[] = $name;
            }
        }
        return implode(' & ', $clean);
    }
}
