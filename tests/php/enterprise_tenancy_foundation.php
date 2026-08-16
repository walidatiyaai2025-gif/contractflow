<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Tenancy\TenantContext;
use SafeContracts\Tenancy\TenantMembershipRepository;
use SafeContracts\Tenancy\TenantResolver;

$tests = 0;

function esc_tenant_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_tenant_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        esc_tenant_assert(true, $message);
        return;
    }
    esc_tenant_assert(false, $message);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();
esc_tenant_assert(Migrator::LATEST_VERSION === '1.16.0', 'enterprise tenancy/ownership expansion migration is current');

$tenantSchema = '';
$membershipSchema = '';
foreach ($GLOBALS['sc_test_dbdelta'] as $schema) {
    if (str_contains($schema, 'wp_safecontracts_tenants')) {
        $tenantSchema = $schema;
    }
    if (str_contains($schema, 'wp_safecontracts_tenant_memberships')) {
        $membershipSchema = $schema;
    }
}
esc_tenant_assert($tenantSchema !== '', 'tenant registry table is migrated');
esc_tenant_assert(str_contains($tenantSchema, 'UNIQUE KEY uuid (uuid)'), 'tenant UUID is unique');
esc_tenant_assert(str_contains($tenantSchema, 'UNIQUE KEY slug (slug)'), 'tenant slug is unique');
esc_tenant_assert(str_contains($tenantSchema, "timezone varchar(64) NOT NULL DEFAULT 'UTC'"), 'tenant timezone has safe default');
esc_tenant_assert(str_contains($tenantSchema, "default_currency char(3) NOT NULL DEFAULT 'USD'"), 'tenant default currency is stored');
esc_tenant_assert(str_contains($tenantSchema, "locale varchar(20) NOT NULL DEFAULT 'en_US'"), 'tenant locale is stored');
esc_tenant_assert($membershipSchema !== '', 'tenant membership table is migrated');
esc_tenant_assert(str_contains($membershipSchema, 'UNIQUE KEY tenant_user (tenant_id, user_id)'), 'membership is unique per tenant/user');
esc_tenant_assert(str_contains($membershipSchema, 'KEY user_status (user_id, status, tenant_id)'), 'user tenant lookup is indexed');

$context = new TenantContext();
esc_tenant_assert(! $context->hasTenant(), 'tenant context starts empty');
esc_tenant_throws(static fn () => $context->requireTenantId(), 'missing tenant fails closed');
$context->setTenantId(7);
esc_tenant_assert($context->requireTenantId() === 7, 'tenant context stores positive tenant id');
esc_tenant_throws(static fn () => $context->setTenantId(8), 'tenant context cannot silently switch during request');
$context->clear();
esc_tenant_assert(! $context->hasTenant(), 'tenant context can be cleared at request boundary');

$memberships = new TenantMembershipRepository();
$GLOBALS['sc_test_results'] = [
    ['tenant_id' => '11'],
    ['tenant_id' => '11'],
    ['tenant_id' => '15'],
];
esc_tenant_assert($memberships->activeTenantIdsForUser(42) === [11, 15], 'membership repository normalizes unique active tenant ids');
$membershipSql = end($GLOBALS['sc_test_read_queries']);
esc_tenant_assert(str_contains((string) $membershipSql, "m.status = 'active'"), 'membership lookup requires active membership');
esc_tenant_assert(str_contains((string) $membershipSql, "t.status = 'active'"), 'membership lookup requires active tenant');

$GLOBALS['sc_test_results'] = [['id' => '1']];
esc_tenant_assert($memberships->isActiveMember(11, 42), 'active requested membership is accepted');
$GLOBALS['sc_test_results'] = [];
esc_tenant_assert(! $memberships->isActiveMember(99, 42), 'missing requested membership is rejected');

$GLOBALS['sc_test_results'] = [['id' => '1']];
$requestedContext = new TenantContext();
$requestedResolver = new TenantResolver($memberships, $requestedContext);
esc_tenant_assert($requestedResolver->resolveForUser(42, 11) === 11, 'explicit authorized tenant resolves');
esc_tenant_assert($requestedContext->requireTenantId() === 11, 'explicit tenant locks request context');

$GLOBALS['sc_test_results'] = [];
$unauthorizedResolver = new TenantResolver($memberships, new TenantContext());
esc_tenant_throws(static fn () => $unauthorizedResolver->resolveForUser(42, 99), 'unauthorized tenant selection fails closed');

$GLOBALS['sc_test_results'] = [['tenant_id' => '21']];
$singleResolver = new TenantResolver($memberships, new TenantContext());
esc_tenant_assert($singleResolver->resolveForUser(42) === 21, 'single active membership may resolve automatically');

$GLOBALS['sc_test_results'] = [['tenant_id' => '21'], ['tenant_id' => '22']];
$multipleResolver = new TenantResolver($memberships, new TenantContext());
esc_tenant_throws(static fn () => $multipleResolver->resolveForUser(42), 'multiple memberships require explicit tenant selection');

$GLOBALS['sc_test_results'] = [];
$noneResolver = new TenantResolver($memberships, new TenantContext());
esc_tenant_throws(static fn () => $noneResolver->resolveForUser(42), 'user without membership cannot enter tenant context');

fwrite(STDOUT, "Enterprise tenancy foundation passed ({$tests} assertions).\n");
