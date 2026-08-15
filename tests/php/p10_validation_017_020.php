<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Rest\ApiAbuseGuard;
use SafeContracts\Rest\ApiListQuery;
use SafeContracts\Rest\ApiScope;
use SafeContracts\Rest\ExcelExportController;
use SafeContracts\Rest\RequestGuard;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p10d_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p10d_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p10d_assert($error instanceof $class, $message);
        return;
    }
    sc_p10d_assert(false, $message);
}
function sc_p10d_source(string $relative): string
{
    $path = dirname(__DIR__, 2) . '/' . $relative;
    $source = file_get_contents($path);
    sc_p10d_assert($source !== false, 'P10 validation source exists: ' . $relative);
    return $source === false ? '' : $source;
}

Router::register();

// SC-P10-017 — revalidate penetration controls against every current REST route.
$GLOBALS['sc_test_current_caps'] = [];
$denied = Router::canAccess();
sc_p10d_assert($denied instanceof WP_Error && ($denied->data['status'] ?? 0) === 403, 'P10-017 missing access/scope fails closed');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
$noScope = Router::canAccess();
sc_p10d_assert($noScope instanceof WP_Error && ($noScope->data['status'] ?? 0) === 403, 'P10-017 access capability without data scope remains forbidden');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
sc_p10d_assert(Router::canAccess() === true, 'P10-017 explicit assigned scope enables authenticated API access');
sc_p10d_expect(DomainException::class, static fn () => ApiScope::assertAccountant(99), 'P10-017 horizontal direct-object access is denied');

foreach ($GLOBALS['sc_test_routes'] as $route => $definition) {
    if ($route === Router::NAMESPACE . '/health') {
        sc_p10d_assert(($definition['permission_callback'] ?? null) === '__return_true', 'P10-017 health is the only intentionally public route');
        continue;
    }
    $definitions = isset($definition['permission_callback']) ? [$definition] : $definition;
    $sawPermission = false;
    foreach ($definitions as $candidate) {
        if (! is_array($candidate) || ! array_key_exists('permission_callback', $candidate)) {
            continue;
        }
        $sawPermission = true;
        sc_p10d_assert($candidate['permission_callback'] !== '__return_true', 'P10-017 protected route never uses public permission callback: ' . $route);
    }
    sc_p10d_assert($sawPermission, 'P10-017 protected route declares permission callback: ' . $route);
}

// SC-P10-018 — assigned-accountant scope remains authoritative across data/export/inbox surfaces.
sc_p10d_assert(ApiScope::mode() === 'assigned', 'P10-018 assigned mode is explicit without VIEW_ALL');
ApiScope::assertAccountant(42);
sc_p10d_assert(true, 'P10-018 own assigned resource is readable');
sc_p10d_expect(DomainException::class, static fn () => ApiScope::assertAccountant(null), 'P10-018 unassigned resource cannot be inferred into assigned scope');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;
sc_p10d_assert(ApiScope::mode() === 'all', 'P10-018 broader scope requires explicit VIEW_ALL');
ApiScope::assertAccountant(99);
sc_p10d_assert(true, 'P10-018 VIEW_ALL can cross assignment only after explicit grant');
unset($GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL]);
$notifications = sc_p10d_source('wordpress-plugin/safecontracts/src/Rest/NotificationsController.php');
sc_p10d_assert(str_contains($notifications, 'recentForUser(') && str_contains($notifications, 'hasSentForUser(') && str_contains($notifications, "'scope' => 'current_user'"), 'P10-018 notification inbox/read state remains current-user isolated');
$reportExport = sc_p10d_source('wordpress-plugin/safecontracts/src/Reports/ReportExportService.php');
sc_p10d_assert(str_contains($reportExport, 'current_user_can(Capabilities::VIEW_ALL)'), 'P10-018 export accountant filter widening is conditional on explicit VIEW_ALL');
$GLOBALS['sc_test_current_caps'][Capabilities::EXPORT_REPORTS] = true;
sc_p10d_assert(ExcelExportController::canExport() === true, 'P10-018 assigned user still needs explicit export grant');
unset($GLOBALS['sc_test_current_caps'][Capabilities::EXPORT_REPORTS]);
$exportDenied = ExcelExportController::canExport();
sc_p10d_assert($exportDenied instanceof WP_Error && ($exportDenied->data['status'] ?? 0) === 403, 'P10-018 normal SafeContracts access alone cannot export');

// SC-P10-019 — exact financial invariants stay backend-authoritative.
sc_p10d_assert(ContractMoney::add('0.1000', '0.2000') === '0.3000', 'P10-019 exact decimal addition remains regression-safe');
sc_p10d_assert(ContractMoney::subtract('10.0000', '3.3333') === '6.6667', 'P10-019 exact remaining-balance subtraction remains regression-safe');
sc_p10d_assert(ContractMoney::reconcile('100.0000', '25.0000', '2.0000', '7.5000') === '119.5000', 'P10-019 contract reconciliation remains exact');
sc_p10d_expect(InvalidArgumentException::class, static fn () => ContractMoney::subtract('1.0000', '1.0001'), 'P10-019 negative remaining balance fails closed');
$collection = sc_p10d_source('wordpress-plugin/safecontracts/src/Collections/CollectionService.php');
foreach (['beginTransaction()', 'lockPayment(', 'collectedTotal(', 'Collection amount exceeds the payment remaining balance', 'assertStoredIntegrity(', 'commitTransaction()', 'rollbackTransaction()'] as $needle) {
    sc_p10d_assert(str_contains($collection, $needle), 'P10-019 collection settlement keeps authoritative transaction/integrity guard: ' . $needle);
}
$mobileSources = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/mobile/lib', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'dart') {
        $mobileSources .= file_get_contents($file->getPathname()) ?: '';
    }
}
sc_p10d_assert(! str_contains($mobileSources, 'double.parse(') && ! str_contains($mobileSources, 'num.parse('), 'P10-019 mobile presentation layer does not parse authoritative money into floating-point values');

// SC-P10-020 — current REST surface keeps strict bounded input and generic failures.
sc_p10d_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['scope' => 'all']), ['page']), 'P10-020 unknown scope parameter cannot widen a route');
sc_p10d_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['page' => ['1', '2']]), ['page']), 'P10-020 parameter pollution fails closed');
sc_p10d_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['status' => str_repeat('x', ApiAbuseGuard::MAX_STRING_BYTES + 1)]), ['status']), 'P10-020 oversized scalar input fails closed');
sc_p10d_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['customer_id' => '-1']), ['customer_id'], ['id'], 'id'), 'P10-020 malformed negative IDs fail closed');
sc_p10d_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['page' => '6']), [], ['id'], 'id'), 'P10-020 list requests cannot exceed the bounded window');
sc_p10d_assert(str_contains($notifications, "boundedInt(\$params['page'] ?? 1, 1, 5") && str_contains($notifications, "boundedInt(\$params['per_page'] ?? 25, 1, 50"), 'P10-020 notification route applies explicit page/per-page bounds');
$failure = RequestGuard::failure(new RuntimeException('INTERNAL-DETAIL-MUST-NOT-LEAK'));
sc_p10d_assert($failure instanceof WP_Error && ($failure->data['status'] ?? 0) === 500, 'P10-020 internal exception maps to generic 500 envelope');
sc_p10d_assert(! str_contains($failure->message, 'INTERNAL-DETAIL-MUST-NOT-LEAK'), 'P10-020 generic internal error envelope does not leak exception details');

printf("SafeContracts P10 validation SC-P10-017..020 passed (%d assertions).\n", $tests);
