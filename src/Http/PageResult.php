<?php

/**
 * Either a rendered page or a redirect back to one of this module's scripts.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

final class PageResult
{
    /**
     * @param array<string, mixed>|null $json When set, visits.php emits JSON instead of HTML.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body = '',
        public readonly ?string $redirect = null,
        public readonly ?array $json = null,
    ) {
    }
}
