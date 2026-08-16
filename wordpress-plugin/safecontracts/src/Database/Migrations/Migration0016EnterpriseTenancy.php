<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0016EnterpriseTenancy implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';

        dbDelta("CREATE TABLE {$tenants} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid char(36) NOT NULL,
            slug varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            legal_name varchar(191) NULL,
            country_code char(2) NULL,
            timezone varchar(64) NOT NULL DEFAULT 'UTC',
            default_currency char(3) NOT NULL DEFAULT 'USD',
            locale varchar(20) NOT NULL DEFAULT 'en_US',
            status varchar(20) NOT NULL DEFAULT 'active',
            settings_json longtext NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            UNIQUE KEY slug (slug),
            KEY status_name (status, name, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$memberships} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            role_code varchar(64) NOT NULL DEFAULT 'member',
            status varchar(20) NOT NULL DEFAULT 'active',
            is_owner tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_user (tenant_id, user_id),
            KEY user_status (user_id, status, tenant_id),
            KEY tenant_status (tenant_id, status, user_id)
        ) {$charset};");
    }
}
