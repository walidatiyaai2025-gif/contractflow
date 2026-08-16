<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\ArchivePage;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\AdminTenantContext;
use SafeContracts\Tenancy\AdminTenantRequestPolicy;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\TenantCapabilityFilter;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Tenancy\TenantRolePolicy;

$assertions = 0;

function esc_p2_admin_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p2_admin_membership(string $roleCode, bool $owner = false): void
{
    $GLOBALS['sc_test_results'] = [[
        'id' => '601',
        'tenant_id' => '41',
        'user_id' => '42',
        'role_code' => $roleCode,
        'is_owner' => $owner ? '1' : '0',
    ]];
}

function esc_p2_admin_request(string $page = '', string $action = ''): void
{
    $_GET = $page === '' ? [] : ['page' => $page];
    $_POST = [];
    $_REQUEST = $action === '' ? [] : ['action' => $action];
}

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '1';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';

// Request ownership classification: platform/control-plane paths do not inherit
// an arbitrary selected tenant; tenant business paths do.
foreach ([
    AdminTenantContext::SELECT_PAGE,
    'safecontracts-active-users',
    'safecontracts-users-roles',
    'safecontracts-settings',
    'safecontracts-payment-methods',
    'safecontracts-firebase-settings',
    'safecontracts-mobile-configuration',
    'safecontracts-translations',
] as $page) {
    esc_p2_admin_request($page);
    esc_p2_admin_assert(! AdminTenantRequestPolicy::isTenantOwnedRequest(), "{$page} is classified platform-global");
}
foreach ([
    'safecontracts',
    'safecontracts-customers',
    'safecontracts-contracts',
    'safecontracts-payments',
    'safecontracts-archive',
    'safecontracts-notification-center',
] as $page) {
    esc_p2_admin_request($page);
    esc_p2_admin_assert(AdminTenantRequestPolicy::isTenantOwnedRequest(), "{$page} requires tenant context");
}
foreach ([
    AdminTenantContext::SELECT_ACTION,
    'safecontracts_save_general_settings',
    'safecontracts_save_payment_method',
    'safecontracts_delete_payment_method',
    'safecontracts_save_role_capabilities',
    'safecontracts_assign_user_role',
    'safecontracts_save_firebase_settings',
    'safecontracts_upload_firebase_service_account',
    'safecontracts_delete_firebase_service_account',
    'safecontracts_test_firebase_connection',
    'safecontracts_save_mobile_configuration',
    'safecontracts_save_translations',
] as $action) {
    esc_p2_admin_request('', $action);
    esc_p2_admin_assert(! AdminTenantRequestPolicy::isTenantOwnedRequest(), "{$action} is a platform-global action");
}
foreach ([
    'safecontracts_save_customer',
    'safecontracts_delete_customer',
    'safecontracts_save_contract_admin',
    'safecontracts_delete_contract_admin',
    'safecontracts_archive_contract_dashboard',
    'safecontracts_send_firebase_test_notification',
] as $action) {
    esc_p2_admin_request('', $action);
    esc_p2_admin_assert(AdminTenantRequestPolicy::isTenantOwnedRequest(), "{$action} requires tenant context");
}

// Direct admin current_user_can() checks are narrowed by the same tenant role
// policy used by REST after context locking.
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(41);
esc_p2_admin_membership(TenantRolePolicy::VIEWER);
$viewerCaps = TenantCapabilityFilter::filter([
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::VIEW_REPORTS => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::MANAGE_SYSTEM => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
    Capabilities::MANAGE_USERS => true,
]);
esc_p2_admin_assert($viewerCaps[Capabilities::ACCESS] === true, 'viewer retains tenant access');
esc_p2_admin_assert($viewerCaps[Capabilities::VIEW_ALL] === false, 'viewer cannot retain global VIEW_ALL inside tenant admin');
esc_p2_admin_assert($viewerCaps[Capabilities::VIEW_ASSIGNED] === true, 'viewer retains assigned scope');
esc_p2_admin_assert($viewerCaps[Capabilities::EDIT_CONTRACTS] === false, 'viewer cannot edit contracts through direct admin capability checks');
esc_p2_admin_assert($viewerCaps[Capabilities::MANAGE_SYSTEM] === false, 'viewer cannot use overloaded MANAGE_SYSTEM on tenant-owned actions');
esc_p2_admin_assert($viewerCaps[Capabilities::MANAGE_REFERENCE_DATA] === false, 'viewer cannot use overloaded MANAGE_REFERENCE_DATA on tenant-owned customer actions');
esc_p2_admin_assert($viewerCaps[Capabilities::MANAGE_USERS] === false, 'viewer cannot use tenant-context user administration checks');

esc_p2_admin_membership(TenantRolePolicy::TENANT_ADMIN);
$tenantAdminCaps = TenantCapabilityFilter::filter([
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_SYSTEM => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
    Capabilities::MANAGE_USERS => true,
]);
esc_p2_admin_assert($tenantAdminCaps[Capabilities::VIEW_ALL] === true, 'tenant admin may retain global all-data scope');
esc_p2_admin_assert($tenantAdminCaps[Capabilities::MANAGE_SYSTEM] === true, 'tenant admin may use matching destructive tenant capability');
esc_p2_admin_assert($tenantAdminCaps[Capabilities::MANAGE_REFERENCE_DATA] === true, 'tenant admin may manage tenant customer master data with matching global grant');
esc_p2_admin_assert($tenantAdminCaps[Capabilities::MANAGE_USERS] === true, 'tenant admin role ceiling recognizes matching user administration grant');

esc_p2_admin_membership('future_unknown_role');
$unknownCaps = TenantCapabilityFilter::filter([
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_SYSTEM => true,
]);
esc_p2_admin_assert($unknownCaps[Capabilities::ACCESS] === false, 'unknown tenant role fails closed in direct admin access checks');
esc_p2_admin_assert($unknownCaps[Capabilities::MANAGE_SYSTEM] === false, 'unknown tenant role cannot exploit a global destructive capability');

// Control-plane/global requests execute with no tenant context, therefore the
// tenant filter is narrowing-neutral and the original WordPress grants survive.
TenantContextStore::reset();
$GLOBALS['sc_test_results'] = [];
$globalCaps = TenantCapabilityFilter::filter([
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_SYSTEM => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
    Capabilities::MANAGE_USERS => true,
]);
esc_p2_admin_assert($globalCaps[Capabilities::ACCESS] === true, 'control-plane access remains global without tenant context');
esc_p2_admin_assert($globalCaps[Capabilities::MANAGE_SYSTEM] === true, 'global system capability remains intact without tenant context');
esc_p2_admin_assert($globalCaps[Capabilities::MANAGE_REFERENCE_DATA] === true, 'global reference capability remains intact without tenant context');
esc_p2_admin_assert($globalCaps[Capabilities::MANAGE_USERS] === true, 'global user capability remains intact without tenant context');

// ArchivePage previously bypassed repositories with unscoped raw SQL. Execute
// the private read helper and inspect every Enterprise query directly.
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(41);
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [];
$rowsMethod = new ReflectionMethod(ArchivePage::class, 'rows');
$rowsMethod->setAccessible(true);
$rowsMethod->invoke(null);
$enterpriseArchiveQueries = $GLOBALS['sc_test_read_queries'];
esc_p2_admin_assert(count($enterpriseArchiveQueries) === 4, 'Enterprise archive reads only tenant-owned core archive tables');
foreach ($enterpriseArchiveQueries as $sql) {
    esc_p2_admin_assert(str_contains((string) $sql, 'tenant_id = 41'), 'every Enterprise archive direct SQL query is tenant-scoped');
}
esc_p2_admin_assert(
    count(array_filter($enterpriseArchiveQueries, static fn (string $sql): bool => str_contains($sql, 'safecontracts_payment_methods'))) === 0,
    'platform-global payment method archive is not mixed into tenant archive'
);

$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
TenantContextStore::reset();
$GLOBALS['sc_test_read_queries'] = [];
$rowsMethod->invoke(null);
$legacyArchiveQueries = $GLOBALS['sc_test_read_queries'];
esc_p2_admin_assert(count($legacyArchiveQueries) === 5, 'Safe Contract legacy archive query shape remains unchanged outside ESC enforcement');
esc_p2_admin_assert(
    count(array_filter($legacyArchiveQueries, static fn (string $sql): bool => str_contains($sql, 'safecontracts_payment_methods'))) === 1,
    'legacy archive still includes the platform payment-method archive'
);

$root = dirname(__DIR__, 2);
$pluginSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Plugin.php');
esc_p2_admin_assert(str_contains($pluginSource, 'TenantCapabilityFilter::register();'), 'plugin boot wires central tenant admin capability filter');
$adminContextSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Tenancy/AdminTenantContext.php');
esc_p2_admin_assert(str_contains($adminContextSource, 'registerSelectorPage'), 'dedicated control-plane tenant selector page is registered');
esc_p2_admin_assert(str_contains($adminContextSource, 'globalCapabilityGranted(Capabilities::ACCESS)'), 'tenant selector can escape an invalid previous tenant role without disabling global access checks');

TenantContextStore::reset();
$GLOBALS['sc_test_options'][CoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_options'][NonCoreTenantEnforcement::OPTION] = '0';
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$_GET = [];
$_POST = [];
$_REQUEST = [];

fwrite(STDOUT, "Enterprise admin authorization P2-003 passed ({$assertions} assertions).\n");
