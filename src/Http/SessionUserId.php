<?php

/**
 * Resolves the authenticated OpenEMR user id from session state.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Common\Session\SessionWrapperFactory;

final class SessionUserId
{
    /**
     * @param object|null $sessionFactory Optional SessionWrapperFactory-like object for tests.
     */
    public static function current(?object $sessionFactory = null): int
    {
        $factory = $sessionFactory;
        if ($factory === null && class_exists(SessionWrapperFactory::class)) {
            $factory = SessionWrapperFactory::getInstance();
        }
        if ($factory !== null && method_exists($factory, 'getActiveSession')) {
            $session = $factory->getActiveSession();
            if (is_object($session) && method_exists($session, 'get')) {
                $fromSession = $session->get('authUserID');
                if ($fromSession !== null && $fromSession !== '') {
                    return (int) $fromSession;
                }
            }
        }
        return (int) ($_SESSION['authUserID'] ?? 0);
    }
}
