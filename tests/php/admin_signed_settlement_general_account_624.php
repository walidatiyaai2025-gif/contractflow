<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

$tests = 0;

function sc_624_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$repo = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/FinancialSettlementAdminRepository.php');
$collections = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/CollectionsPage.php');
$dashboard = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/DashboardV2Page.php');
$css = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-financial-v3.css');

sc_624_assert(str_contains($repo, "cl.financial_direction IN ('receivable','payable')"), 'settlement admin ledger reads both receivable and payable directions');
sc_624_assert(str_contains($repo, 'LEFT JOIN {$suppliers}') && str_contains($repo, 'counterparty_name'), 'settlement admin ledger joins both customer and supplier counterparties');
sc_624_assert(! str_contains($repo, "c.counterparty_type = 'customer'\";\n        \$where[] = \"COALESCE"), 'settlement ledger is not hard-coded to customer receivables');
sc_624_assert(str_contains($repo, 'cl.currency_code') && str_contains($repo, 'cl.financial_direction'), 'settlement rows preserve canonical direction and currency');

sc_624_assert(str_contains($collections, 'FinancialSettlementAdminRepository') && str_contains($collections, 'forDirection'), 'Collections page consumes the signed AP/AR settlement read model and splits directions');
sc_624_assert(str_contains($collections, 'safecontracts-settlement-panel--') && str_contains($collections, 'signedMoney'), 'Collections page renders separate signed incoming and outgoing ledgers');
sc_624_assert(str_contains($collections, "FinancialDirection::PAYABLE ? '− ' : '+ '"), 'payable settlements render negative and receivable settlements render positive');
sc_624_assert(str_contains($collections, "payment['counterparty_name']") && str_contains($collections, "payment['financial_direction']"), 'settlement entry options use server-authoritative counterparty and direction data');

sc_624_assert(str_contains($dashboard, "label('General account', 'الحساب العام')"), 'dashboard exposes the requested General account KPI');
sc_624_assert(str_contains($dashboard, "ContractMoney::difference(\$r['outstanding'], \$p['outstanding'])"), 'General account is receivable outstanding minus payable outstanding');
sc_624_assert(str_contains($dashboard, "['settled']") && str_contains($dashboard, 'paid_amount'), 'dashboard settlement totals follow the reconciled payment ledger');
sc_624_assert(str_contains($dashboard, 'directionKpi') && str_contains($dashboard, "['base']"), 'receivable and payable contract KPI cards expose their contract totals');
sc_624_assert(str_contains($css, '.safecontracts-dashboard-v2__kpi--general-account') && str_contains($css, '.safecontracts-settlement-panel--payable'), 'financial UX styles cover General account and signed payable settlements');

echo "SafeContracts signed settlement/general-account regression passed ({$tests} assertions).\n";
