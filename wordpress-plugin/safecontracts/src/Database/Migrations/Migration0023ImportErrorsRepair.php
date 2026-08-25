<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use RuntimeException;
use SafeContracts\Database\ProductionMigration;

/**
 * Repairs historical schema drift where import_runs exists and the database
 * version advanced, but safecontracts_import_errors was not created.
 *
 * The repair is additive and never deletes import data. Rollback only removes
 * the table when this migration created it during the same migration attempt.
 */
final class Migration0023ImportErrorsRepair implements ProductionMigration
{
    private bool $createdTable = false;

    public function preflight(object $wpdb): void
    {
        $runs = $wpdb->prefix . 'safecontracts_import_runs';
        if (! $this->tableExists($wpdb, $runs)) {
            throw new RuntimeException('SafeContracts import schema repair prerequisite is unavailable: ' . $runs);
        }
    }

    public function up(object $wpdb): void
    {
        $errors = $wpdb->prefix . 'safecontracts_import_errors';
        if ($this->tableExists($wpdb, $errors)) {
            return;
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $sql = "CREATE TABLE {$errors} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            import_run_id bigint(20) unsigned NOT NULL,
            `row_number` int(10) unsigned NOT NULL,
            field_name varchar(100) NULL,
            error_code varchar(80) NOT NULL,
            message text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY run_row (import_run_id, `row_number`, id),
            KEY run_code (import_run_id, error_code, id)
        ) {$charset}";

        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not repair the missing import errors table.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts import errors table repair failed.');
        $this->createdTable = true;
    }

    public function verify(object $wpdb): void
    {
        $errors = $wpdb->prefix . 'safecontracts_import_errors';
        if (! $this->tableExists($wpdb, $errors)) {
            throw new RuntimeException('SafeContracts import errors table repair verification failed: table is still unavailable.');
        }

        $rows = $wpdb->get_results(
            "SELECT id, import_run_id, `row_number`, field_name, error_code, message, created_at FROM {$errors} ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts import errors table repair verification failed.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts import errors table repair verification failed.');
    }

    public function rollback(object $wpdb): void
    {
        if (! $this->createdTable) {
            return;
        }

        $errors = $wpdb->prefix . 'safecontracts_import_errors';
        $result = $wpdb->query("DROP TABLE IF EXISTS {$errors}");
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not roll back the import errors table repair.');
        }
    }

    private function tableExists(object $wpdb, string $table): bool
    {
        $value = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        $this->assertNoDatabaseError($wpdb, 'SafeContracts import schema table check failed.');
        return (string) $value === $table;
    }

    private function assertNoDatabaseError(object $wpdb, string $message): void
    {
        if (property_exists($wpdb, 'last_error') && trim((string) $wpdb->last_error) !== '') {
            throw new RuntimeException($message . ' ' . trim((string) $wpdb->last_error));
        }
    }
}
