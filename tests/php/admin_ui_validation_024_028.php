<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\ContractsPage;
use SafeContracts\Admin\CustomersPage;
use SafeContracts\Admin\DashboardFilters;
use SafeContracts\Admin\DashboardPage;
use SafeContracts\Admin\PaymentsPage;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Customers\CustomerService;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p6v4_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p6v4_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p6v4_assert($error instanceof $class, $message);
        return;
    }
    sc_p6v4_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();
$read = new AdminReadRepository();

// SC-P6-024 — KPI semantics remain backend-authoritative and assignment-scoped.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'contract_count' => '2',
    'scheduled_total' => '500.0000',
    'remaining_total' => '125.0000',
    'overdue_exposure' => '75.0000',
    'collected_total' => '375.0000',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$kpis = $read->kpis(DashboardFilters::normalize(['accountant_user_id' => 999, 'customer_id' => 7]));
$assignedKpiQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6v4_assert($kpis['overdue_exposure'] === '75.0000', 'SC-P6-024 KPI values come from server-side aggregate output');
sc_p6v4_assert(str_contains($assignedKpiQuery, 'c.accountant_user_id = 42'), 'SC-P6-024 assigned KPI scope binds to current user');
sc_p6v4_assert(! str_contains($assignedKpiQuery, 'accountant_user_id = 999'), 'SC-P6-024 requested accountant cannot widen assigned KPI scope');
sc_p6v4_assert(str_contains($assignedKpiQuery, 'p.due_date <') && str_contains($assignedKpiQuery, 'p.remaining_amount > 0'), 'SC-P6-024 overdue exposure uses contractual due date and positive remaining amount');
$GLOBALS['sc_test_result_queue'] = [[]];
$emptyKpis = $read->kpis(DashboardFilters::normalize([]));
sc_p6v4_assert($emptyKpis['contract_count'] === '0' && $emptyKpis['remaining_total'] === '0.0000', 'SC-P6-024 empty KPI result has deterministic zero defaults');
$dashboardSource = file_get_contents((string) (new ReflectionClass(DashboardPage::class))->getFileName()) ?: '';
sc_p6v4_assert(str_contains($dashboardSource, 'AdminReadRepository') && ! str_contains($dashboardSource, '$wpdb'), 'SC-P6-024 dashboard remains presentation-only over scoped read model');

// SC-P6-025 — malformed filters fail closed without PHP scalar-cast warnings or scope widening.
$malformed = DashboardFilters::normalize([
    'customer_id' => ['7'],
    'contract_id' => '-2',
    'accountant_user_id' => '1.5',
    'status' => ['overdue'],
    'due_from' => ['2026-08-01'],
    'due_to' => true,
]);
sc_p6v4_assert($malformed['customer_id'] === 0 && $malformed['contract_id'] === 0 && $malformed['accountant_user_id'] === 0, 'SC-P6-025 malformed ID filters fail closed');
sc_p6v4_assert($malformed['status'] === '' && $malformed['due_from'] === null && $malformed['due_to'] === null, 'SC-P6-025 malformed status/date filters fail closed');
$normalized = DashboardFilters::normalize(['customer_id' => '7', 'contract_id' => '9', 'status' => 'OVERDUE', 'due_from' => '2026-08-31', 'due_to' => '2026-08-01']);
sc_p6v4_assert($normalized['customer_id'] === 7 && $normalized['contract_id'] === 9 && $normalized['status'] === PaymentStatus::OVERDUE, 'SC-P6-025 valid filters normalize deterministically');
sc_p6v4_assert($normalized['due_from'] === '2026-08-01' && $normalized['due_to'] === '2026-08-31', 'SC-P6-025 inverted due window normalizes deterministically');
$GLOBALS['sc_test_result_queue'] = [[]];
$before = count($GLOBALS['sc_test_read_queries']);
$read->payments(['customer_id' => 7, 'contract_id' => 9]);
$dependentQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6v4_assert(str_contains($dependentQuery, 'c.customer_id = 7') && str_contains($dependentQuery, 'c.id = 9'), 'SC-P6-025 customer and dependent contract filters are both enforced server-side');

// SC-P6-026 — customer screen reads and edit selection stay within assignment scope.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[]];
$before = count($GLOBALS['sc_test_read_queries']);
$read->customers(['customer_id' => 12]);
$customerQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6v4_assert(str_contains($customerQuery, 'cu.id = 12') && str_contains($customerQuery, 'accountant_user_id = 42'), 'SC-P6-026 customer lookup combines selected customer with assigned-contract scope');
$customerPageSource = file_get_contents((string) (new ReflectionClass(CustomersPage::class))->getFileName()) ?: '';
sc_p6v4_assert(str_contains($customerPageSource, "$read->customers(['customer_id' => $editId])"), 'SC-P6-026 customer edit selection reuses scoped admin read model');
sc_p6v4_assert(! str_contains($customerPageSource, 'CustomerService())->find($editId)'), 'SC-P6-026 customer edit selection no longer bypasses list scope through raw service find');
sc_p6v4_assert(str_contains($customerPageSource, 'esc_html') && str_contains($customerPageSource, 'esc_attr') && str_contains($customerPageSource, 'esc_textarea'), 'SC-P6-026 customer output paths use WordPress escaping helpers');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_REFERENCE_DATA => true];
sc_p6v4_expect(InvalidArgumentException::class, fn () => (new CustomerService())->save(['name' => 'Acme', 'email' => 'not-an-email']), 'SC-P6-026 malformed customer email is rejected by domain normalization');

// SC-P6-027 — contract details/mutations preserve scope, archive freeze and independent capabilities.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[]];
$before = count($GLOBALS['sc_test_read_queries']);
$read->contracts(['contract_id' => 51, 'accountant_user_id' => 999]);
$contractReadQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6v4_assert(str_contains($contractReadQuery, 'c.id = 51') && str_contains($contractReadQuery, 'c.accountant_user_id = 42'), 'SC-P6-027 selected contract read remains assignment-scoped');
sc_p6v4_assert(! str_contains($contractReadQuery, 'accountant_user_id = 999'), 'SC-P6-027 selected contract ignores attempted accountant scope widening');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::EDIT_CONTRACTS => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '51', 'contract_number' => 'SC-51', 'customer_id' => '7', 'accountant_user_id' => '42',
    'status' => 'active', 'start_date' => null, 'end_date' => null, 'base_value' => '100.0000', 'notes' => '', 'is_archived' => '1',
]]];
sc_p6v4_expect(DomainException::class, fn () => (new ContractService())->edit(51, ['notes' => 'blocked']), 'SC-P6-027 archived contract remains frozen in domain service');
$contractPageSource = file_get_contents((string) (new ReflectionClass(ContractsPage::class))->getFileName()) ?: '';
sc_p6v4_assert(str_contains($contractPageSource, '$contractId === 0') && str_contains($contractPageSource, 'CREATE_CONTRACTS'), 'SC-P6-027 create submission is gated by create capability');
sc_p6v4_assert(str_contains($contractPageSource, 'EDIT_CONTRACTS') && str_contains($contractPageSource, 'ASSIGN_CONTRACTS'), 'SC-P6-027 existing-contract gate keeps edit and assignment capabilities independent');
sc_p6v4_assert(str_contains($contractPageSource, 'Archived contracts are read-only.') && ! str_contains($contractPageSource, '$wpdb'), 'SC-P6-027 contract presentation preserves archive/read-model boundary');

// SC-P6-028 — payment detail uses scoped direct domain lookup and exact terminal semantics.
$paymentRow = [
    'id' => '81', 'contract_id' => '51', 'sequence_no' => '1', 'reference' => 'P-81',
    'due_date' => '2026-08-01', 'expected_payment_date' => '2026-12-01',
    'original_amount' => '100.0000', 'paid_amount' => '25.0000', 'remaining_amount' => '75.0000',
    'status' => 'overdue', 'accountant_user_id' => '42', 'contract_is_archived' => '0',
];
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[$paymentRow]];
$before = count($GLOBALS['sc_test_read_queries']);
$found = (new PaymentService())->find(81);
$paymentFindQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p6v4_assert($found['id'] === 81 && $found['accountant_user_id'] === 42, 'SC-P6-028 scoped payment detail returns assigned payment');
sc_p6v4_assert(str_contains($paymentFindQuery, 'WHERE p.id = 81 LIMIT 1'), 'SC-P6-028 payment detail uses direct bounded ID lookup');
$outside = $paymentRow;
$outside['accountant_user_id'] = '77';
$GLOBALS['sc_test_result_queue'] = [[$outside]];
sc_p6v4_expect(DomainException::class, fn () => (new PaymentService())->find(81), 'SC-P6-028 scoped payment detail rejects another accountant assignment');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[$paymentRow]];
$status = (new PaymentService())->temporalStatus(81, new DateTimeImmutable('2026-08-15'), 10);
sc_p6v4_assert($status === PaymentStatus::OVERDUE, 'SC-P6-028 contractual due date remains authoritative despite later expected payment date');
$archivedPayment = $paymentRow;
$archivedPayment['contract_is_archived'] = '1';
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::MANAGE_PAYMENTS => true];
$GLOBALS['sc_test_result_queue'] = [[$archivedPayment]];
sc_p6v4_expect(DomainException::class, fn () => (new PaymentService())->updateDates(81, '2026-08-20', '2026-09-01'), 'SC-P6-028 payment edits on archived contracts remain blocked server-side');
$paymentPageSource = file_get_contents((string) (new ReflectionClass(PaymentsPage::class))->getFileName()) ?: '';
sc_p6v4_assert(str_contains($paymentPageSource, 'PaymentService())->find($selectedId)'), 'SC-P6-028 payment page uses scoped domain detail lookup');
sc_p6v4_assert(! str_contains($paymentPageSource, "$read->payments(['contract_id' => 0])"), 'SC-P6-028 payment page removed unbounded 500-row detail fallback');
sc_p6v4_assert(str_contains($paymentPageSource, 'ContractMoney::compare') && str_contains($paymentPageSource, 'contract_is_archived'), 'SC-P6-028 terminal detection uses fixed-point zero comparison and archive state');
sc_p6v4_assert(str_contains($paymentPageSource, 'Contractual due date controls') && ! str_contains($paymentPageSource, '$wpdb'), 'SC-P6-028 payment presentation preserves due-date and persistence boundaries');

printf("SafeContracts P6 validation SC-P6-024..028 passed (%d assertions).\n", $tests);
