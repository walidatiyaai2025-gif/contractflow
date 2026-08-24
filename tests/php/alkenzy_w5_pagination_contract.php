<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\ApiRequest;
use SafeContracts\Rest\DataController;
use SafeContracts\Roles\Capabilities;

// Exact-head acceptance regression for B084 query-layer pagination.
$tests = 0;

function sc_w5_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_w5_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_w5_assert($error instanceof $class, $message);
        return;
    }
    sc_w5_assert(false, $message);
}

function sc_w5_row(int $id): array
{
    return [
        'id' => (string) $id,
        'contract_number' => 'SC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
        'customer_id' => '7',
        'customer_name' => 'Customer',
        'counterparty_type' => 'customer',
        'counterparty_id' => '7',
        'counterparty_name' => 'Customer',
        'financial_direction' => 'receivable',
        'currency_code' => 'KWD',
        'accountant_user_id' => '42',
        'status' => 'active',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'base_value' => '100.0000',
        'is_archived' => '0',
    ];
}

function sc_w5_page_rows(int $total, int $page, int $perPage): array
{
    $rows = [];
    $start = $total - (($page - 1) * $perPage);
    for ($id = $start; $id > max(0, $start - $perPage); $id--) {
        $rows[] = sc_w5_row($id);
    }
    return $rows;
}

SafeContracts\Plugin::instance()->boot();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$GLOBALS['sc_test_read_queries'] = [];

$total = 605;
$perPage = 5;

$GLOBALS['sc_test_result_queue'] = [[['total' => (string) $total]], sc_w5_page_rows($total, 1, $perPage)];
$pageOne = DataController::contracts(new WP_REST_Request([
    'page' => '1',
    'per_page' => (string) $perPage,
    'sort' => 'id',
    'order' => 'desc',
]));

$GLOBALS['sc_test_result_queue'] = [[['total' => (string) $total]], sc_w5_page_rows($total, 101, $perPage)];
$pageBeyondLegacyWindow = DataController::contracts(new WP_REST_Request([
    'page' => '101',
    'per_page' => (string) $perPage,
    'sort' => 'id',
    'order' => 'desc',
]));

$GLOBALS['sc_test_result_queue'] = [[['total' => (string) $total]], sc_w5_page_rows($total, 121, $perPage)];
$lastPage = DataController::contracts(new WP_REST_Request([
    'page' => '121',
    'per_page' => (string) $perPage,
    'sort' => 'id',
    'order' => 'desc',
]));

$pageOneIds = array_map(static fn (array $row): int => (int) $row['id'], $pageOne->data['data']);
$pageDeepIds = array_map(static fn (array $row): int => (int) $row['id'], $pageBeyondLegacyWindow->data['data']);
$lastIds = array_map(static fn (array $row): int => (int) $row['id'], $lastPage->data['data']);

sc_w5_assert($pageOneIds === [605, 604, 603, 602, 601], 'B084 page 1 returns the first query-layer slice');
sc_w5_assert($pageDeepIds === [105, 104, 103, 102, 101], 'B084 page 101 returns records beyond the old first-500 materialization window');
sc_w5_assert($lastIds === [5, 4, 3, 2, 1], 'B084 last page returns the final server slice');
sc_w5_assert(array_intersect($pageOneIds, $pageDeepIds) === [], 'B084 distant pages do not duplicate rows');
sc_w5_assert(($pageOne->data['meta']['total'] ?? null) === 605, 'B084 backend reports authoritative total from COUNT');
sc_w5_assert(($pageOne->data['meta']['total_pages'] ?? null) === 121, 'B084 backend reports authoritative total pages');
sc_w5_assert(($pageOne->data['meta']['has_more'] ?? null) === true, 'B084 first page reports more rows');
sc_w5_assert(($lastPage->data['meta']['has_more'] ?? null) === false, 'B084 last page reports no more rows');
sc_w5_assert(($pageBeyondLegacyWindow->data['meta']['page'] ?? null) === 101, 'B084 response preserves deep requested page');
sc_w5_assert(($pageBeyondLegacyWindow->data['meta']['bounded_window'] ?? null) === 5, 'B084 each SQL page query is bounded to requested page size');

$sql = implode("\n", $GLOBALS['sc_test_read_queries']);
sc_w5_assert(str_contains($sql, 'COUNT(c.id) AS total'), 'B084 repository performs authoritative COUNT at query layer');
sc_w5_assert(str_contains($sql, 'LIMIT 5 OFFSET 500'), 'B084 repository performs bounded LIMIT/OFFSET beyond the old 500-row window');
sc_w5_assert(! str_contains($sql, 'ORDER BY c.updated_at DESC, c.id DESC LIMIT 500'), 'B084 list endpoint no longer depends on first-500 materialization');

$deepPage = ApiRequest::pagination(new WP_REST_Request(['page' => '600', 'per_page' => '1']));
sc_w5_assert($deepPage['page'] === 600, 'B084 page bounds permit safe deep pages when per-page keeps offset bounded');
sc_w5_expect(
    InvalidArgumentException::class,
    fn () => ApiRequest::pagination(new WP_REST_Request(['page' => '10002', 'per_page' => '100'])),
    'B084 abusive deep offsets remain rejected'
);

fwrite(STDOUT, "PASS: {$tests} ALKENZY W5 query-layer pagination assertions\n");
