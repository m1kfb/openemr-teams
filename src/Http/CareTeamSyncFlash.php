<?php

/**
 * One-shot admin notice after a batched care-team sync.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Modules\ProviderTeams\Domain\CareTeamSyncBatchResult;

final class CareTeamSyncFlash
{
    private const SESSION_KEY = 'provider_teams_care_sync_flash';

    public static function store(int $teamId, CareTeamSyncBatchResult $result, bool $fromPull): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'team_id' => $teamId,
            'from_pull' => $fromPull,
            'result' => $result->toArray(),
        ];
    }

    /**
     * @return array{team_id: int, from_pull: bool, result: array<string, mixed>}|null
     */
    public static function consume(int $teamId): ?array
    {
        $payload = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($payload) || (int) ($payload['team_id'] ?? 0) !== $teamId) {
            return null;
        }
        unset($_SESSION[self::SESSION_KEY]);
        return $payload;
    }
}
