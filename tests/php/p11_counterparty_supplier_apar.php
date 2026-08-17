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
sc_p11_assert(str_contains($schema, 'customer_id bigint(20) unsigned NULL'), 'legacy customer bridge becomes nullable');
sc_p11_assert(str_contains($schema, 'counterparty_type varchar(16) NULL') && str_contains($schema, 'counterparty_id bigint(20) unsigned NULL'), 'contracts persist explicit counterparty identity');
sc_p11_assert(str_contains($schema, 'financial_direction varchar(16) NULL') && str_contains($schema, 'currency_code char(3) NULL'), 'financial rows persist AP/AR direction and currency');
sc_p11_assert(! str_contains($schema, 'is_paid tinyint'), 'P11 does not introduce a boolean-only paid model');

$migrationSql = implode("\n", $GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($migrationSql, "SET counterparty_type = 'customer', counterparty_id = customer_id"), 'customer_id is the proof used for legacy Customer backfill');
sc_p11_assert(str_contains($migrationSql, "SET financial_direction = 'receivable'") && str_contains($migrationSql, "WHERE counterparty_type = 'customer'"), 'legacy Customer contracts become receivable');
sc_p11_assert(! str_contains($migrationSql, "SET counterparty_type = 'supplier'"), 'legacy migration never fabricates Supplier classification');
sc_p11_assert(str_contains($migrationSql, 'p.financial_direction = COALESCE') && str_contains($migrationSql, 'cl.financial_direction = COALESCE'), 'obligations and ledger rows inherit migrated financial context');

$manager = $GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER] ?? null;
$accountant = $GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT] ?? null;
$viewer = $GLOBALS['sc_test_roles'][RoleRegistrar::VIEWER] ?? null;
sc_p11_assert($manager instanceof SC_Test_Role && ! empty($manager->capabilities[Capabilities::MANAGE_SUPPLIERS]), 'Manager baseline receives Supplier management');
sc_p11_assert($manager instanceof SC_Test_Role && ! empty($manager->capabilities[Capabilities::MANAGE_FINANCE]), 'Manager baseline receives finance management');
sc_p11_assert($accountant instanceof SC_Test_Role && ! empty($accountant->capabilities[Capabilities::VIEW_SUPPLIERS]) && ! empty($accountant->capabilities[Capabilities::MANAGE_FINANCE]), 'Accountant baseline receives Supplier read and finance management');
sc_p11_assert($viewer instanceof SC_Test_Role && ! empty($viewer->capabilities[Capabilities::VIEW_FINANCE]), 'Viewer baseline receives finance read');

sc_p11_assert(Counterparty::defaultFinancialDirection('customer') === 'receivable', 'Customer maps to receivable');
sc_p11_assert(Counterparty::defaultFinancialDirection('supplier') === 'payable', 'Supplier maps to payable');
sc_p11_assert(CurrencyCode::fromInputOrSettings(null) === 'KWD', 'configured currency is explicit');
sc_p11_expect(InvalidArgumentException::class, fn () => Counterparty::normalize('vendor'), 'unsupported counterparty type is rejected');
sc_p11_expect(InvalidArgumentException::class, fn () => FinancialDirection::normalize('incoming'), 'unsupported financial direction is rejected');
sc_p11_expect(InvalidArgumentException::class, fn () => CurrencyCode::normalize('KD'), 'malformed currency is rejected');

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
]);
sc_p11_assert($supplierId === 3101, 'Supplier service creates a Supplier without a fake Customer');
$supplierInsert = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($supplierInsert, 'wp_safecontracts_suppliers') && ! str_contains($supplierInsert, 'wp_safecontracts_customers'), 'Supplier write is isolated to Supplier master');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '3101', 'internal_code' => 'SUP-3101', 'name' => 'P11 Supplier', 'contact_name' => '',
    'email' => '', 'phone' => '', 'notes' => '', 'is_active' => '1', 'is_archived' => '0',
    'archived_by' => null, 'archived_at' => null, 'created_by' => '42', 'updated_by' => '42',
    'created_at' => '2026-08-17 19:00:00', 'updated_at' => '2026-08-17 19:00:00',
]]];
(new SupplierService())->archive(3101);
$supplierArchive = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($supplierArchive, 'is_archived = 1') && str_contains($supplierArchive, 'is_active = 0'), 'Supplier removal is soft archival');
sc_p11_assert(! str_starts_with(ltrim($supplierArchive), 'DELETE'), 'Supplier archival never hard-deletes history');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::CREATE_CONTRACTS => true, Capabilities::VIEW_SUPPLIERS => true];
$GLOBALS['sc_test_result_queue'] = [[['id' => '3101']]];
$GLOBALS['wpdb']->insert_id = 4101;
$supplierContractId = (new CounterpartyContractService())->create([
    'contract_number' => 'SUP-CON-4101',
    'counterparty_type' => 'supplier',
    'counterparty_id' => 3101,
    'currency_code' => 'KWD',
]);
sc_p11_assert($supplierContractId === 4101, 'Supplier contract is created as a real contract counterparty');
$supplierContractSql = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($supplierContractSql, 'contract_number, customer_id, accountant_user_id, counterparty_type, counterparty_id, financial_direction, currency_code'), 'P11 contract fields coexist with legacy fields');
sc_p11_assert(str_contains($supplierContractSql, "'SUP-CON-4101', NULL, NULL, 'supplier', 3101, 'payable', 'KWD'"), 'Supplier contract has NULL customer bridge and payable KWD context');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::CREATE_CONTRACTS => true];
$GLOBALS['sc_test_result_queue'] = [[['id' => '2101']]];
$GLOBALS['wpdb']->insert_id = 4102;
$customerContractId = (new CounterpartyContractService())->create([
    'contract_number' => 'CUS-CON-4102',
    'counterparty_type' => 'customer',
    'counterparty_id' => 2101,
    'currency_code' => 'USD',
]);
sc_p11_assert($customerContractId === 4102, 'Customer contract remains supported by explicit counterparty model');
$customerContractSql = (string) end($GLOBALS['sc_test_queries']);
sc_p11_assert(str_contains($customerContractSql, "'CUS-CON-4102', 2101, NULL, 'customer', 2101, 'receivable', 'USD'"), 'Customer contract keeps legacy bridge and receivable USD context');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::MANAGE_PAYMENTS => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '4101', 'accountant_user_id' => '42', 'is_archived' => '0',
    'counterparty_type' => 'supplier', 'counterparty_id' => '3101', 'financial_direction' => 'payable', 'currency_code' => 'KWD',
]]];
$GLOBALS['wpdb']->insert_id = 8101;
$apPaymentId = (new PaymentService())->create(['contract_id' => 4101, 'sequence_no' => 1, 'due_date' => '2026-09-30', 'original_amount' => '100']);
sc_p11_assert($apPaymentId === 8101, 'Supplier obligation uses existing payment service');
sc_p11_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "4101, 'payable', 'KWD', 1"), 'Supplier obligation inherits payable KWD');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '4102', 'accountant_user_id' => '42', 'is_archived' => '0',
    'counterparty_type' => 'customer', 'counterparty_id' => '2101', 'financial_direction' => 'receivable', 'currency_code' => 'USD',
]]];
$GLOBALS['wpdb']->insert_id = 8102;
$arPaymentId = (new PaymentService())->create(['contract_id' => 4102, 'sequence_no' => 1, 'due_date' => '2026-09-30', 'original_amount' => '100']);
sc_p11_assert($arPaymentId === 8102, 'Customer obligation uses same payment service');
sc_p11_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "4102, 'receivable', 'USD', 1"), 'Customer obligation inherits receivable USD');

$settlements = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::MANAGE_FINANCE => true];
$GLOBALS['sc_test_result_queue'] = [[sc_p11_payment('payable', 'KWD')], [['id' => '2']], [['total' => '0.0000']]];
$GLOBALS['wpdb']->insert_id = 9101;
$before = count($GLOBALS['sc_test_queries']);
sc_p11_assert($settlements->record(['payment_id' => 8101, 'amount' => '25', 'collection_date' => '2026-08-17', 'payment_method_id' => 2]) === 9101, 'AP partial settlement appends a ledger row');
$apSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $before));
sc_p11_assert(str_contains($apSql, "8101, 'payable', 'KWD', '25.0000'") && str_contains($apSql, "remaining_amount = '75.0000'") && str_contains($apSql, "status = 'partially_paid'"), 'AP partial settlement preserves direction/currency and exact balance');

$GLOBALS['sc_test_result_queue'] = [[sc_p11_payment('receivable', 'USD', ['id' => '8102', 'contract_id' => '4102', 'counterparty_id' => '2101'])], [['id' => '2']], [['total' => '0.0000']]];
$GLOBALS['wpdb']->insert_id = 9102;
$before = count($GLOBALS['sc_test_queries']);
sc_p11_assert($settlements->record(['payment_id' => 8102, 'amount' => '40', 'collection_date' => '2026-08-17', 'payment_method_id' => 2]) === 9102, 'AR partial settlement uses same append-only ledger');
$arSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $before));
sc_p11_assert(str_contains($arSql, "8102, 'receivable', 'USD', '40.0000'") && str_contains($arSql, "remaining_amount = '60.0000'") && str_contains($arSql, "status = 'partially_paid'"), 'AR partial settlement preserves direction/currency and exact balance');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::VIEW_FINANCE => true];
$GLOBALS['sc_test_result_queue'] = [[
    ['financial_direction' => 'payable', 'currency_code' => 'KWD', 'obligation_count' => '1', 'scheduled_total' => '100.0000', 'settled_total' => '25.0000', 'outstanding_total' => '75.0000'],
    ['financial_direction' => 'receivable', 'currency_code' => 'USD', 'obligation_count' => '1', 'scheduled_total' => '100.0000', 'settled_total' => '40.0000', 'outstanding_total' => '60.0000'],
]];
$summary = (new CounterpartyReadRepository())->financialSummary();
sc_p11_assert(count($summary) === 2, 'AP/AR currencies remain separate financial buckets');
sc_p11_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'GROUP BY p.financial_direction, p.currency_code'), 'finance summary never cross-sums direction or currency');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::ASSIGN_CONTRACTS => true, Capabilities::VIEW_SUPPLIERS => true];
$GLOBALS['sc_test_result_queue'] = [
    [[
        'id' => '4102', 'contract_number' => 'CUS-CON-4102', 'customer_id' => '2101', 'counterparty_type' => 'customer',
        'counterparty_id' => '2101', 'financial_direction' => 'receivable', 'currency_code' => 'USD', 'accountant_user_id' => '42',
        'status' => 'draft', 'start_date' => null, 'end_date' => null, 'base_value' => '0.0000', 'notes' => '', 'is_archived' => '0',
    ]],
    [['id' => '8102']],
];
sc_p11_expect(DomainException::class, fn () => (new CounterpartyContractService())->assign(4102, 'supplier', 3101), 'counterparty cannot flip after obligations exist');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '4101', 'contract_number' => 'SUP-CON-4101', 'customer_id' => null, 'counterparty_type' => 'supplier',
    'counterparty_id' => '3101', 'counterparty_name' => 'Archived Supplier', 'customer_name' => null, 'supplier_id' => '3101',
    'supplier_name' => 'Archived Supplier', 'financial_direction' => 'payable', 'currency_code' => 'KWD', 'accountant_user_id' => '42',
    'status' => 'active', 'start_date' => null, 'end_date' => null, 'base_value' => '100.0000', 'notes' => '', 'is_archived' => '0',
    'created_at' => '2026-08-17 19:00:00', 'updated_at' => '2026-08-17 19:00:00',
]]];
sc_p11_assert(count((new CounterpartyReadRepository())->contracts(['counterparty_type' => 'supplier', 'counterparty_id' => 3101])) === 1, 'archived Supplier does not erase historical contract visibility');
sc_p11_assert(! str_contains((string) end($GLOBALS['sc_test_read_queries']), 'su.is_archived = 0'), 'historical reads do not require active Supplier state');

sc_p11_expect(InvalidArgumentException::class, fn () => ApiRequest::filters(new WP_REST_Request(['counterparty_type' => 'vendor'])), 'REST rejects unknown counterparty filter');
sc_p11_expect(InvalidArgumentException::class, fn () => ApiRequest::filters(new WP_REST_Request(['financial_direction' => 'incoming'])), 'REST rejects unknown direction filter');
sc_p11_expect(InvalidArgumentException::class, fn () => ApiRequest::filters(new WP_REST_Request(['currency_code' => 'KD'])), 'REST rejects malformed currency filter');

Router::register();
sc_p11_assert(is_array($GLOBALS['sc_test_routes']['safecontracts/v1/contracts'] ?? null) && count($GLOBALS['sc_test_routes']['safecontracts/v1/contracts']) === 2, 'contracts route registers GET and POST together');
sc_p11_assert(is_array($GLOBALS['sc_test_routes']['safecontracts/v1/suppliers'] ?? null), 'Supplier REST route is registered');
sc_p11_assert(is_array($GLOBALS['sc_test_routes']['safecontracts/v1/contracts/(?P<id>\d+)/counterparty'] ?? null), 'counterparty assignment REST route is registered');
sc_p11_assert(is_array($GLOBALS['sc_test_routes']['safecontracts/v1/finance/summary'] ?? null), 'currency-safe finance summary route is registered');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_p11_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'P11 migration is idempotent after 1.16.0');

echo "SafeContracts P11 counterparty/supplier/AP-AR tests passed ({$p11Tests} assertions).\n";
