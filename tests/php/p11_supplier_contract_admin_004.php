<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\ContractsPage;
use SafeContracts\Admin\SuppliersPage;
use SafeContracts\Plugin;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Suppliers\SupplierService;

$p11AdminTests = 0;
function sc_p11admin_assert(bool $ok, string $message): void
{
    global $p11AdminTests;
    $p11AdminTests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

Plugin::instance()->boot();

$supplierPage = file_get_contents((string) (new ReflectionClass(SuppliersPage::class))->getFileName()) ?: '';
$contractPage = file_get_contents((string) (new ReflectionClass(ContractsPage::class))->getFileName()) ?: '';
$supplierService = file_get_contents((string) (new ReflectionClass(SupplierService::class))->getFileName()) ?: '';
$pluginSource = file_get_contents((string) (new ReflectionClass(Plugin::class))->getFileName()) ?: '';
$opsCss = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-ops.css') ?: '';

// Supplier workspace stays capability-gated and delegates all business mutations.
sc_p11admin_assert(str_contains($supplierPage, 'SupplierService'), 'Supplier Admin delegates reads/writes to SupplierService');
foreach ([Capabilities::VIEW_SUPPLIERS, Capabilities::CREATE_SUPPLIERS, Capabilities::EDIT_SUPPLIERS, Capabilities::ARCHIVE_SUPPLIERS] as $capability) {
    sc_p11admin_assert(str_contains($supplierPage, $capability), "Supplier Admin references {$capability} capability");
}
sc_p11admin_assert(substr_count($supplierPage, 'check_admin_referer') >= 2, 'Supplier save and archive actions are nonce-protected');
sc_p11admin_assert(str_contains($supplierPage, 'ARCHIVE_ACTION') && str_contains($supplierPage, 'archive($supplierId)'), 'Supplier deletion lifecycle is archive-only in Admin');
sc_p11admin_assert(! str_contains($supplierPage, '$wpdb') && ! str_contains(strtoupper($supplierPage), 'DELETE FROM'), 'Supplier Admin contains no direct SQL or destructive delete');
sc_p11admin_assert(! str_contains(strtoupper($supplierService), 'DELETE FROM'), 'Supplier service/repository lifecycle contract remains non-destructive');
foreach (['legal_name','trading_name','internal_code','registration_number','tax_number','default_currency','payment_terms','country_code','address','notes'] as $field) {
    sc_p11admin_assert(str_contains($supplierPage, $field), "Supplier Admin exposes domain field {$field}");
}

$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_SUPPLIERS => true];
SuppliersPage::register();
sc_p11admin_assert(($GLOBALS['sc_test_admin_pages'][SuppliersPage::SLUG]['parent'] ?? '') === AdminShell::SLUG, 'Supplier page is registered under SafeContracts shell');
sc_p11admin_assert(($GLOBALS['sc_test_admin_pages'][SuppliersPage::SLUG]['capability'] ?? '') === Capabilities::VIEW_SUPPLIERS, 'Supplier menu requires Supplier view capability');
sc_p11admin_assert(str_contains($pluginSource, '[SuppliersPage::class, \'register\']'), 'Plugin registers Supplier Admin page');
sc_p11admin_assert(str_contains($pluginSource, 'SuppliersPage::SAVE_ACTION') && str_contains($pluginSource, 'SuppliersPage::ARCHIVE_ACTION'), 'Plugin registers Supplier save and archive handlers');

// Contracts Admin uses the P11 counterparty/currency domain instead of Customer-only assignment.
sc_p11admin_assert(str_contains($contractPage, "'counterparty_type' => \$counterpartyType") && str_contains($contractPage, "'counterparty_id' => \$counterpartyId"), 'Contract create passes explicit counterparty type/id to ContractService');
sc_p11admin_assert(str_contains($contractPage, 'assignCounterparty('), 'Contract reassignment uses generic counterparty domain method');
sc_p11admin_assert(! str_contains($contractPage, 'assignCustomer($contractId'), 'Contract Admin no longer hardcodes Customer reassignment');
sc_p11admin_assert(str_contains($contractPage, 'updateCurrency(') && str_contains($contractPage, 'currency_code'), 'Contract Admin persists explicit contract currency through ContractService');
sc_p11admin_assert(str_contains($contractPage, 'Customers · Accounts Receivable') && str_contains($contractPage, 'Suppliers · Accounts Payable'), 'Counterparty selector communicates Customer/AR and Supplier/AP semantics');
sc_p11admin_assert(str_contains($contractPage, 'counterparty_name') && str_contains($contractPage, 'financial_direction'), 'Contract list/details display counterparty and direction');
sc_p11admin_assert(str_contains($contractPage, 'Direction is derived server-side') || str_contains($contractPage, 'determined by the backend'), 'Contract UI states that financial direction is server-authoritative');
sc_p11admin_assert(! str_contains($contractPage, '$wpdb'), 'Contract Admin remains presentation/application wiring without direct SQL');

// Existing design system provides the new surfaces with responsive/RTL-safe composition.
foreach (['safecontracts-status-pill','safecontracts-heading-actions','safecontracts-supplier-editor','safecontracts-contract-editor'] as $marker) {
    sc_p11admin_assert(str_contains($opsCss, '.' . $marker), "Admin design system styles {$marker}");
}
sc_p11admin_assert(str_contains($opsCss, '[dir="rtl"]') && str_contains($opsCss, '@media (max-width: 782px)'), 'Supplier/Contract UX stays within existing RTL/mobile design system');

fwrite(STDOUT, "P11 Supplier/Counterparty Admin tests passed ({$p11AdminTests} assertions).\n");
