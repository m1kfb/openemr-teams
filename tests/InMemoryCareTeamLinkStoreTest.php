<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Tests\Support\InMemoryCareTeamLinkStore;
use PHPUnit\Framework\TestCase;

final class InMemoryCareTeamLinkStoreTest extends TestCase
{
    public function testForgetActiveLinksRemovesOnlyNonExcludedRows(): void
    {
        $store = new InMemoryCareTeamLinkStore();
        $store->rows = [
            ['provider_team_id' => 1, 'pid' => 5, 'user_id' => 10, 'excluded' => 0],
            ['provider_team_id' => 1, 'pid' => 5, 'user_id' => 42, 'excluded' => 1],
        ];

        $store->forgetActiveLinksForPatientTeam(1, 5);

        self::assertSame([42], $store->excludedUserIds(1, 5));
        self::assertSame([], $store->activeTrackedUserIds(1, 5));
    }
}
