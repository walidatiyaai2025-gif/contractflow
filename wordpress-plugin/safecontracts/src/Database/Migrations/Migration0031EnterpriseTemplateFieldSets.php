<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0031EnterpriseTemplateFieldSets implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $wpdb->prefix . 'safecontracts_contract_template_version_fields';

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            template_id bigint(20) unsigned NOT NULL,
            template_version_id bigint(20) unsigned NOT NULL,
            definition_id bigint(20) unsigned NOT NULL,
            position_no int(10) unsigned NOT NULL,
            field_code_snapshot varchar(100) NOT NULL,
            data_type_snapshot varchar(30) NOT NULL,
            label_snapshot varchar(191) NOT NULL,
            help_text_snapshot text NULL,
            definition_required_snapshot tinyint(1) NOT NULL DEFAULT 0,
            required_override tinyint(1) NULL,
            options_json_snapshot longtext NULL,
            validation_json_snapshot longtext NULL,
            definition_config_hash char(64) NOT NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_version_definition (tenant_id, template_version_id, definition_id),
            UNIQUE KEY tenant_version_position (tenant_id, template_version_id, position_no),
            KEY tenant_template_version (tenant_id, template_id, template_version_id, position_no),
            KEY tenant_definition (tenant_id, definition_id, template_version_id)
        ) {$charset};");
    }
}
