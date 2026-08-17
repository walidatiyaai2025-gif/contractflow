<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0036EnterpriseContractWorkflowInstances;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Workflows\ContractWorkflowInstanceRepository;
use SafeContracts\Workflows\ContractWorkflowInstanceService;

$assertions = 0;

function esc_p6_instance_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p6_instance_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p6_instance_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p6_instance_assert(false, $message . ' (no exception)');
}

function esc_p6_instance_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1', 'tenant_id' => '17', 'user_id' => '42',
        'role_code' => $role, 'is_owner' => '0',
    ]];
}

function esc_p6_instance_contract(int $accountant = 42, int $archived = 0, string $status = 'draft'): array
{
    return [[
        'id' => '71', 'accountant_user_id' => (string) $accountant,
        'status' => $status, 'is_archived' => (string) $archived,
    ]];
}

function esc_p6_instance_binding(int $typeId = 31): array
{
    return [['id' => '401', 'contract_id' => '71', 'contract_type_id' => (string) $typeId]];
}

function esc_p6_instance_workflow(string $status = 'active', int $typeId = 31, int $id = 81): array
{
    return [[
        'id' => (string) $id,
        'contract_type_id' => (string) $typeId,
        'workflow_code' => 'standard_flow',
        'status' => $status,
    ]];
}

function esc_p6_instance_version(string $status = 'published', int $workflowId = 81, int $id = 91, int $versionNo = 3): array
{
    return [[
        'id' => (string) $id,
        'workflow_id' => (string) $workflowId,
        'version_no' => (string) $versionNo,
        'version_status' => $status,
    ]];
}

function esc_p6_instance_state(int $id = 301, string $code = 'draft'): array
{
    return [[
        'id' => (string) $id,
        'state_code' => $code,
        'name' => ucfirst($code),
        'is_terminal' => '0',
    ]];
}

function esc_p6_instance_locked_contract(int $typeId = 31): array
{
    return [[
        'id' => '71', 'accountant_user_id' => '42', 'status' => 'draft', 'is_archived' => '0',
        'contract_type_id' => (string) $typeId,
    ]];
}

function esc_p6_instance_locked_definition(int $stateId = 301, string $stateCode = 'draft', int $typeId = 31, int $versionNo = 3): array
{
    return [[
        'workflow_id' => '81',
        'contract_type_id' => (string) $typeId,
        'workflow_code' => 'standard_flow',
        'workflow_version_id' => '91',
        'version_no' => (string) $versionNo,
        'version_status' => 'published',
        'current_state_id' => (string) $stateId,
        'current_state_code' => $stateCode,
    ]];
}

function esc_p6_instance_row(int $id = 501, int $workflowId = 81, int $versionId = 91, int $stateId = 301, int $typeId = 31): array
{
    return [[
        'id' => (string) $id,
        'contract_id' => '71',
        'contract_type_id' => (string) $typeId,
        'workflow_id' => (string) $workflowId,
        'workflow_version_id' => (string) $versionId,
        'workflow_version_no' => '3',
        'workflow_code_snapshot' => 'standard_flow',
        'current_state_id' => (string) $stateId,
        'current_state_code_snapshot' => 'draft',
        'started_by' => '42',
        'started_at' => '2026-08-17 00:00:00',
        'updated_by' => '42',
        'updated_at' => '2026-08-17 00:00:00',
    ]];
}

final class ESC_P6_Instance_Failing_Wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public array $queries = [];
    public array $reads = [];

    public function prepare(string $query, mixed ...$args): string
    {
        $prepared = array_map(
            static fn (mixed $value): mixed => is_int($value) ? $value : "'" . addslashes((string) $value) . "'",
            $args
        );
        return vsprintf($query, $prepared);
    }

    public function query(string $sql): int|false
    {
        $this->queries[] = $sql;
        if (str_starts_with(ltrim($sql), 'INSERT INTO wp_safecontracts_contract_workflow_instances')) {
            return 0;
        }
        return 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        $this->reads[] = $sql;
        if (str_contains($sql, 'FROM wp_safecontracts_contracts c') && str_contains($sql, 'FOR UPDATE')) {
            return esc_p6_instance_locked_contract();
        }
        if (str_contains($sql, 'FROM wp_safecontracts_workflows w') && str_contains($sql, 'FOR UPDATE')) {
            return esc_p6_instance_locked_definition();
        }
        if (str_contains($sql, 'FROM wp_safecontracts_contract_workflow_instances') && str_contains($sql, 'FOR UPDATE')) {
            return [];
        }
        return [];
    }
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0036EnterpriseContractWorkflowInstances.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowInstanceRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowInstanceService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');
$contractSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$statusSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0036EnterpriseContractWorkflowInstances())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p6_instance_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_contract_workflow_instances'), 'P6-002 creates dedicated Contract Workflow Instance table');
esc_p6_instance_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'Contract Workflow Instance is tenant owned');
esc_p6_instance_assert(str_contains($schema, 'contract_id bigint(20) unsigned NOT NULL'), 'instance binds exact contract');
esc_p6_instance_assert(str_contains($schema, 'contract_type_id bigint(20) unsigned NOT NULL'), 'instance snapshots exact Contract Type binding');
esc_p6_instance_assert(str_contains($schema, 'workflow_id bigint(20) unsigned NOT NULL'), 'instance binds exact Workflow');
esc_p6_instance_assert(str_contains($schema, 'workflow_version_id bigint(20) unsigned NOT NULL'), 'instance binds exact Workflow Version');
esc_p6_instance_assert(str_contains($schema, 'workflow_version_no bigint(20) unsigned NOT NULL'), 'instance snapshots immutable version number');
esc_p6_instance_assert(str_contains($schema, 'workflow_code_snapshot varchar(100) NOT NULL'), 'instance snapshots immutable Workflow code');
esc_p6_instance_assert(str_contains($schema, 'current_state_id bigint(20) unsigned NOT NULL'), 'instance stores exact current state identity');
esc_p6_instance_assert(str_contains($schema, 'current_state_code_snapshot varchar(100) NOT NULL'), 'instance snapshots current state code');
esc_p6_instance_assert(str_contains($schema, 'UNIQUE KEY tenant_contract (tenant_id, contract_id)'), 'one Workflow Instance exists per tenant contract');
esc_p6_instance_assert(version_compare(Migrator::LATEST_VERSION, '1.35.0', '>='), 'P6-002 schema version is registered or superseded');
esc_p6_instance_assert(str_contains($migratorSource, "'1.35.0' => Migration0036EnterpriseContractWorkflowInstances::class"), 'P6-002 migration is registered at 1.35.0');
esc_p6_instance_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P6-002 migration is additive');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$service = new ContractWorkflowInstanceService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p6_instance_throws(static fn () => $service->findForContract(71), RuntimeException::class, 'Contract Workflow access fails closed outside Enterprise enforcement');
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p6_instance_throws(static fn () => $service->findForContract(71), RuntimeException::class, 'Contract Workflow access requires locked tenant context');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_instance_actor(),
    esc_p6_instance_contract(),
    esc_p6_instance_binding(),
    esc_p6_instance_workflow(),
    esc_p6_instance_version(),
    esc_p6_instance_state(),
    [],
    esc_p6_instance_locked_contract(),
    esc_p6_instance_locked_definition(),
    [],
];
$GLOBALS['wpdb']->insert_id = 0;
$instanceId = $service->initialize(71, 81, 91);
esc_p6_instance_assert($instanceId === 1001, 'valid initialization returns server-side instance identifier');
esc_p6_instance_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'initialization starts transaction');
$contractLock = (string) ($GLOBALS['sc_test_read_queries'][7] ?? '');
esc_p6_instance_assert(str_contains($contractLock, 'INNER JOIN wp_safecontracts_contract_configuration_bindings b') && str_contains($contractLock, 'FOR UPDATE'), 'initialization locks exact contract and P4 binding');
esc_p6_instance_assert(str_contains($contractLock, 'c.id = 71 AND c.tenant_id = 17 AND c.is_archived = 0'), 'authoritative contract lock is tenant scoped and rejects archived contracts');
$definitionLock = (string) ($GLOBALS['sc_test_read_queries'][8] ?? '');
esc_p6_instance_assert(str_contains($definitionLock, "w.status = 'active'") && str_contains($definitionLock, "v.version_status = 'published'"), 'authoritative Workflow lock requires active Workflow and published Version');
esc_p6_instance_assert(str_contains($definitionLock, 's.is_initial = 1') && str_contains($definitionLock, 'LIMIT 2 FOR UPDATE'), 'authoritative lock proves exactly one bounded initial state');
$instanceLock = (string) ($GLOBALS['sc_test_read_queries'][9] ?? '');
esc_p6_instance_assert(str_contains($instanceLock, 'tenant_id = 17 AND contract_id = 71') && str_contains($instanceLock, 'FOR UPDATE'), 'existing instance identity is locked before insert');
$insertSql = (string) ($GLOBALS['sc_test_queries'][1] ?? '');
esc_p6_instance_assert(str_contains($insertSql, 'INSERT INTO wp_safecontracts_contract_workflow_instances'), 'initialization writes only dedicated P6-002 storage');
esc_p6_instance_assert(str_contains($insertSql, 'SELECT 17, c.id, b.contract_type_id, w.id, v.id, v.version_no'), 'instance identity/snapshots derive server-side from authoritative rows');
esc_p6_instance_assert(str_contains($insertSql, 'w.contract_type_id = b.contract_type_id'), 'write-time Workflow Contract Type must match current P4 binding');
esc_p6_instance_assert(str_contains($insertSql, "v.version_status = 'published'") && str_contains($insertSql, 's.is_initial = 1'), 'write-time version/state publication invariants are revalidated');
esc_p6_instance_assert(($GLOBALS['sc_test_queries'][2] ?? '') === 'COMMIT', 'valid initialization commits atomically');
esc_p6_instance_assert(! in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'valid initialization does not roll back');

$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor(), esc_p6_instance_contract(), esc_p6_instance_row()];
$read = $service->findForContract(71);
esc_p6_instance_assert((int) ($read['id'] ?? 0) === 501, 'tenant-scoped instance read returns current binding');
esc_p6_instance_assert((string) ($read['current_state_code_snapshot'] ?? '') === 'draft', 'instance read preserves current state snapshot');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_instance_actor(), esc_p6_instance_contract(), esc_p6_instance_binding(), esc_p6_instance_workflow(),
    esc_p6_instance_version(), esc_p6_instance_state(), esc_p6_instance_row(),
];
$existingId = $service->initialize(71, 81, 91);
esc_p6_instance_assert($existingId === 501, 'exact re-initialization is idempotent');
esc_p6_instance_assert($GLOBALS['sc_test_queries'] === [], 'exact idempotent initialization performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_instance_actor(), esc_p6_instance_contract(), esc_p6_instance_binding(), esc_p6_instance_workflow(),
    esc_p6_instance_version(), esc_p6_instance_state(), esc_p6_instance_row(501, 81, 92),
];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), DomainException::class, 'conflicting existing Workflow Version cannot be silently rebound');
esc_p6_instance_assert($GLOBALS['sc_test_queries'] === [], 'conflicting rebind rejection occurs before mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor(), esc_p6_instance_contract(42, 1)];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), DomainException::class, 'archived contract cannot initialize Workflow');
esc_p6_instance_assert($GLOBALS['sc_test_queries'] === [], 'archived rejection performs no mutation');

$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor(), esc_p6_instance_contract(), []];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), InvalidArgumentException::class, 'missing P4 Contract Type binding fails closed');

$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor(), esc_p6_instance_contract(), esc_p6_instance_binding(), esc_p6_instance_workflow('inactive')];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), InvalidArgumentException::class, 'inactive Workflow fails closed');

$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor(), esc_p6_instance_contract(), esc_p6_instance_binding(), esc_p6_instance_workflow('active', 32)];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), InvalidArgumentException::class, 'wrong-Contract-Type Workflow fails closed');

$GLOBALS['sc_test_result_queue'] = [
    esc_p6_instance_actor(), esc_p6_instance_contract(), esc_p6_instance_binding(), esc_p6_instance_workflow(), esc_p6_instance_version('draft'),
];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), InvalidArgumentException::class, 'draft Workflow Version cannot initialize instance');

$GLOBALS['sc_test_result_queue'] = [
    esc_p6_instance_actor(), esc_p6_instance_contract(), esc_p6_instance_binding(), esc_p6_instance_workflow(), esc_p6_instance_version(), [],
];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), InvalidArgumentException::class, 'published Workflow without initial state fails closed');

$twoInitial = [esc_p6_instance_state(301, 'draft')[0], esc_p6_instance_state(302, 'other')[0]];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_instance_actor(), esc_p6_instance_contract(), esc_p6_instance_binding(), esc_p6_instance_workflow(), esc_p6_instance_version(), $twoInitial,
];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), InvalidArgumentException::class, 'multiple stored initial states fail closed');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor(), []];
esc_p6_instance_throws(static fn () => $service->initialize(999999, 81, 91), InvalidArgumentException::class, 'foreign contract ID fails current-tenant lookup');
esc_p6_instance_assert(str_contains((string) ($GLOBALS['sc_test_read_queries'][1] ?? ''), 'id = 999999 AND tenant_id = 17'), 'contract identity is never authorization and lookup is tenant scoped');

$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = false;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor(), esc_p6_instance_contract(42), esc_p6_instance_row()];
$own = $service->findForContract(71);
esc_p6_instance_assert((int) ($own['id'] ?? 0) === 501, 'VIEW_ASSIGNED user may read own-accountant Contract Workflow');
$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor(), esc_p6_instance_contract(99)];
esc_p6_instance_throws(static fn () => $service->findForContract(71), DomainException::class, 'VIEW_ASSIGNED user cannot read another accountant Contract Workflow');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), DomainException::class, 'Workflow initialization requires EDIT_CONTRACTS');
esc_p6_instance_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global initialization denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p6_instance_actor('viewer')];
esc_p6_instance_throws(static fn () => $service->initialize(71, 81, 91), DomainException::class, 'tenant viewer cannot bypass Contract Workflow mutation ceiling');

$originalWpdb = $GLOBALS['wpdb'];
$failingWpdb = new ESC_P6_Instance_Failing_Wpdb();
$GLOBALS['wpdb'] = $failingWpdb;
$repository = new ContractWorkflowInstanceRepository();
esc_p6_instance_throws(static fn () => $repository->initialize(71, 81, 91, 42), RuntimeException::class, 'write-time concurrent prerequisite drift aborts initialization');
esc_p6_instance_assert(in_array('ROLLBACK', $failingWpdb->queries, true), 'failed authoritative initialization rolls back');
esc_p6_instance_assert(! in_array('COMMIT', $failingWpdb->queries, true), 'failed authoritative initialization never commits');
esc_p6_instance_assert(count(array_filter($failingWpdb->queries, static fn (string $sql): bool => str_starts_with(ltrim($sql), 'INSERT INTO wp_safecontracts_contract_workflow_instances'))) === 1, 'concurrent drift is detected at dedicated instance insert');
$GLOBALS['wpdb'] = $originalWpdb;

esc_p6_instance_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'Contract Workflow repository has no unscoped tenant fallback');
esc_p6_instance_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'Contract Workflow service enforces tenant-role capability ceiling');
esc_p6_instance_assert(str_contains($serviceSource, 'assertScope') && str_contains($serviceSource, 'VIEW_ASSIGNED'), 'Contract Workflow reads/mutations preserve contract data scope');
esc_p6_instance_assert(str_contains($repositorySource, 'START TRANSACTION') && str_contains($repositorySource, 'FOR UPDATE') && str_contains($repositorySource, 'ROLLBACK'), 'initialization is transactionally lock protected');
esc_p6_instance_assert(! str_contains($repositorySource, 'ON DUPLICATE KEY UPDATE'), 'conflicting instance binding is never silently overwritten');
esc_p6_instance_assert(! str_contains($repositorySource, 'workflow_transitions') && ! str_contains($serviceSource, 'workflow_transitions'), 'P6-002 introduces no transition execution path');
esc_p6_instance_assert(! str_contains($repositorySource, 'approval') && ! str_contains($serviceSource, 'approval'), 'P6-002 introduces no approval routing');
esc_p6_instance_assert(! str_contains($repositorySource, 'custom_field') && ! str_contains($serviceSource, 'custom_field'), 'P6-002 does not rewrite P5 data');
esc_p6_instance_assert(! str_contains($repositorySource, 'UPDATE'), 'P6-002 repository never updates legacy contract or Workflow Definition rows');
esc_p6_instance_assert(str_contains($statusSource, 'final class ContractStatus') && ! str_contains($statusSource, 'ContractWorkflowInstance'), 'legacy ContractStatus remains independent from ESC Workflow instance');
esc_p6_instance_assert(! str_contains($contractSource, 'ContractWorkflowInstance'), 'legacy ContractService lifecycle remains untouched');
esc_p6_instance_assert(str_contains($gateSource, 'enterprise_contract_workflow_instances_p6_002.php'), 'P6-002 regression is explicitly wired into ESC backend Gate');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P6-002 Enterprise Contract Workflow Instance checks passed ({$assertions} assertions).\n";
