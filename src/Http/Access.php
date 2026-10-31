<?php

/**
 * ACL checks for the provider_teams section.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Modules\ProviderTeams\Domain\Permission;

final class Access
{
    public function allows(string $permission): bool
    {
        if (AclMain::aclCheckCore('admin', 'super')) {
            return true;
        }
        $held = [];
        foreach ([Permission::ADMIN, Permission::RECORD, Permission::VIEW] as $candidate) {
            if (AclMain::aclCheckCore('provider_teams', $candidate)) {
                $held[] = $candidate;
            }
        }
        return Permission::allows($held, $permission);
    }

    public function demand(string $permission): void
    {
        if ($this->allows($permission)) {
            return;
        }
        http_response_code(403);
        echo function_exists('xlt') ? xlt('Not authorized') : 'Not authorized';
        exit;
    }

    public function userId(): int
    {
        if (class_exists(SessionWrapperFactory::class)) {
            $fromSession = SessionWrapperFactory::getInstance()->getActiveSession()->get('authUserID');
            if ($fromSession !== null && $fromSession !== '') {
                return (int) $fromSession;
            }
        }
        return (int) ($_SESSION['authUserID'] ?? 0);
    }
}
