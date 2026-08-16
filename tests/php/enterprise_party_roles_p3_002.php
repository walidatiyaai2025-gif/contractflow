<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0021EnterprisePartyRoles;
use SafeContracts\Parties\PartyRolePolicy;
use SafeContracts\Parties\PartyRoleService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p3_role_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p3_role_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p3_role_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p3_role_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0021EnterprisePartyRoles.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Parties/PartyRoleRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Parties/PartyRoleService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0021EnterprisePartyRoles())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p3_role_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_party_roles'), 'P3-002 creates a dedicated Party role assignment table');
esc_p3_role_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'Party role assignment is tenant-owned');
esc_p3_role_assert(str_contains($schema, 'party_id bigint(20) unsigned NOT NULL'), 'Party role assignment targets an explicit Party');
esc_p3_role_assert(str_contains($schema, 'role_code varchar(64) NOT NULL'), 'Party business role uses stable explicit code');
esc_p3_role_assert(str_contains($schema, 'UNIQUE KEY tenant_party_role (tenant_id, party_id, role_code)'), 'same Party role cannot duplicate inside a tenant');
esc_p3_role_assert(str_contains($schema, 'KEY tenant_role_status_party (tenant_id, role_code, status, party_id)'), 'role lookup index is tenant-first');
esc_p3_role_assert(str_contains($schema, 'KEY tenant_party_status (tenant_id, party_id, status, id)'), 'Party role listing index is tenant-first');
esc_p3_role_assert(version_compare(Migrator::LATEST_VERSION, '1.20.0', '>='), 'P3-002 schema remains reachable after later migrations');
esc_p3_role_assert(str_contains($migratorSource, "'1.20.0' => Migration0021EnterprisePartyRoles::class"), 'P3-002 migration is registered specifically at schema 1.20.0');

$expectedRoles = [
    'customer', 'supplier', 'vendor', 'contractor', 'subcontractor', 'agent',
    'consultant', 'landlord', 'lessee', 'buyer', 'seller', 'other',
];
esc_p3_role_assert(PartyRolePolicy::roles() === $expectedRoles, 'baseline Party business-role policy is explicit and deterministic');
esc_p3_role_assert(PartyRolePolicy::normalize('  CUSTOMER ') === 'customer', 'Party role codes normalize to stable lower-case form');
esc_p3_role_assert(! PartyRolePolicy::isSupported('tenant'), 'SaaS tenant terminology is not overloaded as a Party business role');
esc_p3_role_assert(! PartyRolePolicy::isSupported('organization'), 'intrinsic Party kind is not overloaded as a business role');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$service = new PartyRoleService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p3_role_throws(
    static fn () => $service->rolesForParty(55),
    RuntimeException::class,
    'Party role reads fail closed outside Enterprise core enforcement'
);

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p3_role_throws(
    static fn () => $service->rolesForParty(55),
    RuntimeException::class,
    'Party role reads require a locked tenant context'
);

TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '55', 'display_name' => 'Acme']],
    [['role_code' => 'customer'], ['role_code' => 'supplier']],
];
$roles = $service->rolesForParty(55);
esc_p3_role_assert($roles === ['customer', 'supplier'], 'Party may expose multiple active business roles');
esc_p3_role_assert(count($GLOBALS['sc_test_read_queries']) === 2, 'role listing first verifies current-tenant Party ownership then reads roles');
esc_p3_role_assert(str_contains($GLOBALS['sc_test_read_queries'][0], 'WHERE id = 55 AND tenant_id = 17'), 'Party ownership check is tenant-scoped');
esc_p3_role_assert(str_contains($GLOBALS['sc_test_read_queries'][1], "WHERE tenant_id = 17 AND party_id = 55 AND status = 'active'"), 'role list is tenant and Party scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'display_name' => 'Acme']]];
$service->assign(55, ' CUSTOMER ');
esc_p3_role_assert(count($GLOBALS['sc_test_queries']) === 1, 'role assignment performs one atomic role mutation after Party verification');
$assignSql = $GLOBALS['sc_test_queries'][0];
esc_p3_role_assert(str_contains($assignSql, 'INSERT INTO wp_safecontracts_party_roles'), 'role assignment uses dedicated Party role table');
esc_p3_role_assert(str_contains($assignSql, "VALUES (17, 55, 'customer', 'active'"), 'assignment derives tenant and normalized role server-side');
esc_p3_role_assert(str_contains($assignSql, 'ON DUPLICATE KEY UPDATE'), 'duplicate/re-activation assignment is atomic and idempotent');
esc_p3_role_assert(str_contains($assignSql, "assigned_by = IF(status = 'active', assigned_by, VALUES(assigned_by))"), 'already-active duplicate assignment preserves original assignment metadata');
esc_p3_role_assert(! str_contains($assignSql, 'safecontracts_customers'), 'assigning customer role never mutates legacy customers');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'display_name' => 'Acme']]];
$service->assign(55, 'customer');
$repeatAssignSql = $GLOBALS['sc_test_queries'][0] ?? '';
esc_p3_role_assert($repeatAssignSql === $assignSql, 'repeated identical role assignment reaches the same atomic upsert state transition');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p3_role_throws(
    static fn () => $service->assign(55, 'platform_admin'),
    InvalidArgumentException::class,
    'unsupported Party business role fails closed'
);
esc_p3_role_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'unsupported role fails before Party lookup or persistence');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(18);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[]];
esc_p3_role_throws(
    static fn () => $service->assign(55, 'supplier'),
    InvalidArgumentException::class,
    'foreign tenant Party ID cannot receive a role assignment'
);
esc_p3_role_assert($GLOBALS['sc_test_queries'] === [], 'foreign Party miss performs no role mutation');
esc_p3_role_assert(str_contains($GLOBALS['sc_test_read_queries'][0] ?? '', 'WHERE id = 55 AND tenant_id = 18'), 'foreign Party ownership check remains current-tenant scoped');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'display_name' => 'Acme']]];
$service->revoke(55, 'supplier');
esc_p3_role_assert(count($GLOBALS['sc_test_queries']) === 1, 'role revoke performs one mutation after Party verification');
$revokeSql = $GLOBALS['sc_test_queries'][0];
esc_p3_role_assert(str_contains($revokeSql, 'UPDATE wp_safecontracts_party_roles'), 'role revoke uses dedicated Party role table');
esc_p3_role_assert(str_contains($revokeSql, "WHERE tenant_id = 17 AND party_id = 55 AND role_code = 'supplier'"), 'role revoke always includes tenant and Party predicates');
esc_p3_role_assert(str_contains($revokeSql, "revoked_by = IF(status = 'active'"), 'revoke metadata changes only when the assignment was active');
esc_p3_role_assert(str_contains($revokeSql, "status = 'inactive'"), 'role revoke preserves row as inactive instead of destructive deletion');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'display_name' => 'Acme']]];
$service->revoke(55, 'supplier');
$repeatRevokeSql = $GLOBALS['sc_test_queries'][0] ?? '';
esc_p3_role_assert($repeatRevokeSql === $revokeSql, 'repeated revoke is idempotent and remains tenant-scoped');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p3_role_throws(
    static fn () => $service->assign(55, 'vendor'),
    DomainException::class,
    'Party role mutations require tenant-aware MANAGE_REFERENCE_DATA'
);
esc_p3_role_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'write capability denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
esc_p3_role_throws(
    static fn () => $service->rolesForParty(55),
    DomainException::class,
    'Party role reads require tenant-aware ACCESS'
);

esc_p3_role_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'Party role repository has an explicit Enterprise-only boundary');
esc_p3_role_assert(str_contains($repositorySource, 'requireTenantId()'), 'Party role repository never falls back to unscoped access');
esc_p3_role_assert(! str_contains($repositorySource, 'DELETE FROM'), 'Party role revoke is non-destructive');
esc_p3_role_assert(! str_contains($serviceSource, 'party_kind'), 'role assignment service cannot mutate intrinsic Party kind');
esc_p3_role_assert(! str_contains($serviceSource, 'safecontracts_customers'), 'role assignment service has no legacy Customer mutation path');
esc_p3_role_assert(! str_contains($migrationSource, 'safecontracts_customers'), 'role schema migration does not rewrite legacy customers');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise Party business roles P3-002 passed ({$assertions} assertions).\n");
