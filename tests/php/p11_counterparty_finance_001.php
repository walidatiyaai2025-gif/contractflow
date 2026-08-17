<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractService;
use SafeContracts\Counterparties\CounterpartyType;
use SafeContracts\Database\Migrator;
use SafeContracts\Finance\FinancialDirection;
use SafeContracts\Finance\SettlementMath;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$p11Tests = 0;

function sc_p11_assert(bool $condition, string $message): void
{
    global $p11Tests;
    $p11Tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p11_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_p11_assert($error instanceof $class, $message);
        return;
    }
    sc_p11_assert(false, $message);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p11_assert(is_callable($activate), 'plugin activation hook is available');
$activate();
sc_p11_assert(Migrator::LATEST_VERSION === '1.16.0', 'P11 counterparty finance migration is current');

$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
sc_p11_assert(str_contains($schema, 'wp_safecontracts_suppliers'), 'supplier master table is migrated');
sc_p11_assert(str_contains($schema, 'wp_safecontracts_financial_transactions'), 'canonical financial transaction ledger is migrated');
sc_p11_assert(str_contains($schema, 'UNIQUE KEY idempotency_key (idempotency_key)'), 'financial transaction retries are protected by an idempotency constraint');

$queries = implode("\n", $GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($queries, "counterparty_type = 'customer'"), 'legacy customer contracts are deterministically backfilled');
sc_p11_assert(str_contains($queries, "financial_direction = 'receivable'"), 'legacy customer contracts become explicit receivables');
sc_p11_assert(str_contains($queries, 'MODIFY customer_id bigint(20) unsigned NULL'), 'customer compatibility column becomes nullable for supplier contracts');

sc_p11_assert(CounterpartyType::financialDirection('supplier') === FinancialDirection::PAYABLE, 'supplier contracts map to AP/payable');
sc_p11_assert(CounterpartyType::financialDirection('customer') === FinancialDirection::RECEIVABLE, 'customer contracts map to AR/receivable');
sc_p11_assert(PaymentStatus::settledForDirection(FinancialDirection::PAYABLE) === PaymentStatus::PAID, 'payables settle as paid');
sc_p11_assert(PaymentStatus::settledForDirection(FinancialDirection::RECEIVABLE) === PaymentStatus::RECEIVED, 'receivables settle as received');

$supplierPartial = SettlementMath::apply('10000', '0', '3000', FinancialDirection::PAYABLE);
sc_p11_assert($supplierPartial['settled_amount'] === '3000.0000', 'supplier payment records exact settled amount');
sc_p11_assert($supplierPartial['remaining_amount'] === '7000.0000', '10,000 payable minus 3,000 paid leaves 7,000');
sc_p11_assert($supplierPartial['status'] === PaymentStatus::PARTIALLY_PAID, 'partial AP settlement has partially paid status');
$supplierFinal = SettlementMath::apply('10000', '3000', '7000', FinancialDirection::PAYABLE);
sc_p11_assert($supplierFinal['remaining_amount'] === '0.0000' && $supplierFinal['status'] === PaymentStatus::PAID, 'final AP settlement reaches paid exactly');

$customerPartial = SettlementMath::apply('20000', '0', '5000', FinancialDirection::RECEIVABLE);
sc_p11_assert($customerPartial['settled_amount'] === '5000.0000', 'customer receipt records exact received amount');
sc_p11_assert($customerPartial['remaining_amount'] === '15000.0000', '20,000 receivable minus 5,000 received leaves 15,000');
sc_p11_assert($customerPartial['status'] === PaymentStatus::PARTIALLY_RECEIVED, 'partial AR settlement has partially received status');
sc_p11_expect(DomainException::class, fn () => SettlementMath::apply('10000', '9000', '2000', FinancialDirection::PAYABLE), 'over-settlement is rejected');

foreach ([
    Capabilities::VIEW_SUPPLIERS,
    Capabilities::CREATE_SUPPLIERS,
    Capabilities::EDIT_SUPPLIERS,
    Capabilities::ARCHIVE_SUPPLIERS,
    Capabilities::VIEW_PAYABLES,
    Capabilities::VIEW_RECEIVABLES,
    Capabilities::RECORD_PAYMENT,
    Capabilities::RECORD_RECEIPT,
] as $capability) {
    sc_p11_assert(in_array($capability, Capabilities::all(), true), "RBAC exposes {$capability}");
}

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::CREATE_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '88']]];
$GLOBALS['wpdb']->insert_id = 9001;
$contractId = (new ContractService())->create([
    'contract_number' => 'SUP-2026-001',
    'counterparty_type' => 'supplier',
    'counterparty_id' => 88,
    'currency_code' => 'KWD',
    'notes' => 'Supplier contract',
]);
sc_p11_assert($contractId === 9001, 'supplier contract creation returns inserted ID');
$createSql = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($createSql, "'supplier'"), 'supplier contract persists supplier counterparty type');
sc_p11_assert(str_contains($createSql, "'payable'"), 'supplier contract persists payable direction');
sc_p11_assert(str_contains($createSql, "'KWD'"), 'supplier contract persists explicit currency');
sc_p11_assert(str_contains($createSql, 'NULL'), 'supplier contract does not fabricate a customer ID');

$settlementSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Finance/SettlementRepository.php');
sc_p11_assert(is_string($settlementSource) && str_contains($settlementSource, 'FOR UPDATE'), 'settlement repository locks the obligation row for concurrency safety');
sc_p11_assert(is_string($settlementSource) && str_contains($settlementSource, 'idempotency_key'), 'settlement repository persists idempotency keys');

$supplierSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Suppliers/SupplierRepository.php');
sc_p11_assert(is_string($supplierSource) && ! str_contains($supplierSource, 'DELETE FROM'), 'supplier lifecycle never destructively deletes supplier history');

$routerSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
sc_p11_assert(is_string($routerSource) && str_contains($routerSource, 'SuppliersController::register()'), 'supplier REST controller is registered');
sc_p11_assert(is_string($routerSource) && str_contains($routerSource, 'FinanceController::register()'), 'finance REST controller is registered');
sc_p11_assert(is_string($routerSource) && str_contains($routerSource, 'CounterpartyContractsController::register()'), 'counterparty contract REST mutations are registered');

fwrite(STDOUT, "P11 counterparty/finance tests passed ({$p11Tests} assertions).\n");
