<?php

/**
 * Database transaction helper shared by persistence classes.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Persistence;

use OpenEMR\Common\Database\QueryUtils;

final class TransactionRunner
{
    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    public static function run(callable $action): mixed
    {
        if (method_exists(QueryUtils::class, 'inTransaction')) {
            return QueryUtils::inTransaction($action);
        }
        sqlBeginTrans();
        try {
            $result = $action();
            sqlCommitTrans();
            return $result;
        } catch (\Throwable $exception) {
            sqlRollbackTrans();
            throw $exception;
        }
    }
}
