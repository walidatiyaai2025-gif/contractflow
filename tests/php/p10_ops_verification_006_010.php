<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Notifications\NotificationEngine;
use SafeContracts\Rest\ExcelExportController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p10b_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p10b_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p10b_assert($error instanceof $class, $message);
        return;
    }
    sc_p10b_assert(false, $message);
}
function sc_p10b_source(string $relative): string
{
    $path = dirname(__DIR__, 2) . '/' . $relative;
    $source = file_get_contents($path);
    sc_p10b_assert($source !== false, 'P10 verification source exists: ' . $relative);
    return $source === false ? '' : $source;
}

SafeContracts\Plugin::instance()->boot();
Router::register();

// SC-P10-006 — Database/index performance.
$contractsMigration = sc_p10b_source('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php');
$paymentsMigration = sc_p10b_source('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0007Payments.php');
$collectionsMigration = sc_p10b_source('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0008Collections.php');
$followupMigration = sc_p10b_source('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0009FollowupAudit.php');
$notificationMigration = sc_p10b_source('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0011NotificationDelivery.php');
$importMigration = sc_p10b_source('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0012Import.php');
foreach ([
    [$contractsMigration, 'KEY accountant_status (accountant_user_id, status, is_archived)'],
    [$contractsMigration, 'KEY customer_status (customer_id, status, is_archived)'],
    [$paymentsMigration, 'KEY contract_status_due (contract_id, status, due_date)'],
    [$paymentsMigration, 'KEY due_status (due_date, status)'],
    [$collectionsMigration, 'KEY payment_date (payment_id, collection_date, id)'],
    [$followupMigration, 'KEY payment_timeline (payment_id, created_at, id)'],
    [$notificationMigration, 'KEY retry_lookup (status, scheduled_for, attempt_no)'],
    [$notificationMigration, 'KEY user_active (user_id, is_active)'],
    [$importMigration, 'KEY status_created (status, created_at, id)'],
    // ROW_NUMBER is reserved by MySQL 8. The import migration must quote the
    // historical identifier while preserving the exact high-frequency index
    // column order and therefore the P10-006 performance contract.
    [$importMigration, 'KEY run_row (import_run_id, `row_number`, id)'],
] as [$source, $needle]) {
    sc_p10b_assert(str_contains($source, $needle), 'P10-006 high-frequency scoped/index path is explicitly indexed: ' . $needle);
}

// SC-P10-007 — Notification reliability.
$engine = new NotificationEngine();
$settled = $engine->plan(
    ['id' => 3, 'code' => 'due', 'trigger_type' => 'due_day', 'recipient_roles' => ['manager']],
    ['id' => 91, 'status' => 'paid', 'remaining_amount' => '0.0000'],
    new DateTimeImmutable('2026-08-15')
);
sc_p10b_assert($settled === null, 'P10-007 settled payments are suppressed before notification delivery');
$engineSource = sc_p10b_source('wordpress-plugin/safecontracts/src/Notifications/NotificationEngine.php');
$deliverySource = sc_p10b_source('wordpress-plugin/safecontracts/src/Notifications/DeliveryLogRepository.php');
sc_p10b_assert(str_contains($engineSource, "safecontracts_notification_suppressed"), 'P10-007 suppression is observable through an audit/event hook');
sc_p10b_assert(str_contains($engineSource, 'array_unique'), 'P10-007 recipient escalation is deduplicated');
sc_p10b_assert(str_contains($deliverySource, 'attempt_no'), 'P10-007 delivery log persists retry attempt number');
sc_p10b_assert(str_contains($deliverySource, 'status'), 'P10-007 delivery log persists explicit delivery status');

// SC-P10-008 — Firebase delivery verification.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::MANAGE_SYSTEM => true];
$settings = new FirebaseSettings();
sc_p10b_expect(InvalidArgumentException::class, static fn () => $settings->saveCredentialReference('{"private_key":"secret"}'), 'P10-008 secret JSON cannot be stored as Firebase credential reference');
sc_p10b_assert($settings->saveCredentialReference('SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT') === 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT', 'P10-008 backend stores only an environment/secret identifier');
$summary = $settings->safeSummary();
sc_p10b_assert(($summary['configured'] ?? false) === true && ! array_key_exists('credential_reference', $summary), 'P10-008 safe Firebase summary exposes configuration state without credential reference');
$firebaseTransport = sc_p10b_source('wordpress-plugin/safecontracts/src/Notifications/FirebasePushTransport.php');
$mobileConfig = sc_p10b_source('wordpress-plugin/safecontracts/src/Rest/MobileConfigController.php');
sc_p10b_assert(str_contains($firebaseTransport, 'fcm.googleapis.com'), 'P10-008 Firebase transport is an outbound delivery adapter');
sc_p10b_assert(! str_contains(strtolower($mobileConfig), 'private_key') && ! str_contains(strtolower($mobileConfig), 'credential_reference'), 'P10-008 mobile config never exposes Firebase server credentials');

// SC-P10-009 — Import verification.
$importExecution = sc_p10b_source('wordpress-plugin/safecontracts/src/Import/ImportExecutionService.php');
$importRuns = sc_p10b_source('wordpress-plugin/safecontracts/src/Import/ImportRunRepository.php');
sc_p10b_assert(str_contains($importExecution, "['mapped', 'validated']"), 'P10-009 only mapped/validated import runs may execute');
sc_p10b_assert(str_contains($importExecution, 'Completed, running and failed runs are terminal'), 'P10-009 terminal import runs explicitly fail closed');
sc_p10b_assert(str_contains($importExecution, 'if ($validationErrorRows !== [])'), 'P10-009 all row validation completes before business writes');
sc_p10b_assert(str_contains($importExecution, "START TRANSACTION") && str_contains($importExecution, "ROLLBACK") && str_contains($importExecution, "COMMIT"), 'P10-009 each candidate business write is transaction protected');
sc_p10b_assert(str_contains($importExecution, 'Existing payment amount cannot be changed by import update'), 'P10-009 import update cannot rewrite authoritative payment amount');
sc_p10b_assert(str_contains($importRuns, '$wpdb->prepare'), 'P10-009 import repository persists dynamic values through prepared SQL');

// SC-P10-010 — Excel export verification.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$exportDenied = ExcelExportController::canExport();
sc_p10b_expect(WP_Error::class, static fn () => null, '');
$exportDenied = ExcelExportController::canExport();
sc_p10b_assert($exportDenied instanceof WP_Error && ($exportDenied->data['status'] ?? 0) === 403, 'P10-010 export is denied without explicit export capability');
$GLOBALS['sc_test_current_caps'][Capabilities::EXPORT_REPORTS] = true;
sc_p10b_assert(ExcelExportController::canExport() === true, 'P10-010 authorized scoped user can request report export');
$exportSource = sc_p10b_source('wordpress-plugin/safecontracts/src/Reports/ReportExportService.php');
$controllerSource = sc_p10b_source('wordpress-plugin/safecontracts/src/Rest/ExcelExportController.php');
sc_p10b_assert(str_contains($exportSource, 'DashboardFilters::normalize($input)'), 'P10-010 export normalizes the same server-side dashboard filter contract');
sc_p10b_assert(str_contains($exportSource, 'queue(500,') && str_contains($exportSource, "\$filters['date_from']") && str_contains($exportSource, "\$filters['date_to']"), 'P10-010 follow-up export retains a bounded server-side read and forwards the normalized display period');
sc_p10b_assert(str_contains($exportSource, "current_user_can(Capabilities::VIEW_ALL)"), 'P10-010 accountant filter widening is conditional on explicit view-all capability');
sc_p10b_assert(str_contains($exportSource, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"), 'P10-010 server produces a stable XLSX content type');
sc_p10b_assert(str_contains($controllerSource, "'encoding' => 'base64'"), 'P10-010 REST download wraps server-generated workbook in explicit transport encoding');
foreach (['password', 'private_key', 'service_account'] as $secret) {
    sc_p10b_assert(! str_contains(strtolower($exportSource . $controllerSource), $secret), 'P10-010 export contract contains no secret field: ' . $secret);
}

printf("SafeContracts P10 operational verification SC-P10-006..010 passed (%d assertions).\n", $tests);
