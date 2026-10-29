<?php

/**
 * view, record, and admin, where a stronger permission includes the weaker ones.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class Permission
{
    public const VIEW = 'view';
    public const RECORD = 'record';
    public const ADMIN = 'admin';

    /**
     * @param list<string> $held
     */
    public static function allows(array $held, string $needed): bool
    {
        if (in_array(self::ADMIN, $held, true)) {
            return true;
        }
        if ($needed === self::VIEW && in_array(self::RECORD, $held, true)) {
            return true;
        }
        return in_array($needed, $held, true);
    }
}
