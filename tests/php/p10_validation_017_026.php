<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Notifications\NotificationEngine;
use SafeContracts\Notifications\PushDeliveryService;
use SafeContracts\Notifications\PushTransport;
use SafeContracts\Rest\ApiAbuseGuard;
use SafeContracts\Rest\ApiListQuery;
use SafeContracts\Rest\ApiScope;
use SafeContracts\Rest\ExcelExportController;
use SafeContracts\Rest\RequestGuard;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p10v_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p10v_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p10v_assert($error instanceof $class, $message);
        return;
    }
    sc_p10v_assert(false, $message);
}
function sc_p10v_source(string $relative): string
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    sc_p10v_assert($source !== false, 'P10 validation source exists: ' . $relative);
    return $source === false ? '' : $source;
}

final class SC_P10_ValidationPushTransport implements PushTransport
{
    public function send(string $token, array $payload): array
    {
        unset($token, $payload);
        return ['success' => true, 'status_code' => 200, 'error_code' => null];
    }
}

SafeContracts\Plugin::instance()->boot();
Router::register();

// SC-P10-017 — Permission penetration tests — Validate.
$GLOBALS['sc_test_current_caps'] = [];
$denied = Router::canAccess();
sc_p10v_assert($denied instanceof WP_Error && ($denied->data['status'] ?? 0) === 403, 'P10-017 missing SafeContracts access fails closed');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
$noScope = Router::canAccess();
sc_p10v_assert($noScope instanceof WP_Error && ($noScope->data['status'] ?? 0) === 403, 'P10-017 access without data scope remains forbidden');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
sc_p10v_assert(Router::canAccess() === true, 'P10-017 explicit assigned scope authorizes normal API access');
sc_p10v_expect(DomainException::class, static fn () => ApiScope::assertAccountant(99), 'P10-017 horizontal accountant direct-object access is denied');
foreach ($GLOBALS['sc_test_routes'] as $route => $definition) {
    if ($route === Router::NAMESPACE . '/health') {
        sc_p10v_assert(($definition['permission_callback'] ?? null) === '__return_true', 'P10-017 health is the only intentionally public REST route');
        continue;
    }
    $definitions = isset($definition['permission_callback']) ? [$definition] : $definition;
    $sawPermission = false;
    foreach ($definitions as $candidate) {
        if (! is_array($candidate) || ! array_key_exists('permission_callback', $candidate)) {
            continue;
        }
        $sawPermission = true;
        sc_p10v_assert($candidate['permission_callback'] !== '__return_true', 'P10-017 protected route cannot use a public permission callback: ' . $route);
    }
    sc_p10v_assert($sawPermission, 'P10-017 protected route declares an authorization callback: ' . $route);
}

// SC-P10-018 — Accountant-scope tests — Validate.
sc_p10v_assert(ApiScope::mode() === 'assigned', 'P10-018 assigned-only mode remains explicit');
ApiScope::assertAccountant(42);
sc_p10v_assert(true, 'P10-018 assigned accountant can access own resource');
sc_p10v_expect(DomainException::class, static fn () => ApiScope::assertAccountant(null), 'P10-018 unassigned resource cannot silently enter assigned scope');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;
sc_p10v_assert(ApiScope::mode() === 'all', 'P10-018 broad scope requires explicit VIEW_ALL');
ApiScope::assertAccountant(99);
sc_p10v_assert(true, 'P10-018 cross-accountant read is possible only after explicit VIEW_ALL');
unset($GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL]);
$notificationsController = sc_p10v_source('wordpress-plugin/safecontracts/src/Rest/NotificationsController.php');
sc_p10v_assert(str_contains($notificationsController, 'recentForUser(') && str_contains($notificationsController, 'hasSentForUser(') && str_contains($notificationsController, "'scope' => 'current_user'"), 'P10-018 notification inbox and read mutation remain current-user scoped');
$exportSource = sc_p10v_source('wordpress-plugin/safecontracts/src/Reports/ReportExportService.php');
sc_p10v_assert(str_contains($exportSource, 'current_user_can(Capabilities::VIEW_ALL)'), 'P10-018 export accountant filter widening remains conditional on VIEW_ALL');

// SC-P10-019 — Financial regression tests — Validate.
sc_p10v_assert(ContractMoney::normalizeNonNegative('00012.3') === '12.3000', 'P10-019 exact financial normalization remains four-decimal');
sc_p10v_assert(ContractMoney::add('0.1000', '0.2000') === '0.3000', 'P10-019 exact addition avoids float drift');
sc_p10v_assert(ContractMoney::subtract('10.0000', '3.3333') === '6.6667', 'P10-019 exact remaining-balance subtraction remains deterministic');
sc_p10v_assert(ContractMoney::reconcile('100.0000', '25.0000', '2.0000', '7.5000') === '119.5000', 'P10-019 contract reconciliation remains exact');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ContractMoney::subtract('1.0000', '1.0001'), 'P10-019 negative remaining balance cannot be produced');
$collectionSource = sc_p10v_source('wordpress-plugin/safecontracts/src/Collections/CollectionService.php');
foreach (['beginTransaction()', 'lockPayment(', 'collectedTotal(', 'assertStoredIntegrity(', 'Collection amount exceeds the payment remaining balance', 'rollbackTransaction()'] as $needle) {
    sc_p10v_assert(str_contains($collectionSource, $needle), 'P10-019 collection path retains authoritative financial guard: ' . $needle);
}
$mobileSources = '';
$mobileRoot = dirname(__DIR__, 2) . '/mobile/lib';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mobileRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'dart') {
        $mobileSources .= file_get_contents($file->getPathname()) ?: '';
    }
}
sc_p10v_assert(! str_contains($mobileSources, 'double.parse(') && ! str_contains($mobileSources, 'num.parse('), 'P10-019 mobile presentation does not convert authoritative money into floating point');

// SC-P10-020 — API security tests — Validate.
sc_p10v_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['scope' => 'all']), ['customer_id']), 'P10-020 unknown query field cannot widen scope');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['customer_id' => ['7', '8']]), ['customer_id']), 'P10-020 parameter pollution is rejected');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['status' => str_repeat('x', ApiAbuseGuard::MAX_STRING_BYTES + 1)]), ['status']), 'P10-020 oversized scalar input is rejected');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['page' => '6']), [], ['id'], 'id'), 'P10-020 list requests cannot escape bounded backend window');
$genericFailure = RequestGuard::failure(new RuntimeException('INTERNAL-DETAIL-MUST-NOT-LEAK'));
sc_p10v_assert($genericFailure instanceof WP_Error && ($genericFailure->data['status'] ?? 0) === 500, 'P10-020 internal exception maps to generic server envelope');
sc_p10v_assert(! str_contains($genericFailure->message, 'INTERNAL-DETAIL-MUST-NOT-LEAK'), 'P10-020 internal exception details are not exposed');

// SC-P10-021 — Input validation review — Validate.
sc_p10v_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['customer_id' => '-1']), ['customer_id'], ['id'], 'id'), 'P10-021 negative identifiers fail validation');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['due_from' => '2026-02-31']), ['due_from'], ['id'], 'id'), 'P10-021 impossible calendar dates fail validation');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['status' => 'root']), ['status'], ['id'], 'id'), 'P10-021 unsupported status tokens fail validation');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['per_page' => '101']), [], ['id'], 'id'), 'P10-021 oversized pagination fails validation');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ContractMoney::normalizeNonNegative('-1.0000'), 'P10-021 negative decimal strings fail validation');
sc_p10v_expect(InvalidArgumentException::class, static fn () => ContractMoney::normalizeNonNegative('1.00001'), 'P10-021 over-scale decimal strings fail validation');
$contractService = sc_p10v_source('wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$paymentService = sc_p10v_source('wordpress-plugin/safecontracts/src/Payments/PaymentService.php');
sc_p10v_assert(str_contains($contractService, 'must not exceed 100 characters') && str_contains($contractService, 'must not exceed 191 characters'), 'P10-021 contract number/description lengths remain bounded');
sc_p10v_assert(str_contains($paymentService, 'must not exceed 100 characters') && str_contains($paymentService, 'valid calendar date'), 'P10-021 payment reference/date validation remains explicit');

// SC-P10-022 — Database/index performance — Validate.
$indexContracts = [
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php', 'KEY accountant_status (accountant_user_id, status, is_archived)'],
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php', 'KEY customer_status (customer_id, status, is_archived)'],
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0007Payments.php', 'KEY contract_status_due (contract_id, status, due_date)'],
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0007Payments.php', 'KEY due_status (due_date, status)'],
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0008Collections.php', 'KEY payment_date (payment_id, collection_date, id)'],
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0009FollowupAudit.php', 'KEY payment_timeline (payment_id, created_at, id)'],
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0011NotificationDelivery.php', 'KEY retry_lookup (status, scheduled_for, attempt_no)'],
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0011NotificationDelivery.php', 'KEY user_active (user_id, is_active)'],
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0012Import.php', 'KEY status_created (status, created_at, id)'],
    // ROW_NUMBER is reserved by MySQL 8. Preserve the exact index order while
    // validating the required quoted identifier used by the real runtime.
    ['wordpress-plugin/safecontracts/src/Database/Migrations/Migration0012Import.php', 'KEY run_row (import_run_id, `row_number`, id)'],
];
$indexSources = [];
foreach ($indexContracts as [$file, $needle]) {
    $indexSources[$file] ??= sc_p10v_source($file);
    sc_p10v_assert(str_contains($indexSources[$file], $needle), 'P10-022 required high-frequency index remains present: ' . $needle);
}
sc_p10v_assert(ApiListQuery::BOUNDED_WINDOW === 500, 'P10-022 REST list processing remains bounded to 500 rows');

// SC-P10-023 — Notification reliability — Validate.
$engine = new NotificationEngine();
$settledPlan = $engine->plan(
    ['id' => 3, 'code' => 'due', 'trigger_type' => 'due_day', 'recipient_roles' => ['manager']],
    ['id' => 91, 'status' => 'paid', 'remaining_amount' => '0.0000'],
    new DateTimeImmutable('2026-08-15')
);
sc_p10v_assert($settledPlan === null, 'P10-023 settled payment is suppressed before delivery work');
$engineSource = sc_p10v_source('wordpress-plugin/safecontracts/src/Notifications/NotificationEngine.php');
$deliveryLogSource = sc_p10v_source('wordpress-plugin/safecontracts/src/Notifications/DeliveryLogRepository.php');
$delivery = new PushDeliveryService(new SC_P10_ValidationPushTransport());
sc_p10v_assert($delivery->retryDelaySeconds(0) === 60 && $delivery->retryDelaySeconds(1) === 120 && $delivery->retryDelaySeconds(2) === 240, 'P10-023 retry backoff remains deterministic');
sc_p10v_assert(! $delivery->canRetry(3) && $delivery->retryDelaySeconds(3) === 0, 'P10-023 retries terminate after bounded policy');
sc_p10v_assert(str_contains($engineSource, 'array_unique'), 'P10-023 recipient escalation remains deduplicated');
sc_p10v_assert(str_contains($deliveryLogSource, 'attempt_no') && str_contains($deliveryLogSource, 'status'), 'P10-023 every delivery log records attempt and result state');

// SC-P10-024 — Firebase delivery verification — Validate.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::MANAGE_SYSTEM => true];
$firebase = new FirebaseSettings();
sc_p10v_expect(InvalidArgumentException::class, static fn () => $firebase->saveCredentialReference('{"private_key":"secret"}'), 'P10-024 raw Firebase credential JSON cannot enter WordPress settings');
sc_p10v_assert($firebase->saveCredentialReference('SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT') === 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT', 'P10-024 Firebase stores only a secret reference identifier');
$firebaseSummary = $firebase->safeSummary();
sc_p10v_assert(($firebaseSummary['configured'] ?? false) === true && ! array_key_exists('credential_reference', $firebaseSummary), 'P10-024 safe Firebase summary exposes no credential reference/value');
$mobileConfigSource = sc_p10v_source('wordpress-plugin/safecontracts/src/Rest/MobileConfigController.php');
sc_p10v_assert(! str_contains(strtolower($mobileConfigSource), 'private_key') && ! str_contains(strtolower($mobileConfigSource), 'credential_reference'), 'P10-024 mobile config cannot expose Firebase server credentials');
$validPlan = [
    'rule_id' => 9,
    'payment_id' => 7001,
    'recipient_ids' => [],
    'template_code' => 'payment_due_today',
    'scheduled_for' => '2026-08-15',
    'payload' => ['title' => 'Payment due', 'body' => 'Payment is due.', 'data' => ['payment_id' => 7001, 'rule_code' => 'due_today', 'attempt_no' => 0]],
];
$result = $delivery->deliver($validPlan, 0);
sc_p10v_assert($result['attempted'] === 0 && $result['failed'] === 0, 'P10-024 whitelisted internal push metadata passes without devices');
$externalPlan = $validPlan;
$externalPlan['payload']['data'] = ['external_url' => 'https://evil.example'];
sc_p10v_expect(InvalidArgumentException::class, static fn () => $delivery->deliver($externalPlan, 0), 'P10-024 external URL metadata fails closed before Firebase delivery');
$stringIdPlan = $validPlan;
$stringIdPlan['payload']['data'] = ['payment_id' => '7001'];
sc_p10v_expect(InvalidArgumentException::class, static fn () => $delivery->deliver($stringIdPlan, 0), 'P10-024 payment deep-link metadata must retain positive integer type');

// SC-P10-025 — Import verification — Validate.
$importExecution = sc_p10v_source('wordpress-plugin/safecontracts/src/Import/ImportExecutionService.php');
$importRuns = sc_p10v_source('wordpress-plugin/safecontracts/src/Import/ImportRunRepository.php');
foreach ([
    "['mapped', 'validated']",
    'Completed, running and failed runs are terminal',
    'if ($validationErrorRows !== [])',
    'START TRANSACTION',
    'ROLLBACK',
    'COMMIT',
    'Existing payment amount cannot be changed by import update',
    'Existing payment reference cannot be changed by import update',
] as $needle) {
    sc_p10v_assert(str_contains($importExecution, $needle), 'P10-025 import safety contract remains present: ' . $needle);
}
sc_p10v_assert(str_contains($importRuns, '$wpdb->prepare'), 'P10-025 import dynamic persistence remains prepared');

// SC-P10-026 — Excel export verification — Validate.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$exportDenied = ExcelExportController::canExport();
sc_p10v_assert($exportDenied instanceof WP_Error && ($exportDenied->data['status'] ?? 0) === 403, 'P10-026 export remains forbidden without explicit export capability');
$GLOBALS['sc_test_current_caps'][Capabilities::EXPORT_REPORTS] = true;
sc_p10v_assert(ExcelExportController::canExport() === true, 'P10-026 assigned user with explicit export capability may request server export');
$exportController = sc_p10v_source('wordpress-plugin/safecontracts/src/Rest/ExcelExportController.php');
foreach (['DashboardFilters::normalize($input)', 'current_user_can(Capabilities::VIEW_ALL)', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] as $needle) {
    sc_p10v_assert(str_contains($exportSource, $needle), 'P10-026 server export contract remains scoped/stable: ' . $needle);
}
sc_p10v_assert(str_contains($exportSource, 'queue(500,') && str_contains($exportSource, "\$filters['date_from']") && str_contains($exportSource, "\$filters['date_to']"), 'P10-026 follow-up export remains bounded to 500 rows while forwarding the normalized server-side period');
sc_p10v_assert(str_contains($exportController, "'encoding' => 'base64'"), 'P10-026 REST export declares transport encoding');
foreach (['password', 'private_key', 'service_account'] as $secretField) {
    sc_p10v_assert(! str_contains(strtolower($exportSource . $exportController), $secretField), 'P10-026 export surface contains no secret field contract: ' . $secretField);
}

printf("SafeContracts P10 validation SC-P10-017..026 passed (%d assertions).\n", $tests);