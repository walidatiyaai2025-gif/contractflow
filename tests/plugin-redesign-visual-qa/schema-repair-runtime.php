<?php

use SafeContracts\Database\Migrator;

if (! defined('ABSPATH')) {
    fwrite(STDERR, "SafeContracts schema repair runtime test requires WordPress.\n");
    exit(1);
}

global $wpdb;
if (! is_object($wpdb)) {
    fwrite(STDERR, "WordPress database handle is unavailable.\n");
    exit(1);
}

$errors = $wpdb->prefix . 'safecontracts_import_errors';
$runs = $wpdb->prefix . 'safecontracts_import_runs';
$deliveries = $wpdb->prefix . 'safecontracts_notification_deliveries';
$rules = $wpdb->prefix . 'safecontracts_notification_rules';

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert(
    (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $runs)) === $runs,
    'Import runs prerequisite must exist before schema-drift simulation.'
);

$result = $wpdb->query("DROP TABLE IF EXISTS {$errors}");
$assert($result !== false, 'Unable to simulate the historical missing import-errors table.');
$assert(
    (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $errors)) !== $errors,
    'Schema-drift simulation did not remove the import-errors table.'
);

$updated = update_option(Migrator::VERSION_OPTION, '1.21.0', false);
$assert($updated || (string) get_option(Migrator::VERSION_OPTION, '') === '1.21.0', 'Unable to pin schema version to 1.21.0 for repair simulation.');

(new Migrator())->maybeMigrate();

$assert((string) get_option(Migrator::VERSION_OPTION, '') === '1.24.0', 'Repair path did not advance the database version through 1.22.0/1.23.0 to current 1.24.0.');
$assert(
    (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $errors)) === $errors,
    '1.22.0 repair migration did not recreate safecontracts_import_errors.'
);

$rows = $wpdb->get_results(
    "SELECT id, import_run_id, `row_number`, field_name, error_code, message, created_at FROM {$errors} ORDER BY id ASC LIMIT 1",
    ARRAY_A
);
$assert(is_array($rows), 'Repaired import-errors table is not readable with the required schema.');

$deliveryColumns = $wpdb->get_col("SHOW COLUMNS FROM {$deliveries}", 0);
$assert(is_array($deliveryColumns), 'Notification delivery columns are not readable after 1.23.0 migration.');
foreach (['resource_type', 'resource_id', 'contract_id'] as $column) {
    $assert(in_array($column, $deliveryColumns, true), '1.23.0 notification activity migration is missing ' . $column . '.');
}

$ruleColumns = $wpdb->get_col("SHOW COLUMNS FROM {$rules}", 0);
$assert(is_array($ruleColumns), 'Notification rule columns are not readable after 1.24.0 migration.');
foreach (['counterparty_type', 'financial_direction'] as $column) {
    $assert(in_array($column, $ruleColumns, true), '1.24.0 notification rule scope migration is missing ' . $column . '.');
}

$assert(trim((string) $wpdb->last_error) === '', 'Database error remained after schema repair path: ' . (string) $wpdb->last_error);

echo "SafeContracts historical schema drift repaired successfully (1.21.0 -> 1.22.0 -> 1.23.0 -> 1.24.0).\n";
