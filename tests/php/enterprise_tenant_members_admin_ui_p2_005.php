<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\TenantMembersPage;
use SafeContracts\Tenancy\AdminTenantRequestPolicy;

$assertions = 0;

function esc_p2_members_ui_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$pagePath = $root . '/wordpress-plugin/safecontracts/src/Admin/TenantMembersPage.php';
$pluginPath = $root . '/wordpress-plugin/safecontracts/src/Plugin.php';
$globalRolesPath = $root . '/wordpress-plugin/safecontracts/src/Admin/UsersRolesPage.php';
$pageSource = (string) file_get_contents($pagePath);
$pluginSource = (string) file_get_contents($pluginPath);
$globalRolesSource = (string) file_get_contents($globalRolesPath);

esc_p2_members_ui_assert(TenantMembersPage::SLUG === 'safecontracts-tenant-members', 'tenant membership UI has a dedicated tenant-owned slug');
esc_p2_members_ui_assert(str_contains($pageSource, 'new TenantMembershipAdminService()'), 'tenant membership UI delegates to the P2-004 domain service');
esc_p2_members_ui_assert(str_contains($pageSource, 'listForCurrentTenant('), 'membership list is loaded through tenant-scoped service');
esc_p2_members_ui_assert(str_contains($pageSource, '->assignRole('), 'add/reactivate and role changes use the service assignment method');
esc_p2_members_ui_assert(str_contains($pageSource, '->deactivate('), 'deactivation uses the service method');
esc_p2_members_ui_assert(! str_contains($pageSource, '$wpdb'), 'admin page contains no direct database access');
esc_p2_members_ui_assert(! str_contains($pageSource, 'safecontracts_tenant_memberships'), 'admin page never writes/reads membership table directly');

esc_p2_members_ui_assert(str_contains($pageSource, 'TenantRolePolicy::assignableRoles()'), 'UI role options come from explicit assignable role policy');
esc_p2_members_ui_assert(! str_contains($pageSource, 'TenantRolePolicy::MEMBER'), 'legacy member role is not exposed as an assignable UI option');
esc_p2_members_ui_assert(! str_contains($pageSource, 'is_owner" value="1'), 'UI exposes no owner-grant form field');
esc_p2_members_ui_assert(str_contains($pageSource, 'Owner memberships are read-only in this interface.'), 'crafted generic owner deactivation is rejected before service mutation');
esc_p2_members_ui_assert(str_contains($pageSource, 'if ($isOwner)'), 'owner rows have an explicit read-only rendering branch');
esc_p2_members_ui_assert(str_contains($pageSource, 'Ownership workflow required'), 'owner rows expose ownership-workflow text instead of generic mutation action');

esc_p2_members_ui_assert(str_contains($pageSource, 'check_admin_referer(self::ASSIGN_ACTION)'), 'assignment mutation requires CSRF nonce');
esc_p2_members_ui_assert(str_contains($pageSource, 'check_admin_referer(self::DEACTIVATE_ACTION)'), 'deactivation mutation requires CSRF nonce');
esc_p2_members_ui_assert(str_contains($pageSource, 'current_user_can(Capabilities::MANAGE_USERS)'), 'page requires SafeContracts user-management capability');

$_GET = ['page' => TenantMembersPage::SLUG];
$_POST = [];
$_REQUEST = [];
esc_p2_members_ui_assert(AdminTenantRequestPolicy::isTenantOwnedRequest(), 'Tenant Members page is classified tenant-owned');

$_GET = [];
$_REQUEST = ['action' => TenantMembersPage::ASSIGN_ACTION];
esc_p2_members_ui_assert(AdminTenantRequestPolicy::isTenantOwnedRequest(), 'tenant member assignment action requires tenant context');

$_REQUEST = ['action' => TenantMembersPage::DEACTIVATE_ACTION];
esc_p2_members_ui_assert(AdminTenantRequestPolicy::isTenantOwnedRequest(), 'tenant member deactivation action requires tenant context');

esc_p2_members_ui_assert(str_contains($pluginSource, 'use SafeContracts\\Admin\\TenantMembersPage;'), 'plugin imports Tenant Members page');
esc_p2_members_ui_assert(str_contains($pluginSource, "add_action('admin_menu', [TenantMembersPage::class, 'register']"), 'plugin registers Tenant Members menu');
esc_p2_members_ui_assert(str_contains($pluginSource, "'admin_post_' . TenantMembersPage::ASSIGN_ACTION"), 'plugin registers tenant member assignment handler');
esc_p2_members_ui_assert(str_contains($pluginSource, "'admin_post_' . TenantMembersPage::DEACTIVATE_ACTION"), 'plugin registers tenant member deactivation handler');

esc_p2_members_ui_assert(! str_contains($globalRolesSource, 'TenantMembershipAdminService'), 'platform-global WordPress Users & Roles page remains separate from tenant membership service');
esc_p2_members_ui_assert(! str_contains($globalRolesSource, 'TenantRolePolicy'), 'platform-global WordPress role editor is not repurposed as tenant role UI');

$_GET = [];
$_POST = [];
$_REQUEST = [];

fwrite(STDOUT, "Enterprise tenant members admin UI P2-005 passed ({$assertions} assertions).\n");
