<?php

/**
 * Renders per-patient provider-team exclusions on the Care Team card.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ProviderTeams\Module;
use OpenEMR\Modules\ProviderTeams\Persistence\TeamStore;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSync;

final class PatientCareTeamExclusionUi
{
    private string $moduleDirectoryName;

    public function __construct(
        private readonly CareTeamSync $sync = new CareTeamSync(),
        private readonly TeamStore $teams = new TeamStore(),
        private readonly Csrf $csrf = new Csrf(),
        private readonly Templates $templates = new Templates(),
    ) {
        $this->moduleDirectoryName = basename(dirname(__DIR__, 2));
    }

    public function render(int $pid): string
    {
        if ($pid <= 0 || !$this->sync->isAvailable()) {
            return '';
        }
        $assignment = $this->teams->activeTeamForPatient($pid);
        if ($assignment === null) {
            return '';
        }
        $members = $this->sync->excludedMembersForPatient($pid);
        if ($members === []) {
            return '';
        }
        $canRestore = AclMain::aclCheckCore('patients', 'demo', '', 'write');
        return $this->templates->render('care_team_exclusions.html.twig', [
            'cardSelector' => '#card_care_team .card-body, #careteam_ps_expand .card-body',
            'members' => $members,
            'teamId' => (int) $assignment['team_id'],
            'teamName' => (string) $assignment['team_name'],
            'pid' => $pid,
            'canRestore' => $canRestore,
            'csrfToken' => $this->csrf->token(),
            'restoreUrl' => $this->publicUrl('care_team_exclusion.php'),
            'stylesheet' => Module::stylesheetPath(),
            'labels' => [
                'heading' => $this->xl('Provider team members removed for this patient'),
                'removedTag' => $this->xl('Removed for this patient'),
                'addBack' => $this->xl('Add back'),
                'teamLabel' => $this->xl('Provider team'),
            ],
        ]);
    }

    private function publicUrl(string $script): string
    {
        return '/interface/modules/custom_modules/' . $this->moduleDirectoryName . '/public/' . $script;
    }

    private function xl(string $text): string
    {
        return function_exists('xl') ? xl($text) : $text;
    }
}
