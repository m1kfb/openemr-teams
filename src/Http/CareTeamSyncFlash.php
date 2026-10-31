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

    public const CONTEXT_PULL_SYNC = 'pull_sync';
    public const CONTEXT_TEAM_SYNC = 'team_sync';
    public const CONTEXT_DEACTIVATION_CLEANUP = 'deactivation_cleanup';

    public static function store(int $teamId, CareTeamSyncBatchResult $result, string $context): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'team_id' => $teamId,
            'context' => $context,
            'result' => $result->toArray(),
        ];
    }

    /**
     * @return array{team_id: int, context: string, result: array<string, mixed>}|null
     */
    public static function consume(int $teamId): ?array
    {
        $payload = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($payload) || (int) ($payload['team_id'] ?? 0) !== $teamId) {
            return null;
        }
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_string($payload['context'] ?? null)) {
            $payload['context'] = self::CONTEXT_TEAM_SYNC;
        }
        return $payload;
    }
}
