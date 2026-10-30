<?php

/**
 * Planned provider-team staff changes from one patient's care team (patient → team, opt-in).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class CareTeamTeamPlan
{
    /**
     * @param list<int> $addToTeamUserIds
     * @param list<int> $removeFromTeamUserIds
     */
    public function __construct(
        public readonly array $addToTeamUserIds,
        public readonly array $removeFromTeamUserIds,
    ) {
    }
}
