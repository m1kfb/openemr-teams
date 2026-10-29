<?php

/**
 * Adds the Provider Teams menu once the module is enabled.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams;

use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class Bootstrap
{
    private string $moduleDirectoryName;

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
        $this->moduleDirectoryName = basename(dirname(__DIR__));
    }

    public function subscribeToEvents(): void
    {
        (new AclRegistrar())->ensure();
        $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, [$this, 'addMenu']);
    }

    public function addMenu(MenuEvent $event): MenuEvent
    {
        $visitAcl = [
            ['admin', 'super'],
            ['provider_teams', 'view'],
            ['provider_teams', 'record'],
            ['provider_teams', 'admin'],
        ];
        $adminAcl = [
            ['admin', 'super'],
            ['provider_teams', 'admin'],
        ];
        $dashboard = $this->item('providerteams_visits', xl('Visit dashboard'), $this->url('visits.php'), $visitAcl);
        $teams = $this->item('providerteams_admin', xl('Teams'), $this->url('teams.php'), $adminAcl);
        $parent = $this->item('providerteams', xl('Provider Teams'), $this->url('visits.php'), $visitAcl);
        $parent->children = [$dashboard, $teams];

        $menu = $event->getMenu();
        $menu[] = $parent;
        $event->setMenu($menu);
        return $event;
    }

    /**
     * @param list<array{0: string, 1: string}> $acl
     */
    private function item(string $id, string $label, string $url, array $acl): \stdClass
    {
        $item = new \stdClass();
        $item->requirement = 0;
        $item->target = 'mod';
        $item->menu_id = $id;
        $item->label = $label;
        $item->url = $url;
        $item->children = [];
        $item->acl_req = $acl;
        $item->global_req = [];
        return $item;
    }

    private function url(string $script): string
    {
        return '/interface/modules/custom_modules/' . $this->moduleDirectoryName . '/public/' . $script;
    }
}
