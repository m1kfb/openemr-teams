<?php

/**
 * Outcome of syncing provider-team staff onto multiple patient care teams.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class CareTeamSyncBatchResult
{
    /**
     * @param list<array{pid: int, name: string, pubpid: string, reason: string}> $failed
     */
    public function __construct(
        public readonly int $attempted,
        public readonly int $succeeded,
        public readonly array $failed,
    ) {
    }

    public function isFullSuccess(): bool
    {
        return $this->failed === [];
    }

    /**
     * @return list<int>
     */
    public function failedPatientIds(): array
    {
        return array_map(static fn (array $row): int => (int) $row['pid'], $this->failed);
    }

    /**
     * @return array{attempted: int, succeeded: int, failed: list<array{pid: int, name: string, pubpid: string, reason: string}>}
     */
    public function toArray(): array
    {
        return [
            'attempted' => $this->attempted,
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
        ];
    }
}
