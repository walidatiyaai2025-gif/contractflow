<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0024EnterpriseOrgUnitMemberships;
use SafeContracts\Organizations\OrgUnitMembershipPolicy;
use SafeContracts\Organizations\OrgUnitMembershipService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p3_orgmem_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p3_orgmem_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p3_orgmem_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p3_orgmem_assert(false, $message . ' (no exception)');
}

function esc_p3_orgmem_actor(string $role = 'tenant_admin', int $isOwner = 0): array
{
    return [[
        'id' => '1',
        'tenant_id' => '17',
        'user_id' => '42',
        'role_code' => $role,
        'is_owner' => (string) $isOwner,
    ]];
}

function esc_p3_orgmem_unit(int $id = 55): array
{
    return [[
        'id' => (string) $id,
        'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'unit_code' => 'FIN',
        'name' => 'Finance',
        'unit_type' => 'department',
        'parent_unit_id' => null,
        'status' => 'active',
    ]];
}

function esc_p3_orgmem_target(int $userId = 99): array
{
    return [[
        'id' => '9',
        'tenant_id' => '17',
        'user_id' => (string) $userId,
        'role_code' => 'viewer',
        'is_owner' => '0',
    ]];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0024EnterpriseOrgUnitMemberships.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Organizations/OrgUnitMembershipRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Organizations/OrgUnitMembershipService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0024EnterpriseOrgUnitMemberships())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p3_orgmem_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_org_unit_memberships'), 'P3-005 creates dedicated org-unit membership table');
esc_p3_orgmem_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'org-unit membership tenant ownership is mandatory');
esc_p3_orgmem_assert(str_contains($schema, 'org_unit_id bigint(20) unsigned NOT NULL'), 'org-unit membership requires organization-unit identity');
esc_p3_orgmem_assert(str_contains($schema, 'user_id bigint(20) unsigned NOT NULL'), 'org-unit membership requires user identity');
esc_p3_orgmem_assert(str_contains($schema, "assignment_role varchar(32) NOT NULL DEFAULT 'member'"), 'org-unit membership has explicit assignment role');
esc_p3_orgmem_assert(str_contains($schema, 'UNIQUE KEY tenant_unit_user (tenant_id, org_unit_id, user_id)'), 'user/unit identity is unique inside tenant');
esc_p3_orgmem_assert(str_contains($schema, 'KEY tenant_unit_status (tenant_id, org_unit_id, status, assignment_role, user_id, id)'), 'unit member listing index is tenant-first');
esc_p3_orgmem_assert(str_contains($schema, 'KEY tenant_user_status (tenant_id, user_id, status, org_unit_id, id)'), 'user assignment listing index is tenant-first');
esc_p3_orgmem_assert(version_compare(Migrator::LATEST_VERSION, '1.23.0', '>='), 'P3-005 schema remains reachable after future migrations');
esc_p3_orgmem_assert(str_contains($migratorSource, "'1.23.0' => Migration0024EnterpriseOrgUnitMemberships::class"), 'P3-005 migration is registered specifically at schema 1.23.0');

esc_p3_orgmem_assert(OrgUnitMembershipPolicy::roles() === ['member', 'manager'], 'org-unit assignment roles are explicitly bounded');
esc_p3_orgmem_assert(OrgUnitMembershipPolicy::normalize(' MANAGER ') === 'manager', 'assignment role normalization is stable');
esc_p3_orgmem_assert(! OrgUnitMembershipPolicy::isSupported('tenant_admin'), 'tenant RBAC roles are not accepted as org-unit assignment roles');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_USERS => true,
];
$service = new OrgUnitMembershipService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p3_orgmem_throws(
    static fn () => $service->listForUnit(55),
    RuntimeException::class,
    'org-unit memberships fail closed outside Enterprise core tenant enforcement'
);

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p3_orgmem_throws(
    static fn () => $service->listForUnit(55),
    RuntimeException::class,
    'org-unit memberships require locked tenant context'
);

TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p3_orgmem_actor()];
esc_p3_orgmem_throws(
    static fn () => $service->assign(55, 99, 'tenant_admin'),
    InvalidArgumentException::class,
    'tenant RBAC role cannot be smuggled into org-unit assignment role'
);
esc_p3_orgmem_assert($GLOBALS['sc_test_queries'] === [], 'unsupported assignment role fails before mutation');
esc_p3_orgmem_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'unsupported role performs only actor tenant authorization read');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_orgmem_actor(),
    esc_p3_orgmem_unit(55),
    esc_p3_orgmem_target(99),
];
$service->assign(55, 99, 'manager');
esc_p3_orgmem_assert(count($GLOBALS['sc_test_read_queries']) === 3, 'assignment verifies actor tenant role, org unit and target tenant membership');
esc_p3_orgmem_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 55 AND tenant_id = 17'), 'assignment verifies org unit in locked tenant');
esc_p3_orgmem_assert(str_contains($GLOBALS['sc_test_read_queries'][2] ?? '', 'WHERE m.tenant_id = 17 AND m.user_id = 99'), 'assignment verifies target active membership in locked tenant');
$assignSql = end($GLOBALS['sc_test_queries']);
$assignSql = is_string($assignSql) ? $assignSql : '';
esc_p3_orgmem_assert(str_contains($assignSql, 'INSERT INTO wp_safecontracts_org_unit_memberships'), 'assignment writes only dedicated org-unit membership table');
esc_p3_orgmem_assert(str_contains($assignSql, "VALUES (17, 55, 99, 'manager', 'active'"), 'assignment ownership and role are normalized server-side');
esc_p3_orgmem_assert(str_contains($assignSql, 'ON DUPLICATE KEY UPDATE'), 'assignment/reactivation is atomic and idempotent');
esc_p3_orgmem_assert(str_contains($assignSql, "assignment_role = VALUES(assignment_role)"), 'reassigning user/unit safely changes only org-unit assignment role');
esc_p3_orgmem_assert(! str_contains($assignSql, 'safecontracts_tenant_memberships'), 'org-unit manager assignment never mutates tenant membership/RBAC table');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_orgmem_actor(),
    [],
];
esc_p3_orgmem_throws(
    static fn () => $service->assign(999, 99, 'member'),
    InvalidArgumentException::class,
    'foreign organization-unit ID cannot receive tenant users'
);
esc_p3_orgmem_assert($GLOBALS['sc_test_queries'] === [], 'foreign organization-unit rejection performs no mutation');
esc_p3_orgmem_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 999 AND tenant_id = 17'), 'foreign unit lookup remains tenant-scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_orgmem_actor(),
    esc_p3_orgmem_unit(55),
    [],
];
esc_p3_orgmem_throws(
    static fn () => $service->assign(55, 999, 'member'),
    InvalidArgumentException::class,
    'foreign or stale user cannot be assigned to current-tenant org unit'
);
esc_p3_orgmem_assert($GLOBALS['sc_test_queries'] === [], 'foreign/stale target user rejection performs no mutation');
esc_p3_orgmem_assert(str_contains($GLOBALS['sc_test_read_queries'][2] ?? '', 'WHERE m.tenant_id = 17 AND m.user_id = 999'), 'target user authorization is locked to current tenant');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_orgmem_actor(),
    esc_p3_orgmem_unit(55),
    [[
        'id' => '3',
        'org_unit_id' => '55',
        'user_id' => '99',
        'assignment_role' => 'manager',
        'status' => 'active',
    ]],
];
$unitMembers = $service->listForUnit(55, 500, -10);
esc_p3_orgmem_assert(count($unitMembers) === 1, 'current-tenant unit member list returns active assignment rows');
$unitListSql = end($GLOBALS['sc_test_read_queries']);
$unitListSql = is_string($unitListSql) ? $unitListSql : '';
esc_p3_orgmem_assert(str_contains($unitListSql, 'WHERE tenant_id = 17 AND org_unit_id = 55'), 'unit member list is tenant-scoped');
esc_p3_orgmem_assert(str_contains($unitListSql, "status = 'active'"), 'unit member list excludes revoked assignments');
esc_p3_orgmem_assert(str_contains($unitListSql, 'LIMIT 100 OFFSET 0'), 'unit member list is bounded');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_orgmem_actor(),
    esc_p3_orgmem_target(99),
    [[
        'id' => '3',
        'org_unit_id' => '55',
        'user_id' => '99',
        'assignment_role' => 'member',
        'status' => 'active',
    ]],
];
$userUnits = $service->listForUser(99, 999, -1);
esc_p3_orgmem_assert(count($userUnits) === 1, 'active tenant user can be listed across their org-unit assignments');
esc_p3_orgmem_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE m.tenant_id = 17 AND m.user_id = 99'), 'list-by-user verifies active current-tenant membership');
$userListSql = end($GLOBALS['sc_test_read_queries']);
$userListSql = is_string($userListSql) ? $userListSql : '';
esc_p3_orgmem_assert(str_contains($userListSql, 'WHERE tenant_id = 17 AND user_id = 99'), 'list-by-user assignment query is tenant-scoped');
esc_p3_orgmem_assert(str_contains($userListSql, 'LIMIT 100 OFFSET 0'), 'list-by-user pagination is bounded');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_orgmem_actor(),
    [],
];
esc_p3_orgmem_throws(
    static fn () => $service->listForUser(777),
    InvalidArgumentException::class,
    'foreign/stale tenant user cannot be used for list-by-user access'
);
esc_p3_orgmem_assert($GLOBALS['sc_test_queries'] === [], 'foreign list-by-user performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p3_orgmem_actor(),
    esc_p3_orgmem_unit(55),
];
$service->revoke(55, 99);
$revokeSql = end($GLOBALS['sc_test_queries']);
$revokeSql = is_string($revokeSql) ? $revokeSql : '';
esc_p3_orgmem_assert(count($GLOBALS['sc_test_read_queries']) === 2, 'revoke verifies actor and org unit but permits stale-target cleanup');
esc_p3_orgmem_assert(str_contains($revokeSql, "SET status = 'inactive'"), 'revoke is non-destructive');
esc_p3_orgmem_assert(str_contains($revokeSql, 'WHERE tenant_id = 17 AND org_unit_id = 55 AND user_id = 99'), 'revoke is scoped by tenant + unit + user');
esc_p3_orgmem_assert(str_contains($revokeSql, "status <> 'inactive'"), 'repeated revoke is idempotent');
esc_p3_orgmem_assert(! str_contains($revokeSql, 'DELETE FROM'), 'revoke never deletes assignment history');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_USERS] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p3_orgmem_throws(
    static fn () => $service->assign(55, 99, 'member'),
    DomainException::class,
    'org-unit assignment mutation requires MANAGE_USERS global ceiling'
);
esc_p3_orgmem_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global write capability denial occurs before data access');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_USERS] = true;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p3_orgmem_actor('manager', 0)];
esc_p3_orgmem_throws(
    static fn () => $service->assign(55, 99, 'member'),
    DomainException::class,
    'tenant role ceiling independently denies MANAGE_USERS to tenant manager role'
);
esc_p3_orgmem_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'tenant-role denial performs only actor membership read');
esc_p3_orgmem_assert($GLOBALS['sc_test_queries'] === [], 'tenant-role denial performs no mutation');

$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_queries'] = [];
esc_p3_orgmem_throws(
    static fn () => $service->listForUnit(55),
    DomainException::class,
    'org-unit membership reads require ACCESS global ceiling'
);
esc_p3_orgmem_assert($GLOBALS['sc_test_read_queries'] === [] && $GLOBALS['sc_test_queries'] === [], 'read capability denial occurs before data access');

esc_p3_orgmem_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'assignment repository has explicit Enterprise-only boundary');
esc_p3_orgmem_assert(str_contains($repositorySource, 'requireTenantId()'), 'assignment repository has no unscoped tenant fallback');
esc_p3_orgmem_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'service enforces tenant-role capability ceiling directly');
esc_p3_orgmem_assert(str_contains($serviceSource, 'findActiveMembership($tenantId, $userId)'), 'service verifies target active tenant membership before assignment/list-by-user');
esc_p3_orgmem_assert(! str_contains($repositorySource, 'safecontracts_tenant_memberships'), 'assignment repository cannot mutate tenant RBAC membership storage');
esc_p3_orgmem_assert(! str_contains($migrationSource, 'role_code'), 'org-unit membership schema does not reuse tenant RBAC role_code');
esc_p3_orgmem_assert(! str_contains($migrationSource, 'is_owner'), 'org-unit membership schema cannot encode tenant ownership');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise org-unit memberships P3-005 passed ({$assertions} assertions).\n");
