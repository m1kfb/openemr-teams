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
    public function testApplyUserChangesKeepsContactMembersWhenRemovingUsers(): void
    {
        $bridge = new class extends CareTeamBridge {
            /** @var list<array<string, mixed>> */
            public array $savedPayload = [];

            protected function detectModernSchema(): bool
            {
                return true;
            }

            /**
             * @return array<string, mixed>
             */
            protected function fetchCareTeamData(int $pid): array
            {
                return [
                    'team_id' => 1,
                    'team_name' => 'Care Team',
                    'team_status' => 'active',
                    'members' => [
                        [
                            'member_type' => 'user',
                            'user_id' => 10,
                            'contact_id' => null,
                            'role' => 'healthcare_professional',
                            'status' => 'active',
                            'note' => '',
                        ],
                        [
                            'member_type' => 'contact',
                            'user_id' => null,
                            'contact_id' => 42,
                            'role' => 'family',
                            'status' => 'active',
                            'note' => 'related',
                        ],
                    ],
                ];
            }

            /**
             * @param list<array<string, mixed>> $payload
             */
            protected function saveCareTeamPayload(
                int $pid,
                ?int $teamId,
                string $teamName,
                array $payload,
                string $teamStatus,
            ): void {
                $this->savedPayload = $payload;
            }
        };

        $bridge->applyUserChanges(5, [], [10]);

        self::assertCount(1, $bridge->savedPayload);
        self::assertSame(42, $bridge->savedPayload[0]['contact_id']);
        self::assertSame('family', $bridge->savedPayload[0]['role']);
        self::assertSame('related', $bridge->savedPayload[0]['note']);
    }
}
