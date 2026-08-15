<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0012Import implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $runs = $wpdb->prefix . 'safecontracts_import_runs';
        $errors = $wpdb->prefix . 'safecontracts_import_errors';

        dbDelta("CREATE TABLE {$runs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            original_filename varchar(191) NOT NULL,
            storage_key char(64) NOT NULL,
            file_sha256 char(64) NOT NULL,
            file_size bigint(20) unsigned NOT NULL,
            status varchar(32) NOT NULL,
            selected_sheet varchar(191) NULL,
            discovery_json longtext NULL,
            mapping_json longtext NULL,
            duplicate_strategy varchar(16) NOT NULL DEFAULT 'fail',
            total_rows int(10) unsigned NOT NULL DEFAULT 0,
            valid_rows int(10) unsigned NOT NULL DEFAULT 0,
            imported_rows int(10) unsigned NOT NULL DEFAULT 0,
            skipped_rows int(10) unsigned NOT NULL DEFAULT 0,
            error_rows int(10) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY storage_key (storage_key),
            KEY file_hash (file_sha256),
            KEY status_created (status, created_at, id),
            KEY actor_created (created_by, created_at, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$errors} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            import_run_id bigint(20) unsigned NOT NULL,
            row_number int(10) unsigned NOT NULL,
            field_name varchar(100) NULL,
            error_code varchar(80) NOT NULL,
            message text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY run_row (import_run_id, row_number, id),
            KEY run_code (import_run_id, error_code, id)
        ) {$charset};");
    }
}
