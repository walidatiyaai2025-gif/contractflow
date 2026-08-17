<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\DashboardPage;
use SafeContracts\Admin\FinancePage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Reports\ReportExportService;

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
$dashboardSource = file_get_contents((string) (new ReflectionClass(DashboardPage::class))->getFileName()) ?: '';
$readSource = file_get_contents((string) (new ReflectionClass(AdminReadRepository::class))->getFileName()) ?: '';
$reportsSource = file_get_contents((string) (new ReflectionClass(ReportsPage::class))->getFileName()) ?: '';
$exportSource = file_get_contents((string) (new ReflectionClass(ReportExportService::class))->getFileName()) ?: '';

sc_p11fr_assert(str_contains($financePageSource, 'FinanceOverviewService'), 'Finance admin workspace reuses canonical finance overview service');
sc_p11fr_assert(str_contains($financePageSource, 'Accounts Payable') && str_contains($financePageSource, 'Accounts Receivable'), 'Finance workspace labels AP and AR independently');
sc_p11fr_assert(str_contains($financePageSource, 'Aging') && str_contains($financePageSource, 'renderCashFlow') && str_contains($financePageSource, 'Expected cash flow'), 'Finance workspace exposes Aging and cash-flow intelligence');
sc_p11fr_assert(str_contains($financePageSource, 'currency_code'), 'Finance workspace keeps currency explicit in rendering and filters');

sc_p11fr_assert(str_contains($dashboardSource, 'FinanceOverviewService'), 'Main admin dashboard reuses canonical finance intelligence');
sc_p11fr_assert(str_contains($dashboardSource, 'AP / AR by currency'), 'Main dashboard presents AP and AR by currency instead of a cross-direction total');
sc_p11fr_assert(str_contains($dashboardSource, 'Counterparty') && str_contains($dashboardSource, 'counterparty_name'), 'Dashboard contract list is Customer/Supplier counterparty-aware');
sc_p11fr_assert(! str_contains($dashboardSource, "self::kpi(__('Scheduled'"), 'Dashboard no longer renders ambiguous legacy scheduled-money KPI');
sc_p11fr_assert(str_contains($readSource, "c.counterparty_type = 'customer'"), 'Legacy KPI compatibility read is explicitly Customer scoped');
sc_p11fr_assert(str_contains($readSource, "COALESCE(NULLIF(p.financial_direction, ''), 'receivable') = 'receivable'"), 'Legacy KPI compatibility read excludes AP and safely maps legacy Customer rows to receivable');

sc_p11fr_assert(str_contains($reportsSource, 'FinanceOverviewService'), 'Reports UI reads finance intelligence from the same service');
sc_p11fr_assert(str_contains($reportsSource, 'AP / AR by currency'), 'Reports UI presents financial totals by direction and currency');
sc_p11fr_assert(str_contains($reportsSource, 'Aging report'), 'Reports UI exposes Aging report rows');
sc_p11fr_assert(str_contains($reportsSource, 'Receivable operations history'), 'Legacy collection metrics are explicitly separated from canonical finance reporting');
sc_p11fr_assert(str_contains($reportsSource, 'Supplier payables are never folded into collection totals.'), 'Reports UI states that AP is excluded from legacy collection totals');

foreach (['Finance Summary', 'Aging', 'Cash Flow', 'Finance Obligations'] as $sheet) {
    sc_p11fr_assert(str_contains($exportSource, "'{$sheet}'"), "XLSX export contains {$sheet} sheet");
}
sc_p11fr_assert(str_contains($exportSource, "'financial_direction','currency_code'"), 'Finance export keeps direction and currency as first-class columns');
sc_p11fr_assert(str_contains($exportSource, "'counterparty_type','counterparty_id','counterparty_name'"), 'Finance export keeps Supplier/Customer counterparty identity explicit');
sc_p11fr_assert(str_contains($exportSource, "'finance_row_counts'"), 'Finance export extends row counts without changing the legacy row_counts contract');
sc_p11fr_assert(str_contains($exportSource, 'Legacy money totals are blank when multiple currencies are present.'), 'Export documents fail-safe multi-currency legacy semantics');
sc_p11fr_assert(! str_contains($exportSource, '$wpdb'), 'Report export contains no presentation/service-layer SQL');

fwrite(STDOUT, "P11 finance reports tests passed ({$p11FinanceReportTests} assertions).\n");
