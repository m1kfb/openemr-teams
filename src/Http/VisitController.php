<?php

/**
 * Daily visit entry and the date-range audit matrix.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Modules\ProviderTeams\Domain\DateSpan;
use OpenEMR\Modules\ProviderTeams\Domain\Permission;
use OpenEMR\Modules\ProviderTeams\Domain\VisitExportFormat;
use OpenEMR\Modules\ProviderTeams\Domain\VisitPeriod;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSaveJson;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSaveMode;
use OpenEMR\Modules\ProviderTeams\Domain\VisitSaveScope;
use OpenEMR\Modules\ProviderTeams\Service\TeamAdmin;
use OpenEMR\Modules\ProviderTeams\Service\VisitBoard;

final class VisitController
{
    public function __construct(
        private readonly TeamAdmin $admin,
        private readonly VisitBoard $board,
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
        $this->access->demand(Permission::VIEW);
        if (strtoupper($method) === 'POST') {
            $this->access->demand(Permission::RECORD);
            $token = $post['csrf_token'] ?? null;
            $this->csrf->check(is_string($token) ? $token : null);
            return $this->save($post);
        }
        return $this->show($query, [], null, null);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function export(array $query): void
    {
        $this->access->demand(Permission::VIEW);
        $token = $query['csrf_token'] ?? null;
        $this->csrf->check(is_string($token) ? $token : null);
        $teamId = $this->int($query['team_id'] ?? 0);
        $team = $this->admin->find($teamId);
        if ($team === null) {
            http_response_code(404);
            echo function_exists('xlt') ? xlt('That team was not found.') : 'That team was not found.';
            exit;
        }
        $from = $this->text($query['date_from'] ?? $query['date'] ?? '');
        $to = $this->text($query['date_to'] ?? $query['date'] ?? '');
        try {
            $csv = $this->board->exportCsv($teamId, $from, $to);
        } catch (\InvalidArgumentException $exception) {
            http_response_code(400);
            echo function_exists('text') ? text($exception->getMessage()) : htmlspecialchars($exception->getMessage(), ENT_QUOTES);
            exit;
        }
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $team['name']) ?: 'team';
        $filename = $slug . '-visits-' . $from . '-to-' . $to . '.csv';
        header('Content-Type: ' . VisitExportFormat::CONTENT_TYPE);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo VisitExportFormat::BOM . $csv;
        exit;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function save(array $post): PageResult
    {
        $teamId = $this->int($post['team_id'] ?? 0);
        $date = is_string($post['date'] ?? null) ? $post['date'] : '';
        $staff = $post['staff'] ?? [];
        $slots = $post['slot'] ?? [];
        $dirtyPatients = is_array($post['dirty'] ?? null) ? $post['dirty'] : [];
        $staffDirty = VisitSaveScope::shouldUpdateStaff($post['staff_dirty'] ?? null);
        $staffIds = [];
        if (is_array($staff)) {
            foreach ($staff as $staffId) {
                if (is_scalar($staffId)) {
                    $staffIds[] = (int) $staffId;
                }
            }
        }
        $rawSlots = is_array($slots) ? $slots : [];
        $rowSave = VisitSaveMode::isRowSave($post);
        $errors = $this->board->saveDay(
            $teamId,
            $date,
            $staffIds,
            $rawSlots,
            $this->access->userId(),
            $dirtyPatients,
            $staffDirty
        );
        if ($errors === []) {
            if ($rowSave) {
                return new PageResult('', '', null, VisitSaveJson::success());
            }
            return new PageResult('Visit dashboard', '', 'visits.php?' . http_build_query([
                'team_id' => $teamId,
                'date' => $date,
                'notice' => 'saved',
            ]));
        }
        if ($rowSave) {
            return new PageResult('', '', null, VisitSaveJson::failure($errors));
        }
        return $this->show(
            ['team_id' => $teamId, 'date' => $date, 'mode' => 'day'],
            $errors,
            $staffIds,
            $rawSlots
        );
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $errors
     * @param list<int>|null $staffOverride
     * @param array<int|string, mixed>|null $rawSlots
     */
    private function show(array $query, array $errors, ?array $staffOverride, ?array $rawSlots): PageResult
    {
        $teams = $this->admin->listTeams();
        $teamId = $this->int($query['team_id'] ?? 0);
        $team = $teamId > 0 ? $this->admin->find($teamId) : null;
        $mode = $this->text($query['mode'] ?? 'day');
        if ($mode !== 'range') {
            $mode = 'day';
        }
        $today = date('Y-m-d');
        $date = $this->text($query['date'] ?? $today);
        if (DateSpan::day($date) === null) {
            $date = $today;
        }
        $dateFrom = $this->text($query['date_from'] ?? $date);
        $dateTo = $this->text($query['date_to'] ?? $date);
        $rows = [];
        $members = [];
        $selectedStaff = [];
        $dayEmptyReason = null;
        if ($team !== null && $mode === 'day') {
            try {
                $day = $this->board->day($team['id'], $date, $staffOverride, $rawSlots);
                $rows = $day['rows'];
                $members = $day['members'];
                $selectedStaff = $day['selectedStaff'];
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
            if ($rows === [] && $errors === []) {
                $dayEmptyReason = $this->board->dayEmptyReason($team['id'], $date);
            }
        }
        if ($team !== null && $mode === 'range') {
            try {
                $rows = $this->board->range($team['id'], $dateFrom, $dateTo);
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        $token = $this->csrf->token();
        $exportQuery = [
            'team_id' => $team['id'] ?? 0,
            'csrf_token' => $token,
        ];
        if ($mode === 'range') {
            $exportQuery['date_from'] = $dateFrom;
            $exportQuery['date_to'] = $dateTo;
        } else {
            $exportQuery['date'] = $date;
        }
        $body = $this->templates->render('visits.html.twig', [
            'csrf' => $token,
            'teams' => $teams,
            'team' => $team,
            'mode' => $mode,
            'date' => $date,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'periods' => $this->periods(),
            'members' => $members,
            'selectedStaff' => $selectedStaff,
            'editable' => $mode === 'day' && $team !== null && (int) $team['active'] === 1 && $this->access->allows(Permission::RECORD),
            'errors' => $errors,
            'notice' => $this->notice($this->text($query['notice'] ?? '')),
            'exportUrl' => 'export.php?' . http_build_query($exportQuery),
            'dayEmptyReason' => $dayEmptyReason,
            'teamsPageUrl' => 'teams.php' . ($team !== null ? '?team_id=' . (int) $team['id'] : ''),
        ]);
        return new PageResult('Visit dashboard', $body);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function periods(): array
    {
        $periods = [];
        foreach (VisitPeriod::all() as $period) {
            $label = VisitPeriod::label($period);
            $periods[] = [
                'key' => $period,
                'label' => function_exists('xl') ? xl($label) : $label,
            ];
        }
        return $periods;
    }

    private function notice(string $code): string
    {
        if ($code !== 'saved') {
            return '';
        }
        $text = 'Visit times saved.';
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
