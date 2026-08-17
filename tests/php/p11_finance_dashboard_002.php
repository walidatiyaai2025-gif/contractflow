<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Finance\AgingBucket;
use SafeContracts\Finance\FinanceAgingRepository;
use SafeContracts\Finance\FinanceObligationRepository;
use SafeContracts\Finance\FinanceOverviewService;
use SafeContracts\Finance\FinanceReadAccess;
use SafeContracts\Finance\FinanceReadFilters;
use SafeContracts\Finance\FinanceReadSql;
use SafeContracts\Finance\FinanceSummaryRepository;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Rest\FinanceController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$p11FinanceTests = 0;

function sc_p11f_assert(bool $condition, string $message): void
{
    global $p11FinanceTests;
    $p11FinanceTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p11f_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_p11f_assert($error instanceof $class, $message);
        return;
    }
    sc_p11f_assert(false, $message);
}

$today = new DateTimeImmutable('2026-08-17');
sc_p11f_assert(AgingBucket::forDueDate('2026-08-17', $today) === AgingBucket::CURRENT, 'due today is Current aging');
sc_p11f_assert(AgingBucket::forDueDate('2026-08-18', $today) === AgingBucket::CURRENT, 'future due date is Current aging');
sc_p11f_assert(AgingBucket::forDueDate('2026-08-16', $today) === AgingBucket::DAYS_1_30, 'one day overdue is 1-30 aging');
sc_p11f_assert(AgingBucket::forDueDate('2026-07-18', $today) === AgingBucket::DAYS_1_30, '30 days overdue remains 1-30 aging');
sc_p11f_assert(AgingBucket::forDueDate('2026-07-17', $today) === AgingBucket::DAYS_31_60, '31 days overdue enters 31-60 aging');
sc_p11f_assert(AgingBucket::forDueDate('2026-06-18', $today) === AgingBucket::DAYS_31_60, '60 days overdue remains 31-60 aging');
sc_p11f_assert(AgingBucket::forDueDate('2026-06-17', $today) === AgingBucket::DAYS_61_90, '61 days overdue enters 61-90 aging');
sc_p11f_assert(AgingBucket::forDueDate('2026-05-19', $today) === AgingBucket::DAYS_61_90, '90 days overdue remains 61-90 aging');
sc_p11f_assert(AgingBucket::forDueDate('2026-05-18', $today) === AgingBucket::DAYS_90_PLUS, '91 days overdue enters 90+ aging');

$filters = FinanceReadFilters::normalize([
    'financial_direction' => 'PAYABLE',
    'currency_code' => 'kwd',
    'counterparty_type' => 'SUPPLIER',
    'supplier_id' => '55',
    'accountant_user_id' => '77',
    'status' => 'OVERDUE',
    'aging_bucket' => '31_60',
    'limit' => '9999',
]);
sc_p11f_assert($filters['direction'] === FinancialDirection::PAYABLE, 'finance direction accepts canonical financial_direction filter');
sc_p11f_assert($filters['currency_code'] === 'KWD', 'finance currency normalizes without conversion');
sc_p11f_assert($filters['counterparty_type'] === 'supplier' && $filters['supplier_id'] === 55, 'supplier filters normalize');
sc_p11f_assert($filters['counterparty_id'] === 55, 'supplier selector maps to authoritative counterparty id');
sc_p11f_assert($filters['accountant_user_id'] === 77, 'accountant filter normalizes');
sc_p11f_assert($filters['status'] === 'overdue', 'finance status normalizes');
sc_p11f_assert($filters['aging_bucket'] === AgingBucket::DAYS_31_60, 'aging filter normalizes');
sc_p11f_assert($filters['limit'] === 500, 'finance work queue limit is bounded');
sc_p11f_assert(FinanceReadFilters::normalize(['currency_code' => CurrencyCode::UNKNOWN])['currency_code'] === CurrencyCode::UNKNOWN, 'legacy unknown currency remains explicit XXX');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
sc_p11f_assert(FinanceReadAccess::authorizedDirections() === [], 'VIEW_ALL alone does not grant finance access');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_FINANCE => true,
];
sc_p11f_assert(
    FinanceReadAccess::authorizedDirections() === [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE],
    'VIEW_FINANCE authorizes both explicit directions while keeping them separate in data'
);
$scope = FinanceReadAccess::scopeClause(77);
sc_p11f_assert($scope['clause'] === 'c.accountant_user_id = %d' && $scope['args'] === [77], 'VIEW_ALL may filter by requested accountant');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::VIEW_FINANCE => true,
];
$scope = FinanceReadAccess::scopeClause(999);
sc_p11f_assert($scope['clause'] === 'c.accountant_user_id = %d' && $scope['args'] === [42], 'assigned scope ignores forged accountant filter and uses current user');

$sqlScope = FinanceReadSql::where(FinanceReadFilters::normalize([
    'direction' => 'receivable',
    'currency_code' => 'USD',
    'customer_id' => 9,
]), [FinancialDirection::RECEIVABLE]);
sc_p11f_assert(str_contains($sqlScope['where'], 'p.financial_direction IN (%s)'), 'finance SQL scopes by direction');
sc_p11f_assert(str_contains($sqlScope['where'], 'c.counterparty_type = %s'), 'customer filter uses prepared counterparty semantics');
sc_p11f_assert(str_contains($sqlScope['where'], 'COALESCE(NULLIF(p.currency_code'), 'currency is a real server-side filter');
sc_p11f_assert(in_array('USD', $sqlScope['args'], true), 'currency value remains a prepared argument');
sc_p11f_assert(in_array('customer', $sqlScope['args'], true), 'counterparty type remains a prepared argument');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_FINANCE => true,
];
$GLOBALS['sc_test_result_queue'] = [[
    [
        'financial_direction' => 'payable', 'currency_code' => 'KWD', 'obligation_count' => '2',
        'original_total' => '10000.0000', 'settled_total' => '3000.0000', 'outstanding_total' => '7000.0000',
        'overdue_total' => '2000.0000', 'overdue_count' => '1', 'due_today_total' => '0.0000', 'due_today_count' => '0',
        'due_7_total' => '1000.0000', 'due_7_count' => '1', 'due_30_total' => '5000.0000', 'due_30_count' => '1',
        'upcoming_total' => '5000.0000',
    ],
    [
        'financial_direction' => 'receivable', 'currency_code' => 'USD', 'obligation_count' => '1',
        'original_total' => '20000.0000', 'settled_total' => '5000.0000', 'outstanding_total' => '15000.0000',
        'overdue_total' => '0.0000', 'overdue_count' => '0', 'due_today_total' => '0.0000', 'due_today_count' => '0',
        'due_7_total' => '0.0000', 'due_7_count' => '0', 'due_30_total' => '15000.0000', 'due_30_count' => '1',
        'upcoming_total' => '15000.0000',
    ],
]];
$readOffset = count($GLOBALS['sc_test_read_queries']);
$summary = (new FinanceSummaryRepository())->summary();
sc_p11f_assert(count($summary) === 2, 'AP and AR summary rows stay separate');
$summarySql = $GLOBALS['sc_test_read_queries'][$readOffset] ?? '';
sc_p11f_assert(str_contains($summarySql, 'GROUP BY p.financial_direction, COALESCE(NULLIF(p.currency_code'), 'financial totals group by direction and currency');
sc_p11f_assert(! str_contains($summarySql, 'SUM(p.remaining_amount) OVER'), 'summary does not manufacture a cross-currency grand total');
sc_p11f_assert(str_contains($summarySql, "'receivable', 'payable'"), 'authorized directions are explicitly bounded in prepared SQL');

$GLOBALS['sc_test_result_queue'] = [[
    ['financial_direction' => 'payable', 'currency_code' => 'KWD', 'aging_bucket' => '1_30', 'obligation_count' => '1', 'outstanding_total' => '7000.0000'],
    ['financial_direction' => 'receivable', 'currency_code' => 'USD', 'aging_bucket' => 'current', 'obligation_count' => '1', 'outstanding_total' => '15000.0000'],
]];
$readOffset = count($GLOBALS['sc_test_read_queries']);
$aging = (new FinanceAgingRepository())->aging();
sc_p11f_assert(count($aging) === 2, 'aging result keeps AP and AR/currency rows separate');
$agingSql = $GLOBALS['sc_test_read_queries'][$readOffset] ?? '';
foreach (['current', '1_30', '31_60', '61_90', '90_plus'] as $bucket) {
    sc_p11f_assert(str_contains($agingSql, "'{$bucket}'"), "aging SQL includes {$bucket} bucket");
}
sc_p11f_assert(str_contains($agingSql, 'GROUP BY p.financial_direction'), 'aging is grouped by financial direction');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '101', 'contract_id' => '9', 'financial_direction' => 'payable', 'currency_code' => 'KWD',
    'counterparty_type' => 'supplier', 'counterparty_id' => '55', 'counterparty_name' => 'Supplier Co',
]]];
$readOffset = count($GLOBALS['sc_test_read_queries']);
$rows = (new FinanceObligationRepository())->obligations(['direction' => 'payable', 'supplier_id' => 55]);
sc_p11f_assert(count($rows) === 1 && $rows[0]['counterparty_name'] === 'Supplier Co', 'supplier obligation survives finance work queue');
$queueSql = $GLOBALS['sc_test_read_queries'][$readOffset] ?? '';
sc_p11f_assert(str_contains($queueSql, 'LEFT JOIN wp_safecontracts_suppliers'), 'work queue joins Supplier master data');
sc_p11f_assert(str_contains($queueSql, "'supplier'"), 'Supplier lookup is type-aware and prepared');
sc_p11f_assert(str_contains($queueSql, 'LEFT JOIN wp_safecontracts_customers'), 'work queue also retains Customer counterparties');

Router::register();
sc_p11f_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/finance/overview']), 'finance overview REST endpoint is registered');
sc_p11f_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/finance/obligations']), 'finance obligations REST endpoint is registered');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
sc_p11f_assert(FinanceController::canViewFinance() instanceof WP_Error, 'VIEW_ALL cannot bypass finance authorization');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$empty = (new FinanceOverviewService())->overview();
sc_p11f_assert($empty['directions'] === [] && $empty['summary'] === [], 'finance overview fails closed without finance permission');

$dashboardSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/DashboardController.php');
sc_p11f_assert(is_string($dashboardSource) && str_contains($dashboardSource, "'finance' => (new FinanceOverviewService())->overview"), 'dashboard REST payload includes finance intelligence');

fwrite(STDOUT, "P11 finance dashboard tests passed ({$p11FinanceTests} assertions).\n");
