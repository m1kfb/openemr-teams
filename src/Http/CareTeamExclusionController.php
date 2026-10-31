<?php

/**
 * POST handler for restoring a per-patient provider-team exclusion on the Care Team card.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSync;
use OpenEMR\Modules\ProviderTeams\Service\TeamRuleException;

final class CareTeamExclusionController
{
    public function __construct(
        private readonly CareTeamSync $sync = new CareTeamSync(),
        private readonly TeamStore $teams = new TeamStore(),
        private readonly Csrf $csrf = new Csrf(),
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public function handlePost(array $post): void
    {
        if (!AclMain::aclCheckCore('patients', 'demo', '', 'write')) {
            http_response_code(403);
            echo function_exists('xlt') ? xlt('Not authorized') : 'Not authorized';
            exit;
        }

        $token = $post['csrf_token'] ?? null;
        $this->csrf->check(is_string($token) ? $token : null);

        $action = is_string($post['action'] ?? null) ? $post['action'] : '';
        if ($action !== 'restore_exclusion') {
            http_response_code(400);
            echo function_exists('xlt') ? xlt('Unknown action.') : 'Unknown action.';
            exit;
        }

        $pid = is_scalar($post['pid'] ?? null) ? (int) $post['pid'] : 0;
        $teamId = is_scalar($post['team_id'] ?? null) ? (int) $post['team_id'] : 0;
        $userId = is_scalar($post['user_id'] ?? null) ? (int) $post['user_id'] : 0;

        if ($pid <= 0 || $teamId <= 0 || $userId <= 0) {
            throw new TeamRuleException('Invalid request.');
        }

        $assignment = $this->teams->activeTeamForPatient($pid);
        if ($assignment === null || (int) $assignment['team_id'] !== $teamId) {
            throw new TeamRuleException('That patient is not on this provider team.');
        }

        if (!$this->sync->isAvailable()) {
            throw new TeamRuleException('Care team sync is not available on this OpenEMR database schema.');
        }

        $this->sync->restoreExcludedMember($teamId, $pid, $userId);

        header('Location: ' . WebPaths::patientDemographics($pid));
        exit;
    }
}
