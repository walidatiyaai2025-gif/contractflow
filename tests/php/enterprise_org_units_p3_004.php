<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0023EnterpriseOrgUnits;
use SafeContracts\Organizations\OrgUnitPolicy;
use SafeContracts\Organizations\OrgUnitService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p3_org_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p3_org_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p3_org_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p3_org_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0023EnterpriseOrgUnits.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Organizations/OrgUnitRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Organizations/OrgUnitService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0023EnterpriseOrgUnits())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p3_org_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_org_units'), 'P3-004 creates a dedicated organization-unit table');
esc_p3_org_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'organization-unit tenant ownership is mandatory');
esc_p3_org_assert(str_contains($schema, 'uuid char(36) NOT NULL'), 'organization units carry stable UUID identity');
esc_p3_org_assert(str_contains($schema, 'parent_unit_id bigint(20) unsigned NULL'), 'organization hierarchy uses an optional parent-unit identifier');
esc_p3_org_assert(str_contains($schema, 'UNIQUE KEY tenant_code (tenant_id, unit_code)'), 'organization unit codes are unique only inside a tenant');
esc_p3_org_assert(str_contains($schema, 'KEY tenant_status_name (tenant_id, status, name, id)'), 'unit listing index is tenant-first');
esc_p3_org_assert(str_contains($schema, 'KEY tenant_parent_status (tenant_id, parent_unit_id, status, unit_type, id)'), 'hierarchy child lookup is tenant-first');
esc_p3_org_assert(version_compare(Migrator::LATEST_VERSION, '1.22.0', '>='), 'P3-004 schema remains reachable after future migrations');
esc_p3_org_assert(str_contains($migratorSource, "'1.22.0' => Migration0023EnterpriseOrgUnits::class"), 'P3-004 migration is registered specifically at schema 1.22.0');

esc_p3_org_assert(OrgUnitPolicy::types() === ['department', 'team'], 'organization unit type policy is explicit and bounded');
esc_p3_org_assert(OrgUnitPolicy::statuses() === ['active', 'inactive'], 'organization unit status policy is explicit');
esc_p3_org_assert(OrgUnitPolicy::MAX_HIERARCHY_DEPTH === 64, 'organization hierarchy depth has a fixed safety bound');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$service = new OrgUnitService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p3_org_throws(
    static fn () => $service->find(10),
    RuntimeException::class,
    'organization-unit repository fails closed outside Enterprise core tenant enforcement'
);

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p3_org_throws(
    static fn () => $service->find(10),
    RuntimeException::class,
    'organization-unit repository requires locked tenant context'
);

TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [];
$rootId = $service->save([
    'name' => 'Finance',
    'unit_type' => 'department',
    'metadata' => ['cost_center' => 'FIN'],
]);
esc_p3_org_assert($rootId > 0, 'root department create returns persisted identifier');
$createSql = end($GLOBALS['sc_test_queries']);
$createSql = is_string($createSql) ? $createSql : '';
esc_p3_org_assert(str_contains($createSql, 'INSERT INTO wp_safecontracts_org_units'), 'organization-unit create uses dedicated table');
esc_p3_org_assert(str_contains($createSql, 'VALUES (17,'), 'organization-unit create derives tenant ownership from server context');
esc_p3_org_assert(str_contains($createSql, ', NULL,') && substr_count($createSql, 'NULL') >= 2, 'empty code and root parent persist as NULL');
esc_p3_org_assert(str_contains($createSql, "'department'"), 'department type is persisted from explicit policy');
esc_p3_org_assert(str_contains($createSql, 'cost_center') && str_contains($createSql, 'FIN'), 'bounded metadata is encoded before persistence');
esc_p3_org_assert(preg_match("/'[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}'/", $createSql) === 1, 'organization-unit UUID is generated server-side');

$GLOBALS['sc_test_queries'] = [];
esc_p3_org_throws(
    static fn () => $service->save(['tenant_id' => 999, 'name' => 'Spoof', 'unit_type' => 'department']),
    InvalidArgumentException::class,
    'caller cannot supply organization-unit tenant ownership'
);
esc_p3_org_throws(
    static fn () => $service->save(['uuid' => '00000000-0000-4000-8000-000000000000', 'name' => 'Spoof', 'unit_type' => 'department']),
    InvalidArgumentException::class,
    'caller cannot supply organization-unit UUID'
);
esc_p3_org_throws(
    static fn () => $service->save(['name' => 'Unsupported', 'unit_type' => 'division']),
    InvalidArgumentException::class,
    'unsupported organization-unit types fail closed'
);
esc_p3_org_assert($GLOBALS['sc_test_queries'] === [], 'reserved/unsupported fields fail before mutation');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [['id' => '55', 'name' => 'Finance', 'parent_unit_id' => null]];
$found = $service->find(55);
esc_p3_org_assert(($found['id'] ?? null) === '55', 'organization-unit find returns current-tenant row');
$findSql = end($GLOBALS['sc_test_read_queries']);
$findSql = is_string($findSql) ? $findSql : '';
esc_p3_org_assert(str_contains($findSql, 'WHERE id = 55 AND tenant_id = 17'), 'organization-unit find scopes object ID by tenant');

$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_results'] = [];
$service->search('', 500, -10);
$searchSql = end($GLOBALS['sc_test_read_queries']);
$searchSql = is_string($searchSql) ? $searchSql : '';
esc_p3_org_assert(str_contains($searchSql, 'WHERE tenant_id = 17'), 'organization-unit search starts with tenant predicate');
esc_p3_org_assert(str_contains($searchSql, 'LIMIT 100 OFFSET 0'), 'organization-unit search pagination is bounded');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '55', 'name' => 'Finance', 'parent_unit_id' => null]]];
$childId = $service->save([
    'unit_code' => 'AP',
    'name' => 'Accounts Payable',
    'unit_type' => 'team',
    'parent_unit_id' => 55,
]);
esc_p3_org_assert($childId > 0, 'child team can be created under a current-tenant parent');
esc_p3_org_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'child create verifies the parent before mutation');
esc_p3_org_assert(str_contains($GLOBALS['sc_test_read_queries'][0], 'WHERE id = 55 AND tenant_id = 17'), 'parent verification is tenant-scoped');
$childSql = end($GLOBALS['sc_test_queries']);
$childSql = is_string($childSql) ? $childSql : '';
esc_p3_org_assert(str_contains($childSql, "'AP', 'Accounts Payable', 'team', 55"), 'verified parent ID is persisted with normalized team data');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(18);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[]];
esc_p3_org_throws(
    static fn () => $service->save(['name' => 'Foreign Child', 'unit_type' => 'team', 'parent_unit_id' => 55]),
    InvalidArgumentException::class,
    'foreign parent cannot be attached in another tenant'
);
esc_p3_org_assert($GLOBALS['sc_test_queries'] === [], 'foreign parent miss performs no mutation');
esc_p3_org_assert(str_contains($GLOBALS['sc_test_read_queries'][0] ?? '', 'WHERE id = 55 AND tenant_id = 18'), 'foreign parent spoof remains locked to current tenant');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[['id' => '10', 'name' => 'Finance', 'parent_unit_id' => null]]];
esc_p3_org_throws(
    static fn () => $service->save(['id' => 10, 'name' => 'Finance', 'unit_type' => 'department', 'parent_unit_id' => 10]),
    InvalidArgumentException::class,
    'self-parenting is rejected before update mutation'
);
esc_p3_org_assert($GLOBALS['sc_test_queries'] === [], 'self-parenting performs no update mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '10', 'name' => 'Root', 'parent_unit_id' => null]],
    [['id' => '20', 'name' => 'Child', 'parent_unit_id' => '10']],
];
esc_p3_org_throws(
    static fn () => $service->save(['id' => 10, 'name' => 'Root', 'unit_type' => 'department', 'parent_unit_id' => 20]),
    InvalidArgumentException::class,
    'reparenting under a descendant is rejected as a cycle'
);
esc_p3_org_assert($GLOBALS['sc_test_queries'] === [], 'cycle-producing reparent performs no mutation');
esc_p3_org_assert(count($GLOBALS['sc_test_read_queries']) === 2, 'cycle check uses bounded tenant-scoped ancestry reads');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$deepRows = [];
for ($id = 1; $id <= OrgUnitPolicy::MAX_HIERARCHY_DEPTH; $id++) {
    $deepRows[] = [['id' => (string) $id, 'name' => 'Level ' . $id, 'parent_unit_id' => (string) ($id + 1)]];
}
$GLOBALS['sc_test_result_queue'] = $deepRows;
esc_p3_org_throws(
    static fn () => $service->save(['name' => 'Too Deep', 'unit_type' => 'team', 'parent_unit_id' => 1]),
    InvalidArgumentException::class,
    'hierarchy traversal fails closed beyond maximum depth'
);
esc_p3_org_assert(count($GLOBALS['sc_test_read_queries']) === OrgUnitPolicy::MAX_HIERARCHY_DEPTH, 'hierarchy traversal stops exactly at configured bound');
esc_p3_org_assert($GLOBALS['sc_test_queries'] === [], 'over-depth hierarchy performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '10', 'name' => 'Finance', 'parent_unit_id' => null]],
    [['id' => '20', 'name' => 'Corporate', 'parent_unit_id' => null]],
];
$updatedId = $service->save([
    'id' => 10,
    'unit_code' => 'FIN',
    'name' => 'Finance & Treasury',
    'unit_type' => 'department',
    'parent_unit_id' => 20,
]);
esc_p3_org_assert($updatedId === 10, 'valid current-tenant reparent preserves unit identifier');
$updateSql = end($GLOBALS['sc_test_queries']);
$updateSql = is_string($updateSql) ? $updateSql : '';
esc_p3_org_assert(str_contains($updateSql, 'WHERE id = 10 AND tenant_id = 17'), 'organization-unit update always includes tenant predicate');
esc_p3_org_assert(str_contains($updateSql, 'parent_unit_id = 20'), 'validated parent is applied by tenant-scoped update');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(18);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [[]];
esc_p3_org_throws(
    static fn () => $service->deactivate(10),
    InvalidArgumentException::class,
    'foreign organization-unit ID cannot be deactivated'
);
esc_p3_org_assert($GLOBALS['sc_test_queries'] === [], 'foreign deactivate miss performs no mutation');
esc_p3_org_assert(str_contains($GLOBALS['sc_test_read_queries'][0] ?? '', 'WHERE id = 10 AND tenant_id = 18'), 'deactivate ownership check remains tenant-scoped');

TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p3_org_throws(
    static fn () => $service->save(['name' => 'Denied', 'unit_type' => 'team']),
    DomainException::class,
    'organization-unit mutation requires MANAGE_REFERENCE_DATA'
);
esc_p3_org_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'write capability denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
esc_p3_org_throws(
    static fn () => $service->find(10),
    DomainException::class,
    'organization-unit reads require ACCESS'
);

esc_p3_org_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'organization-unit repository has explicit Enterprise-only boundary');
esc_p3_org_assert(str_contains($repositorySource, 'requireTenantId()'), 'organization-unit repository has no unscoped tenant fallback');
esc_p3_org_assert(! str_contains($serviceSource, "'tenant_id'"), 'tenant_id is absent from supported organization-unit mutation fields');
esc_p3_org_assert(! str_contains($serviceSource, "'uuid'"), 'uuid is absent from supported organization-unit mutation fields');
esc_p3_org_assert(! str_contains($migrationSource, 'safecontracts_customers'), 'P3-004 schema does not rewrite legacy customers');
esc_p3_org_assert(! str_contains($migrationSource, 'safecontracts_parties'), 'P3-004 schema does not overload Party identity');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise organization units P3-004 passed ({$assertions} assertions).\n");
