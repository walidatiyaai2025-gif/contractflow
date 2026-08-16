<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0018NonCoreTenantOwnershipExpand;

$assertions = 0;

function esc_noncore_expand_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_noncore_expand_query_contains(string $needle): bool
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
esc_noncore_expand_assert(
    version_compare(Migrator::LATEST_VERSION, '1.17.0', '>='),
    'non-core tenant ownership expansion remains registered after later Enterprise schema versions'
);

$tables = [
    'wp_safecontracts_notification_rules',
    'wp_safecontracts_notification_templates',
    'wp_safecontracts_device_tokens',
    'wp_safecontracts_notification_deliveries',
    'wp_safecontracts_notification_schedule',
    'wp_safecontracts_notification_suppressions',
    'wp_safecontracts_import_runs',
    'wp_safecontracts_import_errors',
    'wp_safecontracts_audit_log',
];
foreach ($tables as $table) {
    esc_noncore_expand_assert(
        esc_noncore_expand_query_contains("ALTER TABLE {$table} ADD COLUMN tenant_id bigint(20) unsigned NULL AFTER id"),
        "{$table} receives nullable tenant ownership only during expand"
    );
    esc_noncore_expand_assert(
        esc_noncore_expand_query_contains("ALTER TABLE {$table} ADD KEY esc_tenant_record (tenant_id, id)"),
        "{$table} receives tenant lookup index"
    );
}

esc_noncore_expand_assert(
    ! esc_noncore_expand_query_contains('ALTER TABLE wp_safecontracts_payment_methods ADD COLUMN tenant_id'),
    'platform-global payment methods are not mechanically converted to tenant ownership'
);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_results'] = [['Field' => 'tenant_id']];
(new Migration0018NonCoreTenantOwnershipExpand())->up($GLOBALS['wpdb']);
esc_noncore_expand_assert($GLOBALS['sc_test_queries'] === [], 'non-core expansion is idempotent when column/index already exist');

$doc = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/enterprise/NON_CORE_TENANT_OWNERSHIP.md');
esc_noncore_expand_assert(str_contains($doc, 'expand → explicit/derived backfill → verify → runtime enforce → adversarial validate → harden'), 'non-core ownership runbook documents verify/enforce/adversarial/harden rollout');
esc_noncore_expand_assert(str_contains($doc, 'Do **not** copy every legacy notification rule/template/token/import into every tenant.'), 'runbook forbids guessed fan-out of global legacy roots');
esc_noncore_expand_assert(str_contains($doc, 'payment-method/reference catalog'), 'runbook preserves explicitly global reference data');
esc_noncore_expand_assert(str_contains($doc, 'scheduler/cron execution enumerates tenants explicitly'), 'runbook requires tenant-aware background iteration');
esc_noncore_expand_assert(str_contains($doc, 'notification dispatch-time and email enable/from-name/from-address business settings'), 'runbook classifies notification business settings as tenant-owned');
esc_noncore_expand_assert(str_contains($doc, 'Firebase application/project identity, project-keyed OAuth access-token cache'), 'runbook keeps Firebase deployment identity explicitly platform-global');

$migratorSource = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
esc_noncore_expand_assert(str_contains($migratorSource, "'1.17.0' => Migration0018NonCoreTenantOwnershipExpand::class"), 'non-core expansion is registered at schema 1.17.0');

fwrite(STDOUT, "Enterprise non-core tenant ownership expansion passed ({$assertions} assertions).\n");
