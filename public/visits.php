<?php

/**
 * Visit dashboard for a provider team.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

require_once dirname(__DIR__, 4) . '/globals.php';

use OpenEMR\Modules\ProviderTeams\Http\HtmlPage;
use OpenEMR\Modules\ProviderTeams\Module;

$result = Module::visitController()->handle(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_GET,
    $_POST
);
if ($result->redirect !== null) {
    header('Location: ' . $result->redirect);
    exit;
}
if ($result->json !== null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result->json, JSON_THROW_ON_ERROR);
    exit;
}
HtmlPage::emit($result->title, $result->body, Module::stylesheetPath());
