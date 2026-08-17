<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\FinancePage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Reports\ReportExportService;
use SafeContracts\Rest\DashboardController;
use SafeContracts\Roles\Capabilities;

$p11FinanceReportTests = 0;

function sc_p11fr_assert(bool $condition, string $message): void
{
    global $p11FinanceReportTests;
    $p11FinanceReportTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$financePageSource = file_get_contents((string) (new ReflectionClass(FinancePage::class))->getFileName()) ?: '';
$dashboardSource = file_get_contents((string) (new ReflectionClass(DashboardController::class))->getFileName()) ?: '';
$readSource = file_get_contents((string) (new ReflectionClass(AdminReadRepository::class))->getFileName()) ?: '';
$reportsSource = file_get_contents((string) (new ReflectionClass(ReportsPage::class))->getFileName()) ?: '';
$exportSource = file_get_contents((string) (new ReflectionClass(ReportExportService::class))->getFileName()) ?: '';
$pluginSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Plugin.php') ?: '';

sc_p11fr_assert(str_contains($financePageSource, 'FinanceOverviewService'), 'Finance admin workspace reuses canonical finance overview service');
sc_p11fr_assert(str_contains($financePageSource, 'Accounts Payable') && str_contains($financePageSource, 'Accounts Receivable'), 'Finance workspace labels AP and AR independently');
sc_p11fr_assert(str_contains($financePageSource, 'Aging') && str_contains($financePageSource, 'renderCashFlow') && str_contains($financePageSource, 'Expected cash flow'), 'Finance workspace exposes Aging and cash-flow intelligence');
sc_p11fr_assert(str_contains($financePageSource, 'currency_code') && str_contains($financePageSource, 'No cross-currency grand total'), 'Finance workspace keeps currency explicit and rejects a cross-currency total');

sc_p11fr_assert(str_contains($dashboardSource, 'FinanceOverviewService'), 'REST dashboard reuses canonical finance intelligence');
sc_p11fr_assert(str_contains($dashboardSource, "'finance' => (new FinanceOverviewService())->overview"), 'REST dashboard adds finance intelligence without removing legacy fields');

sc_p11fr_assert(str_contains($readSource, "c.counterparty_type = 'customer'"), 'Legacy KPI/report compatibility reads are explicitly Customer scoped');
sc_p11fr_assert(str_contains($readSource, "COALESCE(NULLIF(p.financial_direction, ''), 'receivable') = 'receivable'"), 'Legacy financial operations exclude Supplier/AP and map historic Customer rows to receivable');
sc_p11fr_assert(str_contains($readSource, 'LEFT JOIN {$suppliers} s'), 'Admin contract/payment reads include Supplier master data');
sc_p11fr_assert(str_contains($readSource, "WHEN c.counterparty_type = 'supplier' THEN s.name"), 'Supplier counterparty name uses merged Supplier schema');
sc_p11fr_assert(str_contains($readSource, "counterparty_type = 'supplier')"), 'Archived Supplier masters do not hide historical payable contracts');
sc_p11fr_assert(str_contains($readSource, 'currency_group_count') && str_contains($readSource, 'ELSE NULL END AS scheduled_total'), 'Legacy KPI totals fail safe instead of summing multiple currencies');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_FINANCE => true,
];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '701',
    'contract_number' => 'SUP-701',
    'customer_id' => null,
    'counterparty_type' => 'supplier',
    'counterparty_id' => '55',
    'counterparty_name' => 'Supplier Co',
    'supplier_id' => '55',
    'supplier_name' => 'Supplier Co',
    'financial_direction' => 'payable',
    'currency_code' => 'KWD',
    'accountant_user_id' => '42',
    'status' => 'active',
    'is_archived' => '0',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$supplierContracts = (new AdminReadRepository())->contracts(['counterparty_type' => 'supplier', 'counterparty_id' => 55]);
$contractSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p11fr_assert(count($supplierContracts) === 1 && ($supplierContracts[0]['counterparty_name'] ?? '') === 'Supplier Co', 'Supplier contract is visible without a fabricated Customer');
sc_p11fr_assert(str_contains($contractSql, 'LEFT JOIN wp_safecontracts_suppliers'), 'Supplier contract read uses a non-destructive LEFT JOIN');
sc_p11fr_assert(str_contains($contractSql, "c.counterparty_type = 'supplier'") && str_contains($contractSql, 'c.counterparty_id = 55'), 'Supplier contract filters are server-side');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '801', 'contract_id' => '701', 'financial_direction' => 'payable', 'currency_code' => 'KWD',
    'counterparty_type' => 'supplier', 'counterparty_id' => '55', 'counterparty_name' => 'Supplier Co',
    'supplier_id' => '55', 'supplier_name' => 'Supplier Co', 'remaining_amount' => '300.0000',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$supplierPayments = (new AdminReadRepository())->payments(['financial_direction' => 'payable', 'currency_code' => 'KWD']);
$paymentSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p11fr_assert(count($supplierPayments) === 1 && ($supplierPayments[0]['financial_direction'] ?? '') === 'payable', 'Supplier payable is visible in admin payment reads');
sc_p11fr_assert(str_contains($paymentSql, "p.financial_direction = 'payable'") && str_contains($paymentSql, "p.currency_code = 'KWD'"), 'Payment finance filters remain server-side');

sc_p11fr_assert(str_contains($reportsSource, 'FinanceOverviewService'), 'Reports UI reads finance intelligence from the same service');
sc_p11fr_assert(str_contains($reportsSource, 'AP / AR by currency'), 'Reports UI presents AP and AR by currency');
sc_p11fr_assert(str_contains($reportsSource, 'Aging report'), 'Reports UI exposes Aging report rows');
sc_p11fr_assert(str_contains($reportsSource, 'Receivable operations history'), 'Legacy collection metrics are explicitly separated from canonical finance reporting');
sc_p11fr_assert(str_contains($reportsSource, 'Supplier payables are never folded into collection totals.'), 'Reports UI states that AP is excluded from legacy collection totals');

foreach (['Finance Summary', 'Aging', 'Cash Flow', 'Finance Obligations'] as $sheet) {
    sc_p11fr_assert(str_contains($exportSource, "'{$sheet}'"), "XLSX export contains {$sheet} sheet");
}
sc_p11fr_assert(str_contains($exportSource, "'financial_direction','currency_code'"), 'Finance export keeps direction and currency as first-class columns');
sc_p11fr_assert(str_contains($exportSource, "'counterparty_type','counterparty_id','counterparty_name'"), 'Finance export keeps Supplier/Customer counterparty identity explicit');
sc_p11fr_assert(str_contains($exportSource, "'finance_row_counts'"), 'Finance export extends row counts without changing the legacy row_counts contract');
sc_p11fr_assert(str_contains($exportSource, 'No cross-currency grand total is produced.'), 'Export documents currency-safe aggregation semantics');
sc_p11fr_assert(! str_contains($exportSource, '$wpdb'), 'Report export contains no presentation/service-layer SQL');

sc_p11fr_assert(str_contains($pluginSource, 'use SafeContracts\\Admin\\FinancePage;'), 'Plugin imports Finance admin workspace');
sc_p11fr_assert(str_contains($pluginSource, "add_action('admin_menu', [FinancePage::class, 'register']"), 'Plugin registers Finance workspace in the admin menu');

fwrite(STDOUT, "P11 finance reports tests passed ({$p11FinanceReportTests} assertions).\n");
