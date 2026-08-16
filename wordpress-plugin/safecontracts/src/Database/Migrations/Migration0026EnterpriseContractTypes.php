<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0026EnterpriseContractTypes implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $wpdb->prefix . 'safecontracts_contract_types';

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            type_code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            description longtext NULL,
            category varchar(100) NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            metadata_json longtext NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            UNIQUE KEY tenant_code (tenant_id, type_code),
            KEY tenant_status_name (tenant_id, status, name, id),
            KEY tenant_category_status (tenant_id, category, status, name, id)
        ) {$charset};");
    }
}
