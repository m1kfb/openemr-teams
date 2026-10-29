<?php

/**
 * CSV download of the visit matrix.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

require_once dirname(__DIR__, 4) . '/globals.php';

use OpenEMR\Modules\ProviderTeams\Module;

Module::visitController()->export($_GET);
