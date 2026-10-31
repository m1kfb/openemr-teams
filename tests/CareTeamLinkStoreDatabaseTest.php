<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use PHPUnit\Framework\TestCase;

/**
 * This module ships PHPUnit without an OpenEMR/MySQL harness; SQL persistence is covered by
 * {@see InMemoryCareTeamLinkStoreTest} simulating DELETE … AND excluded = 0 semantics.
 */
final class CareTeamLinkStoreDatabaseTest extends TestCase
{
    public function testExcludedRowsPersistInDatabaseAfterCleanup(): void
    {
        self::markTestSkipped(
            'No database harness in this repository (composer test runs without OpenEMR/MySQL).'
        );
    }
}
