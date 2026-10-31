<?php

/**
 * Detects per-row AJAX save requests from the visit dashboard.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Domain;

final class VisitSaveMode
{
    /**
     * @param array<string, mixed> $post
     */
    public static function isRowSave(array $post): bool
    {
        return ($post['save_mode'] ?? '') === 'row';
    }
}
