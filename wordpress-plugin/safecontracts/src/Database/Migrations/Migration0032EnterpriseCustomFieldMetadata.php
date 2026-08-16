<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0032EnterpriseCustomFieldMetadata implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $wpdb->prefix . 'safecontracts_custom_field_metadata';

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            definition_id bigint(20) unsigned NOT NULL,
            data_type_snapshot varchar(30) NOT NULL,
            show_in_form tinyint(1) NOT NULL DEFAULT 1,
            show_in_summary tinyint(1) NOT NULL DEFAULT 0,
            show_in_mobile tinyint(1) NOT NULL DEFAULT 1,
            show_in_print tinyint(1) NOT NULL DEFAULT 0,
            filterable tinyint(1) NOT NULL DEFAULT 0,
            sortable tinyint(1) NOT NULL DEFAULT 0,
            groupable tinyint(1) NOT NULL DEFAULT 0,
            exportable tinyint(1) NOT NULL DEFAULT 0,
            dashboard_visible tinyint(1) NOT NULL DEFAULT 0,
            report_label varchar(191) NULL,
            report_data_class varchar(30) NOT NULL DEFAULT 'text',
            aggregation_policy varchar(20) NOT NULL DEFAULT 'none',
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_definition (tenant_id, definition_id),
            KEY tenant_report_class (tenant_id, report_data_class, definition_id),
            KEY tenant_dashboard (tenant_id, dashboard_visible, definition_id)
        ) {$charset};");
    }
}
