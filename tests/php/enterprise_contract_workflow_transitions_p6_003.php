<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0037EnterpriseWorkflowTransitionHistory;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Workflows\ContractWorkflowTransitionPolicy;
use SafeContracts\Workflows\ContractWorkflowTransitionRepository;
use SafeContracts\Workflows\ContractWorkflowTransitionService;

$assertions = 0;

function esc_p6_transition_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p6_transition_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p6_transition_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p6_transition_assert(false, $message . ' (no exception)');
}

function esc_p6_transition_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1', 'tenant_id' => '17', 'user_id' => '42',
        'role_code' => $role, 'is_owner' => '0',
    ]];
}

function esc_p6_transition_contract(int $accountant = 42, int $archived = 0): array
{
    return [[
        'id' => '71', 'accountant_user_id' => (string) $accountant,
        'status' => 'draft', 'is_archived' => (string) $archived,
    ]];
}

function esc_p6_transition_instance(int $stateId = 301, string $stateCode = 'draft'): array
{
    return [[
        'id' => '501',
        'contract_id' => '71',
        'contract_type_id' => '31',
        'workflow_id' => '81',
        'workflow_version_id' => '91',
        'workflow_version_no' => '3',
        'workflow_code_snapshot' => 'standard_flow',
        'current_state_id' => (string) $stateId,
        'current_state_code_snapshot' => $stateCode,
        'started_by' => '42', 'started_at' => '2026-08-17 00:00:00',
        'updated_by' => '42', 'updated_at' => '2026-08-17 00:00:00',
    ]];
}

function esc_p6_transition_locked_instance(int $stateId = 301, string $stateCode = 'draft'): array
{
    return [[
        'contract_id' => '71',
        'accountant_user_id' => '42',
        'is_archived' => '0',
        'instance_id' => '501',
        'workflow_id' => '81',
        'workflow_version_id' => '91',
        'current_state_id' => (string) $stateId,
        'current_state_code_snapshot' => $stateCode,
    ]];
}

function esc_p6_transition_definition(string $code = 'submit', int $fromId = 301, string $fromCode = 'draft', int $toId = 302, string $toCode = 'review'): array
{
    return [[
        'transition_id' => '701',
        'transition_code' => $code,
        'source_state_id' => (string) $fromId,
        'source_state_code' => $fromCode,
        'destination_state_id' => (string) $toId,
        'destination_state_code' => $toCode,
    ]];
}

function esc_p6_transition_history(string $code = 'submit', string $hash = '', int $id = 801): array
{
    if ($hash === '') {
        $hash = ContractWorkflowTransitionPolicy::requestKeyHash('req-001');
    }
    return [[
        'id' => (string) $id,
        'instance_id' => '501',
        'contract_id' => '71',
        'workflow_id' => '81',
        'workflow_version_id' => '91',
        'transition_id' => '701',
        'transition_code_snapshot' => $code,
        'from_state_id' => '301',
        'from_state_code_snapshot' => 'draft',
        'to_state_id' => '302',
        'to_state_code_snapshot' => 'review',
        'request_key_hash' => $hash,
        'actor_user_id' => '42',
        'occurred_at' => '2026-08-17 00:30:00',
    ]];
}

final class ESC_P6_Transition_Update_Failing_Wpdb
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
        $trimmed = ltrim($sql);
        if (str_starts_with($trimmed, 'INSERT INTO wp_safecontracts_contract_workflow_transition_history')) {
            $this->insert_id = 9001;
            return 1;
        }
        if (str_starts_with($trimmed, 'UPDATE wp_safecontracts_contract_workflow_instances')) {
            return 0;
        }
        return 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        $this->reads[] = $sql;
        if (str_contains($sql, 'FROM wp_safecontracts_contracts c') && str_contains($sql, 'FOR UPDATE')) {
            return esc_p6_transition_locked_instance();
        }
        if (str_contains($sql, 'FROM wp_safecontracts_contract_workflow_transition_history') && str_contains($sql, 'FOR UPDATE')) {
            return [];
        }
        if (str_contains($sql, 'FROM wp_safecontracts_workflow_transitions t') && str_contains($sql, 'FOR UPDATE')) {
            return esc_p6_transition_definition();
        }
        return [];
    }
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0037EnterpriseWorkflowTransitionHistory.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');
$contractSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$statusSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0037EnterpriseWorkflowTransitionHistory())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p6_transition_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_contract_workflow_transition_history'), 'P6-003 creates immutable transition history table');
esc_p6_transition_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'transition history is tenant owned');
esc_p6_transition_assert(str_contains($schema, 'instance_id bigint(20) unsigned NOT NULL'), 'transition history snapshots instance identity');
esc_p6_transition_assert(str_contains($schema, 'workflow_version_id bigint(20) unsigned NOT NULL'), 'transition history snapshots exact Workflow Version');
esc_p6_transition_assert(str_contains($schema, 'transition_id bigint(20) unsigned NOT NULL'), 'transition history snapshots exact Transition identity');
esc_p6_transition_assert(str_contains($schema, 'from_state_id bigint(20) unsigned NOT NULL') && str_contains($schema, 'to_state_id bigint(20) unsigned NOT NULL'), 'transition history snapshots exact from/to states');
esc_p6_transition_assert(str_contains($schema, 'request_key_hash char(64) NOT NULL'), 'idempotency key is persisted as SHA-256 hash only');
esc_p6_transition_assert(str_contains($schema, 'UNIQUE KEY tenant_instance_request (tenant_id, instance_id, request_key_hash)'), 'idempotency identity is unique per tenant instance');
esc_p6_transition_assert(version_compare(Migrator::LATEST_VERSION, '1.36.0', '>='), 'P6-003 schema version is registered or superseded');
esc_p6_transition_assert(str_contains($migratorSource, "'1.36.0' => Migration0037EnterpriseWorkflowTransitionHistory::class"), 'P6-003 migration is registered at 1.36.0');
esc_p6_transition_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P6-003 migration is additive');

esc_p6_transition_assert(ContractWorkflowTransitionPolicy::normalizeTransitionCode(' Submit ') === 'submit', 'transition code normalizes through P6-001 machine-code policy');
esc_p6_transition_assert(ContractWorkflowTransitionPolicy::normalizeIdempotencyKey(' req-001 ') === 'req-001', 'idempotency key trims deterministically');
$hash = ContractWorkflowTransitionPolicy::requestKeyHash('req-001');
esc_p6_transition_assert(strlen($hash) === 64 && ctype_xdigit($hash), 'idempotency hash is deterministic SHA-256 hex');
esc_p6_transition_assert($hash === ContractWorkflowTransitionPolicy::requestKeyHash('req-001'), 'same idempotency key hashes identically');
esc_p6_transition_throws(static fn () => ContractWorkflowTransitionPolicy::normalizeIdempotencyKey(''), InvalidArgumentException::class, 'empty idempotency key fails closed');
esc_p6_transition_throws(static fn () => ContractWorkflowTransitionPolicy::normalizeIdempotencyKey(str_repeat('x', 192)), InvalidArgumentException::class, 'oversized idempotency key fails closed');
esc_p6_transition_throws(static fn () => ContractWorkflowTransitionPolicy::normalizeIdempotencyKey("bad\nkey"), InvalidArgumentException::class, 'control characters in idempotency key fail closed');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::EDIT_CONTRACTS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$service = new ContractWorkflowTransitionService();

CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p6_transition_throws(static fn () => $service->listHistory(71), RuntimeException::class, 'transition history access fails closed outside Enterprise enforcement');
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p6_transition_throws(static fn () => $service->listHistory(71), RuntimeException::class, 'transition history access requires locked tenant');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_fired_actions'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_transition_actor(),
    esc_p6_transition_contract(),
    esc_p6_transition_instance(),
    esc_p6_transition_locked_instance(),
    [],
    esc_p6_transition_definition(),
];
$GLOBALS['wpdb']->insert_id = 0;
$result = $service->execute(71, ' Submit ', ' req-001 ');
esc_p6_transition_assert(($result['idempotent'] ?? true) === false, 'first valid transition is not reported as idempotent retry');
$history = $result['history'] ?? [];
esc_p6_transition_assert((int) ($history['id'] ?? 0) === 1001, 'valid transition returns immutable history identifier');
esc_p6_transition_assert((string) ($history['transition_code_snapshot'] ?? '') === 'submit', 'transition code snapshot is canonical');
esc_p6_transition_assert((int) ($history['from_state_id'] ?? 0) === 301 && (int) ($history['to_state_id'] ?? 0) === 302, 'from/to states are server-derived from exact transition');
esc_p6_transition_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'transition execution starts transaction');
$instanceLock = (string) ($GLOBALS['sc_test_read_queries'][3] ?? '');
esc_p6_transition_assert(str_contains($instanceLock, 'INNER JOIN wp_safecontracts_contract_workflow_instances i') && str_contains($instanceLock, 'FOR UPDATE'), 'transition locks exact contract and Workflow Instance');
esc_p6_transition_assert(str_contains($instanceLock, 'c.id = 71 AND c.tenant_id = 17 AND c.is_archived = 0 AND i.id = 501'), 'authoritative lock is tenant scoped and rejects archived/wrong instance');
$idempotencyLock = (string) ($GLOBALS['sc_test_read_queries'][4] ?? '');
esc_p6_transition_assert(str_contains($idempotencyLock, 'instance_id = 501') && str_contains($idempotencyLock, $hash) && str_contains($idempotencyLock, 'FOR UPDATE'), 'idempotency identity is checked under lock before graph execution');
$transitionLock = (string) ($GLOBALS['sc_test_read_queries'][5] ?? '');
esc_p6_transition_assert(str_contains($transitionLock, 't.workflow_id = 81 AND t.workflow_version_id = 91'), 'transition lookup is bound to instance exact Workflow Version');
esc_p6_transition_assert(str_contains($transitionLock, "t.transition_code = 'submit' AND t.source_state_id = 301"), 'transition lookup is bound to canonical code and locked current source state');
esc_p6_transition_assert(str_contains($transitionLock, "v.version_status = 'published'") && str_contains($transitionLock, 'LIMIT 2 FOR UPDATE'), 'transition execution requires exact published version and bounded unique row');
$historyInsert = (string) ($GLOBALS['sc_test_queries'][1] ?? '');
esc_p6_transition_assert(str_contains($historyInsert, 'INSERT INTO wp_safecontracts_contract_workflow_transition_history'), 'transition persists immutable history before state mutation');
esc_p6_transition_assert(str_contains($historyInsert, $hash), 'history persists only normalized request hash');
$instanceUpdate = (string) ($GLOBALS['sc_test_queries'][2] ?? '');
esc_p6_transition_assert(str_contains($instanceUpdate, 'UPDATE wp_safecontracts_contract_workflow_instances'), 'transition mutates only dedicated P6-002 instance current state');
esc_p6_transition_assert(str_contains($instanceUpdate, 'current_state_id = 302') && str_contains($instanceUpdate, "current_state_code_snapshot = 'review'"), 'instance destination is server-derived');
esc_p6_transition_assert(str_contains($instanceUpdate, 'workflow_version_id = 91 AND current_state_id = 301'), 'instance update uses exact-version compare-and-set protection');
esc_p6_transition_assert(($GLOBALS['sc_test_queries'][3] ?? '') === 'COMMIT', 'valid transition commits history and state atomically');
esc_p6_transition_assert(! in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'valid transition does not roll back');
esc_p6_transition_assert(count($GLOBALS['sc_test_fired_actions']['safecontracts_enterprise_contract_workflow_transitioned'] ?? []) === 1, 'new transition emits one downstream integration action');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_fired_actions'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_transition_actor(), esc_p6_transition_contract(), esc_p6_transition_instance(302, 'review'),
    esc_p6_transition_locked_instance(302, 'review'), esc_p6_transition_history('submit', $hash),
];
$retry = $service->execute(71, 'submit', 'req-001');
esc_p6_transition_assert(($retry['idempotent'] ?? false) === true, 'exact idempotent retry returns original result after current state advanced');
esc_p6_transition_assert((int) (($retry['history']['id'] ?? 0)) === 801, 'idempotent retry returns original immutable history row');
esc_p6_transition_assert($GLOBALS['sc_test_queries'] === ['START TRANSACTION', 'COMMIT'], 'idempotent retry performs no duplicate history insert or state update');
esc_p6_transition_assert(($GLOBALS['sc_test_fired_actions']['safecontracts_enterprise_contract_workflow_transitioned'] ?? []) === [], 'idempotent retry does not emit duplicate transition action');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_transition_actor(), esc_p6_transition_contract(), esc_p6_transition_instance(302, 'review'),
    esc_p6_transition_locked_instance(302, 'review'), esc_p6_transition_history('submit', $hash),
];
esc_p6_transition_throws(static fn () => $service->execute(71, 'finish', 'req-001'), RuntimeException::class, 'idempotency key reuse for another transition fails closed');
esc_p6_transition_assert($GLOBALS['sc_test_queries'] === ['START TRANSACTION', 'ROLLBACK'], 'conflicting idempotency reuse performs no history/state mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_transition_actor(), esc_p6_transition_contract(), esc_p6_transition_instance(),
    esc_p6_transition_locked_instance(), [], [],
];
esc_p6_transition_throws(static fn () => $service->execute(71, 'finish', 'req-002'), RuntimeException::class, 'transition unavailable from locked current state fails closed');
esc_p6_transition_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'wrong-current-state transition rolls back');
esc_p6_transition_assert(! array_filter($GLOBALS['sc_test_queries'], static fn (string $sql): bool => str_starts_with(ltrim($sql), 'INSERT INTO wp_safecontracts_contract_workflow_transition_history')), 'wrong-current-state transition writes no history');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_transition_actor(), esc_p6_transition_contract(42, 1)];
esc_p6_transition_throws(static fn () => $service->execute(71, 'submit', 'req-003'), DomainException::class, 'archived contract cannot execute Workflow transition');
esc_p6_transition_assert($GLOBALS['sc_test_queries'] === [], 'archived transition rejection occurs before mutation');

$GLOBALS['sc_test_result_queue'] = [esc_p6_transition_actor(), esc_p6_transition_contract(), []];
esc_p6_transition_throws(static fn () => $service->execute(71, 'submit', 'req-004'), InvalidArgumentException::class, 'contract without Workflow Instance fails closed');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_transition_actor(), []];
esc_p6_transition_throws(static fn () => $service->execute(999999, 'submit', 'req-005'), InvalidArgumentException::class, 'foreign contract identity fails current-tenant lookup');
esc_p6_transition_assert(str_contains((string) ($GLOBALS['sc_test_read_queries'][1] ?? ''), 'id = 999999 AND tenant_id = 17'), 'contract object ID is never authorization');

$GLOBALS['sc_test_result_queue'] = [esc_p6_transition_actor(), esc_p6_transition_contract(), [esc_p6_transition_history()[0]]];
$listed = $service->listHistory(71, 1000, -10);
esc_p6_transition_assert(count($listed) === 1 && (int) ($listed[0]['id'] ?? 0) === 801, 'history read is tenant scoped and bounded');
$historyReadSql = (string) end($GLOBALS['sc_test_read_queries']);
esc_p6_transition_assert(str_contains($historyReadSql, 'tenant_id = 17 AND contract_id = 71') && str_contains($historyReadSql, 'LIMIT 100 OFFSET 0'), 'history pagination clamps to safe bounds');

$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = false;
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ASSIGNED] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p6_transition_actor(), esc_p6_transition_contract(42), []];
esc_p6_transition_assert($service->listHistory(71) === [], 'VIEW_ASSIGNED user may read own-accountant Workflow history');
$GLOBALS['sc_test_result_queue'] = [esc_p6_transition_actor(), esc_p6_transition_contract(99)];
esc_p6_transition_throws(static fn () => $service->listHistory(71), DomainException::class, 'VIEW_ASSIGNED user cannot read another accountant Workflow history');
$GLOBALS['sc_test_current_caps'][Capabilities::VIEW_ALL] = true;

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p6_transition_throws(static fn () => $service->execute(71, 'submit', 'req-006'), DomainException::class, 'Workflow transition mutation requires EDIT_CONTRACTS');
esc_p6_transition_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global transition denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p6_transition_actor('viewer')];
esc_p6_transition_throws(static fn () => $service->execute(71, 'submit', 'req-007'), DomainException::class, 'tenant viewer cannot bypass Workflow transition mutation ceiling');

$originalWpdb = $GLOBALS['wpdb'];
$failingWpdb = new ESC_P6_Transition_Update_Failing_Wpdb();
$GLOBALS['wpdb'] = $failingWpdb;
$repository = new ContractWorkflowTransitionRepository();
esc_p6_transition_throws(static fn () => $repository->execute(71, 501, 'submit', $hash, 42), RuntimeException::class, 'compare-and-set state failure aborts transition after history insert');
esc_p6_transition_assert(in_array('ROLLBACK', $failingWpdb->queries, true), 'failed instance compare-and-set rolls back immutable history too');
esc_p6_transition_assert(! in_array('COMMIT', $failingWpdb->queries, true), 'partial Workflow transition never commits');
esc_p6_transition_assert(count(array_filter($failingWpdb->queries, static fn (string $sql): bool => str_starts_with(ltrim($sql), 'INSERT INTO wp_safecontracts_contract_workflow_transition_history'))) === 1, 'failure occurs after one attempted history insert');
esc_p6_transition_assert(count(array_filter($failingWpdb->queries, static fn (string $sql): bool => str_starts_with(ltrim($sql), 'UPDATE wp_safecontracts_contract_workflow_instances'))) === 1, 'failure occurs at compare-and-set instance update');
$GLOBALS['wpdb'] = $originalWpdb;

esc_p6_transition_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'transition repository has no unscoped tenant fallback');
esc_p6_transition_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability') && str_contains($serviceSource, 'assertScope'), 'transition service enforces tenant-role and contract data scope');
esc_p6_transition_assert(str_contains($repositorySource, 'START TRANSACTION') && str_contains($repositorySource, 'FOR UPDATE') && str_contains($repositorySource, 'ROLLBACK'), 'transition execution is transactionally lock protected');
esc_p6_transition_assert(str_contains($repositorySource, 'request_key_hash') && str_contains($repositorySource, 'created' . "' => false"), 'transition repository has explicit retry-safe idempotency path');
esc_p6_transition_assert(str_contains($repositorySource, 'UPDATE {$instances}') && ! str_contains($repositorySource, 'UPDATE {$contracts}'), 'runtime transition updates only dedicated P6-002 instance, never legacy contract');
esc_p6_transition_assert(! str_contains($repositorySource, 'UPDATE {$workflows}') && ! str_contains($repositorySource, 'UPDATE {$versions}') && ! str_contains($repositorySource, 'UPDATE {$states}') && ! str_contains($repositorySource, 'UPDATE {$transitions}'), 'P6-001 Workflow Definition/history remains immutable during execution');
esc_p6_transition_assert(! str_contains($repositorySource, 'custom_field') && ! str_contains($serviceSource, 'custom_field'), 'P6-003 does not rewrite P5 data');
esc_p6_transition_assert(
    str_contains($repositorySource, 'approval_route_id')
        && str_contains($repositorySource, 'allowApprovalRouted')
        && ! str_contains($repositorySource, 'workflow_approval_decisions')
        && ! str_contains($repositorySource, 'workflow_approval_releases')
        && ! str_contains($serviceSource, 'ApprovalDecision')
        && ! str_contains($serviceSource, 'ApprovalRelease'),
    'P6 runtime exposes only the P7 route-presence execution gate; Approval Decision/Release orchestration remains outside P6'
);
esc_p6_transition_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'condition') && ! str_contains($policySource, 'expression'), 'P6-003 introduces no condition/expression engine');
esc_p6_transition_assert(str_contains($statusSource, 'final class ContractStatus') && ! str_contains($statusSource, 'ContractWorkflowTransition'), 'legacy ContractStatus remains separate');
esc_p6_transition_assert(! str_contains($contractSource, 'ContractWorkflowTransition'), 'legacy ContractService remains untouched');
esc_p6_transition_assert(str_contains($gateSource, 'enterprise_contract_workflow_transitions_p6_003.php'), 'P6-003 regression is explicitly wired into ESC backend Gate');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P6-003 Enterprise Contract Workflow transition checks passed ({$assertions} assertions).\n";
