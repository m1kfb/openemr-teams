<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use OpenEMR\Modules\ProviderTeams\Http\TeamController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class TeamControllerNoticeTest extends TestCase
{
    public function testCareTeamPulledNoticeText(): void
    {
        $controller = (new ReflectionClass(TeamController::class))->newInstanceWithoutConstructor();
        $notice = new ReflectionMethod(TeamController::class, 'notice');
        $notice->setAccessible(true);

        self::assertSame(
            'Provider team staff updated from the patient care team.',
            $notice->invoke($controller, 'care_team_pulled')
        );
    }
}
