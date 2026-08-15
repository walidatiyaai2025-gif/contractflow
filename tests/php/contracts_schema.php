<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;

$tests = 0;

function sc_contract_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_contract_assert(is_callable($activate), 'plugin activation hook is available');
$activate();

sc_contract_assert(Migrator::LATEST_VERSION === '1.5.0', 'contract schema migrations are current');
sc_contract_assert(get_option(Migrator::VERSION_OPTION) === '1.5.0', 'current contract schema version is stored');
sc_contract_assert(count($GLOBALS['sc_test_dbdelta']) === 8, 'contract, financial and history schemas migrate without replay');

$schema = $GLOBALS['sc_test_dbdelta'][3];
sc_contract_assert(str_contains($schema, 'wp_safecontracts_contracts'), 'dedicated contracts table uses the WordPress prefix');
sc_contract_assert(str_contains($schema, 'contract_number varchar(100) NOT NULL'), 'contract number is required');
sc_contract_assert(str_contains($schema, 'UNIQUE KEY contract_number (contract_number)'), 'contract number is unique');
sc_contract_assert(str_contains($schema, 'customer_id bigint(20) unsigned NOT NULL'), 'contract belongs to a customer');
sc_contract_assert(str_contains($schema, 'accountant_user_id bigint(20) unsigned NULL'), 'contract can reference the responsible Accountant');
sc_contract_assert(str_contains($schema, "status varchar(32) NOT NULL DEFAULT 'draft'"), 'contract has a controlled lifecycle status field');
sc_contract_assert(str_contains($schema, 'start_date date NULL'), 'contract start date is supported');
sc_contract_assert(str_contains($schema, 'end_date date NULL'), 'contract end date is supported');
sc_contract_assert(str_contains($schema, 'base_value decimal(20,4) NOT NULL DEFAULT 0.0000'), 'contract base value uses fixed-point financial precision');
sc_contract_assert(str_contains($schema, 'notes longtext NULL'), 'contract notes are supported');
sc_contract_assert(str_contains($schema, 'is_archived tinyint(1) NOT NULL DEFAULT 0'), 'contract archive state is explicit and non-destructive');
sc_contract_assert(str_contains($schema, 'created_by bigint(20) unsigned NULL'), 'contract creator is traceable');
sc_contract_assert(str_contains($schema, 'updated_by bigint(20) unsigned NULL'), 'contract last updater is traceable');
sc_contract_assert(str_contains($schema, 'KEY customer_status (customer_id, status, is_archived)'), 'customer/status portfolio queries are indexed');
sc_contract_assert(str_contains($schema, 'KEY accountant_status (accountant_user_id, status, is_archived)'), 'Accountant scope/status queries are indexed');
sc_contract_assert(str_contains($schema, 'KEY contract_dates (start_date, end_date)'), 'contract date-range queries are indexed');
sc_contract_assert(! str_contains($schema, 'currency_code'), 'contract rows do not introduce a competing per-contract currency');

$historySchema = $GLOBALS['sc_test_dbdelta'][7];
sc_contract_assert(str_contains($historySchema, 'wp_safecontracts_contract_history'), 'contract history uses a dedicated prefixed table');
sc_contract_assert(str_contains($historySchema, 'event_type varchar(64) NOT NULL'), 'contract history records event type');
sc_contract_assert(str_contains($historySchema, 'actor_user_id bigint(20) unsigned NULL'), 'contract history records actor when available');
sc_contract_assert(str_contains($historySchema, 'snapshot_json longtext NULL'), 'contract history records state snapshot');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_contract_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'current contract migrations are idempotent on runtime bootstrap');

$optionsBeforeDeactivate = $GLOBALS['sc_test_options'];
$deactivate = $GLOBALS['sc_test_deactivation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_contract_assert(is_callable($deactivate), 'plugin deactivation hook is available');
$deactivate();
sc_contract_assert($GLOBALS['sc_test_options'] === $optionsBeforeDeactivate, 'deactivation preserves contract schema/version state');
sc_contract_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'deactivation does not alter contract schema/data');

echo "SafeContracts contract data-model tests passed ({$tests} assertions).\n";
