<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Collections\CollectionReadRepository;
use SafeContracts\Collections\CollectionRepository;
use SafeContracts\Contracts\ContractArchiveRepository;
use SafeContracts\Contracts\ContractRepository;
use SafeContracts\FollowUps\FollowUpRepository;
use SafeContracts\Payments\PaymentRepository;
use SafeContracts\Rest\CoreTenantRestGuard;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\CoreTenantScope;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_enforcement_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_enforcement_query_contains(string $needle): bool
{
    foreach (array_merge($GLOBALS['sc_test_read_queries'], $GLOBALS['sc_test_queries']) as $query) {
        if (str_contains((string) $query, $needle)) {
            return true;
        }
    }
    return false;
}

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_enforcement_assert(! CoreTenantEnforcement::isEnabled(), 'core tenant enforcement defaults off');
esc_enforcement_assert(CoreTenantScope::tenantId() === null, 'disabled enforcement preserves legacy no-context behavior');

$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [['total' => '1']];
$blocked = false;
try {
    CoreTenantEnforcement::enable();
} catch (Throwable $error) {
    $blocked = str_contains($error->getMessage(), 'not ready');
}
esc_enforcement_assert($blocked, 'enforcement cannot be enabled before ownership verification is ready');
esc_enforcement_assert(! CoreTenantEnforcement::isEnabled(), 'failed readiness check does not enable enforcement');

$GLOBALS['sc_test_results'] = [['total' => '0']];
CoreTenantEnforcement::enable();
esc_enforcement_assert(CoreTenantEnforcement::isEnabled(), 'verified ownership may enable enforcement');

TenantContextStore::reset();
$missingContextBlocked = false;
try {
    CoreTenantScope::tenantId();
} catch (Throwable $error) {
    $missingContextBlocked = str_contains($error->getMessage(), 'tenant context is required');
}
esc_enforcement_assert($missingContextBlocked, 'enabled enforcement fails closed without TenantContext');

TenantContextStore::context()->setTenantId(17);
esc_enforcement_assert(CoreTenantScope::tenantId() === 17, 'locked TenantContext supplies authoritative tenant id');
esc_enforcement_assert(CoreTenantScope::condition('c.tenant_id') === ' AND c.tenant_id = 17', 'tenant SQL condition is server-derived');

foreach ([
    '/safecontracts/v1/customers',
    '/safecontracts/v1/contracts/99',
    '/safecontracts/v1/payments/12/followups',
    '/safecontracts/v1/collections/5',
    '/safecontracts/v1/dashboard',
    '/safecontracts/v1/reports/excel',
] as $route) {
    esc_enforcement_assert(CoreTenantRestGuard::isCoreBusinessRoute($route), "{$route} is protected by the core REST tenant gate");
}
foreach ([
    '/safecontracts/v1/health',
    '/safecontracts/v1/session',
    '/safecontracts/v1/me',
    '/safecontracts/v1/tenants',
] as $route) {
    esc_enforcement_assert(! CoreTenantRestGuard::isCoreBusinessRoute($route), "{$route} remains outside the core business gate");
}

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
];
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_read_queries'] = [];
(new AdminReadRepository())->contracts();
esc_enforcement_assert(esc_enforcement_query_contains('c.tenant_id = 17'), 'contract lists are tenant scoped');
esc_enforcement_assert(esc_enforcement_query_contains('cu.tenant_id = 17'), 'joined customer rows are tenant scoped');

$GLOBALS['sc_test_read_queries'] = [];
(new PaymentRepository())->find(501);
esc_enforcement_assert(esc_enforcement_query_contains('p.tenant_id = 17'), 'payment detail is tenant scoped');
esc_enforcement_assert(esc_enforcement_query_contains('c.tenant_id = 17'), 'payment parent contract is tenant scoped');

$GLOBALS['sc_test_read_queries'] = [];
(new CollectionReadRepository())->find(601);
esc_enforcement_assert(esc_enforcement_query_contains('cl.tenant_id = 17'), 'collection detail is tenant scoped');
esc_enforcement_assert(esc_enforcement_query_contains('p.tenant_id = 17'), 'collection payment parent is tenant scoped');

$GLOBALS['sc_test_read_queries'] = [];
(new FollowUpRepository())->history(701, 25);
esc_enforcement_assert(esc_enforcement_query_contains('tenant_id = 17'), 'follow-up history is tenant scoped');

$GLOBALS['sc_test_queries'] = [];
(new ContractArchiveRepository())->archive(801, 42);
esc_enforcement_assert(esc_enforcement_query_contains('tenant_id = 17'), 'contract archive mutation is tenant scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [['id' => '1']];
(new ContractRepository())->create('ESC-17-001', 10, null, '', 42);
esc_enforcement_assert(esc_enforcement_query_contains('INSERT INTO wp_safecontracts_contracts (tenant_id,'), 'new contracts derive tenant ownership from context');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [['id' => '10', 'accountant_user_id' => null, 'is_archived' => '0']];
(new PaymentRepository())->create(10, 1, null, '2026-09-01', null, '100.0000', 42);
esc_enforcement_assert(esc_enforcement_query_contains('INSERT INTO wp_safecontracts_scheduled_payments'), 'payment insert executes');
esc_enforcement_assert(esc_enforcement_query_contains('(tenant_id, contract_id'), 'new payments derive tenant ownership from context');

$GLOBALS['sc_test_queries'] = [];
(new CollectionRepository())->create(20, '10.0000', '2026-08-16', 1, null, null, null, 42);
esc_enforcement_assert(esc_enforcement_query_contains('(tenant_id, payment_id'), 'new collections derive tenant ownership from context');

$GLOBALS['sc_test_queries'] = [];
(new FollowUpRepository())->append(20, 'note', 'Tenant scoped', null, null, 42);
esc_enforcement_assert(esc_enforcement_query_contains('(tenant_id, payment_id'), 'new follow-ups derive tenant ownership from context');

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_enforcement_assert(! CoreTenantEnforcement::isEnabled(), 'controlled remediation can disable enforcement explicitly');

fwrite(STDOUT, "Enterprise core tenant runtime enforcement passed ({$assertions} assertions).\n");
