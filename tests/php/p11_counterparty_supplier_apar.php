<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Contracts\Counterparty;
use SafeContracts\Contracts\CounterpartyContractService;
use SafeContracts\Counterparties\CounterpartyReadRepository;
use SafeContracts\Database\Migrator;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Rest\ApiRequest;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use SafeContracts\Suppliers\SupplierService;

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
        sc_p11_assert($error instanceof $class, $message . ' (' . get_class($error) . ')');
        return;
    }
    sc_p11_assert(false, $message . ' (no exception)');
}

/** @return array<string,mixed> */
function sc_p11_payment(string $direction, string $currency, array $overrides = []): array
{
    return array_merge([
        'id' => '8101',
        'contract_id' => '4101',
        'financial_direction' => $direction,
        'currency_code' => $currency,
        'sequence_no' => '1',
        'reference' => 'P11-001',
        'due_date' => '2026-09-30',
        'expected_payment_date' => null,
        'original_amount' => '100.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '100.0000',
        'status' => PaymentStatus::UPCOMING,
        'is_archived' => '0',
        'accountant_user_id' => '42',
        'contract_is_archived' => '0',
        'counterparty_type' => $direction === FinancialDirection::PAYABLE ? Counterparty::SUPPLIER : Counterparty::CUSTOMER,
        'counterparty_id' => $direction === FinancialDirection::PAYABLE ? '3101' : '2101',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p11_assert(is_callable($activate), 'P11 activation hook is available');
$GLOBALS['sc_test_options']['safecontracts_general_settings'] = ['currency_code' => 'KWD'];
$activate();

sc_p11_assert(Migrator::LATEST_VERSION === '1.16.0', 'P11 migration is the current schema version');
$schema = implode("\n---\n", $GLOBALS['sc_test_dbdelta']);
sc_p11_assert(str_contains($schema, 'wp_safecontracts_suppliers'), 'P11 creates dedicated supplier master data');
sc_p11_assert(str_contains($schema, 'customer_id bigint(20) unsigned NULL'), 'P11 makes legacy customer bridge nullable for supplier contracts');
sc_p11_assert(str_contains($schema, 'counterparty_type varchar(16) NULL'), 'P11 persists explicit contract counterparty type');
sc_p11_assert(str_contains($schema, 'counterparty_id bigint(20) unsigned NULL'), 'P11 persists explicit contract counterparty id');
sc_p11_assert(str_contains($schema, 'financial_direction varchar(16) NULL'), 'P11 persists AP/AR direction');
sc_p11_assert(str_contains($schema, 'currency_code char(3) NULL'), 'P11 persists explicit currency scope');
sc_p11_assert(! str_contains($schema, 'is_paid tinyint'), 'P11 does not introduce a boolean-only paid model');

$migrationSql = implode("\n", $GLOBALS['sc_test_queries']);
sc_p11_assert(
    str_contains($migrationSql, "SET counterparty_type = 'customer', counterparty_id = customer_id"),
    'legacy customer_id is the only proof used to backfill Customer counterparties'
);
sc_p11_assert(
    str_contains($migrationSql, "SET financial_direction = 'receivable'") && str_contains($migrationSql, "WHERE counterparty_type = 'customer'"),
    'legacy customer contracts are deterministically backfilled as receivable'
);
sc_p11_assert(
    ! str_contains($migrationSql, "SET counterparty_type = 'supplier'"),
    'legacy migration never fabricates Supplier classification'
);
sc_p11_assert(
    str_contains($migrationSql, "p.financial_direction = COALESCE") && str_contains($migrationSql, "cl.financial_direction = COALESCE"),
    'obligations and settlement history inherit the migrated financial context'
);

$manager = $GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER] ?? null;
$accountant = $GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT] ?? null;
$viewer = $GLOBALS['sc_test_roles'][RoleRegistrar::VIEWER] ?? null;
sc_p11_assert($manager instanceof SC_Test_Role && ! empty($manager->capabilities[Capabilities::MANAGE_SUPPLIERS]), 'Manager baseline receives supplier management capability');
sc_p11_assert($manager instanceof SC_Test_Role && ! empty($manager->capabilities[Capabilities::MANAGE_FINANCE]), 'Manager baseline receives finance management capability');
sc_p11_assert($accountant instanceof SC_Test_Role && ! empty($accountant->capabilities[Capabilities::VIEW_SUPPLIERS]), 'Accountant baseline can view suppliers');
sc_p11_assert($accountant instanceof SC_Test_Role && ! empty($accountant->capabilities[Capabilities::MANAGE_FINANCE]), 'Accountant baseline can manage AP/AR settlements');
sc_p11_assert($viewer instanceof SC_Test_Role && ! empty($viewer->capabilities[Capabilities::VIEW_FINANCE]), 'Viewer baseline can read currency-safe finance data');

sc_p11_assert(Counterparty::defaultFinancialDirection(Counterparty::CUSTOMER) === FinancialDirection::RECEIVABLE, 'Customer contracts map to receivable direction');
sc_p11_assert(Counterparty::defaultFinancialDirection(Counterparty::SUPPLIER) === FinancialDirection::PAYABLE, 'Supplier contracts map to payable direction');
sc_p11_assert(CurrencyCode::fromInputOrSettings(null) === 'KWD', 'configured currency becomes explicit contract currency');
sc_p11_expect(InvalidArgumentException::class, fn () => Counterparty::normalize('vendor'), 'unsupported counterparty labels are rejected');
sc_p11_expect(InvalidArgumentException::class, fn () => FinancialDirection::normalize('incoming'), 'unsupported finance directions are rejected');
sc_p11_expect(InvalidArgumentException::class, fn () => CurrencyCode::normalize('KD'), 'malformed currency codes are rejected');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::CREATE_SUPPLIERS => true,
    Capabilities::EDIT_SUPPLIERS => true,
    Capabilities::VIEW_SUPPLIERS => true,
];
$GLOBALS['wpdb']->insert_id = 3101;
$supplierId = (new SupplierService())->save([
    'internal_code' => 'SUP-3101',
    'name' => 'P11 Supplier',
    'contact_name' => 'Accounts Payable',
    'email' => 'ap@example.test',
    'phone' => '+96555555555',
    'notes' => 'Foundation supplier',
]);
sc_p11_assert($supplierId === 3101, 'Supplier service creates supplier without fabricating a customer');
$supplierInsert = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($supplierInsert, 'wp_safecontracts_suppliers'), 'Supplier creation writes only supplier master table');
sc_p11_assert(! str_contains($supplierInsert, 'wp_safecontracts_customers'), 'Supplier creation does not duplicate Customer data');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '3101', 'internal_code' => 'SUP-3101', 'name' => 'P11 Supplier', 'contact_name' => '',
    'email' => '', 'phone' => '', 'notes' => '', 'is_active' => '1', 'is_archived' => '0',
    'archived_by' => null, 'archived_at' => null, 'created_by' => '42', 'updated_by' => '42',
    'created_at' => '2026-08-17 19:00:00', 'updated_at' => '2026-08-17 19:00:00',
]]];
(new SupplierService())->archive(3101);
$supplierArchive = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($supplierArchive, 'is_archived = 1') && str_contains($supplierArchive, 'is_active = 0'), 'Supplier deletion is archival and deactivates future use');
sc_p11_assert(! str_starts_with(ltrim($supplierArchive), 'DELETE'), 'Supplier archival never hard-deletes audit history');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::CREATE_CONTRACTS => true,
    Capabilities::VIEW_SUPPLIERS => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '3101']]];
$GLOBALS['wpdb']->insert_id = 4101;
$supplierContractId = (new CounterpartyContractService())->create([
    'contract_number' => 'SUP-CON-4101',
    'counterparty_type' => 'supplier',
    'counterparty_id' => 3101,
    'currency_code' => 'KWD',
]);
sc_p11_assert($supplierContractId === 4101, 'Supplier contract can be represented as a real contract counterparty');
$supplierContractSql = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($supplierContractSql, 'customer_id, counterparty_type, counterparty_id, financial_direction, currency_code'), 'Supplier contract persists explicit counterparty and financial context');
sc_p11_assert(str_contains($supplierContractSql, "VALUES ('SUP-CON-4101', NULL, 'supplier', 3101, 'payable', 'KWD'"), 'Supplier contract stores NULL customer bridge and payable KWD context');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::CREATE_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[['id' => '2101']]];
$GLOBALS['wpdb']->insert_id = 4102;
$customerContractId = (new CounterpartyContractService())->create([
    'contract_number' => 'CUS-CON-4102',
    'counterparty_type' => 'customer',
    'counterparty_id' => 2101,
    'currency_code' => 'USD',
]);
sc_p11_assert($customerContractId === 4102, 'Customer contract remains supported through explicit counterparty model');
$customerContractSql = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($customerContractSql, "VALUES ('CUS-CON-4102', 2101, 'customer', 2101, 'receivable', 'USD'"), 'Customer contract preserves customer bridge and receivable USD context');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_PAYMENTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '4101', 'accountant_user_id' => '42', 'is_archived' => '0',
    'counterparty_type' => 'supplier', 'counterparty_id' => '3101',
    'financial_direction' => 'payable', 'currency_code' => 'KWD',
]]];
$GLOBALS['wpdb']->insert_id = 8101;
$apPaymentId = (new PaymentService())->create([
    'contract_id' => 4101,
    'sequence_no' => 1,
    'due_date' => '2026-09-30',
    'original_amount' => '100',
]);
sc_p11_assert($apPaymentId === 8101, 'Supplier obligation is created through existing payment service');
$apPaymentSql = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($apPaymentSql, "4101, 'payable', 'KWD', 1"), 'Supplier obligation inherits payable KWD from validated contract context');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '4102', 'accountant_user_id' => '42', 'is_archived' => '0',
    'counterparty_type' => 'customer', 'counterparty_id' => '2101',
    'financial_direction' => 'receivable', 'currency_code' => 'USD',
]]];
$GLOBALS['wpdb']->insert_id = 8102;
$arPaymentId = (new PaymentService())->create([
    'contract_id' => 4102,
    'sequence_no' => 1,
    'due_date' => '2026-09-30',
    'original_amount' => '100',
]);
sc_p11_assert($arPaymentId === 8102, 'Customer obligation is created through same payment service');
$arPaymentSql = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($arPaymentSql, "4102, 'receivable', 'USD', 1"), 'Customer obligation inherits receivable USD from validated contract context');

$settlements = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_FINANCE => true,
];
$GLOBALS['sc_test_result_queue'] = [
    [sc_p11_payment('payable', 'KWD')],
    [['id' => '2']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9101;
$beforeApSettlement = count($GLOBALS['sc_test_queries']);
$apSettlementId = $settlements->record([
    'payment_id' => 8101,
    'amount' => '25',
    'collection_date' => '2026-08-17',
    'payment_method_id' => 2,
]);
sc_p11_assert($apSettlementId === 9101, 'AP partial settlement appends a settlement row');
$apSettlementSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeApSettlement));
sc_p11_assert(str_contains($apSettlementSql, "8101, 'payable', 'KWD', '25.0000'"), 'AP settlement is immutably tagged payable KWD');
sc_p11_assert(str_contains($apSettlementSql, "remaining_amount = '75.0000'") && str_contains($apSettlementSql, "status = 'partially_paid'"), 'AP partial settlement updates exact remaining balance and lifecycle');

$GLOBALS['sc_test_result_queue'] = [
    [sc_p11_payment('receivable', 'USD', ['id' => '8102', 'contract_id' => '4102', 'counterparty_id' => '2101'])],
    [['id' => '2']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9102;
$beforeArSettlement = count($GLOBALS['sc_test_queries']);
$arSettlementId = $settlements->record([
    'payment_id' => 8102,
    'amount' => '40',
    'collection_date' => '2026-08-17',
    'payment_method_id' => 2,
]);
sc_p11_assert($arSettlementId === 9102, 'AR partial settlement uses the same append-only ledger');
$arSettlementSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeArSettlement));
sc_p11_assert(str_contains($arSettlementSql, "8102, 'receivable', 'USD', '40.0000'"), 'AR settlement is immutably tagged receivable USD');
sc_p11_assert(str_contains($arSettlementSql, "remaining_amount = '60.0000'") && str_contains($arSettlementSql, "status = 'partially_paid'"), 'AR partial settlement updates exact remaining balance and lifecycle');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::VIEW_FINANCE => true];
$GLOBALS['sc_test_result_queue'] = [[
    ['financial_direction' => 'payable', 'currency_code' => 'KWD', 'obligation_count' => '1', 'scheduled_total' => '100.0000', 'settled_total' => '25.0000', 'outstanding_total' => '75.0000'],
    ['financial_direction' => 'receivable', 'currency_code' => 'USD', 'obligation_count' => '1', 'scheduled_total' => '100.0000', 'settled_total' => '40.0000', 'outstanding_total' => '60.0000'],
]];
$summary = (new CounterpartyReadRepository())->financialSummary();
sc_p11_assert(count($summary) === 2, 'AP and AR/currencies remain separate finance summary buckets');
$summarySql = (string) end($GLOBALS['sc_test_read_queries']);
sc_p11_assert(str_contains($summarySql, 'GROUP BY p.financial_direction, p.currency_code'), 'finance metrics never aggregate across direction or currency');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::ASSIGN_CONTRACTS => true,
    Capabilities::VIEW_SUPPLIERS => true,
];
$GLOBALS['sc_test_result_queue'] = [
    [[
        'id' => '4102', 'contract_number' => 'CUS-CON-4102', 'customer_id' => '2101',
        'counterparty_type' => 'customer', 'counterparty_id' => '2101', 'financial_direction' => 'receivable',
        'currency_code' => 'USD', 'accountant_user_id' => '42', 'status' => 'draft', 'start_date' => null,
        'end_date' => null, 'base_value' => '0.0000', 'notes' => '', 'is_archived' => '0',
    ]],
    [['id' => '8102']],
];
sc_p11_expect(
    DomainException::class,
    fn () => (new CounterpartyContractService())->assign(4102, 'supplier', 3101),
    'counterparty direction cannot flip after financial obligations exist'
);

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '4101', 'contract_number' => 'SUP-CON-4101', 'customer_id' => null,
    'counterparty_type' => 'supplier', 'counterparty_id' => '3101', 'counterparty_name' => 'Archived Supplier',
    'customer_name' => null, 'supplier_id' => '3101', 'supplier_name' => 'Archived Supplier',
    'financial_direction' => 'payable', 'currency_code' => 'KWD', 'accountant_user_id' => '42',
    'status' => 'active', 'start_date' => null, 'end_date' => null, 'base_value' => '100.0000', 'notes' => '',
    'is_archived' => '0', 'created_at' => '2026-08-17 19:00:00', 'updated_at' => '2026-08-17 19:00:00',
]]];
$archivedSupplierContract = (new CounterpartyReadRepository())->contracts(['counterparty_type' => 'supplier', 'counterparty_id' => 3101]);
sc_p11_assert(count($archivedSupplierContract) === 1, 'archiving a supplier does not erase contract history from the counterparty read model');
$historySql = (string) end($GLOBALS['sc_test_read_queries']);
sc_p11_assert(! str_contains($historySql, 'su.is_archived = 0'), 'historical contract reads do not depend on supplier active/archive state');

sc_p11_expect(
    InvalidArgumentException::class,
    fn () => ApiRequest::filters(new WP_REST_Request(['counterparty_type' => 'vendor'])),
    'REST filters reject unknown counterparty types instead of silently widening scope'
);
sc_p11_expect(
    InvalidArgumentException::class,
    fn () => ApiRequest::filters(new WP_REST_Request(['financial_direction' => 'incoming'])),
    'REST filters reject unknown financial directions'
);
sc_p11_expect(
    InvalidArgumentException::class,
    fn () => ApiRequest::filters(new WP_REST_Request(['currency_code' => 'KD'])),
    'REST filters reject malformed currency filters'
);

Router::register();
$contractRoute = $GLOBALS['sc_test_routes']['safecontracts/v1/contracts'] ?? null;
$supplierRoute = $GLOBALS['sc_test_routes']['safecontracts/v1/suppliers'] ?? null;
$counterpartyRoute = $GLOBALS['sc_test_routes']['safecontracts/v1/contracts/(?P<id>\d+)/counterparty'] ?? null;
$financeRoute = $GLOBALS['sc_test_routes']['safecontracts/v1/finance/summary'] ?? null;
sc_p11_assert(is_array($contractRoute) && count($contractRoute) === 2, 'contract collection route exposes GET and explicit counterparty POST together');
sc_p11_assert(is_array($supplierRoute), 'supplier REST lifecycle is registered');
sc_p11_assert(is_array($counterpartyRoute), 'counterparty reassignment route is registered');
sc_p11_assert(is_array($financeRoute), 'currency-safe finance summary route is registered');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_p11_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'P11 migration remains idempotent once 1.16.0 is stored');

echo "SafeContracts P11 counterparty/supplier/AP-AR tests passed ({$p11Tests} assertions).\n";
