<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0038EnterpriseWorkflowTransitionGuards;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Workflows\ContractWorkflowTransitionPolicy;
use SafeContracts\Workflows\ContractWorkflowTransitionService;
use SafeContracts\Workflows\WorkflowDefinitionRepository;
use SafeContracts\Workflows\WorkflowTransitionGuardEvaluator;
use SafeContracts\Workflows\WorkflowTransitionGuardPolicy;
use SafeContracts\Workflows\WorkflowTransitionGuardRepository;
use SafeContracts\Workflows\WorkflowTransitionGuardService;

$assertions = 0;

function esc_p6_guard_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p6_guard_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p6_guard_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p6_guard_assert(false, $message . ' (no exception)');
}

function esc_p6_guard_actor(string $role = 'tenant_admin'): array
{
    return [['id' => '1', 'tenant_id' => '17', 'user_id' => '42', 'role_code' => $role, 'is_owner' => '0']];
}

function esc_p6_guard_workflow(string $status = 'active'): array
{
    return [[
        'id' => '81', 'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'contract_type_id' => '31', 'workflow_code' => 'standard_flow', 'name' => 'Standard Flow',
        'description' => '', 'status' => $status, 'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p6_guard_version(string $status = 'draft'): array
{
    return [['id' => '91', 'workflow_id' => '81', 'version_no' => '3', 'version_status' => $status, 'created_by' => '42', 'updated_by' => '42']];
}

function esc_p6_guard_transition(): array
{
    return [[
        'id' => '701', 'transition_code' => 'submit', 'source_state_id' => '301', 'source_state_code' => 'draft',
        'destination_state_id' => '302', 'destination_state_code' => 'review',
    ]];
}

function esc_p6_guard_locked_transition(): array
{
    return [[
        'transition_id' => '701', 'transition_code' => 'submit', 'source_state_id' => '301', 'source_state_code' => 'draft',
        'destination_state_id' => '302', 'destination_state_code' => 'review',
    ]];
}

function esc_p6_guard_row(bool $stale = false): array
{
    return [[
        'id' => '901', 'workflow_id' => '81', 'workflow_version_id' => '91', 'transition_id' => '701',
        'position_no' => '1', 'guard_type' => 'dynamic_fields_ready',
        'transition_code_snapshot' => $stale ? 'old_submit' : 'submit',
        'source_state_id_snapshot' => '301', 'source_state_code_snapshot' => 'draft',
        'destination_state_id_snapshot' => '302', 'destination_state_code_snapshot' => 'review',
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p6_guard_contract(int $accountant = 42, int $archived = 0): array
{
    return [['id' => '71', 'accountant_user_id' => (string) $accountant, 'status' => 'draft', 'is_archived' => (string) $archived]];
}

function esc_p6_guard_instance(int $stateId = 301, string $stateCode = 'draft'): array
{
    return [[
        'id' => '501', 'contract_id' => '71', 'contract_type_id' => '31', 'workflow_id' => '81',
        'workflow_version_id' => '91', 'workflow_version_no' => '3', 'workflow_code_snapshot' => 'standard_flow',
        'current_state_id' => (string) $stateId, 'current_state_code_snapshot' => $stateCode,
        'started_by' => '42', 'started_at' => '2026-08-17 00:00:00', 'updated_by' => '42', 'updated_at' => '2026-08-17 00:00:00',
    ]];
}

function esc_p6_guard_locked_instance(int $stateId = 301, string $stateCode = 'draft'): array
{
    return [[
        'contract_id' => '71', 'accountant_user_id' => '42', 'is_archived' => '0', 'instance_id' => '501',
        'workflow_id' => '81', 'workflow_version_id' => '91', 'current_state_id' => (string) $stateId,
        'current_state_code_snapshot' => $stateCode,
    ]];
}

function esc_p6_guard_runtime_transition(): array
{
    return [[
        'transition_id' => '701', 'workflow_id' => '81', 'workflow_version_id' => '91', 'transition_code' => 'submit',
        'source_state_id' => '301', 'source_state_code' => 'draft',
        'destination_state_id' => '302', 'destination_state_code' => 'review',
    ]];
}

function esc_p6_guard_binding(): array
{
    return [['contract_id' => '71', 'contract_type_id' => '31']];
}

function esc_p6_guard_required_definition(): array
{
    return [[
        'id' => '61', 'contract_type_id' => '31', 'field_code' => 'po_number', 'data_type' => 'text',
        'label' => 'PO Number', 'is_required' => '1', 'status' => 'active', 'sort_order' => '1',
        'options_json' => '', 'validation_json' => '',
    ]];
}

function esc_p6_guard_history(string $hash): array
{
    return [[
        'id' => '801', 'instance_id' => '501', 'contract_id' => '71', 'workflow_id' => '81', 'workflow_version_id' => '91',
        'transition_id' => '701', 'transition_code_snapshot' => 'submit', 'from_state_id' => '301',
        'from_state_code_snapshot' => 'draft', 'to_state_id' => '302', 'to_state_code_snapshot' => 'review',
        'request_key_hash' => $hash, 'actor_user_id' => '42', 'occurred_at' => '2026-08-17 00:30:00',
    ]];
}

final class ESC_P6_Guard_Failing_Wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public array $queries = [];

    public function prepare(string $query, mixed ...$args): string
    {
        $prepared = array_map(static fn (mixed $value): mixed => is_int($value) ? $value : "'" . addslashes((string) $value) . "'", $args);
        return vsprintf($query, $prepared);
    }

    public function query(string $sql): int|false
    {
        $this->queries[] = $sql;
        if (str_starts_with(ltrim($sql), 'INSERT INTO wp_safecontracts_workflow_transition_guards')) {
            return false;
        }
        return 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output, $sql);
        return esc_p6_guard_locked_transition();
    }
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0038EnterpriseWorkflowTransitionGuards.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowTransitionGuardPolicy.php');
$guardRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowTransitionGuardRepository.php');
$guardServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowTransitionGuardService.php');
$guardEvaluatorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowTransitionGuardEvaluator.php');
$workflowRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowDefinitionRepository.php');
$transitionRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionRepository.php');
$transitionServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionService.php');
$validationRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldValidationRepository.php');
$validationServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/CustomFields/CustomFieldValidationService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');
$statusSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0038EnterpriseWorkflowTransitionGuards())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p6_guard_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_transition_guards'), 'P6-004 creates dedicated transition guard table');
esc_p6_guard_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'guard rows are tenant owned');
esc_p6_guard_assert(str_contains($schema, 'workflow_version_id bigint(20) unsigned NOT NULL') && str_contains($schema, 'transition_id bigint(20) unsigned NOT NULL'), 'guards bind exact Workflow Version and Transition');
esc_p6_guard_assert(str_contains($schema, 'guard_type varchar(50) NOT NULL'), 'guard type is explicit declarative data');
esc_p6_guard_assert(str_contains($schema, 'transition_code_snapshot varchar(100) NOT NULL'), 'Transition code snapshot is persisted');
esc_p6_guard_assert(str_contains($schema, 'source_state_id_snapshot bigint(20) unsigned NOT NULL') && str_contains($schema, 'destination_state_id_snapshot bigint(20) unsigned NOT NULL'), 'source/destination identity snapshots are persisted');
esc_p6_guard_assert(str_contains($schema, 'UNIQUE KEY tenant_version_transition_type'), 'guard type uniqueness is version/transition scoped');
esc_p6_guard_assert(str_contains($schema, 'UNIQUE KEY tenant_version_transition_position'), 'guard ordering is deterministic');
esc_p6_guard_assert(version_compare(Migrator::LATEST_VERSION, '1.37.0', '>='), 'P6-004 schema version is registered or superseded');
esc_p6_guard_assert(str_contains($migratorSource, "'1.37.0' => Migration0038EnterpriseWorkflowTransitionGuards::class"), 'P6-004 migration is registered at 1.37.0');
esc_p6_guard_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P6-004 migration is additive');

esc_p6_guard_assert(WorkflowTransitionGuardPolicy::normalizeGuardTypes([' dynamic_fields_ready ']) === ['dynamic_fields_ready'], 'guard type normalizes deterministically');
esc_p6_guard_assert(WorkflowTransitionGuardPolicy::normalizeGuardTypes([]) === [], 'empty guard set explicitly means unguarded transition');
esc_p6_guard_throws(static fn () => WorkflowTransitionGuardPolicy::normalizeGuardTypes(['unknown']), InvalidArgumentException::class, 'unsupported guard type fails closed');
esc_p6_guard_throws(static fn () => WorkflowTransitionGuardPolicy::normalizeGuardTypes(['dynamic_fields_ready', 'dynamic_fields_ready']), InvalidArgumentException::class, 'duplicate guard type fails closed');
esc_p6_guard_throws(static fn () => WorkflowTransitionGuardPolicy::normalizeGuardTypes([['guard_type' => 'dynamic_fields_ready']]), InvalidArgumentException::class, 'guard parameters/objects are not accepted');
esc_p6_guard_throws(static fn () => WorkflowTransitionGuardPolicy::normalizeGuardTypes(array_fill(0, 9, 'dynamic_fields_ready')), InvalidArgumentException::class, 'guard count above bound fails closed before parsing');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true, Capabilities::MANAGE_REFERENCE_DATA => true, Capabilities::EDIT_CONTRACTS => true,
    Capabilities::VIEW_ALL => true, Capabilities::VIEW_ASSIGNED => true,
];
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);

$guardService = new WorkflowTransitionGuardService();
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_guard_actor(), esc_p6_guard_workflow(), esc_p6_guard_version(), esc_p6_guard_transition(), esc_p6_guard_locked_transition(),
];
$guardService->replaceDraftGuards(81, 91, 701, ['dynamic_fields_ready']);
esc_p6_guard_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'guard replacement starts transaction');
esc_p6_guard_assert(str_contains((string) ($GLOBALS['sc_test_read_queries'][4] ?? ''), "v.version_status = 'draft'") && str_contains((string) ($GLOBALS['sc_test_read_queries'][4] ?? ''), 'FOR UPDATE'), 'guard replacement locks exact draft Transition and active Workflow/Type');
esc_p6_guard_assert(str_contains((string) ($GLOBALS['sc_test_queries'][1] ?? ''), 'DELETE FROM wp_safecontracts_workflow_transition_guards'), 'guard replacement deletes old exact-transition rows inside transaction');
esc_p6_guard_assert(str_contains((string) ($GLOBALS['sc_test_queries'][2] ?? ''), 'INSERT INTO wp_safecontracts_workflow_transition_guards'), 'guard replacement persists dedicated declarative row');
esc_p6_guard_assert(str_contains((string) ($GLOBALS['sc_test_queries'][2] ?? ''), "'submit'") && str_contains((string) ($GLOBALS['sc_test_queries'][2] ?? ''), "'draft'") && str_contains((string) ($GLOBALS['sc_test_queries'][2] ?? ''), "'review'"), 'guard snapshots authoritative Transition and State codes');
esc_p6_guard_assert(($GLOBALS['sc_test_queries'][3] ?? '') === 'COMMIT', 'valid guard replacement commits atomically');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_guard_actor(), esc_p6_guard_workflow(), esc_p6_guard_version('published'), esc_p6_guard_transition()];
esc_p6_guard_throws(static fn () => $guardService->replaceDraftGuards(81, 91, 701, []), InvalidArgumentException::class, 'published-version guards are immutable');
esc_p6_guard_assert($GLOBALS['sc_test_queries'] === [], 'published guard mutation rejection occurs before write');

$originalWpdb = $GLOBALS['wpdb'];
$failingWpdb = new ESC_P6_Guard_Failing_Wpdb();
$GLOBALS['wpdb'] = $failingWpdb;
$guardRepository = new WorkflowTransitionGuardRepository();
esc_p6_guard_throws(static fn () => $guardRepository->replaceDraftGuards(81, 91, 701, ['dynamic_fields_ready'], 42), RuntimeException::class, 'guard insert failure aborts replacement');
esc_p6_guard_assert(in_array('ROLLBACK', $failingWpdb->queries, true), 'partial guard replacement rolls back');
esc_p6_guard_assert(! in_array('COMMIT', $failingWpdb->queries, true), 'partial guard replacement never commits');
$GLOBALS['wpdb'] = $originalWpdb;

$workflowRepository = new WorkflowDefinitionRepository();
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [['id' => '91']],
    [['id' => '301', 'state_code' => 'draft', 'name' => 'Draft', 'description' => '', 'sort_order' => '1', 'is_initial' => '1', 'is_terminal' => '0'],
     ['id' => '302', 'state_code' => 'review', 'name' => 'Review', 'description' => '', 'sort_order' => '2', 'is_initial' => '0', 'is_terminal' => '1']],
    [['id' => '701', 'transition_code' => 'submit', 'name' => 'Submit', 'description' => '', 'sort_order' => '1', 'source_state_code' => 'draft', 'destination_state_code' => 'review']],
    [['id' => '901']],
];
esc_p6_guard_throws(static fn () => $workflowRepository->publishDraftVersion(81, 91, 42), RuntimeException::class, 'stale/orphan guard snapshot blocks Workflow publication');
esc_p6_guard_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'stale guard publication rolls back');
esc_p6_guard_assert(! array_filter($GLOBALS['sc_test_queries'], static fn (string $sql): bool => str_starts_with(ltrim($sql), 'UPDATE wp_safecontracts_workflow_versions')), 'stale guard blocks before version publication update');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_fired_actions'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_guard_actor(), esc_p6_guard_contract(), esc_p6_guard_instance(),
    esc_p6_guard_locked_instance(), [], esc_p6_guard_runtime_transition(), esc_p6_guard_row(),
    esc_p6_guard_actor(), esc_p6_guard_contract(), esc_p6_guard_binding(), esc_p6_guard_required_definition(), [],
];
$transitionService = new ContractWorkflowTransitionService();
esc_p6_guard_throws(static fn () => $transitionService->execute(71, 'submit', 'guard-block-001'), DomainException::class, 'dynamic_fields_ready guard blocks transition when required value is missing');
esc_p6_guard_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'blocked guarded transition rolls back');
esc_p6_guard_assert(! array_filter($GLOBALS['sc_test_queries'], static fn (string $sql): bool => str_starts_with(ltrim($sql), 'INSERT INTO wp_safecontracts_contract_workflow_transition_history')), 'blocked guard creates no transition history');
esc_p6_guard_assert(! array_filter($GLOBALS['sc_test_queries'], static fn (string $sql): bool => str_starts_with(ltrim($sql), 'UPDATE wp_safecontracts_contract_workflow_instances')), 'blocked guard performs no state movement');
$bindingLock = implode("\n", $GLOBALS['sc_test_read_queries']);
esc_p6_guard_assert(str_contains($bindingLock, 'safecontracts_contract_configuration_bindings') && str_contains($bindingLock, 'FOR UPDATE'), 'guarded readiness locks P4 binding inside transition transaction');
esc_p6_guard_assert(str_contains($bindingLock, 'safecontracts_custom_field_definitions') && str_contains($bindingLock, 'LIMIT 501 FOR UPDATE'), 'guarded readiness locks bounded active-definition range');
esc_p6_guard_assert(str_contains($bindingLock, 'safecontracts_custom_field_values') && str_contains($bindingLock, 'LIMIT 501 FOR UPDATE'), 'guarded readiness locks bounded set-value range');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_fired_actions'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_guard_actor(), esc_p6_guard_contract(), esc_p6_guard_instance(),
    esc_p6_guard_locked_instance(), [], esc_p6_guard_runtime_transition(), esc_p6_guard_row(),
    esc_p6_guard_actor(), esc_p6_guard_contract(), esc_p6_guard_binding(), [], [],
];
$GLOBALS['wpdb']->insert_id = 0;
$allowed = $transitionService->execute(71, 'submit', 'guard-pass-001');
esc_p6_guard_assert(($allowed['idempotent'] ?? true) === false, 'ready guarded transition executes normally');
esc_p6_guard_assert((int) (($allowed['history']['to_state_id'] ?? 0)) === 302, 'ready guard preserves server-derived destination state');
esc_p6_guard_assert(str_contains((string) ($GLOBALS['sc_test_queries'][1] ?? ''), 'INSERT INTO wp_safecontracts_contract_workflow_transition_history'), 'ready guard permits immutable history creation');
esc_p6_guard_assert(str_contains((string) ($GLOBALS['sc_test_queries'][2] ?? ''), 'UPDATE wp_safecontracts_contract_workflow_instances'), 'ready guard permits instance state compare-and-set');
esc_p6_guard_assert(($GLOBALS['sc_test_queries'][3] ?? '') === 'COMMIT', 'ready guarded transition commits atomically');

$retryHash = ContractWorkflowTransitionPolicy::requestKeyHash('guard-pass-001');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_guard_actor(), esc_p6_guard_contract(), esc_p6_guard_instance(302, 'review'),
    esc_p6_guard_locked_instance(302, 'review'),
    [[
        'id' => '801', 'instance_id' => '501', 'contract_id' => '71', 'workflow_id' => '81', 'workflow_version_id' => '91',
        'transition_id' => '701', 'transition_code_snapshot' => 'submit', 'from_state_id' => '301', 'from_state_code_snapshot' => 'draft',
        'to_state_id' => '302', 'to_state_code_snapshot' => 'review', 'request_key_hash' => $retryHash, 'actor_user_id' => '42', 'occurred_at' => '2026-08-17 00:30:00',
    ]],
];
$retry = $transitionService->execute(71, 'submit', 'guard-pass-001');
esc_p6_guard_assert(($retry['idempotent'] ?? false) === true, 'exact guarded retry remains idempotent after state movement');
esc_p6_guard_assert(! array_filter($GLOBALS['sc_test_read_queries'], static fn (string $sql): bool => str_contains($sql, 'safecontracts_workflow_transition_guards')), 'exact retry returns before re-running guards');
esc_p6_guard_assert($GLOBALS['sc_test_queries'] === ['START TRANSACTION', 'COMMIT'], 'exact guarded retry performs no duplicate mutation');

$GLOBALS['sc_test_result_queue'] = [esc_p6_guard_row(true)];
$evaluator = new WorkflowTransitionGuardEvaluator();
esc_p6_guard_throws(static fn () => $evaluator->assertAllowed(71, esc_p6_guard_runtime_transition()[0]), RuntimeException::class, 'stale execution guard snapshot fails closed');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p6_guard_throws(static fn () => $guardService->replaceDraftGuards(81, 91, 701, []), DomainException::class, 'guard authoring requires MANAGE_REFERENCE_DATA');
esc_p6_guard_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'guard authoring denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p6_guard_actor('viewer')];
esc_p6_guard_throws(static fn () => $guardService->replaceDraftGuards(81, 91, 701, []), DomainException::class, 'tenant viewer cannot bypass guard authoring ceiling');

esc_p6_guard_assert(str_contains($guardRepositorySource, 'START TRANSACTION') && str_contains($guardRepositorySource, 'FOR UPDATE') && str_contains($guardRepositorySource, 'ROLLBACK'), 'guard authoring is transactionally protected');
esc_p6_guard_assert(str_contains($workflowRepositorySource, 'assertTransitionGuardsPublishable') && str_contains($workflowRepositorySource, 'guard_type <> %s'), 'Workflow publication fail-closes stale/unsupported guard snapshots');
esc_p6_guard_assert(strpos($transitionRepositorySource, 'return [\'history\' => $existing, \'created\' => false]') < strpos($transitionRepositorySource, 'if ($beforeMutation !== null)'), 'idempotency retry path occurs before guard evaluation');
esc_p6_guard_assert(strpos($transitionRepositorySource, 'if ($beforeMutation !== null)') < strpos($transitionRepositorySource, 'INSERT INTO {$history}'), 'guards run before history/state mutation');
esc_p6_guard_assert(str_contains($transitionServiceSource, 'WorkflowTransitionGuardEvaluator') && str_contains($transitionServiceSource, 'assertAllowed'), 'P6-003 transition service is guard-aware');
esc_p6_guard_assert(str_contains($validationServiceSource, 'validateContractForWorkflowTransition') && str_contains($validationRepositorySource, 'FOR UPDATE'), 'P5 readiness exposes a locked workflow-transition snapshot path');
esc_p6_guard_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'expression') && ! str_contains($policySource, 'condition'), 'guard policy contains no executable condition/expression language');
esc_p6_guard_assert(! str_contains($guardRepositorySource, 'safecontracts_contracts'), 'guard authoring never mutates runtime contracts');
esc_p6_guard_assert(str_contains($statusSource, 'final class ContractStatus') && ! str_contains($statusSource, 'WorkflowTransitionGuard'), 'legacy ContractStatus remains independent');
esc_p6_guard_assert(str_contains($gateSource, 'enterprise_workflow_transition_guards_p6_004.php'), 'P6-004 regression is explicitly wired into ESC backend Gate');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P6-004 Enterprise Workflow transition guard checks passed ({$assertions} assertions).\n";
