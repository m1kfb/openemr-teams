<?php

/**
 * CSRF token helpers for module forms.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Common\Csrf\CsrfUtils;

final class Csrf
{
    public function token(): string
    {
        return CsrfUtils::collectCsrfToken();
    }

    public function check(?string $token): void
    {
        if (!CsrfUtils::verifyCsrfToken($token ?? '')) {
            CsrfUtils::csrfNotVerified();
        }
    }
}
