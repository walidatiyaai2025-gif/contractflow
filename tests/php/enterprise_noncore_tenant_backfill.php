<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Tenancy\NonCoreTenantOwnershipBackfill;

$assertions = 0;

function esc_noncore_backfill_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_noncore_backfill_query_contains(string $needle): bool
{
    foreach ($GLOBALS['sc_test_queries'] as $query) {
        if (str_contains((string) $query, $needle)) {
            return true;
        }
    }
    return false;
}

$service = new NonCoreTenantOwnershipBackfill();
esc_noncore_backfill_assert(
    NonCoreTenantOwnershipBackfill::rootGroups() === ['rules', 'templates', 'devices', 'deliveries', 'imports', 'suppressions', 'audit'],
    'explicit non-core root groups are stable and enumerable'
);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [['total' => '0']];
$derived = $service->deriveDeterministic();
esc_noncore_backfill_assert($derived['ready'] === true, 'deterministic derivation may report ready when no roots remain');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('START TRANSACTION'), 'deterministic derivation is transactional');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_notification_schedule s INNER JOIN wp_safecontracts_scheduled_payments p'), 'schedule ownership derives from payment');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_notification_deliveries d INNER JOIN wp_safecontracts_scheduled_payments p'), 'delivery ownership derives from payment');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_notification_deliveries d INNER JOIN wp_safecontracts_notification_rules r'), 'delivery ownership may derive from an already-owned rule');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_import_errors e INNER JOIN wp_safecontracts_import_runs r ON r.id = e.import_run_id'), 'import error ownership derives from import run using live import_run_id schema');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains("s.scope_type = 'payment'"), 'payment suppression ownership derives through scope_type/scope_id');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains("s.scope_type = 'contract'"), 'contract suppression ownership derives through scope_type/scope_id');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains("a.entity_type = 'contract'"), 'contract audit ownership derives from contract parent');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains("a.entity_type = 'import_run'"), 'import audit ownership uses live import_run audit entity type');
esc_noncore_backfill_assert(! esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_notification_rules SET tenant_id'), 'derive mode never guesses notification rule root ownership');
esc_noncore_backfill_assert(! esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_device_tokens SET tenant_id'), 'derive mode never guesses device root ownership');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('COMMIT'), 'mismatch-free deterministic derivation commits');

// Suppression ownership must use the live scope_type/scope_id schema. Limit the
// regression to suppression SQL so legitimate schedule alias `s.payment_id` does
// not create a false positive.
$suppressionQueries = array_values(array_filter(
    array_map('strval', $GLOBALS['sc_test_queries']),
    static fn (string $query): bool => str_contains($query, 'wp_safecontracts_notification_suppressions s')
));
esc_noncore_backfill_assert($suppressionQueries !== [], 'deterministic derivation emits suppression ownership SQL');
$suppressionSql = implode("\n", $suppressionQueries);
esc_noncore_backfill_assert(! str_contains($suppressionSql, 's.payment_id'), 'suppression backfill contains no nonexistent payment_id column');
esc_noncore_backfill_assert(! str_contains($suppressionSql, 's.rule_id'), 'suppression backfill contains no nonexistent rule_id column');
esc_noncore_backfill_assert(str_contains($suppressionSql, 's.scope_id'), 'suppression backfill uses live scope_id ownership relation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '17']]];
$GLOBALS['sc_test_results'] = [['total' => '0']];
$partial = $service->assignRootsToTenant(17, ['rules', 'devices']);
esc_noncore_backfill_assert($partial['ready'] === true, 'reviewed root mapping returns ownership report');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_notification_rules SET tenant_id = 17 WHERE tenant_id IS NULL'), 'reviewed rules root may be assigned explicitly');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_device_tokens SET tenant_id = 17 WHERE tenant_id IS NULL'), 'reviewed devices root may be assigned explicitly');
esc_noncore_backfill_assert(! esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_notification_templates SET tenant_id = 17'), 'unselected template root is not assigned');
esc_noncore_backfill_assert(! esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_import_runs SET tenant_id = 17'), 'unselected import root is not assigned');
esc_noncore_backfill_assert(! esc_noncore_backfill_query_contains('UPDATE wp_safecontracts_audit_log SET tenant_id = 17'), 'unselected audit root is not assigned');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '17']]];
$GLOBALS['sc_test_results'] = [['total' => '0']];
$service->assignRootsToTenant(17, ['audit']);
esc_noncore_backfill_assert(
    esc_noncore_backfill_query_contains("UPDATE wp_safecontracts_audit_log SET tenant_id = 17 WHERE tenant_id IS NULL AND NOT (entity_type IN ('payment_method','role','system') OR event_type = 'user_role_changed')"),
    'explicit audit mapping excludes platform-global audit classes'
);

$unsupported = false;
try {
    $service->assignRootsToTenant(17, ['everything']);
} catch (Throwable $error) {
    $unsupported = str_contains($error->getMessage(), 'Unsupported non-core root group');
}
esc_noncore_backfill_assert($unsupported, 'unsupported root group fails before any broad assignment');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '17']]];
$GLOBALS['sc_test_results'] = [['total' => '1']];
$rolledBack = false;
try {
    $service->assignRootsToTenant(17, ['rules']);
} catch (Throwable $error) {
    $rolledBack = str_contains($error->getMessage(), 'cross-tenant mismatches');
}
esc_noncore_backfill_assert($rolledBack, 'cross-tenant mismatch blocks reviewed root mapping');
esc_noncore_backfill_assert(esc_noncore_backfill_query_contains('ROLLBACK'), 'mismatch root mapping rolls back');

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Tenancy/NonCoreTenantOwnershipBackfill.php');
esc_noncore_backfill_assert(! str_contains($source, 'e.run_id'), 'backfill contains no obsolete import-error run_id reference');

$doc = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/enterprise/NON_CORE_TENANT_OWNERSHIP.md');
esc_noncore_backfill_assert(str_contains($doc, 'explicit reviewed mapping/recreation'), 'runbook requires explicit root decisions');
esc_noncore_backfill_assert(str_contains($doc, 'platform-global audit'), 'runbook distinguishes platform audit from tenant audit');

$script = (string) file_get_contents(dirname(__DIR__, 2) . '/scripts/enterprise_noncore_tenant_backfill.php');
esc_noncore_backfill_assert(str_contains($script, '--derive'), 'operator script exposes deterministic derivation separately');

fwrite(STDOUT, "Enterprise non-core tenant ownership backfill passed ({$assertions} assertions).\n");
