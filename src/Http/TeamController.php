<?php

/**
 * Team setup: name the team, add staff, and assign patients.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamSyncBatchResult;
use OpenEMR\Modules\ProviderTeams\Domain\Permission;
use OpenEMR\Modules\ProviderTeams\Service\CareTeamSyncFailureMessage;
use OpenEMR\Modules\ProviderTeams\Service\TeamAdmin;
use OpenEMR\Modules\ProviderTeams\Service\TeamRuleException;

final class TeamController
{
    public function __construct(
        private readonly TeamAdmin $admin,
        private readonly Access $access,
        private readonly Csrf $csrf,
        private readonly Templates $templates,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public function handle(string $method, array $query, array $post): PageResult
    {
        $this->access->demand(Permission::ADMIN);
        $teamId = $this->int($post['team_id'] ?? $query['team_id'] ?? 0);
        if (strtoupper($method) === 'POST') {
            $token = $post['csrf_token'] ?? null;
            $this->csrf->check(is_string($token) ? $token : null);
            return $this->post($teamId, $post);
        }
        return $this->get($teamId, $query, []);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function post(int $teamId, array $post): PageResult
    {
        $action = $this->text($post['action'] ?? '');
        try {
            switch ($action) {
                case 'create':
                    $teamId = $this->admin->create(
                        $this->text($post['name'] ?? ''),
                        $this->text($post['notes'] ?? ''),
                        isset($post['active'])
                    );
                    return $this->redirect($teamId, 'created');
                case 'update':
                    $batch = $this->admin->update(
                        $teamId,
                        $this->text($post['name'] ?? ''),
                        $this->text($post['notes'] ?? ''),
                        isset($post['active'])
                    );
                    if ($batch !== null && !$batch->isFullSuccess()) {
                        $this->storeSyncFlash($teamId, $batch, CareTeamSyncFlash::CONTEXT_DEACTIVATION_CLEANUP);
                    }
                    return $this->redirect($teamId, 'updated');
                case 'add_member':
                    $this->admin->addMember($teamId, $this->int($post['user_id'] ?? 0));
                    return $this->redirect($teamId, 'member_added');
                case 'remove_member':
                    $this->admin->removeMember($teamId, $this->int($post['user_id'] ?? 0));
                    return $this->redirect($teamId, 'member_removed');
                case 'assign_patient':
                    $result = $this->admin->assignPatient($teamId, $this->int($post['pid'] ?? 0));
                    return $this->redirect($teamId, $result === 'already' ? 'patient_already' : 'patient_assigned');
                case 'unassign_patient':
                    $this->admin->unassignPatient($teamId, $this->int($post['pid'] ?? 0));
                    return $this->redirect($teamId, 'patient_unassigned');
                case 'save_order':
                    $positions = $post['position'] ?? [];
                    $this->admin->saveOrder($teamId, is_array($positions) ? $positions : []);
                    return $this->redirect($teamId, 'order_saved');
                case 'pull_care_team':
                    $pid = $this->int($post['pid'] ?? 0);
                    $result = $this->admin->pullCareTeamFromPatient($teamId, $pid);
                    if ($result === null) {
                        return $this->get($teamId, ['team_id' => $teamId], [
                            'Care team sync is not available on this OpenEMR database schema.',
                        ]);
                    }
                    $this->storeSyncFlash($teamId, $result['care_team_sync'], CareTeamSyncFlash::CONTEXT_PULL_SYNC);
                    return $this->redirect($teamId, 'care_team_pulled');
                case 'retry_care_team_sync':
                    $retryIds = $post['retry_pid'] ?? [];
                    if (!is_array($retryIds)) {
                        $retryIds = [];
                    }
                    $context = $this->text($post['retry_context'] ?? CareTeamSyncFlash::CONTEXT_TEAM_SYNC);
                    if ($context !== CareTeamSyncFlash::CONTEXT_DEACTIVATION_CLEANUP) {
                        $context = CareTeamSyncFlash::CONTEXT_TEAM_SYNC;
                    }
                    $batch = $this->admin->retryCareTeamSync($teamId, $retryIds, $context);
                    if (!$batch->isFullSuccess()) {
                        $this->storeSyncFlash($teamId, $batch, $context);
                    }
                    if (
                        $context === CareTeamSyncFlash::CONTEXT_DEACTIVATION_CLEANUP
                        && $this->batchOnlyTeamActiveAgainFailures($batch)
                    ) {
                        return $this->redirect($teamId, 'care_team_active_retry_skipped');
                    }
                    return $this->redirect($teamId, 'care_team_sync_retried');
                default:
                    return $this->get($teamId, ['team_id' => $teamId], ['Unknown action.']);
            }
        } catch (TeamRuleException $exception) {
            return $this->get($teamId, [
                'team_id' => $teamId,
                'user_q' => $this->text($post['user_q'] ?? ''),
                'patient_q' => $this->text($post['patient_q'] ?? ''),
            ], [$exception->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $errors
     */
    private function get(int $teamId, array $query, array $errors): PageResult
    {
        $teams = $this->admin->listTeams();
        $team = $teamId > 0 ? $this->admin->find($teamId) : null;
        $userQuery = trim($this->text($query['user_q'] ?? ''));
        $patientQuery = trim($this->text($query['patient_q'] ?? ''));
        $syncFlash = $team !== null ? CareTeamSyncFlash::consume((int) $team['id']) : null;
        $body = $this->templates->render('teams.html.twig', [
            'csrf' => $this->csrf->token(),
            'teams' => $teams,
            'team' => $team,
            'members' => $team === null ? [] : $this->admin->members($team['id']),
            'patients' => $team === null ? [] : $this->admin->patients($team['id']),
            'userQuery' => $userQuery,
            'patientQuery' => $patientQuery,
            'userResults' => $team === null ? [] : $this->admin->searchUsers($userQuery),
            'patientResults' => $team === null ? [] : $this->admin->searchPatients($patientQuery),
            'careTeamSyncAvailable' => $this->admin->careTeamSyncAvailable(),
            'teamPatientCount' => $team === null ? 0 : $this->admin->activePatientCount($team['id']),
            'errors' => $errors,
            'notice' => $this->notice($this->text($query['notice'] ?? '')),
            'syncWarning' => $syncFlash === null ? null : $this->syncWarningView($syncFlash),
        ]);
        return new PageResult('Provider Teams', $body);
    }

    private function redirect(int $teamId, string $notice): PageResult
    {
        return new PageResult('Provider Teams', '', 'teams.php?' . http_build_query([
            'team_id' => $teamId,
            'notice' => $notice,
        ]));
    }

    private function notice(string $code): string
    {
        $text = match ($code) {
            'created' => 'Team created.',
            'updated' => 'Team updated.',
            'member_added' => 'Staff member added.',
            'member_removed' => 'Staff member removed.',
            'patient_assigned' => 'Patient assigned.',
            'patient_already' => 'This patient is already on the team.',
            'patient_unassigned' => 'Patient unassigned. Past visits stay on the dashboard.',
            'order_saved' => 'Patient order saved.',
            'care_team_pulled' => 'Provider team staff updated from the patient care team.',
            'care_team_sync_retried' => 'Care team sync retry finished.',
            'care_team_active_retry_skipped' => 'This team is active again, so module-added care team members were not removed.',
            default => '',
        };
        if ($text === '') {
            return '';
        }
        return function_exists('xl') ? xl($text) : $text;
    }

    private function storeSyncFlash(int $teamId, CareTeamSyncBatchResult $batch, string $context): void
    {
        if ($batch->attempted === 0) {
            return;
        }
        if ($batch->isFullSuccess()) {
            return;
        }
        CareTeamSyncFlash::store($teamId, $batch, $context);
    }

    private function batchOnlyTeamActiveAgainFailures(CareTeamSyncBatchResult $batch): bool
    {
        if ($batch->attempted === 0 || $batch->failed === []) {
            return false;
        }
        $expected = CareTeamSyncFailureMessage::teamActiveAgain();
        foreach ($batch->failed as $row) {
            if (($row['reason'] ?? '') !== $expected) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array{team_id: int, context: string, result: array<string, mixed>} $flash
     * @return array{
     *   headline: string,
     *   failed: list<array{pid: int, name: string, pubpid: string, reason: string, demographics_url: string}>,
     *   retry_pids: list<int>,
     *   retry_context: string,
     *   retry_button_label: string,
     *   team_id: int
     * }
     */
    private function syncWarningView(array $flash): array
    {
        $context = (string) ($flash['context'] ?? CareTeamSyncFlash::CONTEXT_TEAM_SYNC);
        $result = is_array($flash['result'] ?? null) ? $flash['result'] : [];
        $attempted = (int) ($result['attempted'] ?? 0);
        $succeeded = (int) ($result['succeeded'] ?? 0);
        $failedRows = is_array($result['failed'] ?? null) ? $result['failed'] : [];
        $failed = [];
        $retryPids = [];
        foreach ($failedRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = (int) ($row['pid'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $retryPids[] = $pid;
            $failed[] = [
                'pid' => $pid,
                'name' => (string) ($row['name'] ?? ('Patient ' . $pid)),
                'pubpid' => (string) ($row['pubpid'] ?? ''),
                'reason' => (string) ($row['reason'] ?? ''),
                'demographics_url' => WebPaths::patientDemographics($pid),
            ];
        }

        return [
            'headline' => $this->formatUpdatedPatientsHeadline($succeeded, $attempted),
            'failed' => $failed,
            'retry_pids' => $retryPids,
            'retry_context' => $context,
            'retry_button_label' => $this->retryButtonLabel($context, count($retryPids)),
            'team_id' => (int) ($flash['team_id'] ?? 0),
        ];
    }

    private function retryButtonLabel(string $context, int $patientCount): string
    {
        if ($context === CareTeamSyncFlash::CONTEXT_DEACTIVATION_CLEANUP) {
            $template = 'Retry removing staff from %1 patients';
            if (function_exists('xl')) {
                return sprintf(xl($template), (string) $patientCount);
            }
            return sprintf($template, $patientCount);
        }
        $text = 'Retry failed';
        return function_exists('xl') ? xl($text) : $text;
    }

    private function formatUpdatedPatientsHeadline(int $succeeded, int $attempted): string
    {
        $template = 'Updated %1 of %2 patients';
        if (function_exists('xl')) {
            return sprintf(xl($template), (string) $succeeded, (string) $attempted);
        }
        return sprintf($template, $succeeded, $attempted);
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function int(mixed $value): int
    {
        return is_scalar($value) ? (int) $value : 0;
    }
}
