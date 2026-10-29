<?php

/**
 * Renders this module's Twig templates through OpenEMR's Twig container.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Common\Twig\TwigContainer;

final class Templates
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(string $name, array $context): string
    {
        $kernel = $GLOBALS['kernel'] ?? null;
        $twig = (new TwigContainer(dirname(__DIR__, 2) . '/templates', $kernel))->getTwig();
        return $twig->render($name, $context);
    }
}
