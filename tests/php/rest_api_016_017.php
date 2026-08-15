<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\ApiAbuseGuard;
use SafeContracts\Rest\ApiListQuery;
use SafeContracts\Rest\DataController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p8_016_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p8_016_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p8_016_assert($error instanceof $class, $message);
        return;
    }
    sc_p8_016_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();
Router::register();

// SC-P8-016 — bounded pagination/filter/sort contract.
sc_p8_016_assert(ApiListQuery::BOUNDED_WINDOW === 500, 'SC-P8-016 list reads retain a hard 500-row window');
$query = ApiListQuery::parse(
    new WP_REST_Request([
        'customer_id' => '7',
        'status' => 'overdue',
        'page' => '2',
        'per_page' => '25',
        'sort' => 'remaining_amount',
        'order' => 'DESC',
    ]),
    ['customer_id', 'status'],
    ['due_date', 'remaining_amount'],
    'due_date'
);
sc_p8_016_assert($query['page'] === 2 && $query['per_page'] === 25, 'SC-P8-016 pagination is normalized and bounded');
sc_p8_016_assert($query['sort'] === 'remaining_amount' && $query['order'] === 'desc', 'SC-P8-016 sort/order are normalized through endpoint allow-lists');
sc_p8_016_assert(($query['filters']['customer_id'] ?? 0) === 7 && ($query['filters']['status'] ?? '') === 'overdue', 'SC-P8-016 existing server-side filters remain authoritative');

sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(
    new WP_REST_Request(['sort' => 'password']), [], ['id'], 'id'
), 'SC-P8-016 unknown sort field is rejected');
sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(
    new WP_REST_Request(['order' => 'drop table']), [], ['id'], 'id'
), 'SC-P8-016 invalid order token is rejected');
sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(
    new WP_REST_Request(['page' => '6']), [], ['id'], 'id'
), 'SC-P8-016 page cannot exceed bounded read window');
sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiListQuery::parse(
    new WP_REST_Request(['per_page' => '101']), [], ['id'], 'id'
), 'SC-P8-016 per_page cannot exceed 100');

$rows = [
    ['id' => 3, 'name' => 'beta'],
    ['id' => 2, 'name' => 'Alpha'],
    ['id' => 1, 'name' => 'alpha'],
];
$sorted = ApiListQuery::sortRows($rows, 'name', 'asc');
sc_p8_016_assert(array_column($sorted, 'id') === [1, 2, 3], 'SC-P8-016 stable sort uses deterministic ID tie-breaker');
$sortedDesc = ApiListQuery::sortRows($rows, 'name', 'desc');
sc_p8_016_assert(array_column($sortedDesc, 'id') === [3, 2, 1], 'SC-P8-016 descending sort reverses primary and deterministic tie-break ordering');

// SC-P8-017 — abuse/security guard.
sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(
    new WP_REST_Request(['include_secret' => '1']), ['customer_id']
), 'SC-P8-017 unknown query parameters fail closed');
sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(
    new WP_REST_Request(['customer_id' => ['7', '8']]), ['customer_id']
), 'SC-P8-017 parameter pollution arrays fail closed');
sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(
    new WP_REST_Request(['customer_id' => true]), ['customer_id']
), 'SC-P8-017 boolean type confusion fails closed');
sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(
    new WP_REST_Request(['status' => str_repeat('x', ApiAbuseGuard::MAX_STRING_BYTES + 1)]), ['status']
), 'SC-P8-017 oversized scalar parameter fails closed');
$many = [];
$allowed = [];
for ($i = 0; $i < ApiAbuseGuard::MAX_QUERY_PARAMS + 1; $i++) {
    $key = 'p' . $i;
    $many[$key] = '1';
    $allowed[] = $key;
}
sc_p8_016_expect(InvalidArgumentException::class, static fn () => ApiAbuseGuard::safeParams(
    new WP_REST_Request($many), $allowed
), 'SC-P8-017 excessive parameter count fails closed before business logic');

// Integration: customer list supports deterministic sorting/paging and endpoint-specific filters.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '3', 'internal_code' => null, 'name' => 'Zulu', 'contact_name' => '', 'email' => '', 'phone' => '', 'notes' => 'hidden', 'is_active' => '1'],
    ['id' => '1', 'internal_code' => null, 'name' => 'Alpha', 'contact_name' => '', 'email' => '', 'phone' => '', 'notes' => 'hidden', 'is_active' => '1'],
    ['id' => '2', 'internal_code' => null, 'name' => 'Beta', 'contact_name' => '', 'email' => '', 'phone' => '', 'notes' => 'hidden', 'is_active' => '1'],
]];
$customers = DataController::customers(new WP_REST_Request(['sort' => 'name', 'order' => 'asc', 'page' => '2', 'per_page' => '1']));
sc_p8_016_assert($customers instanceof WP_REST_Response && $customers->status === 200, 'SC-P8-016 sorted customer list returns normal REST response');
sc_p8_016_assert(($customers->data['data'][0]['name'] ?? '') === 'Beta', 'SC-P8-016 pagination is applied after deterministic scoped sorting');
sc_p8_016_assert(($customers->data['meta']['sort'] ?? '') === 'name' && ($customers->data['meta']['order'] ?? '') === 'asc', 'SC-P8-016 response metadata reports effective sorting');
sc_p8_016_assert(($customers->data['meta']['bounded_window'] ?? 0) === 500 && ($customers->data['meta']['has_more'] ?? false) === true, 'SC-P8-016 response exposes bounded-window paging evidence');
sc_p8_016_assert(! array_key_exists('notes', $customers->data['data'][0]), 'SC-P8-017 sorting does not widen safe field projection');

$badCustomerFilter = DataController::customers(new WP_REST_Request(['due_from' => '2026-08-01']));
sc_p8_016_assert($badCustomerFilter instanceof WP_Error && ($badCustomerFilter->data['status'] ?? 0) === 422, 'SC-P8-017 irrelevant endpoint filter is rejected instead of ignored');
$badContractOption = DataController::contractOptions(new WP_REST_Request(['sort' => 'contract_number']));
sc_p8_016_assert($badContractOption instanceof WP_Error && ($badContractOption->data['status'] ?? 0) === 422, 'SC-P8-017 dependent lookup rejects unsupported query surface');

foreach (['/customers', '/contracts', '/payments', '/collections', '/followups'] as $route) {
    $definition = $GLOBALS['sc_test_routes'][Router::NAMESPACE . $route] ?? [];
    sc_p8_016_assert(($definition['methods'] ?? null) === WP_REST_Server::READABLE, "SC-P8-017 {$route} remains read-only");
}

$dataSource = file_get_contents((string) (new ReflectionClass(DataController::class))->getFileName()) ?: '';
$listSource = file_get_contents((string) (new ReflectionClass(ApiListQuery::class))->getFileName()) ?: '';
$guardSource = file_get_contents((string) (new ReflectionClass(ApiAbuseGuard::class))->getFileName()) ?: '';
sc_p8_016_assert(! str_contains($dataSource, '$wpdb') && ! str_contains($listSource, '$wpdb') && ! str_contains($guardSource, '$wpdb'), 'SC-P8-016/017 REST hardening adds no presentation-layer SQL');
foreach (['password', 'private_key', 'service_account', 'access_token'] as $secret) {
    sc_p8_016_assert(! str_contains(strtolower($dataSource . $listSource . $guardSource), $secret), 'SC-P8-017 hardening source contains no secret field contract: ' . $secret);
}

printf("SafeContracts P8 REST SC-P8-016..017 passed (%d assertions).\n", $tests);
