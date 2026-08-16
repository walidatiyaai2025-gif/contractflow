<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0021EnterprisePartyRoles implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $roles = $wpdb->prefix . 'safecontracts_party_roles';

        dbDelta("CREATE TABLE {$roles} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            party_id bigint(20) unsigned NOT NULL,
            role_code varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            assigned_by bigint(20) unsigned NULL,
            revoked_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            revoked_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_party_role (tenant_id, party_id, role_code),
            KEY tenant_role_status_party (tenant_id, role_code, status, party_id),
            KEY tenant_party_status (tenant_id, party_id, status, id)
        ) {$charset};");
    }
}
