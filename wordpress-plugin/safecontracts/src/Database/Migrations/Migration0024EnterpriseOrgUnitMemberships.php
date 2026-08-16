<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0024EnterpriseOrgUnitMemberships implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $wpdb->prefix . 'safecontracts_org_unit_memberships';

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            org_unit_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            assignment_role varchar(32) NOT NULL DEFAULT 'member',
            status varchar(20) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_unit_user (tenant_id, org_unit_id, user_id),
            KEY tenant_unit_status (tenant_id, org_unit_id, status, assignment_role, user_id, id),
            KEY tenant_user_status (tenant_id, user_id, status, org_unit_id, id)
        ) {$charset};");
    }
}
