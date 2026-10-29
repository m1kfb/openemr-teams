<?php

/**
 * Team setup: name the team, add staff, and assign patients.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Modules\ProviderTeams\Domain\Permission;
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
                    $this->admin->update(
                        $teamId,
                        $this->text($post['name'] ?? ''),
                        $this->text($post['notes'] ?? ''),
                        isset($post['active'])
                    );
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
            'errors' => $errors,
            'notice' => $this->notice($this->text($query['notice'] ?? '')),
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
            default => '',
        };
        if ($text === '') {
            return '';
        }
        return function_exists('xl') ? xl($text) : $text;
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
