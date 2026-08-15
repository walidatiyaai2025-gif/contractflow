<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\ApiRequest;
use SafeContracts\Rest\DataController;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p8v_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p8v_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p8v_assert($error instanceof $class, $message);
        return;
    }
    sc_p8v_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();
$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = true;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;

// SC-P8-016 — pagination is bounded without an artificial five-page ceiling.
$page = ApiRequest::pagination(new WP_REST_Request(['page' => '999', 'per_page' => '100']));
sc_p8v_assert($page['page'] === 999 && $page['per_page'] === 100, 'SC-P8-016 pagination accepts deep pages within the server bound');
sc_p8v_expect(InvalidArgumentException::class, fn () => ApiRequest::pagination(new WP_REST_Request(['page' => '100001'])), 'SC-P8-016 pagination rejects page values above the server bound');
sc_p8v_expect(InvalidArgumentException::class, fn () => ApiRequest::pagination(new WP_REST_Request(['per_page' => '101'])), 'SC-P8-016 per_page remains capped at 100');
sc_p8v_expect(InvalidArgumentException::class, fn () => ApiRequest::pagination(new WP_REST_Request(['page' => '1 OR 1=1'])), 'SC-P8-016 pagination rejects non-integer input');

// Sort field and direction are resource allow-listed.
$sort = ApiRequest::sort(new WP_REST_Request(['sort' => 'due_date', 'direction' => 'DESC']), ['id','due_date'], 'id');
sc_p8v_assert($sort === ['field' => 'due_date', 'direction' => 'desc'], 'SC-P8-016 sort/direction normalize through an explicit allowlist');
sc_p8v_expect(InvalidArgumentException::class, fn () => ApiRequest::sort(new WP_REST_Request(['sort' => 'DROP TABLE']), ['id','due_date'], 'id'), 'SC-P8-016 unsupported sort field fails closed');
sc_p8v_expect(InvalidArgumentException::class, fn () => ApiRequest::sort(new WP_REST_Request(['direction' => 'sideways']), ['id'], 'id'), 'SC-P8-016 unsupported sort direction fails closed');

// Existing filters stay validated and due-date ranges cannot be inverted.
sc_p8v_expect(InvalidArgumentException::class, fn () => ApiRequest::listQuery(
    new WP_REST_Request(['due_from' => '2026-09-10', 'due_to' => '2026-09-01']), ['id'], 'id'
), 'SC-P8-016 inverted due date filter range is rejected');
$query = ApiRequest::listQuery(
    new WP_REST_Request(['customer_id' => '7', 'status' => 'active', 'page' => '2', 'per_page' => '25', 'sort' => 'id', 'direction' => 'desc']),
    ['id','contract_number'], 'id', 'desc'
);
sc_p8v_assert($query['filters']['customer_id'] === 7 && $query['filters']['status'] === 'active' && $query['page'] === 2 && $query['sort'] === 'id' && $query['direction'] === 'desc', 'SC-P8-016 filter/page/sort query contract is deterministic');

// Controller applies sorting before slicing and reports the effective contract in metadata.
$GLOBALS['sc_test_result_queue'][] = [
    ['id' => '2', 'internal_code' => 'B', 'name' => 'Beta', 'contact_name' => '', 'email' => '', 'phone' => '', 'is_active' => '1'],
    ['id' => '7', 'internal_code' => 'A', 'name' => 'Alpha', 'contact_name' => '', 'email' => '', 'phone' => '', 'is_active' => '1'],
    ['id' => '4', 'internal_code' => 'C', 'name' => 'Charlie', 'contact_name' => '', 'email' => '', 'phone' => '', 'is_active' => '1'],
];
$response = DataController::customers(new WP_REST_Request(['sort' => 'id', 'direction' => 'desc', 'page' => '1', 'per_page' => '2']));
sc_p8v_assert($response instanceof WP_REST_Response, 'SC-P8-016 valid list query returns REST response');
$payload = $response->data;
sc_p8v_assert(($payload['data'][0]['id'] ?? null) === '7' && ($payload['data'][1]['id'] ?? null) === '4', 'SC-P8-016 resource rows are sorted before page slicing');
sc_p8v_assert(($payload['meta']['sort'] ?? '') === 'id' && ($payload['meta']['direction'] ?? '') === 'desc' && ($payload['meta']['returned'] ?? 0) === 2 && ($payload['meta']['has_more'] ?? false) === true, 'SC-P8-016 response metadata exposes sort/paging state and continuation');

// Resource-specific allowlists reject fields that are not exposed for that collection.
$GLOBALS['sc_test_result_queue'][] = [];
$badSort = DataController::customers(new WP_REST_Request(['sort' => 'password']));
sc_p8v_assert($badSort instanceof WP_Error && $badSort->code === 'safecontracts_invalid_request' && ($badSort->data['status'] ?? 0) === 422, 'SC-P8-016 unsupported resource sort becomes canonical 422 error envelope');

$dataSource = file_get_contents((string) (new ReflectionClass(DataController::class))->getFileName()) ?: '';
sc_p8v_assert(str_contains($dataSource, 'sortRows') && str_contains($dataSource, "['id','name','internal_code']") && str_contains($dataSource, "['id','due_date','expected_payment_date'"), 'SC-P8-016 controllers define resource-specific sort allowlists');
sc_p8v_assert(str_contains($dataSource, 'array_slice($rows, $offset, $perPage)') && str_contains($dataSource, "'sort' => \$sort") && str_contains($dataSource, "'direction' => \$direction"), 'SC-P8-016 deterministic sort happens before bounded page slicing and is reflected in metadata');

printf("SafeContracts P8 pagination/filter/sort SC-P8-016 passed (%d assertions).\n", $tests);
