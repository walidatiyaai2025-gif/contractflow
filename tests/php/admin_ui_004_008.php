<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\ContractsPage;
use SafeContracts\Admin\CustomersPage;
use SafeContracts\Admin\DashboardFilters;
use SafeContracts\Admin\PaymentsPage;
use SafeContracts\Customers\CustomerService;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p6core_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p6core_expect(string $class, callable $fn, string $message): void
{
    try { $fn(); } catch (Throwable $error) { sc_p6core_assert($error instanceof $class, $message); return; }
    sc_p6core_assert(false, $message);
}
if (! function_exists('add_submenu_page')) {
    function add_submenu_page(string $parent, string $pageTitle, string $menuTitle, string $capability, string $slug, callable $callback): string
    {
        $GLOBALS['sc_test_admin_pages'][$slug] = ['parent' => $parent, 'page_title' => $pageTitle, 'menu_title' => $menuTitle, 'capability' => $capability, 'callback' => $callback];
        return $parent . '_page_' . $slug;
    }
}

SafeContracts\Plugin::instance()->boot();

// SC-P6-004/005 — normalized filters and scoped KPI/read queries.
$filters = DashboardFilters::normalize([
    'customer_id' => '7', 'contract_id' => '9', 'accountant_user_id' => '88', 'status' => 'overdue',
    'due_from' => '2026-09-30', 'due_to' => '2026-09-01',
]);
sc_p6core_assert($filters['customer_id'] === 7 && $filters['contract_id'] === 9, 'P6 dashboard numeric filters normalize deterministically');
sc_p6core_assert($filters['due_from'] === '2026-09-01' && $filters['due_to'] === '2026-09-30', 'P6 dashboard reverses inverted due windows safely');
sc_p6core_assert(DashboardFilters::normalize(['status' => 'DROP TABLE'])['status'] === '', 'P6 dashboard rejects unknown status filters');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[['contract_count' => '3', 'scheduled_total' => '1000.0000', 'remaining_total' => '450.0000', 'overdue_exposure' => '125.0000', 'collected_total' => '550.0000']]];
$read = new AdminReadRepository();
$before = count($GLOBALS['sc_test_read_queries']);
$kpis = $read->kpis($filters);
$query = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6core_assert($kpis['contract_count'] === '3' && $kpis['overdue_exposure'] === '125.0000', 'SC-P6-004 KPI read model returns server-side aggregates');
sc_p6core_assert(str_contains($query, 'p.due_date <') && str_contains($query, 'p.remaining_amount > 0'), 'SC-P6-004 overdue exposure uses contractual due date plus positive remaining balance');
sc_p6core_assert(str_contains($query, 'c.customer_id = 7') && str_contains($query, 'c.id = 9') && str_contains($query, 'c.accountant_user_id = 88'), 'SC-P6-005 manager filters are applied server-side');
sc_p6core_assert(str_contains($query, "p.status = 'overdue'") && str_contains($query, "p.due_date >= '2026-09-01'") && str_contains($query, "p.due_date <= '2026-09-30'"), 'SC-P6-005 payment status and due-window filters are server-side');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[]];
$before = count($GLOBALS['sc_test_read_queries']);
$read->contracts(['accountant_user_id' => 999, 'customer_id' => 4]);
$scopeQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6core_assert(str_contains($scopeQuery, 'c.accountant_user_id = 42') && ! str_contains($scopeQuery, 'accountant_user_id = 999'), 'SC-P6-005 assigned scope cannot be widened by requested accountant filter');
sc_p6core_assert(str_contains($scopeQuery, 'c.customer_id = 4'), 'SC-P6-005 assigned scope can still narrow by customer');

$GLOBALS['sc_test_result_queue'] = [[]];
$before = count($GLOBALS['sc_test_read_queries']);
$read->customers();
$customerScopeQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6core_assert(str_contains($customerScopeQuery, 'EXISTS') && str_contains($customerScopeQuery, 'accountant_user_id = 42'), 'SC-P6-006 customer list is restricted through assigned contracts');

$GLOBALS['sc_test_result_queue'] = [[['id' => '12', 'contract_number' => 'SC-12', 'customer_id' => '4', 'customer_name' => 'Acme', 'accountant_user_id' => '42', 'status' => 'active', 'start_date' => null, 'end_date' => null, 'base_value' => '100.0000', 'notes' => '', 'is_archived' => '0']]];
$before = count($GLOBALS['sc_test_read_queries']);
$options = $read->contractOptions(4);
$dependentQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6core_assert(count($options) === 1 && $options[0]['customer_id'] === 4, 'SC-P6-005 dependent contract options preserve customer relationship');
sc_p6core_assert(str_contains($dependentQuery, 'c.customer_id = 4'), 'SC-P6-005 dependent contract dropdown is filtered in SQL');

// SC-P6-006 — customer mutation boundary and optional internal code semantics.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6core_expect(DomainException::class, fn () => (new CustomerService())->save(['name' => 'Denied']), 'SC-P6-006 customer writes require reference-data capability');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_REFERENCE_DATA => true];
$beforeWrites = count($GLOBALS['sc_test_queries']);
(new CustomerService())->save(['name' => 'No Code Customer', 'internal_code' => '', 'is_active' => true]);
$customerWriteSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeWrites));
sc_p6core_assert(str_contains($customerWriteSql, 'VALUES (NULL,'), 'SC-P6-006 blank optional internal code persists as NULL, not duplicate empty unique key');
sc_p6core_assert(! str_contains($customerWriteSql, "VALUES ('',"), 'SC-P6-006 optional internal code does not collapse to empty unique value');

// SC-P6-006..008 — presentation stays separated from SQL/business rules.
foreach ([CustomersPage::class, ContractsPage::class, PaymentsPage::class] as $pageClass) {
    $reflection = new ReflectionClass($pageClass);
    $source = file_get_contents((string) $reflection->getFileName()) ?: '';
    sc_p6core_assert(! str_contains($source, '$wpdb'), $pageClass . ' contains no direct presentation-layer SQL');
}
$contractSource = file_get_contents((string) (new ReflectionClass(ContractsPage::class))->getFileName()) ?: '';
$paymentSource = file_get_contents((string) (new ReflectionClass(PaymentsPage::class))->getFileName()) ?: '';
sc_p6core_assert(str_contains($contractSource, 'ContractService'), 'SC-P6-007 contract screen delegates mutations/reconciliation to ContractService');
sc_p6core_assert(str_contains($paymentSource, 'PaymentService'), 'SC-P6-008 payment screen delegates mutations to PaymentService');
sc_p6core_assert(str_contains($paymentSource, 'Contractual due date controls') && str_contains($paymentSource, 'Settled payments are terminal'), 'SC-P6-008 UI communicates due-date authority and settled read-only semantics');

// Page registration and assets.
CustomersPage::register(); ContractsPage::register(); PaymentsPage::register();
foreach ([CustomersPage::SLUG, ContractsPage::SLUG, PaymentsPage::SLUG] as $slug) {
    sc_p6core_assert(($GLOBALS['sc_test_admin_pages'][$slug]['parent'] ?? '') === AdminShell::SLUG, $slug . ' is registered under SafeContracts shell');
    sc_p6core_assert(($GLOBALS['sc_test_admin_pages'][$slug]['capability'] ?? '') === Capabilities::ACCESS, $slug . ' registration requires SafeContracts access');
}
$_GET['page'] = AdminShell::SLUG;
AdminShell::enqueueAssets();
sc_p6core_assert(isset($GLOBALS['sc_test_enqueued_styles'][AdminShell::STYLE_HANDLE]), 'SC-P6 dashboard loads base SafeContracts admin identity');
sc_p6core_assert(isset($GLOBALS['sc_test_enqueued_styles'][AdminShell::CORE_STYLE_HANDLE]), 'SC-P6 core screens load responsive dashboard styles');
sc_p6core_assert(($GLOBALS['sc_test_enqueued_styles'][AdminShell::CORE_STYLE_HANDLE]['deps'] ?? []) === [AdminShell::STYLE_HANDLE], 'SC-P6 core styles layer safely on base identity');

printf("SafeContracts P6 dashboard/core screens SC-P6-004..008 passed (%d assertions).\n", $tests);
