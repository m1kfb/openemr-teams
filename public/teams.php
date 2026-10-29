<?php

/**
 * Provider Teams administration page.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

require_once dirname(__DIR__, 4) . '/globals.php';

use OpenEMR\Modules\ProviderTeams\Http\HtmlPage;
use OpenEMR\Modules\ProviderTeams\Module;

$result = Module::teamController()->handle(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_GET,
    $_POST
);
if ($result->redirect !== null) {
    header('Location: ' . $result->redirect);
    exit;
}
HtmlPage::emit($result->title, $result->body, Module::stylesheetPath());
