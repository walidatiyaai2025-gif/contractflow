<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0017CoreTenantOwnershipExpand;
use SafeContracts\Tenancy\CoreTenantOwnershipBackfill;

$assertions = 0;

function esc_ownership_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_ownership_query_contains(string $needle): bool
{
    foreach ($GLOBALS['sc_test_queries'] as $query) {
        if (str_contains((string) $query, $needle)) {
            return true;
        }
    }
    return false;
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();
esc_ownership_assert(Migrator::LATEST_VERSION === '1.16.0', 'core tenant ownership expansion is the current migration');

$tables = [
    'wp_safecontracts_customers',
    'wp_safecontracts_contracts',
    'wp_safecontracts_contract_financial_items',
    'wp_safecontracts_contract_adjustments',
    'wp_safecontracts_contract_attachments',
    'wp_safecontracts_contract_history',
    'wp_safecontracts_scheduled_payments',
    'wp_safecontracts_payment_collections',
    'wp_safecontracts_payment_followups',
];
foreach ($tables as $table) {
    esc_ownership_assert(
        esc_ownership_query_contains("ALTER TABLE {$table} ADD COLUMN tenant_id bigint(20) unsigned NULL AFTER id"),
        "{$table} receives nullable tenant ownership during expand phase"
    );
    esc_ownership_assert(
        esc_ownership_query_contains("ALTER TABLE {$table} ADD KEY esc_tenant_record (tenant_id, id)"),
        "{$table} receives tenant-first lookup index"
    );
}

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_results'] = [['Field' => 'tenant_id']];
(new Migration0017CoreTenantOwnershipExpand())->up($GLOBALS['wpdb']);
esc_ownership_assert($GLOBALS['sc_test_queries'] === [], 'ownership expansion is idempotent when column/index already exist');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '17']]];
$GLOBALS['sc_test_results'] = [['total' => '0']];
$backfill = new CoreTenantOwnershipBackfill();
$report = $backfill->applyDefaultTenant(17);
esc_ownership_assert($report['ready'] === true, 'backfill commits only when ownership report is ready');
esc_ownership_assert(esc_ownership_query_contains('START TRANSACTION'), 'backfill is transactional');
esc_ownership_assert(esc_ownership_query_contains('UPDATE wp_safecontracts_customers SET tenant_id = 17 WHERE tenant_id IS NULL'), 'default tenant is applied only to unowned root customers');
esc_ownership_assert(esc_ownership_query_contains('UPDATE wp_safecontracts_contracts c INNER JOIN wp_safecontracts_customers cu'), 'contract ownership is derived from customer ownership');
esc_ownership_assert(esc_ownership_query_contains('UPDATE wp_safecontracts_scheduled_payments p INNER JOIN wp_safecontracts_contracts c'), 'payment ownership is derived from contract ownership');
esc_ownership_assert(esc_ownership_query_contains('UPDATE wp_safecontracts_payment_collections cl INNER JOIN wp_safecontracts_scheduled_payments p'), 'collection ownership is derived from payment ownership');
esc_ownership_assert(esc_ownership_query_contains('UPDATE wp_safecontracts_payment_followups f INNER JOIN wp_safecontracts_scheduled_payments p'), 'follow-up ownership is derived from payment ownership');
esc_ownership_assert(esc_ownership_query_contains('COMMIT'), 'ready backfill commits');
esc_ownership_assert(! esc_ownership_query_contains('UPDATE wp_safecontracts_contracts SET tenant_id = 17'), 'children are never blindly assigned the default tenant');

$GLOBALS['sc_test_result_queue'] = [[]];
$GLOBALS['sc_test_results'] = [['total' => '0']];
$blocked = false;
try {
    $backfill->applyDefaultTenant(999);
} catch (Throwable $error) {
    $blocked = str_contains($error->getMessage(), 'active Enterprise tenant');
}
esc_ownership_assert($blocked, 'unknown/inactive legacy target is rejected');

$doc = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/enterprise/TENANT_DATA_OWNERSHIP.md');
esc_ownership_assert(str_contains($doc, 'expand → backfill → verify → enforce'), 'ownership runbook documents staged enforcement');
esc_ownership_assert(str_contains($doc, 'Do not use the default-tenant command as a substitute for a real mapping decision.'), 'runbook warns against guessing multi-tenant legacy mappings');

fwrite(STDOUT, "Enterprise core tenant ownership expansion passed ({$assertions} assertions).\n");
