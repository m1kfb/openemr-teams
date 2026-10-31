<?php

/**
 * In-memory simulation of provider_team_care_team_links delete semantics (excluded rows kept).
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests\Support;

class InMemoryCareTeamLinkStore extends \OpenEMR\Modules\ProviderTeams\Persistence\CareTeamLinkStore
{
    /** @var list<array{provider_team_id: int, pid: int, user_id: int, excluded: int}> */
    public array $rows = [];

    public function withPatientTeamLock(int $providerTeamId, int $pid, callable $action): mixed
    {
        return $action();
    }

    public function activeTrackedUserIds(int $providerTeamId, int $pid): array
    {
        $ids = [];
        foreach ($this->rows as $row) {
            if ($row['provider_team_id'] === $providerTeamId
                && $row['pid'] === $pid
                && $row['excluded'] === 0) {
                $ids[] = $row['user_id'];
            }
        }
        return $ids;
    }

    public function excludedUserIds(int $providerTeamId, int $pid): array
    {
        $ids = [];
        foreach ($this->rows as $row) {
            if ($row['provider_team_id'] === $providerTeamId
                && $row['pid'] === $pid
                && $row['excluded'] === 1) {
                $ids[] = $row['user_id'];
            }
        }
        return $ids;
    }

    public function forgetActiveLinksForPatientTeam(int $providerTeamId, int $pid): void
    {
        $this->rows = array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => !(
                $row['provider_team_id'] === $providerTeamId
                && $row['pid'] === $pid
                && $row['excluded'] === 0
            )
        ));
    }

    public function remember(int $providerTeamId, int $pid, int $userId, int $careTeamMemberId): void
    {
        foreach ($this->rows as &$row) {
            if ($row['provider_team_id'] === $providerTeamId
                && $row['pid'] === $pid
                && $row['user_id'] === $userId) {
                $row['excluded'] = 0;
                return;
            }
        }
        unset($row);
        $this->rows[] = [
            'provider_team_id' => $providerTeamId,
            'pid' => $pid,
            'user_id' => $userId,
            'excluded' => 0,
        ];
    }
}
