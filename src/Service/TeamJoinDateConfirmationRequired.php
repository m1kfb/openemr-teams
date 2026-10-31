<?php

/**
 * Join date would hide existing visits; user must confirm.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Service;

final class TeamJoinDateConfirmationRequired extends TeamRuleException
{
    public function __construct(
        public readonly int $affectedVisitCount,
        public readonly int $pid,
        public readonly string $assignedAtInput,
    ) {
        parent::__construct(sprintf(
            'Moving the team join date later would hide %d recorded visit(s) on the dashboard. '
            . 'Confirm to save anyway, or choose an earlier join date.',
            $affectedVisitCount
        ));
    }
}
