<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0029EnterpriseCustomFieldDefinitions implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $wpdb->prefix . 'safecontracts_custom_field_definitions';

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_type_id bigint(20) unsigned NOT NULL,
            field_code varchar(100) NOT NULL,
            data_type varchar(30) NOT NULL,
            label varchar(191) NOT NULL,
            help_text text NULL,
            is_required tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            options_json longtext NULL,
            validation_json longtext NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_uuid (tenant_id, uuid),
            UNIQUE KEY tenant_type_code (tenant_id, contract_type_id, field_code),
            KEY tenant_type_status_sort (tenant_id, contract_type_id, status, sort_order, id),
            KEY tenant_status (tenant_id, status, id)
        ) {$charset};");
    }
}
