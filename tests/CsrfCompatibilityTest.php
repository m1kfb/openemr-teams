<?php

/**
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class CsrfCompatibilityTest extends TestCase
{
    public function testLegacyCollectCsrfTokenHasNoRequiredParameters(): void
    {
        $legacy = new class () {
            public static function collectCsrfToken(): string
            {
                return '';
            }
        };

        self::assertSame(
            0,
            (new ReflectionMethod($legacy::class, 'collectCsrfToken'))->getNumberOfRequiredParameters()
        );
    }

    public function testModernCollectCsrfTokenRequiresSession(): void
    {
        $modern = new class () {
            public static function collectCsrfToken(object $session, string $subject = 'default'): string
            {
                return '';
            }
        };

        self::assertGreaterThan(
            0,
            (new ReflectionMethod($modern::class, 'collectCsrfToken'))->getNumberOfRequiredParameters()
        );
    }
}
