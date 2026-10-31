<?php

/**
 * Deactivation cleanup was requested but the provider team is active again.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Service;

final class TeamActiveAgainException extends TeamRuleException
{
    public function __construct()
    {
        parent::__construct('team_active_again');
    }
}
