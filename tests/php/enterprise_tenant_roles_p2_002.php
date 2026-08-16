<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\Permission;
use SafeContracts\Roles\AccessScope;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Tenancy\TenantRolePolicy;

$assertions = 0;

function esc_p2_role_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p2_role_membership(string $roleCode, bool $owner = false): void
{
    $GLOBALS['sc_test_results'] = [[
        'id' => '501',
        'tenant_id' => '31',
        'user_id' => '42',
        'role_code' => $roleCode,
        'is_owner' => $owner ? '1' : '0',
    ]];
}

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(31);

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::MANAGE_PAYMENTS => true,
    Capabilities::VIEW_REPORTS => true,
    Capabilities::MANAGE_NOTIFICATIONS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];

esc_p2_role_membership(TenantRolePolicy::VIEWER);
esc_p2_role_assert(AccessScope::current() === AccessScope::ASSIGNED, 'viewer narrows a global all-data grant to assigned scope');
esc_p2_role_assert(Permission::capability(Capabilities::VIEW_REPORTS) === true, 'viewer may use globally granted reporting capability');
esc_p2_role_assert(Permission::capability(Capabilities::EDIT_CONTRACTS) instanceof WP_Error, 'viewer cannot edit contracts even when WordPress grants edit globally');
esc_p2_role_assert(Permission::capability(Capabilities::MANAGE_REFERENCE_DATA) instanceof WP_Error, 'a shared global capability is narrowed when it is evaluated inside locked tenant context');

esc_p2_role_membership(TenantRolePolicy::ACCOUNTANT);
esc_p2_role_assert(AccessScope::current() === AccessScope::ASSIGNED, 'accountant remains assigned scope even with global VIEW_ALL');
esc_p2_role_assert(Permission::capability(Capabilities::MANAGE_PAYMENTS) === true, 'accountant may use globally granted payment capability');
esc_p2_role_assert(Permission::capability(Capabilities::EDIT_CONTRACTS) instanceof WP_Error, 'accountant role ceiling denies contract editing');

esc_p2_role_membership(TenantRolePolicy::MANAGER);
esc_p2_role_assert(AccessScope::current() === AccessScope::ALL, 'manager may retain globally granted all-data scope');
esc_p2_role_assert(Permission::capability(Capabilities::EDIT_CONTRACTS) === true, 'manager may use globally granted contract edit capability');
esc_p2_role_assert(Permission::capability(Capabilities::MANAGE_NOTIFICATIONS) instanceof WP_Error, 'manager cannot gain tenant notification administration outside its role ceiling');

esc_p2_role_membership(TenantRolePolicy::TENANT_ADMIN);
esc_p2_role_assert(AccessScope::current() === AccessScope::ALL, 'tenant admin may retain globally granted all-data scope');
esc_p2_role_assert(Permission::capability(Capabilities::MANAGE_NOTIFICATIONS) === true, 'tenant admin may use globally granted notification administration');
esc_p2_role_assert(Permission::capability(Capabilities::MANAGE_REFERENCE_DATA) === true, 'tenant admin may use a matching shared capability inside tenant context');

esc_p2_role_membership(TenantRolePolicy::MEMBER);
esc_p2_role_assert(AccessScope::current() === AccessScope::ALL, 'legacy member role inherits existing global data scope for compatibility');
esc_p2_role_assert(Permission::capability(Capabilities::EDIT_CONTRACTS) === true, 'legacy member role preserves existing globally granted capability');
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
esc_p2_role_assert(Permission::capability(Capabilities::EDIT_CONTRACTS) instanceof WP_Error, 'legacy member role never manufactures a missing global capability');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::MANAGE_NOTIFICATIONS => true,
];
esc_p2_role_membership('future_super_admin');
esc_p2_role_assert(AccessScope::current() === AccessScope::NONE, 'unknown tenant role fails closed instead of inheriting global VIEW_ALL');
esc_p2_role_assert(Permission::access() instanceof WP_Error, 'unknown tenant role cannot access locked tenant business data');

esc_p2_role_membership('', true);
esc_p2_role_assert(AccessScope::current() === AccessScope::NONE, 'blank tenant role fails closed even when owner flag is set');

esc_p2_role_membership(TenantRolePolicy::VIEWER, true);
esc_p2_role_assert(AccessScope::current() === AccessScope::ALL, 'recognized tenant owner may raise tenant role ceiling to all within global scope');
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
];
esc_p2_role_assert(Permission::capability(Capabilities::MANAGE_NOTIFICATIONS) instanceof WP_Error, 'tenant owner cannot bypass missing global notification capability');
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_NOTIFICATIONS] = true;
esc_p2_role_assert(Permission::capability(Capabilities::MANAGE_NOTIFICATIONS) === true, 'tenant owner can use capability only after matching global grant exists');

// A tenant role must not broaden a globally assigned-only user to all-data scope.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::EDIT_CONTRACTS => true,
];
esc_p2_role_membership(TenantRolePolicy::MANAGER);
esc_p2_role_assert(AccessScope::current() === AccessScope::ASSIGNED, 'manager role never broadens global assigned scope to VIEW_ALL');

// Outside ESC enforcement, tenant role metadata must not alter Safe Contract behavior.
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
TenantContextStore::reset();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_results'] = [];
esc_p2_role_assert(AccessScope::current() === AccessScope::ALL, 'legacy scope remains capability-driven when ESC enforcement is disabled');
esc_p2_role_assert(Permission::capability(Capabilities::EDIT_CONTRACTS) === true, 'legacy capability behavior remains unchanged outside ESC enforcement');

TenantContextStore::reset();
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_current_caps'] = [];
$GLOBALS['sc_test_results'] = [];

fwrite(STDOUT, "Enterprise tenant roles P2-002 passed ({$assertions} assertions).\n");
