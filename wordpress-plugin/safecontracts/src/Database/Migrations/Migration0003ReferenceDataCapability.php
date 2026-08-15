<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

final class Migration0003ReferenceDataCapability implements Migration
{
    public function up(object $wpdb): void
    {
        unset($wpdb);

        foreach (['administrator', RoleRegistrar::SYSTEM_ADMIN] as $roleSlug) {
            $role = get_role($roleSlug);
            if ($role) {
                $role->add_cap(Capabilities::MANAGE_REFERENCE_DATA);
            }
        }
    }
}
