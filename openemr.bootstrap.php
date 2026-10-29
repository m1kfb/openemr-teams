<?php

/**
 * Boots the Provider Teams module inside OpenEMR.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

namespace OpenEMR\Modules\ProviderTeams;

$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\ProviderTeams\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);

$bootstrap = new Bootstrap($eventDispatcher);
$bootstrap->subscribeToEvents();
