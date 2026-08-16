<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

final class Migration0016MobileCrudCapabilities implements Migration
{
    public function up(object $wpdb): void
    {
        unset($wpdb);

        foreach (['administrator', RoleRegistrar::SYSTEM_ADMIN] as $roleSlug) {
            $role = get_role($roleSlug);
            if (! $role) {
                continue;
            }
            foreach ([
                Capabilities::CREATE_CUSTOMERS,
                Capabilities::EDIT_CUSTOMERS,
                Capabilities::CREATE_PAYMENTS,
                Capabilities::EDIT_PAYMENTS,
            ] as $capability) {
                $role->add_cap($capability);
            }
        }

        $manager = get_role(RoleRegistrar::MANAGER);
        if ($manager) {
            $manager->add_cap(Capabilities::CREATE_CUSTOMERS);
            $manager->add_cap(Capabilities::EDIT_CUSTOMERS);
        }

        foreach ([RoleRegistrar::MANAGER, RoleRegistrar::ACCOUNTANT] as $roleSlug) {
            $role = get_role($roleSlug);
            if (! $role) {
                continue;
            }
            if (! empty($role->capabilities[Capabilities::MANAGE_PAYMENTS])) {
                $role->add_cap(Capabilities::CREATE_PAYMENTS);
                $role->add_cap(Capabilities::EDIT_PAYMENTS);
            }
        }
    }
}
