<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Roles\AccessScope;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p2_auth_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
];
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
TenantContextStore::reset();

esc_p2_auth_assert(AccessScope::canAccess(), 'legacy access remains capability-driven when ESC enforcement is disabled');

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
esc_p2_auth_assert(AccessScope::canAccess(), 'unlocked non-tenant path keeps existing behavior before tenant-owned context is selected');

TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_results'] = [];
esc_p2_auth_assert(! AccessScope::canAccess(), 'global capabilities do not authorize a user without active membership in locked tenant');
$staleSql = end($GLOBALS['sc_test_read_queries']);
esc_p2_auth_assert(str_contains((string) $staleSql, 'm.tenant_id = 17'), 'tenant authorization revalidation queries the locked tenant');
esc_p2_auth_assert(str_contains((string) $staleSql, "m.status = 'active'"), 'tenant authorization requires active membership');
esc_p2_auth_assert(str_contains((string) $staleSql, "t.status = 'active'"), 'tenant authorization requires active tenant');

$GLOBALS['sc_test_results'] = [['id' => '1']];
esc_p2_auth_assert(AccessScope::canAccess(), 'active tenant member with global access capability is authorized');

// Membership is revalidated on every authorization check so a membership that
// becomes stale after context locking fails closed without changing context.
$GLOBALS['sc_test_results'] = [];
esc_p2_auth_assert(! AccessScope::canAccess(), 'stale membership after context locking fails closed');

TenantContextStore::reset();
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '1';
TenantContextStore::context()->setTenantId(23);
$GLOBALS['sc_test_results'] = [];
esc_p2_auth_assert(! AccessScope::canAccess(), 'non-core enforcement uses the same tenant membership authorization boundary');
$GLOBALS['sc_test_results'] = [['id' => '1']];
esc_p2_auth_assert(AccessScope::canAccess(), 'active membership authorizes non-core locked tenant context');

$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_ALL => true];
esc_p2_auth_assert(! AccessScope::canAccess(), 'tenant membership never replaces the global SafeContracts access capability ceiling');

$root = dirname(__DIR__, 2);
$guardSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/CoreTenantRestGuard.php');
$resolvePosition = strpos($guardSource, '$tenantId = TenantRequestContext::resolve($request, true);');
$accessPosition = strpos($guardSource, '$access = Permission::access();');
esc_p2_auth_assert($resolvePosition !== false && $accessPosition !== false && $resolvePosition < $accessPosition, 'core REST guard locks tenant context before tenant-aware authorization');

$scopeSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Roles/AccessScope.php');
esc_p2_auth_assert(str_contains($scopeSource, 'TenantAuthorization::currentUserHasActiveMembership()'), 'AccessScope is wired to the shared tenant authorization boundary');

TenantContextStore::reset();
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_current_caps'] = [];
$GLOBALS['sc_test_results'] = [];

fwrite(STDOUT, "Enterprise tenant authorization P2-001 passed ({$assertions} assertions).\n");
