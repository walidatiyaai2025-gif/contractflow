<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0030EnterpriseCustomFieldValues implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $wpdb->prefix . 'safecontracts_custom_field_values';

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            definition_id bigint(20) unsigned NOT NULL,
            is_set tinyint(1) NOT NULL DEFAULT 1,
            value_json longtext NULL,
            data_type_snapshot varchar(30) NOT NULL,
            definition_config_hash char(64) NOT NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_contract_definition (tenant_id, contract_id, definition_id),
            KEY tenant_contract_set (tenant_id, contract_id, is_set, definition_id),
            KEY tenant_definition_set (tenant_id, definition_id, is_set, contract_id)
        ) {$charset};");
    }
}
