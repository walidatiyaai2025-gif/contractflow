<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Customers\CustomerRepository;
use SafeContracts\Deletion\SafeDeletionService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_admin_tenant_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_admin_tenant_query_contains(string $needle): bool
{
    foreach (array_merge($GLOBALS['sc_test_read_queries'], $GLOBALS['sc_test_queries']) as $query) {
        if (str_contains((string) $query, $needle)) {
            return true;
        }
    }
    return false;
}

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
    Capabilities::MANAGE_PAYMENTS => true,
    Capabilities::MANAGE_COLLECTIONS => true,
];

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [];
(new CustomerRepository())->find(11);
esc_admin_tenant_assert(esc_admin_tenant_query_contains('WHERE id = 11 AND tenant_id = 17'), 'customer detail is tenant scoped');

$GLOBALS['sc_test_queries'] = [];
(new CustomerRepository())->create([
    'internal_code' => 'C-17',
    'name' => 'Tenant Customer',
    'contact_name' => '',
    'email' => '',
    'phone' => '',
    'notes' => '',
    'is_active' => true,
], 42);
esc_admin_tenant_assert(esc_admin_tenant_query_contains('INSERT INTO wp_safecontracts_customers (tenant_id,'), 'new customer derives tenant ownership from server context');

$GLOBALS['sc_test_queries'] = [];
(new CustomerRepository())->update(11, [
    'internal_code' => 'C-17',
    'name' => 'Tenant Customer 2',
    'contact_name' => '',
    'email' => '',
    'phone' => '',
    'notes' => '',
    'is_active' => true,
]);
esc_admin_tenant_assert(esc_admin_tenant_query_contains('WHERE id = 11 AND tenant_id = 17'), 'customer mutation is tenant scoped');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_results'] = [['id' => '11', 'is_active' => '1']];
(new SafeDeletionService())->archiveCustomer(11);
esc_admin_tenant_assert(esc_admin_tenant_query_contains('SELECT id, is_active FROM wp_safecontracts_customers WHERE id = 11 AND tenant_id = 17'), 'customer deletion lookup is tenant scoped');
esc_admin_tenant_assert(esc_admin_tenant_query_contains('UPDATE wp_safecontracts_customers SET is_active = 0'), 'customer archive update executes');
esc_admin_tenant_assert(esc_admin_tenant_query_contains('WHERE id = 11 AND tenant_id = 17'), 'customer archive update remains tenant scoped');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '21',
    'paid_amount' => '0.0000',
    'status' => 'upcoming',
    'is_archived' => '0',
    'accountant_user_id' => '42',
]], []];
$GLOBALS['sc_test_results'] = [];
(new SafeDeletionService())->archivePayment(21);
esc_admin_tenant_assert(esc_admin_tenant_query_contains('p.tenant_id = 17'), 'payment deletion lookup is tenant scoped');
esc_admin_tenant_assert(esc_admin_tenant_query_contains('c.tenant_id = 17'), 'payment deletion parent contract is tenant scoped');
esc_admin_tenant_assert(esc_admin_tenant_query_contains('WHERE id = 21 AND tenant_id = 17'), 'payment archive mutation is tenant scoped');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise admin/core deletion tenant enforcement passed ({$assertions} assertions).\n");
