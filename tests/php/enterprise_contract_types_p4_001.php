<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\ContractTypes\ContractTypePolicy;
use SafeContracts\ContractTypes\ContractTypeService;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0026EnterpriseContractTypes;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;

function esc_p4_type_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p4_type_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p4_type_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ')');
        return;
    }
    esc_p4_type_assert(false, $message . ' (no exception)');
}

function esc_p4_type_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1',
        'tenant_id' => '17',
        'user_id' => '42',
        'role_code' => $role,
        'is_owner' => '0',
    ]];
}

function esc_p4_type_row(int $id = 31): array
{
    return [[
        'id' => (string) $id,
        'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'type_code' => 'construction.main',
        'name' => 'Construction Contract',
        'description' => 'Construction agreements',
        'category' => 'construction',
        'status' => 'active',
        'metadata_json' => '{"industry":"construction"}',
        'created_by' => '42',
        'updated_by' => '42',
    ]];
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0026EnterpriseContractTypes.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/ContractTypes/ContractTypeRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/ContractTypes/ContractTypeService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$contractMigrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php');
$contractServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$statusSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0026EnterpriseContractTypes())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p4_type_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_contract_types'), 'P4-001 creates dedicated Contract Type table');
esc_p4_type_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'Contract Type tenant ownership is mandatory');
esc_p4_type_assert(str_contains($schema, 'uuid char(36) NOT NULL'), 'Contract Type has stable server-generated UUID identity');
esc_p4_type_assert(str_contains($schema, 'type_code varchar(100) NOT NULL'), 'Contract Type has stable machine code');
esc_p4_type_assert(str_contains($schema, 'UNIQUE KEY tenant_code (tenant_id, type_code)'), 'Contract Type code uniqueness is tenant-local');
esc_p4_type_assert(str_contains($schema, 'KEY tenant_status_name (tenant_id, status, name, id)'), 'Contract Type listing index is tenant-first');
esc_p4_type_assert(str_contains($schema, 'KEY tenant_category_status (tenant_id, category, status, name, id)'), 'Contract Type category index is tenant-first');
esc_p4_type_assert(version_compare(Migrator::LATEST_VERSION, '1.25.0', '>='), 'P4-001 schema remains reachable after future migrations');
esc_p4_type_assert(str_contains($migratorSource, "'1.25.0' => Migration0026EnterpriseContractTypes::class"), 'P4-001 migration is registered specifically at schema 1.25.0');

esc_p4_type_assert(ContractTypePolicy::normalizeCode(' Construction.Main ') === 'construction.main', 'Contract Type code normalization is deterministic');
esc_p4_type_assert(ContractTypePolicy::normalizeCode('IT Support') === 'it_support', 'Contract Type code normalizes whitespace to underscore');
esc_p4_type_throws(static fn () => ContractTypePolicy::normalizeCode('bad/type'), InvalidArgumentException::class, 'unsupported Contract Type code characters fail closed');
esc_p4_type_assert(ContractTypePolicy::statuses() === ['active', 'inactive'], 'Contract Type status policy is explicit and bounded');
esc_p4_type_throws(static fn () => ContractTypePolicy::normalizeStatus('deleted'), InvalidArgumentException::class, 'unsupported Contract Type status fails closed');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::MANAGE_REFERENCE_DATA => true,
];
$service = new ContractTypeService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p4_type_throws(static fn () => $service->find(31), RuntimeException::class, 'Contract Type access fails closed outside Enterprise core enforcement');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p4_type_throws(static fn () => $service->find(31), RuntimeException::class, 'Contract Type access requires locked tenant context');

TenantContextStore::context()->setTenantId(17);
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor()];
$typeId = $service->create([
    'type_code' => ' Construction.Main ',
    'name' => 'Construction Contract',
    'description' => 'Construction agreements',
    'category' => 'construction',
    'metadata' => ['industry' => 'construction'],
]);
esc_p4_type_assert($typeId > 0, 'Contract Type create returns persisted identifier');
esc_p4_type_assert(count($GLOBALS['sc_test_queries']) === 1, 'Contract Type create performs one mutation');
$createSql = end($GLOBALS['sc_test_queries']);
$createSql = is_string($createSql) ? $createSql : '';
esc_p4_type_assert(str_contains($createSql, 'INSERT INTO wp_safecontracts_contract_types'), 'Contract Type create uses dedicated table');
esc_p4_type_assert(str_contains($createSql, "VALUES (17,"), 'Contract Type tenant ownership comes from locked server context');
esc_p4_type_assert(str_contains($createSql, "'construction.main', 'Construction Contract'"), 'normalized immutable code and display name are persisted');
esc_p4_type_assert(str_contains($createSql, "'active'"), 'Contract Type defaults to active status');
esc_p4_type_assert(str_contains($createSql, 'industry') && str_contains($createSql, 'construction'), 'Contract Type metadata is encoded before persistence');
esc_p4_type_assert(preg_match("/'[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}'/", $createSql) === 1, 'Contract Type UUID is generated server-side');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor()];
esc_p4_type_throws(static fn () => $service->create(['tenant_id' => 999, 'type_code' => 'x', 'name' => 'X']), InvalidArgumentException::class, 'caller cannot provide Contract Type tenant ownership');
esc_p4_type_assert($GLOBALS['sc_test_queries'] === [], 'tenant spoof fails before mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor()];
esc_p4_type_throws(static fn () => $service->create(['uuid' => '00000000-0000-4000-8000-000000000000', 'type_code' => 'x', 'name' => 'X']), InvalidArgumentException::class, 'caller cannot provide Contract Type UUID');
esc_p4_type_assert($GLOBALS['sc_test_queries'] === [], 'UUID spoof fails before mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor()];
esc_p4_type_throws(static fn () => $service->create(['type_code' => 'x', 'name' => 'X', 'status' => 'deleted']), InvalidArgumentException::class, 'unsupported create status fails before mutation');
esc_p4_type_assert($GLOBALS['sc_test_queries'] === [], 'unsupported create status performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor(), esc_p4_type_row(31)];
$found = $service->find(31);
esc_p4_type_assert(($found['type_code'] ?? '') === 'construction.main', 'Contract Type find returns current-tenant row');
$findSql = end($GLOBALS['sc_test_read_queries']);
$findSql = is_string($findSql) ? $findSql : '';
esc_p4_type_assert(str_contains($findSql, 'WHERE id = 31 AND tenant_id = 17'), 'Contract Type object lookup is tenant-scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor(), []];
$service->search('construct', 'active', 500, -1);
$searchSql = end($GLOBALS['sc_test_read_queries']);
$searchSql = is_string($searchSql) ? $searchSql : '';
esc_p4_type_assert(str_contains($searchSql, 'WHERE tenant_id = 17 AND status ='), 'Contract Type search begins with tenant/status predicates');
esc_p4_type_assert(str_contains($searchSql, 'name LIKE') && str_contains($searchSql, 'type_code LIKE') && str_contains($searchSql, 'category LIKE'), 'Contract Type search covers bounded catalog metadata');
esc_p4_type_assert(str_contains($searchSql, 'LIMIT 100 OFFSET 0'), 'Contract Type search pagination is bounded');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor()];
esc_p4_type_throws(static fn () => $service->update(31, ['type_code' => 'changed']), InvalidArgumentException::class, 'Contract Type code is immutable after creation');
esc_p4_type_assert($GLOBALS['sc_test_queries'] === [], 'immutable code rejection performs no mutation');
esc_p4_type_assert(count($GLOBALS['sc_test_read_queries']) === 1, 'immutable code rejection occurs before type lookup/mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor(), []];
esc_p4_type_throws(static fn () => $service->update(999, ['name' => 'Foreign']), InvalidArgumentException::class, 'foreign Contract Type ID cannot be updated');
esc_p4_type_assert($GLOBALS['sc_test_queries'] === [], 'foreign Contract Type update performs no mutation');
esc_p4_type_assert(str_contains($GLOBALS['sc_test_read_queries'][1] ?? '', 'WHERE id = 999 AND tenant_id = 17'), 'foreign Contract Type lookup remains tenant-scoped');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor(), esc_p4_type_row(31)];
$service->update(31, [
    'name' => 'Construction Agreement',
    'description' => 'Updated display description',
    'category' => 'construction',
    'metadata' => ['family' => 'works'],
]);
$updateSql = end($GLOBALS['sc_test_queries']);
$updateSql = is_string($updateSql) ? $updateSql : '';
esc_p4_type_assert(str_contains($updateSql, 'UPDATE wp_safecontracts_contract_types SET name ='), 'Contract Type update changes display metadata only');
esc_p4_type_assert(str_contains($updateSql, 'WHERE id = 31 AND tenant_id = 17'), 'Contract Type update includes tenant predicate');
esc_p4_type_assert(! str_contains($updateSql, 'type_code ='), 'Contract Type update SQL cannot change stable type_code');
esc_p4_type_assert(! str_contains($updateSql, 'status ='), 'general Contract Type metadata update cannot silently alter lifecycle status');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor(), esc_p4_type_row(31)];
$service->deactivate(31);
$deactivateSql = end($GLOBALS['sc_test_queries']);
$deactivateSql = is_string($deactivateSql) ? $deactivateSql : '';
esc_p4_type_assert(str_contains($deactivateSql, "SET status = 'inactive'"), 'Contract Type deactivate is non-destructive');
esc_p4_type_assert(str_contains($deactivateSql, 'WHERE id = 31 AND tenant_id = 17'), 'Contract Type deactivate is tenant-scoped');
esc_p4_type_assert(str_contains($deactivateSql, "status <> 'inactive'"), 'Contract Type deactivate is idempotent');
esc_p4_type_assert(! str_contains($deactivateSql, 'DELETE FROM'), 'Contract Type deactivate preserves historical configuration');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p4_type_throws(static fn () => $service->create(['type_code' => 'denied', 'name' => 'Denied']), DomainException::class, 'Contract Type mutation requires MANAGE_REFERENCE_DATA global ceiling');
esc_p4_type_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global mutation denial occurs before data access');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p4_type_actor('viewer')];
esc_p4_type_throws(static fn () => $service->create(['type_code' => 'denied', 'name' => 'Denied']), DomainException::class, 'tenant viewer cannot bypass Contract Type mutation ceiling');
esc_p4_type_assert(count($GLOBALS['sc_test_read_queries']) === 1 && $GLOBALS['sc_test_queries'] === [], 'tenant-role mutation denial performs only authorization membership read');

$GLOBALS['sc_test_current_caps'][Capabilities::ACCESS] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p4_type_throws(static fn () => $service->find(31), DomainException::class, 'Contract Type reads require ACCESS global ceiling');
esc_p4_type_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'read denial occurs before data access');

esc_p4_type_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()'), 'Contract Type repository has explicit Enterprise-only boundary');
esc_p4_type_assert(str_contains($repositorySource, 'requireTenantId()'), 'Contract Type repository has no unscoped tenant fallback');
esc_p4_type_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'Contract Type service enforces tenant-role capability ceiling');
esc_p4_type_assert(! str_contains($migrationSource, 'safecontracts_contracts'), 'P4-001 migration does not alter existing contracts table');
esc_p4_type_assert(! str_contains($repositorySource, 'safecontracts_contracts'), 'P4-001 repository cannot mutate existing contracts');
esc_p4_type_assert(! str_contains($serviceSource, 'ContractStatus'), 'P4-001 service does not alter inherited lifecycle transitions');
esc_p4_type_assert(! str_contains($contractMigrationSource, 'contract_type'), 'legacy contract schema remains unchanged in P4-001');
esc_p4_type_assert(! str_contains($contractServiceSource, 'ContractType'), 'legacy ContractService creation/edit behavior remains unchanged in P4-001');
esc_p4_type_assert(str_contains($statusSource, "self::DRAFT => [self::ACTIVE, self::CANCELLED]"), 'existing fixed ContractStatus lifecycle remains intact');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

fwrite(STDOUT, "Enterprise Contract Types P4-001 passed ({$assertions} assertions).\n");
