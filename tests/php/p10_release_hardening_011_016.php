<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Audit\AuditRecorder;
use SafeContracts\Database\Migrator;
use SafeContracts\Lifecycle\Deactivator;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use SafeContracts\Support\RecoveryManifest;

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
    sc_p10c_assert($source !== false, 'P10 release-hardening source exists: ' . $relative);
    return $source === false ? '' : $source;
}

// SC-P10-011 — sensitive audit completeness and append-only storage.
AuditRecorder::register();
$requiredAuditHooks = [
    'safecontracts_contract_created',
    'safecontracts_contract_edited',
    'safecontracts_contract_base_value_changed',
    'safecontracts_payment_created',
    'safecontracts_payment_settled',
    'safecontracts_collection_recorded',
    'safecontracts_followup_recorded',
    'safecontracts_export_completed',
    'safecontracts_import_uploaded',
    'safecontracts_import_completed',
    'safecontracts_notification_rule_saved',
    'safecontracts_general_settings_saved',
    'safecontracts_mobile_configuration_saved',
    'safecontracts_firebase_public_settings_saved',
    'safecontracts_firebase_credential_reference_saved',
    'safecontracts_device_token_registered',
    'safecontracts_device_token_revoked',
    'safecontracts_database_migrated',
];
foreach ($requiredAuditHooks as $hook) {
    sc_p10c_assert(isset($GLOBALS['sc_test_actions'][$hook]), 'P10-011 sensitive event is attached to audit recorder: ' . $hook);
}

$GLOBALS['sc_test_queries'] = [];
do_action('safecontracts_contract_created', 70, 42, 7, 42);
do_action('safecontracts_payment_created', 91, 70, 1, '2026-08-20', null, '100.0000', 42);
do_action('safecontracts_collection_recorded', 501, 91, '25.0000', '2026-08-15', 3, 777, 42);
do_action('safecontracts_firebase_credential_reference_saved', 42);
do_action('safecontracts_device_token_registered', 42, 'TOKEN_HASH_MUST_NOT_ENTER_AUDIT', 'android');
$auditSql = implode("\n", $GLOBALS['sc_test_queries']);
foreach (['contract_created', 'payment_created', 'collection_recorded', 'firebase_credential_reference_saved', 'device_token_registered'] as $event) {
    sc_p10c_assert(str_contains($auditSql, $event), 'P10-011 audit append contains event identity: ' . $event);
}
sc_p10c_assert(! str_contains($auditSql, 'TOKEN_HASH_MUST_NOT_ENTER_AUDIT'), 'P10-011 device token hash never enters audit payload');
$auditRepository = sc_p10c_source('wordpress-plugin/safecontracts/src/Audit/AuditRepository.php');
sc_p10c_assert(str_contains($auditRepository, 'INSERT INTO {$table}'), 'P10-011 audit repository exposes append insert');
sc_p10c_assert(! preg_match('/\bUPDATE\s+\{\$table\}|\bDELETE\s+FROM\s+\{\$table\}/i', $auditRepository), 'P10-011 audit repository exposes no update/delete mutation path');

// SC-P10-012 — RTL/accessibility release contracts across admin/mobile.
$mobileLayout = sc_p10c_source('mobile/lib/features/ui/mobile_layout.dart');
$mobileApp = sc_p10c_source('mobile/lib/app.dart');
$adminResponsive = sc_p10c_source('wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-responsive.css');
sc_p10c_assert(str_contains($mobileLayout, "normalized.startsWith('ar-')") && str_contains($mobileLayout, "normalized.startsWith('ar_')"), 'P10-012 regional Arabic locales resolve to RTL');
sc_p10c_assert(str_contains($mobileApp, "label: 'SafeContracts application'") && str_contains($mobileApp, "semanticsLabel: 'Loading SafeContracts session'"), 'P10-012 bootstrap exposes explicit accessibility semantics');
foreach (['[dir="rtl"]', '.rtl ', '@media (max-width: 782px)', 'overflow-x: auto', 'overflow-wrap: anywhere'] as $needle) {
    sc_p10c_assert(str_contains($adminResponsive, $needle), 'P10-012 admin responsive/RTL contract exists: ' . $needle);
}

// SC-P10-013 — explicit recovery manifest and non-destructive deactivation.
$tables = RecoveryManifest::tableSuffixes();
sc_p10c_assert(count($tables) === 18 && count(array_unique($tables)) === 18, 'P10-013 recovery manifest enumerates 18 unique SafeContracts tables');
foreach ([
    'safecontracts_contracts',
    'safecontracts_scheduled_payments',
    'safecontracts_payment_collections',
    'safecontracts_audit_log',
    'safecontracts_notification_deliveries',
    'safecontracts_import_runs',
] as $table) {
    sc_p10c_assert(in_array($table, $tables, true), 'P10-013 critical table is recoverable: ' . $table);
}
$options = RecoveryManifest::optionKeys();
foreach ([Migrator::VERSION_OPTION, 'safecontracts_general_settings', 'safecontracts_mobile_configuration', 'safecontracts_firebase_public_config', 'safecontracts_firebase_credential_reference'] as $option) {
    sc_p10c_assert(in_array($option, $options, true), 'P10-013 critical WordPress option is recoverable: ' . $option);
}
sc_p10c_assert(RecoveryManifest::userMetaKeys() === ['safecontracts_notification_read_ids'], 'P10-013 notification read state user meta is explicitly recoverable');
sc_p10c_assert(in_array('environment-secret-values-referenced-by-wordpress-options', RecoveryManifest::externalDependencies(), true), 'P10-013 secret values remain an external recovery dependency by reference');
$GLOBALS['sc_test_queries'] = [];
Deactivator::deactivate();
sc_p10c_assert($GLOBALS['sc_test_queries'] === [], 'P10-013 plugin deactivation remains non-destructive');
sc_p10c_assert(is_file(dirname(__DIR__, 2) . '/docs/RECOVERY_RUNBOOK.md'), 'P10-013 recovery runbook is version-controlled');

// SC-P10-014 — ordered/idempotent upgrade path and no downgrade/destructive SQL.
$GLOBALS['sc_test_options'][Migrator::VERSION_OPTION] = '1.8.0';
$GLOBALS['sc_test_dbdelta'] = [];
(new Migrator())->migrate();
sc_p10c_assert(($GLOBALS['sc_test_options'][Migrator::VERSION_OPTION] ?? '') === Migrator::LATEST_VERSION, 'P10-014 historical 1.8.0 schema upgrades to latest version');
sc_p10c_assert(count($GLOBALS['sc_test_dbdelta']) === 7, 'P10-014 upgrade from 1.8.0 applies only 1.9/1.10/1.11 schemas');
$upgradeSql = implode("\n", $GLOBALS['sc_test_dbdelta']);
foreach (['safecontracts_notification_rules', 'safecontracts_notification_deliveries', 'safecontracts_import_runs', 'safecontracts_import_errors'] as $table) {
    sc_p10c_assert(str_contains($upgradeSql, $table), 'P10-014 upgrade creates expected current schema: ' . $table);
}
$afterFirstUpgrade = count($GLOBALS['sc_test_dbdelta']);
(new Migrator())->maybeMigrate();
sc_p10c_assert(count($GLOBALS['sc_test_dbdelta']) === $afterFirstUpgrade, 'P10-014 current migration rerun is idempotent');
$GLOBALS['sc_test_options'][Migrator::VERSION_OPTION] = '2.0.0';
(new Migrator())->maybeMigrate();
sc_p10c_assert(($GLOBALS['sc_test_options'][Migrator::VERSION_OPTION] ?? '') === '2.0.0' && count($GLOBALS['sc_test_dbdelta']) === $afterFirstUpgrade, 'P10-014 migrator never attempts a destructive downgrade');
$migrationSources = '';
foreach (glob(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Database/Migrations/*.php') ?: [] as $file) {
    $migrationSources .= file_get_contents($file) ?: '';
}
sc_p10c_assert(! preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\s+TABLE\b/i', $migrationSources), 'P10-014 migration catalog contains no DROP/TRUNCATE table operation');

// SC-P10-015 — executable role/UAT baseline plus scenario catalog.
RoleRegistrar::registerDefaults();
$manager = $GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER] ?? null;
$accountant = $GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT] ?? null;
$viewer = $GLOBALS['sc_test_roles'][RoleRegistrar::VIEWER] ?? null;
$systemAdmin = $GLOBALS['sc_test_roles'][RoleRegistrar::SYSTEM_ADMIN] ?? null;
sc_p10c_assert($manager instanceof SC_Test_Role && ! empty($manager->capabilities[Capabilities::VIEW_ALL]) && ! empty($manager->capabilities[Capabilities::EDIT_CONTRACTS]) && ! empty($manager->capabilities[Capabilities::EXPORT_REPORTS]), 'P10-015 Manager UAT baseline has portfolio/edit/export grants');
sc_p10c_assert($accountant instanceof SC_Test_Role && ! empty($accountant->capabilities[Capabilities::VIEW_ASSIGNED]) && ! empty($accountant->capabilities[Capabilities::CREATE_CONTRACTS]) && empty($accountant->capabilities[Capabilities::EDIT_CONTRACTS]), 'P10-015 Accountant UAT baseline is assigned/create without default contract edit');
sc_p10c_assert($viewer instanceof SC_Test_Role && ! empty($viewer->capabilities[Capabilities::VIEW_ASSIGNED]) && ! empty($viewer->capabilities[Capabilities::VIEW_REPORTS]) && empty($viewer->capabilities[Capabilities::EXPORT_REPORTS]) && empty($viewer->capabilities[Capabilities::MANAGE_COLLECTIONS]), 'P10-015 Viewer UAT baseline remains read-only');
sc_p10c_assert($systemAdmin instanceof SC_Test_Role && count(array_filter($systemAdmin->capabilities)) === count(Capabilities::all()), 'P10-015 System Administrator baseline contains every SafeContracts capability');
$uat = sc_p10c_source('docs/UAT_V1.md');
foreach (['UAT-ADMIN-01', 'UAT-MANAGER-01', 'UAT-ACCOUNTANT-01', 'UAT-VIEWER-01', 'UAT-COLLECTION-01', 'UAT-NOTIFY-01', 'UAT-IMPORT-01', 'UAT-EXPORT-01', 'UAT-RECOVERY-01'] as $scenario) {
    sc_p10c_assert(str_contains($uat, $scenario), 'P10-015 UAT catalog includes measurable scenario: ' . $scenario);
}

// SC-P10-016 — production readiness is an executable CI gate, not a checklist only.
sc_p10c_assert(is_file(dirname(__DIR__, 2) . '/scripts/validate-release-readiness.py'), 'P10-016 release-readiness validator exists');
$qualityWorkflow = sc_p10c_source('.github/workflows/quality-gates.yml');
sc_p10c_assert(str_contains($qualityWorkflow, 'validate-release-readiness.py'), 'P10-016 repository Quality Gate invokes release-readiness validation');

printf("SafeContracts P10 release hardening SC-P10-011..016 passed (%d assertions).\n", $tests);
