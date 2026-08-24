<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Rest\ApiAbuseGuard;
use SafeContracts\Rest\ApiListQuery;
use SafeContracts\Rest\ApiScope;
use SafeContracts\Rest\ExcelExportController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p10a_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p10a_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p10a_assert($error instanceof $class, $message);
        return;
    }
    sc_p10a_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();
Router::register();

// SC-P10-001 — Permission penetration tests.
$GLOBALS['sc_test_current_caps'] = [];
$denied = Router::canAccess();
sc_p10a_assert($denied instanceof WP_Error && ($denied->data['status'] ?? 0) === 403, 'P10-001 access fails closed without SafeContracts capabilities');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
$noScope = Router::canAccess();
sc_p10a_assert($noScope instanceof WP_Error && ($noScope->data['status'] ?? 0) === 403, 'P10-001 access capability without a data scope remains forbidden');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
sc_p10a_assert(Router::canAccess() === true, 'P10-001 assigned scope permits authenticated SafeContracts access');
sc_p10a_expect(DomainException::class, static fn () => ApiScope::assertAccountant(99), 'P10-001 direct-object horizontal accountant access is rejected');
ApiScope::assertAccountant(42);
sc_p10a_assert(true, 'P10-001 assigned accountant can access own resource');

// SC-P10-002 — Accountant-scope tests.
sc_p10a_assert(ApiScope::mode() === 'assigned', 'P10-002 assigned-only scope is explicit');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;
sc_p10a_assert(ApiScope::mode() === 'all', 'P10-002 view-all requires explicit server capability');
ApiScope::assertAccountant(99);
sc_p10a_assert(true, 'P10-002 view-all can cross accountant assignment only when capability is granted');
unset($GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL]);

// Export must independently require export permission in addition to normal access/scope.
$exportDenied = ExcelExportController::canExport();
sc_p10a_assert($exportDenied instanceof WP_Error && ($exportDenied->data['status'] ?? 0) === 403, 'P10-002 report export does not inherit access capability as export permission');
$GLOBALS['sc_test_current_caps'][Capabilities::EXPORT_REPORTS] = true;
sc_p10a_assert(ExcelExportController::canExport() === true, 'P10-002 export becomes available only with explicit export capability');

// SC-P10-003 — Financial regression tests: exact fixed-point semantics, never float arithmetic.
sc_p10a_assert(ContractMoney::normalizeNonNegative('00012.3') === '12.3000', 'P10-003 financial normalization is exact to four decimals');
sc_p10a_assert(ContractMoney::add('0.1000', '0.2000') === '0.3000', 'P10-003 decimal addition avoids binary float drift');
sc_p10a_assert(ContractMoney::subtract('100.0000', '37.1250') === '62.8750', 'P10-003 remaining-value subtraction stays exact');
sc_p10a_assert(ContractMoney::reconcile('100.0000', '20.0000', '5.5000', '10.2500') === '115.2500', 'P10-003 net-value reconciliation is deterministic');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ContractMoney::normalizeNonNegative('1.00001'), 'P10-003 amounts above four-decimal scale fail closed');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ContractMoney::subtract('1.0000', '2.0000'), 'P10-003 negative remaining balances cannot be produced by subtraction');
$moneySource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Contracts/ContractMoney.php') ?: '';
sc_p10a_assert(! str_contains($moneySource, 'floatval(') && ! str_contains($moneySource, '(float)'), 'P10-003 authoritative money helper contains no float conversion');

// SC-P10-004 — API security tests.
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['scope' => 'all']), ['customer_id']), 'P10-004 unknown query parameters cannot widen scope');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['customer_id' => ['7', '8']]), ['customer_id']), 'P10-004 parameter pollution arrays are rejected');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(new WP_REST_Request(['status' => str_repeat('x', ApiAbuseGuard::MAX_STRING_BYTES + 1)]), ['status']), 'P10-004 oversized query values are rejected');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['sort' => 'password']), [], ['id'], 'id'), 'P10-004 unsupported sort fields fail closed');
$deepPage = ApiListQuery::parse(new WP_REST_Request(['page' => '6', 'per_page' => '100']), [], ['id'], 'id');
sc_p10a_assert(($deepPage['page'] ?? 0) === 6 && ($deepPage['per_page'] ?? 0) === 100, 'P10-004 deep pages remain available inside the bounded query-offset window');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['page' => '10002', 'per_page' => '100']), [], ['id'], 'id'), 'P10-004 reads cannot exceed the bounded server query-offset window');
sc_p10a_assert(ApiListQuery::BOUNDED_WINDOW === 500, 'P10-004 legacy materialized list reads retain a hard bounded backend window');

// SC-P10-005 — Input validation review.
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['customer_id' => '-1']), ['customer_id'], ['id'], 'id'), 'P10-005 negative identifiers are rejected');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['due_from' => '2026-02-31']), ['due_from'], ['id'], 'id'), 'P10-005 impossible dates are rejected');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['status' => 'root']), ['status'], ['id'], 'id'), 'P10-005 unsupported status values are rejected');
sc_p10a_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(new WP_REST_Request(['per_page' => '101']), [], ['id'], 'id'), 'P10-005 pagination bounds reject oversized requests');

$apiSources = '';
foreach (glob(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/*.php') ?: [] as $file) {
    $apiSources .= file_get_contents($file) ?: '';
}
foreach (['private_key', 'service_account_json', 'database_password'] as $secretField) {
    sc_p10a_assert(! str_contains(strtolower($apiSources), $secretField), 'P10-004 REST surface does not expose secret contract: ' . $secretField);
}

printf("SafeContracts P10 security/financial SC-P10-001..005 passed (%d assertions).\n", $tests);
