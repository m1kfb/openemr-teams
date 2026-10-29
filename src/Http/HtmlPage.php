<?php

/**
 * Page shell around a module template, including OpenEMR's standard header.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams\Http;

use OpenEMR\Core\Header;

final class HtmlPage
{
    public static function emit(string $title, string $body, string $stylesheet): void
    {
        $safeTitle = function_exists('text') ? text($title) : htmlspecialchars($title, ENT_QUOTES);
        $href = ($GLOBALS['webroot'] ?? '') . $stylesheet;
        $safeHref = function_exists('attr') ? attr($href) : htmlspecialchars($href, ENT_QUOTES);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $safeTitle . '</title>';
        Header::setupHeader();
        echo '<link rel="stylesheet" href="' . $safeHref . '"></head>';
        echo '<body class="body_top"><div class="container-fluid mt-3 provider-teams">';
        echo $body;
        $script = preg_replace('/\.css$/', '.js', $href);
        $safeScript = function_exists('attr') ? attr((string) $script) : htmlspecialchars((string) $script, ENT_QUOTES);
        echo '</div><script src="' . $safeScript . '"></script></body></html>';
    }
}
