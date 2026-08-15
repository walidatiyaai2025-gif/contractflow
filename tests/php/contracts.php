<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;

$contractTests = 0;

function sc_contract_assert(bool $condition, string $message): void
{
    global $contractTests;
    $contractTests++;

    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();

sc_contract_assert(Migrator::LATEST_VERSION === '1.3.0', 'contract schema migration is the latest registered version');
sc_contract_assert(get_option(Migrator::VERSION_OPTION) === '1.3.0', 'contract schema version is stored after activation');
sc_contract_assert(count($GLOBALS['sc_test_dbdelta']) === 4, 'contract table is added after foundation and master-data tables');

$contractSchema = $GLOBALS['sc_test_dbdelta'][3];
sc_contract_assert(str_contains($contractSchema, 'wp_safecontracts_contracts'), 'contract table uses WordPress prefix');
sc_contract_assert(str_contains($contractSchema, 'contract_number varchar(100) NOT NULL'), 'contract number is required');
sc_contract_assert(str_contains($contractSchema, 'UNIQUE KEY contract_number (contract_number)'), 'contract number is globally unique');
sc_contract_assert(str_contains($contractSchema, 'customer_id bigint(20) unsigned NOT NULL'), 'customer relation is required');
sc_contract_assert(str_contains($contractSchema, 'accountant_user_id bigint(20) unsigned NOT NULL'), 'responsible accountant relation is required');
sc_contract_assert(str_contains($contractSchema, "status varchar(32) NOT NULL DEFAULT 'draft'"), 'contract status has a stable draft baseline');
sc_contract_assert(str_contains($contractSchema, 'start_date date NULL'), 'contract start date is represented');
sc_contract_assert(str_contains($contractSchema, 'end_date date NULL'), 'contract end date is represented');
sc_contract_assert(str_contains($contractSchema, 'base_value decimal(19,3) NOT NULL DEFAULT 0.000'), 'base value uses fixed-point three-decimal precision');
sc_contract_assert(str_contains($contractSchema, 'archived_at datetime NULL'), 'archive state is non-destructive');
sc_contract_assert(str_contains($contractSchema, 'created_by bigint(20) unsigned NOT NULL'), 'contract creator is auditable');
sc_contract_assert(str_contains($contractSchema, 'created_at datetime NOT NULL'), 'contract creation timestamp is stored');
sc_contract_assert(str_contains($contractSchema, 'updated_at datetime NOT NULL'), 'contract update timestamp is stored');
sc_contract_assert(str_contains($contractSchema, 'KEY customer_status (customer_id, status)'), 'customer/status reporting path is indexed');
sc_contract_assert(str_contains($contractSchema, 'KEY accountant_status (accountant_user_id, status)'), 'accountant scope/status path is indexed');
sc_contract_assert(str_contains($contractSchema, 'KEY date_window (start_date, end_date)'), 'contract date filtering is indexed');
sc_contract_assert(! str_contains($contractSchema, 'currency_code'), 'single-currency V1 avoids per-contract currency duplication');
sc_contract_assert(! str_contains($contractSchema, 'net_value'), 'net reconciliation remains in its dedicated later task');

$migrationCountBeforeBoot = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_contract_assert(count($GLOBALS['sc_test_dbdelta']) === $migrationCountBeforeBoot, 'contract migration is idempotent after version is current');

$migrationEvents = $GLOBALS['sc_test_fired_actions']['safecontracts_database_migrated'] ?? [];
$latestMigration = end($migrationEvents);
sc_contract_assert(is_array($latestMigration) && ($latestMigration[0] ?? null) === '1.3.0', 'contract migration emits the versioned migration event');

echo "SafeContracts contract schema tests passed ({$contractTests} assertions).\n";
