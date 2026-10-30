<?php

/**
 * Planned care-team membership changes for one patient (team → patient direction).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class CareTeamPatientPlan
{
    /**
     * @param list<int> $ensureUserIds Users to add to the patient's care team
     * @param list<int> $removeModuleUserIds Module-tracked users to remove from the care team
     */
    public function __construct(
        public readonly array $ensureUserIds,
        public readonly array $removeModuleUserIds,
    ) {
    }
}
