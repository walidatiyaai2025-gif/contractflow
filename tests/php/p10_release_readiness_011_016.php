<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Audit\AuditRecorder;
use SafeContracts\Database\Migrator;

$tests = 0;
function sc_p10c_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p10c_source(string $relative): string
{
    $path = dirname(__DIR__, 2) . '/' . $relative;
    $source = file_get_contents($path);
    sc_p10c_assert($source !== false, 'P10 release source exists: ' . $relative);
    return $source === false ? '' : $source;
}

// SC-P10-011 — Audit completeness.
AuditRecorder::register();
$requiredAuditEvents = [
    'safecontracts_contract_base_value_changed',
    'safecontracts_contract_financial_item_added',
    'safecontracts_contract_adjustment_added',
    'safecontracts_payment_settled',
    'safecontracts_contract_customer_assigned',
    'safecontracts_contract_accountant_assigned',
    'safecontracts_contract_status_changed',
    'safecontracts_contract_dates_changed',
    'safecontracts_payment_status_changed',
    'safecontracts_payment_dates_changed',
    'safecontracts_followup_recorded',
    'safecontracts_export_completed',
    'safecontracts_import_uploaded',
    'safecontracts_import_discovered',
    'safecontracts_import_mapping_saved',
    'safecontracts_import_validated',
    'safecontracts_import_completed',
];
foreach ($requiredAuditEvents as $event) {
    sc_p10c_assert(($GLOBALS['sc_test_actions'][$event] ?? []) !== [], 'P10-011 critical audit hook is registered: ' . $event);
}
$auditSource = sc_p10c_source('wordpress-plugin/safecontracts/src/Audit/AuditRecorder.php');
foreach (['self::sanitize($context)', 'token|secret|password|credential|authorization', 'private[_-]?key', 'service[_-]?account'] as $marker) {
    sc_p10c_assert(str_contains($auditSource, $marker), 'P10-011 audit sanitization marker is enforced: ' . $marker);
}

// SC-P10-012 — RTL/accessibility pass.
$adminShell = sc_p10c_source('wordpress-plugin/safecontracts/src/Admin/AdminShell.php');
$responsiveCss = sc_p10c_source('wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-responsive.css');
$mobileLayout = sc_p10c_source('mobile/lib/features/ui/mobile_layout.dart');
$mobileStates = sc_p10c_source('mobile/lib/features/ui/mobile_states.dart');
foreach ([
    [$adminShell, 'dir="auto"'],
    [$adminShell, 'aria-hidden="true"'],
    [$responsiveCss, '[dir="rtl"]'],
    [$responsiveCss, ':focus-visible'],
    [$mobileLayout, 'safeContractsIsRtlLanguage'],
    [$mobileLayout, "startsWith('ar-')"],
    [$mobileLayout, 'TextDirection.rtl'],
    [$mobileStates, 'Semantics('],
    [$mobileStates, 'liveRegion:'],
    [$mobileStates, 'mobileStateAllowsRetry'],
] as [$source, $marker]) {
    sc_p10c_assert(str_contains($source, $marker), 'P10-012 accessibility/RTL marker is present: ' . $marker);
}

// SC-P10-013 — Backup/restore verification implementation.
$backupScript = sc_p10c_source('scripts/backup_manifest.py');
$backupRunbook = sc_p10c_source('docs/BACKUP_RESTORE_RUNBOOK.md');
foreach (['TABLE_PATTERN', 'safecontracts_%', 'EXTERNAL_SECRET_EXCLUSIONS', 'LATEST_VERSION_PATTERN'] as $marker) {
    sc_p10c_assert(str_contains($backupScript, $marker), 'P10-013 backup manifest contract is present: ' . $marker);
}
foreach (['safecontracts-backup-manifest.json', 'service-account JSON/private keys', 'row counts', 'UAT-008'] as $marker) {
    sc_p10c_assert(str_contains($backupRunbook, $marker), 'P10-013 restore runbook evidence is present: ' . $marker);
}

// SC-P10-014 — Migration/upgrade testing.
$GLOBALS['sc_test_options'][Migrator::VERSION_OPTION] = '1.10.0';
$GLOBALS['sc_test_dbdelta'] = [];
$migrator = new Migrator();
$migrator->migrate();
sc_p10c_assert(($GLOBALS['sc_test_options'][Migrator::VERSION_OPTION] ?? '') === Migrator::LATEST_VERSION, 'P10-014 upgrade reaches the latest registered schema version');
$firstPassDeltaCount = count($GLOBALS['sc_test_dbdelta']);
sc_p10c_assert($firstPassDeltaCount > 0, 'P10-014 pending migration applies schema changes from the previous version');
$migrator->migrate();
sc_p10c_assert(count($GLOBALS['sc_test_dbdelta']) === $firstPassDeltaCount, 'P10-014 latest-version migration is idempotent on a second run');
$migratorSource = sc_p10c_source('wordpress-plugin/safecontracts/src/Database/Migrator.php');
$safeDeletionMigration = sc_p10c_source('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0013SafeDeletion.php');
sc_p10c_assert(version_compare(Migrator::LATEST_VERSION, '1.12.0', '>='), 'P10-014 latest migration version remains explicit and at or beyond the safe-deletion baseline');
sc_p10c_assert(str_contains($migratorSource, "'1.12.0' => Migration0013SafeDeletion::class"), 'P10-014 safe-deletion version remains mapped to its migration class');
foreach (['is_archived', 'archived_by', 'archived_at', 'archived_payment_date'] as $marker) {
    sc_p10c_assert(str_contains($safeDeletionMigration, $marker), 'P10-014 safe-deletion migration contains marker: ' . $marker);
}

// SC-P10-015 — Executable UAT scenario manifest.
$uatRaw = sc_p10c_source('ops/uat-scenarios.json');
$uat = json_decode($uatRaw, true);
sc_p10c_assert(is_array($uat) && ($uat['schema_version'] ?? 0) === 1, 'P10-015 UAT manifest schema is supported');
$scenarios = is_array($uat['scenarios'] ?? null) ? $uat['scenarios'] : [];
sc_p10c_assert(count($scenarios) >= 8, 'P10-015 UAT manifest contains the production scenario baseline');
$roles = [];
$flows = [];
foreach ($scenarios as $scenario) {
    sc_p10c_assert(is_array($scenario), 'P10-015 each UAT scenario is structured');
    $id = (string) ($scenario['id'] ?? '');
    sc_p10c_assert((bool) preg_match('/^UAT-\d{3}$/', $id), 'P10-015 UAT scenario id is deterministic: ' . $id);
    foreach (['preconditions', 'steps', 'expected', 'evidence'] as $field) {
        sc_p10c_assert(is_array($scenario[$field] ?? null) && ($scenario[$field] ?? []) !== [], 'P10-015 UAT scenario ' . $id . ' contains ' . $field);
    }
    $roles[] = (string) ($scenario['role'] ?? '');
    $flows[] = (string) ($scenario['flow'] ?? '');
}
foreach (['safecontracts_system_admin', 'safecontracts_manager', 'safecontracts_accountant', 'safecontracts_viewer'] as $role) {
    sc_p10c_assert(in_array($role, $roles, true), 'P10-015 UAT role coverage includes ' . $role);
}
foreach (['contract-lifecycle', 'assigned-scope', 'collection-settlement', 'followup-workflow', 'report-export', 'read-only-boundary', 'mobile-notification-deeplink', 'upgrade-backup-restore'] as $flow) {
    sc_p10c_assert(in_array($flow, $flows, true), 'P10-015 UAT flow coverage includes ' . $flow);
}

// SC-P10-016 — Production release-readiness gate.
$qualityGates = sc_p10c_source('.github/workflows/quality-gates.yml');
$releaseVerifier = sc_p10c_source('scripts/release_readiness.py');
$releaseDoc = sc_p10c_source('docs/PRODUCTION_RELEASE_READINESS.md');
foreach (['release-readiness:', 'needs: [repository-standards, backend-foundation, mobile-foundation]', 'python3 scripts/backup_manifest.py --check', 'python3 scripts/release_readiness.py --check'] as $marker) {
    sc_p10c_assert(str_contains($qualityGates, $marker), 'P10-016 Quality Gates release marker is present: ' . $marker);
}
foreach (['validate_audit_completeness', 'validate_migration_chain', 'validate_accessibility_contract', 'validate_backup_contract', 'validate_uat_contract', 'validate_ci_release_gate'] as $marker) {
    sc_p10c_assert(str_contains($releaseVerifier, $marker), 'P10-016 release verifier section is present: ' . $marker);
}
foreach (['SC-P10-011', 'SC-P10-012', 'SC-P10-013', 'SC-P10-014', 'SC-P10-015', 'SC-P10-016', 'not production-ready'] as $marker) {
    sc_p10c_assert(str_contains($releaseDoc, $marker), 'P10-016 release documentation marker is present: ' . $marker);
}

printf("SafeContracts P10 release readiness SC-P10-011..016 passed (%d assertions).\n", $tests);
