<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Persistence\CareTeamBridge;
use PHPUnit\Framework\TestCase;

final class CareTeamBridgeContactsTest extends TestCase
{
    public function testMemberPayloadRetainsContactFields(): void
    {
        $bridge = new CareTeamBridge();
        $method = new \ReflectionMethod(CareTeamBridge::class, 'memberPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($bridge, [
            'user_id' => null,
            'contact_id' => 42,
            'role' => 'family',
            'facility_id' => null,
            'provider_since' => '2020-01-01',
            'status' => 'active',
            'note' => 'related',
        ]);
        self::assertSame(42, $payload['contact_id']);
        self::assertNull($payload['user_id']);
        self::assertSame('family', $payload['role']);
        self::assertSame('related', $payload['note']);
    }
}
