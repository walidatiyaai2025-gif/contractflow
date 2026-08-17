<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

final class Migration0018SupplierFinanceReconciliation implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';

        // Additive evolution of the 1.16 Supplier table. The legacy `name` and
        // `is_active` columns remain compatibility fields for existing reads.
        dbDelta("CREATE TABLE {$suppliers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            internal_code varchar(100) NULL,
            name varchar(191) NOT NULL,
            legal_name varchar(191) NULL,
            trading_name varchar(191) NULL,
            contact_name varchar(191) NULL,
            email varchar(191) NULL,
            phone varchar(64) NULL,
            address text NULL,
            country_code char(2) NULL,
            registration_number varchar(100) NULL,
            tax_number varchar(100) NULL,
            default_currency char(3) NULL,
            payment_terms varchar(191) NULL,
            status varchar(32) NOT NULL DEFAULT 'active',
            notes text NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            archived_by bigint(20) unsigned NULL,
            archived_at datetime NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY internal_code (internal_code),
            KEY active_name (is_active, is_archived, name),
            KEY supplier_status (status, is_archived, legal_name),
            KEY supplier_registration (registration_number),
            KEY supplier_tax (tax_number)
        ) {$charset};");

        // Existing rows are known Suppliers already. Preserve their identity and
        // derive only the richer profile aliases/lifecycle from objective columns.
        $wpdb->query("UPDATE {$suppliers}
            SET legal_name = name
            WHERE legal_name IS NULL OR legal_name = ''");
        $wpdb->query("UPDATE {$suppliers}
            SET status = CASE
                WHEN is_archived = 1 THEN 'archived'
                WHEN is_active = 0 THEN 'inactive'
                ELSE 'active'
            END
            WHERE status IS NULL OR status = '' OR status = 'active'");

        $this->grantCompatibilityCapabilities();
    }

    private function grantCompatibilityCapabilities(): void
    {
        $slugs = ['administrator', RoleRegistrar::SYSTEM_ADMIN, RoleRegistrar::MANAGER, RoleRegistrar::ACCOUNTANT, RoleRegistrar::VIEWER];
        if (function_exists('wp_roles')) {
            $roles = wp_roles();
            if (is_object($roles) && isset($roles->role_names) && is_array($roles->role_names)) {
                $slugs = array_values(array_unique([...$slugs, ...array_keys($roles->role_names)]));
            }
        }

        foreach ($slugs as $slug) {
            $role = get_role((string) $slug);
            if (! $role) {
                continue;
            }

            if ($this->roleHasCapability($role, Capabilities::VIEW_FINANCE)
                || $this->roleHasCapability($role, Capabilities::MANAGE_FINANCE)) {
                $role->add_cap(Capabilities::VIEW_PAYABLES);
                $role->add_cap(Capabilities::VIEW_RECEIVABLES);
            }
            if ($this->roleHasCapability($role, Capabilities::EDIT_SUPPLIERS)
                || $this->roleHasCapability($role, Capabilities::MANAGE_SUPPLIERS)) {
                $role->add_cap(Capabilities::ARCHIVE_SUPPLIERS);
            }
        }
    }

    private function roleHasCapability(object $role, string $capability): bool
    {
        if (method_exists($role, 'has_cap')) {
            return (bool) $role->has_cap($capability);
        }
        return isset($role->capabilities)
            && is_array($role->capabilities)
            && ! empty($role->capabilities[$capability]);
    }
}
