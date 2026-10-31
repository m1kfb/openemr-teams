<?php

/**
 * Restore a provider-team member to one patient's Care Team (clears per-patient exclusion).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

require_once dirname(__DIR__, 4) . '/globals.php';

use OpenEMR\Modules\ProviderTeams\Http\CareTeamExclusionController;
use OpenEMR\Modules\ProviderTeams\Service\TeamRuleException;

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit;
    }
    (new CareTeamExclusionController())->handlePost($_POST);
} catch (TeamRuleException $exception) {
    http_response_code(400);
    echo function_exists('xlt') ? xlt($exception->getMessage()) : $exception->getMessage();
}
