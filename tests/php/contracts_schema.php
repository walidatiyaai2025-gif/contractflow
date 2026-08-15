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

sc_contract_assert(version_compare(Migrator::LATEST_VERSION, '1.7.0', '>='), 'contract/payment/collection schema migrations remain registered after later schema versions');
sc_contract_assert(get_option(Migrator::VERSION_OPTION) === Migrator::LATEST_VERSION, 'current schema version is stored');
sc_contract_assert(count($GLOBALS['sc_test_dbdelta']) >= 10, 'contract, financial, history, payment and collection schemas migrate before later schemas');

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

$paymentSchema = $GLOBALS['sc_test_dbdelta'][8];
sc_contract_assert(str_contains($paymentSchema, 'wp_safecontracts_scheduled_payments'), 'scheduled payments use a dedicated prefixed table');
sc_contract_assert(str_contains($paymentSchema, 'due_date date NOT NULL'), 'scheduled payment due date is required');
sc_contract_assert(str_contains($paymentSchema, 'expected_payment_date date NULL'), 'scheduled payment expected date is optional');
sc_contract_assert(str_contains($paymentSchema, 'original_amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'scheduled payment original amount uses fixed-point precision');
sc_contract_assert(str_contains($paymentSchema, 'remaining_amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'scheduled payment remaining amount uses fixed-point precision');
sc_contract_assert(str_contains($paymentSchema, 'UNIQUE KEY contract_sequence (contract_id, sequence_no)'), 'payment sequence is unique within a contract');

$collectionSchema = $GLOBALS['sc_test_dbdelta'][9];
sc_contract_assert(str_contains($collectionSchema, 'wp_safecontracts_payment_collections'), 'collection ledger extends payment model non-destructively');
sc_contract_assert(str_contains($collectionSchema, 'payment_method_id bigint(20) unsigned NOT NULL'), 'collection ledger requires payment method');
sc_contract_assert(str_contains($collectionSchema, 'proof_media_id bigint(20) unsigned NULL'), 'collection proof remains optional');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_contract_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'current migrations are idempotent on runtime bootstrap');

$optionsBeforeDeactivate = $GLOBALS['sc_test_options'];
$deactivate = $GLOBALS['sc_test_deactivation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_contract_assert(is_callable($deactivate), 'plugin deactivation hook is available');
$deactivate();
sc_contract_assert($GLOBALS['sc_test_options'] === $optionsBeforeDeactivate, 'deactivation preserves schema/version state');
sc_contract_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'deactivation does not alter schema/data');

echo "SafeContracts contract data-model tests passed ({$tests} assertions).\n";