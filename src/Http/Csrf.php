<?php

/**
 * CSRF token helpers for module forms.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use ReflectionMethod;

final class Csrf
{
    private static ?bool $collectRequiresSession = null;

    private static function collectRequiresSession(): bool
    {
        if (self::$collectRequiresSession === null) {
            self::$collectRequiresSession = (new ReflectionMethod(CsrfUtils::class, 'collectCsrfToken'))
                ->getNumberOfRequiredParameters() > 0;
        }

        return self::$collectRequiresSession;
    }

    public function token(): string
    {
        if (self::collectRequiresSession()) {
            return CsrfUtils::collectCsrfToken(
                SessionWrapperFactory::getInstance()->getActiveSession()
            );
        }

        return CsrfUtils::collectCsrfToken();
    }

    public function check(?string $token): void
    {
        $verified = self::collectRequiresSession()
            ? CsrfUtils::verifyCsrfToken(
                $token ?? '',
                SessionWrapperFactory::getInstance()->getActiveSession()
            )
            : CsrfUtils::verifyCsrfToken($token ?? '');

        if (!$verified) {
            CsrfUtils::csrfNotVerified();
        }
    }
}
