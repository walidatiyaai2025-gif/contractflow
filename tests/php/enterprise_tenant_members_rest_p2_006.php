<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\CoreTenantRestGuard;
use SafeContracts\Rest\Router;
use SafeContracts\Rest\TenantMembersController;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p2_members_rest_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/wordpress-plugin/safecontracts/src/Rest/TenantMembersController.php';
$routerPath = $root . '/wordpress-plugin/safecontracts/src/Rest/Router.php';
$guardPath = $root . '/wordpress-plugin/safecontracts/src/Rest/CoreTenantRestGuard.php';
$controllerSource = (string) file_get_contents($controllerPath);
$routerSource = (string) file_get_contents($routerPath);
$guardSource = (string) file_get_contents($guardPath);

// The generic membership API is an Enterprise-only surface and is covered by the
// core tenant request guard before capability evaluation.
esc_p2_members_rest_assert(CoreTenantRestGuard::isCoreBusinessRoute('/safecontracts/v1/tenant-members'), 'tenant-members collection route is protected by the core tenant REST guard');
esc_p2_members_rest_assert(CoreTenantRestGuard::isCoreBusinessRoute('/safecontracts/v1/tenant-members/42'), 'tenant-members item route is protected by the core tenant REST guard');
esc_p2_members_rest_assert(str_contains($guardSource, 'tenant-members(?:/|$)'), 'core tenant guard source explicitly owns the tenant-members route family');
esc_p2_members_rest_assert(str_contains($controllerSource, 'CoreTenantEnforcement::isEnabled()'), 'tenant membership REST routes register only under Enterprise enforcement');
esc_p2_members_rest_assert(str_contains($routerSource, 'TenantMembersController::register();'), 'REST router wires the tenant membership controller');

$GLOBALS['sc_test_routes'] = [];
CoreTenantEnforcement::disable();
TenantMembersController::register();
esc_p2_members_rest_assert(! isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/tenant-members']), 'Safe Contract/non-ESC mode does not expose the tenant membership API');

$GLOBALS['sc_test_results'] = [['total' => '0']];
CoreTenantEnforcement::enable();
TenantMembersController::register();
esc_p2_members_rest_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/tenant-members']), 'Enterprise enforcement exposes the tenant membership collection route');
esc_p2_members_rest_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . '/tenant-members/(?P<user_id>\\d+)']), 'Enterprise enforcement exposes the tenant membership item route');

$collectionRoutes = $GLOBALS['sc_test_routes'][Router::NAMESPACE . '/tenant-members'];
$itemRoutes = $GLOBALS['sc_test_routes'][Router::NAMESPACE . '/tenant-members/(?P<user_id>\\d+)'];
esc_p2_members_rest_assert(count($collectionRoutes) === 2, 'tenant membership collection exposes read and create handlers only');
esc_p2_members_rest_assert(count($itemRoutes) === 2, 'tenant membership item exposes update and deactivate handlers only');
foreach (array_merge($collectionRoutes, $itemRoutes) as $route) {
    esc_p2_members_rest_assert(($route['permission_callback'] ?? null) === [TenantMembersController::class, 'canManage'], 'every tenant membership REST handler uses the tenant-aware MANAGE_USERS permission callback');
}

// Permission evaluation must establish a locked tenant context before applying the
// tenant-aware capability ceiling. Ambiguous and foreign tenant selections fail closed.
esc_p2_members_rest_assert(str_contains($controllerSource, 'TenantRequestContext::resolve($request, true)'), 'permission callback requires explicit resolvable tenant context');
esc_p2_members_rest_assert(str_contains($controllerSource, 'Permission::capability('), 'permission callback delegates to central tenant-aware capability authorization');
esc_p2_members_rest_assert(str_contains($controllerSource, 'Capabilities::MANAGE_USERS'), 'tenant membership API requires MANAGE_USERS');

$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_USERS => true];
TenantContextStore::reset();
unset($_SERVER['HTTP_X_ESC_TENANT_ID']);
$GLOBALS['sc_test_results'] = [['tenant_id' => '17'], ['tenant_id' => '18']];
$ambiguous = TenantMembersController::canManage(new WP_REST_Request());
esc_p2_members_rest_assert($ambiguous instanceof WP_Error, 'multiple tenant memberships cannot access the API without explicit tenant selection');

TenantContextStore::reset();
$_SERVER['HTTP_X_ESC_TENANT_ID'] = '99';
$GLOBALS['sc_test_results'] = [];
$foreign = TenantMembersController::canManage(new WP_REST_Request());
esc_p2_members_rest_assert($foreign instanceof WP_Error, 'client-supplied foreign tenant id cannot authorize tenant membership administration');

// Controller input deliberately has no tenant selector. Tenant ownership comes only
// from the locked server-side context and all data access stays behind P2-004 service.
esc_p2_members_rest_assert(str_contains($controllerSource, "self::jsonObject(\$request, ['user_id', 'role_code'])"), 'create accepts only user_id and role_code mutation fields');
esc_p2_members_rest_assert(str_contains($controllerSource, "self::jsonObject(\$request, ['role_code'])"), 'update accepts only role_code mutation field');
esc_p2_members_rest_assert(! str_contains($controllerSource, "'tenant_id'"), 'controller accepts no tenant_id payload or route field');
esc_p2_members_rest_assert(str_contains($controllerSource, 'Unsupported tenant membership field.'), 'crafted extra mutation fields are rejected');
esc_p2_members_rest_assert(str_contains($controllerSource, 'TenantRolePolicy::isAssignable($roleCode)'), 'assignment validates only explicit P2-004 assignable tenant roles');
esc_p2_members_rest_assert(str_contains($controllerSource, 'TenantRolePolicy::assignableRoles()'), 'GET publishes only explicit assignable roles');
esc_p2_members_rest_assert(! str_contains($controllerSource, 'TenantRolePolicy::MEMBER'), 'legacy member role is never deliberately exposed by the generic REST controller');

esc_p2_members_rest_assert(str_contains($controllerSource, 'new TenantMembershipAdminService()'), 'tenant membership REST controller delegates to the P2-004 administration service');
esc_p2_members_rest_assert(substr_count($controllerSource, '->assignRole(') >= 2, 'create/update mutations delegate role assignment to the service');
esc_p2_members_rest_assert(str_contains($controllerSource, '->deactivate('), 'deactivation delegates to the service');
esc_p2_members_rest_assert(str_contains($controllerSource, 'listForCurrentTenant('), 'membership reads and post-mutation lookups are current-tenant scoped by the service');
esc_p2_members_rest_assert(! str_contains($controllerSource, '$wpdb'), 'controller performs no direct database access');
esc_p2_members_rest_assert(! str_contains($controllerSource, 'safecontracts_tenant_memberships'), 'controller never reads or writes the membership table directly');

$deactivateStart = strpos($controllerSource, 'public static function deactivate');
$deactivateEnd = strpos($controllerSource, 'private static function jsonObject');
esc_p2_members_rest_assert($deactivateStart !== false && $deactivateEnd !== false && $deactivateEnd > $deactivateStart, 'deactivate handler source boundary is discoverable');
$deactivateSource = substr($controllerSource, (int) $deactivateStart, (int) $deactivateEnd - (int) $deactivateStart);
$ownerGuard = strpos($deactivateSource, "! empty(\$current['is_owner'])");
$serviceMutation = strpos($deactivateSource, '->deactivate(');
esc_p2_members_rest_assert($ownerGuard !== false, 'crafted owner deactivation has an explicit controller-level guard');
esc_p2_members_rest_assert($serviceMutation !== false && $ownerGuard < $serviceMutation, 'owner target is rejected before the service deactivation mutation is invoked');
esc_p2_members_rest_assert(str_contains($deactivateSource, 'Owner memberships are read-only in the generic tenant-members REST API.'), 'generic REST endpoint documents owner rows as mutation-protected');

$presentStart = strpos($controllerSource, 'private static function present');
esc_p2_members_rest_assert($presentStart !== false, 'membership response presenter exists');
$presentSource = substr($controllerSource, (int) $presentStart);
esc_p2_members_rest_assert(! str_contains($presentSource, "'tenant_id'"), 'membership response does not disclose or echo a foreign/request tenant id');

unset($_SERVER['HTTP_X_ESC_TENANT_ID']);
TenantContextStore::reset();
CoreTenantEnforcement::disable();

fwrite(STDOUT, "Enterprise tenant membership REST P2-006 passed ({$assertions} assertions).\n");
