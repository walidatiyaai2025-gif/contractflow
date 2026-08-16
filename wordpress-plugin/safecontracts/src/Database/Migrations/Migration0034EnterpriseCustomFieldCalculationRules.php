<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0034EnterpriseCustomFieldCalculationRules implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $rules = $wpdb->prefix . 'safecontracts_custom_field_calculation_rules';
        $dependencies = $wpdb->prefix . 'safecontracts_custom_field_calculation_dependencies';

        dbDelta("CREATE TABLE {$rules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            target_definition_id bigint(20) unsigned NOT NULL,
            contract_type_id bigint(20) unsigned NOT NULL,
            target_field_code_snapshot varchar(100) NOT NULL,
            target_data_type_snapshot varchar(30) NOT NULL,
            target_config_hash char(64) NOT NULL,
            expression_json longtext NOT NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_target (tenant_id, target_definition_id),
            KEY tenant_type_target (tenant_id, contract_type_id, target_definition_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$dependencies} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            rule_id bigint(20) unsigned NOT NULL,
            target_definition_id bigint(20) unsigned NOT NULL,
            position_no int(10) unsigned NOT NULL,
            source_definition_id bigint(20) unsigned NOT NULL,
            source_field_code_snapshot varchar(100) NOT NULL,
            source_data_type_snapshot varchar(30) NOT NULL,
            source_config_hash char(64) NOT NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_rule_source (tenant_id, rule_id, source_definition_id),
            UNIQUE KEY tenant_rule_position (tenant_id, rule_id, position_no),
            KEY tenant_target_source (tenant_id, target_definition_id, source_definition_id)
        ) {$charset};");
    }
}
