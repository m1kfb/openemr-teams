<?php

/**
 * Registers the provider_teams ACL section and grants it to Administrators.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ProviderTeams;

use OpenEMR\Gacl\GaclApi;

final class AclRegistrar
{
    public const SECTION = 'provider_teams';

    public function ensure(): void
    {
        if (!class_exists(GaclApi::class)) {
            return;
        }
        try {
            $gacl = new GaclApi();
            $this->install($gacl);
        } catch (\Throwable $exception) {
            error_log('Provider Teams ACL setup failed: ' . $exception->getMessage());
        }
    }

    private function install(GaclApi $gacl): void
    {
        if (!$gacl->get_object_section_section_id('Provider Teams', self::SECTION, 'ACO')) {
            $gacl->add_object_section('Provider Teams', self::SECTION, 10, 0, 'ACO');
        }
        $objects = [
            'view' => 'View',
            'record' => 'Record',
            'admin' => 'Administer',
        ];
        foreach ($objects as $value => $title) {
            if (!$gacl->get_object_id(self::SECTION, $value, 'ACO')) {
                $gacl->add_object(self::SECTION, $title, $value, 10, 0, 'ACO');
            }
        }
        $adminAcls = $gacl->search_acl(false, false, false, false, 'Administrators', false, false, false, 'write');
        if (!is_array($adminAcls) || count($adminAcls) !== 1) {
            return;
        }
        $aclId = $adminAcls[0];
        foreach (array_keys($objects) as $value) {
            $existing = $gacl->search_acl(self::SECTION, $value, false, false, 'Administrators', false, false, false, 'write');
            if (is_array($existing) && count($existing) > 0) {
                continue;
            }
            $gacl->append_acl($aclId, null, null, null, null, [self::SECTION => [$value]]);
        }
    }
}
