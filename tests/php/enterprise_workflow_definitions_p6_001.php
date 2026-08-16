<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0035EnterpriseWorkflowDefinitions;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Workflows\WorkflowDefinitionPolicy;
use SafeContracts\Workflows\WorkflowDefinitionRepository;
use SafeContracts\Workflows\WorkflowDefinitionService;

$assertions = 0;

function esc_p6_wf_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p6_wf_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p6_wf_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p6_wf_assert(false, $message . ' (no exception)');
}

function esc_p6_wf_actor(string $role = 'tenant_admin'): array
{
    return [[
        'id' => '1', 'tenant_id' => '17', 'user_id' => '42',
        'role_code' => $role, 'is_owner' => '0',
    ]];
}

function esc_p6_wf_type(string $status = 'active', int $id = 31): array
{
    return [[
        'id' => (string) $id,
        'type_code' => 'service',
        'name' => 'Service',
        'status' => $status,
    ]];
}

function esc_p6_wf_workflow(string $status = 'active', int $typeId = 31, int $id = 81): array
{
    return [[
        'id' => (string) $id,
        'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'contract_type_id' => (string) $typeId,
        'workflow_code' => 'standard_flow',
        'name' => 'Standard Flow',
        'description' => 'Workflow',
        'status' => $status,
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p6_wf_version(string $status = 'draft', int $workflowId = 81, int $id = 91, int $versionNo = 1): array
{
    return [[
        'id' => (string) $id,
        'workflow_id' => (string) $workflowId,
        'version_no' => (string) $versionNo,
        'version_status' => $status,
        'created_by' => '42', 'updated_by' => '42',
    ]];
}

function esc_p6_wf_state(string $code, bool $initial, bool $terminal, int $order): array
{
    return [
        'state_code' => $code,
        'name' => ucfirst($code),
        'description' => '',
        'sort_order' => $order,
        'is_initial' => $initial,
        'is_terminal' => $terminal,
    ];
}

function esc_p6_wf_transition(string $code, string $source, string $destination, int $order): array
{
    return [
        'transition_code' => $code,
        'source_state_code' => $source,
        'destination_state_code' => $destination,
        'name' => ucfirst($code),
        'description' => '',
        'sort_order' => $order,
    ];
}

function esc_p6_wf_db_state(int $id, string $code, bool $initial, bool $terminal, int $order): array
{
    return [
        'id' => (string) $id,
        'state_code' => $code,
        'name' => ucfirst($code),
        'description' => '',
        'sort_order' => (string) $order,
        'is_initial' => $initial ? '1' : '0',
        'is_terminal' => $terminal ? '1' : '0',
    ];
}

function esc_p6_wf_db_transition(int $id, string $code, string $source, string $destination, int $order): array
{
    return [
        'id' => (string) $id,
        'transition_code' => $code,
        'source_state_code' => $source,
        'destination_state_code' => $destination,
        'name' => ucfirst($code),
        'description' => '',
        'sort_order' => (string) $order,
    ];
}

final class ESC_P6_WF_Failing_Wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public array $queries = [];
    private int $stateSequence = 2000;

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
        if (str_starts_with($trimmed, 'INSERT INTO wp_safecontracts_workflow_states')) {
            $this->insert_id = ++$this->stateSequence;
            return 1;
        }
        if (str_starts_with($trimmed, 'INSERT INTO wp_safecontracts_workflow_transitions')) {
            return false;
        }
        return 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        if (str_contains($sql, 'FOR UPDATE')) {
            return [['id' => '91']];
        }
        return [];
    }
}

final class ESC_P6_WF_Draft_Failing_Wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public array $queries = [];

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
        return 1;
    }

    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output, $sql);
        return [];
    }
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0035EnterpriseWorkflowDefinitions.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowDefinitionPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowDefinitionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/WorkflowDefinitionService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');
$contractSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$statusSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0035EnterpriseWorkflowDefinitions())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
esc_p6_wf_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflows'), 'P6-001 creates tenant Workflow catalog');
esc_p6_wf_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_versions'), 'P6-001 creates Workflow Version table');
esc_p6_wf_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_states'), 'P6-001 creates version State table');
esc_p6_wf_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_transitions'), 'P6-001 creates version Transition table');
esc_p6_wf_assert(substr_count($schema, 'tenant_id bigint(20) unsigned NOT NULL') === 4, 'every P6-001 table is tenant owned');
esc_p6_wf_assert(str_contains($schema, 'contract_type_id bigint(20) unsigned NOT NULL'), 'Workflow identity binds one Contract Type');
esc_p6_wf_assert(str_contains($schema, 'UNIQUE KEY tenant_code (tenant_id, workflow_code)'), 'workflow_code is tenant-local and immutable by schema identity');
esc_p6_wf_assert(str_contains($schema, 'UNIQUE KEY tenant_workflow_version (tenant_id, workflow_id, version_no)'), 'version numbers are unique per tenant Workflow');
esc_p6_wf_assert(substr_count($schema, 'UNIQUE KEY tenant_version_code (tenant_id, workflow_version_id') === 2, 'state and transition codes are version-local unique');
esc_p6_wf_assert(str_contains($schema, 'source_state_id bigint(20) unsigned NOT NULL') && str_contains($schema, 'destination_state_id bigint(20) unsigned NOT NULL'), 'transitions persist exact state endpoints');
esc_p6_wf_assert(Migrator::LATEST_VERSION === '1.34.0', 'P6-001 is current schema version');
esc_p6_wf_assert(str_contains($migratorSource, "'1.34.0' => Migration0035EnterpriseWorkflowDefinitions::class"), 'P6-001 migration is registered');
esc_p6_wf_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P6-001 schema is additive');

esc_p6_wf_assert(WorkflowDefinitionPolicy::normalizeCode(' Approval Flow ') === 'approval_flow', 'Workflow code normalization is deterministic');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeCode('bad/code'), InvalidArgumentException::class, 'invalid Workflow machine code fails closed');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeStatus('deleted'), InvalidArgumentException::class, 'unsupported Workflow status fails closed');

$states = [
    esc_p6_wf_state('draft', true, false, 10),
    esc_p6_wf_state('review', false, false, 20),
    esc_p6_wf_state('done', false, true, 30),
];
$transitions = [
    esc_p6_wf_transition('submit', 'draft', 'review', 10),
    esc_p6_wf_transition('finish', 'review', 'done', 20),
];
$graph = WorkflowDefinitionPolicy::normalizeGraph($states, $transitions);
esc_p6_wf_assert($graph['initial_state_code'] === 'draft', 'exact initial state is resolved');
esc_p6_wf_assert(count($graph['states']) === 3 && count($graph['transitions']) === 2, 'valid bounded graph normalizes');

$cycle = WorkflowDefinitionPolicy::normalizeGraph(
    [esc_p6_wf_state('a', true, false, 1), esc_p6_wf_state('b', false, false, 2)],
    [esc_p6_wf_transition('a_to_b', 'a', 'b', 1), esc_p6_wf_transition('b_to_a', 'b', 'a', 2)]
);
esc_p6_wf_assert(count($cycle['transitions']) === 2, 'reachable non-self cycles remain allowed in Workflow foundation');

esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([], []), InvalidArgumentException::class, 'zero-state graph fails closed');
$tooManyStates = [];
for ($i = 0; $i < 65; $i++) {
    $tooManyStates[] = esc_p6_wf_state('s' . $i, $i === 0, $i === 64, $i);
}
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph($tooManyStates, []), InvalidArgumentException::class, 'state count above 64 fails closed');
$tooManyTransitions = array_fill(0, 257, esc_p6_wf_transition('x', 'a', 'b', 1));
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([esc_p6_wf_state('a', true, false, 1), esc_p6_wf_state('b', false, true, 2)], $tooManyTransitions), InvalidArgumentException::class, 'transition count above 256 fails closed before detailed parsing');

esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('a', false, false, 1), esc_p6_wf_state('b', false, true, 2),
], [esc_p6_wf_transition('go', 'a', 'b', 1)]), InvalidArgumentException::class, 'missing initial state fails closed');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('a', true, false, 1), esc_p6_wf_state('b', true, true, 2),
], [esc_p6_wf_transition('go', 'a', 'b', 1)]), InvalidArgumentException::class, 'multiple initial states fail closed');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('Review Step', true, false, 1), esc_p6_wf_state('review_step', false, true, 2),
], []), InvalidArgumentException::class, 'state-code uniqueness applies after normalization');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('a', true, false, 1), esc_p6_wf_state('b', false, true, 2),
], [esc_p6_wf_transition('go', 'a', 'missing', 1)]), InvalidArgumentException::class, 'foreign/dangling transition endpoint fails closed');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('a', true, false, 1),
], [esc_p6_wf_transition('loop', 'a', 'a', 1)]), InvalidArgumentException::class, 'self-transition fails closed');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('a', true, true, 1), esc_p6_wf_state('b', false, true, 2),
], [esc_p6_wf_transition('go', 'a', 'b', 1)]), InvalidArgumentException::class, 'terminal state cannot have outgoing transition');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('a', true, false, 1), esc_p6_wf_state('b', false, false, 2), esc_p6_wf_state('c', false, true, 3),
], [esc_p6_wf_transition('a_to_b', 'a', 'b', 1)]), InvalidArgumentException::class, 'unreachable state fails closed');
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('a', true, false, 1), esc_p6_wf_state('b', false, true, 2),
], [esc_p6_wf_transition('GO', 'a', 'b', 1), esc_p6_wf_transition('go', 'a', 'b', 2)]), InvalidArgumentException::class, 'duplicate transition code fails closed after normalization');
$stateWithCondition = esc_p6_wf_state('a', true, true, 1);
$stateWithCondition['condition'] = 'php()';
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([$stateWithCondition], []), InvalidArgumentException::class, 'condition/executable graph property is rejected');
$transitionWithExpression = esc_p6_wf_transition('go', 'a', 'b', 1);
$transitionWithExpression['expression'] = 'field > 1';
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([
    esc_p6_wf_state('a', true, false, 1), esc_p6_wf_state('b', false, true, 2),
], [$transitionWithExpression]), InvalidArgumentException::class, 'transition expression property is rejected');
$badBoolean = esc_p6_wf_state('a', true, true, 1);
$badBoolean['is_terminal'] = 1;
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([$badBoolean], []), InvalidArgumentException::class, 'state flags require actual booleans');
$badOrder = esc_p6_wf_state('a', true, true, 1);
$badOrder['sort_order'] = '1';
esc_p6_wf_throws(static fn () => WorkflowDefinitionPolicy::normalizeGraph([$badOrder], []), InvalidArgumentException::class, 'state sort order requires bounded integer');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_REFERENCE_DATA => true];
$service = new WorkflowDefinitionService();
CoreTenantEnforcement::disable();
TenantContextStore::reset();
esc_p6_wf_throws(static fn () => $service->findWorkflow(81), RuntimeException::class, 'Workflow access fails closed outside Enterprise tenant enforcement');
update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
esc_p6_wf_throws(static fn () => $service->findWorkflow(81), RuntimeException::class, 'Workflow access requires locked tenant context');
TenantContextStore::context()->setTenantId(17);

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_wf_actor(), esc_p6_wf_type()];
$GLOBALS['wpdb']->insert_id = 0;
$workflowId = $service->createWorkflow([
    'contract_type_id' => 31,
    'workflow_code' => ' Standard Flow ',
    'name' => 'Standard Flow',
    'description' => 'Base workflow',
    'status' => 'active',
]);
esc_p6_wf_assert($workflowId === 1001, 'Workflow create returns server-side identifier');
$createSql = (string) ($GLOBALS['sc_test_queries'][0] ?? '');
esc_p6_wf_assert(str_contains($createSql, 'INSERT INTO wp_safecontracts_workflows'), 'Workflow create writes dedicated P6 catalog');
esc_p6_wf_assert(str_contains($createSql, 'FROM wp_safecontracts_contract_types ct'), 'Workflow create derives from tenant Contract Type');
esc_p6_wf_assert(str_contains($createSql, "ct.id = 31 AND ct.tenant_id = 17 AND ct.status = 'active'"), 'Workflow create atomically revalidates active current-tenant Contract Type');
esc_p6_wf_assert(str_contains($createSql, "'standard_flow'"), 'workflow_code persists canonical form');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_wf_actor(), esc_p6_wf_type('inactive')];
esc_p6_wf_throws(static fn () => $service->createWorkflow([
    'contract_type_id' => 31, 'workflow_code' => 'blocked', 'name' => 'Blocked', 'description' => '', 'status' => 'active',
]), InvalidArgumentException::class, 'inactive Contract Type cannot author Workflow');
esc_p6_wf_assert($GLOBALS['sc_test_queries'] === [], 'inactive Contract Type rejection performs no mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_wf_actor(), []];
esc_p6_wf_throws(static fn () => $service->createWorkflow([
    'contract_type_id' => 999, 'workflow_code' => 'foreign', 'name' => 'Foreign', 'description' => '', 'status' => 'active',
]), InvalidArgumentException::class, 'foreign Contract Type fails current-tenant lookup');
esc_p6_wf_assert(str_contains((string) ($GLOBALS['sc_test_read_queries'][count($GLOBALS['sc_test_read_queries']) - 1] ?? ''), 'tenant_id = 17'), 'Contract Type identity is never authorization');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_wf_actor()];
esc_p6_wf_throws(static fn () => $service->updateWorkflow(81, ['workflow_code' => 'changed']), InvalidArgumentException::class, 'workflow_code is immutable after creation');
esc_p6_wf_assert($GLOBALS['sc_test_queries'] === [], 'immutable workflow_code rejection performs no mutation');
$GLOBALS['sc_test_result_queue'] = [esc_p6_wf_actor()];
esc_p6_wf_throws(static fn () => $service->updateWorkflow(81, ['contract_type_id' => 32]), InvalidArgumentException::class, 'Workflow Contract Type binding is immutable');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_wf_actor(), esc_p6_wf_workflow(), esc_p6_wf_type(),
    [['id' => '81']], [['max_version' => '7']],
];
$GLOBALS['wpdb']->insert_id = 0;
$versionId = $service->createDraftVersion(81);
esc_p6_wf_assert($versionId === 1001, 'draft version returns server-side identifier');
esc_p6_wf_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'draft version numbering starts transaction');
$versionLock = (string) ($GLOBALS['sc_test_read_queries'][3] ?? '');
esc_p6_wf_assert(str_contains($versionLock, 'FOR UPDATE') && str_contains($versionLock, 'wp_safecontracts_workflows'), 'draft version creation locks exact active Workflow');
$versionInsert = (string) ($GLOBALS['sc_test_queries'][1] ?? '');
esc_p6_wf_assert(str_contains($versionInsert, "VALUES (17, 81, 8, 'draft'"), 'version_no is server-controlled from locked max+1');
esc_p6_wf_assert(($GLOBALS['sc_test_queries'][2] ?? '') === 'COMMIT', 'draft version creation commits atomically');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_wf_actor(), esc_p6_wf_workflow(), esc_p6_wf_type(), esc_p6_wf_version(), [['id' => '91']],
];
$GLOBALS['wpdb']->insert_id = 0;
$service->replaceDraftGraph(81, 91, [esc_p6_wf_state('only', true, true, 1)], []);
esc_p6_wf_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'draft graph replacement starts transaction');
$graphLock = (string) ($GLOBALS['sc_test_read_queries'][4] ?? '');
esc_p6_wf_assert(str_contains($graphLock, 'v.version_status = \'draft\'') && str_contains($graphLock, 'FOR UPDATE'), 'graph replacement locks exact draft version');
esc_p6_wf_assert(str_contains((string) ($GLOBALS['sc_test_queries'][1] ?? ''), 'DELETE FROM wp_safecontracts_workflow_transitions'), 'graph replacement deletes old transitions first');
esc_p6_wf_assert(str_contains((string) ($GLOBALS['sc_test_queries'][2] ?? ''), 'DELETE FROM wp_safecontracts_workflow_states'), 'graph replacement then deletes old states');
esc_p6_wf_assert(str_contains((string) ($GLOBALS['sc_test_queries'][3] ?? ''), 'INSERT INTO wp_safecontracts_workflow_states'), 'graph replacement persists normalized states');
esc_p6_wf_assert(($GLOBALS['sc_test_queries'][4] ?? '') === 'COMMIT', 'valid draft graph replacement commits');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_wf_actor(), esc_p6_wf_workflow(), esc_p6_wf_type(), esc_p6_wf_version('published')];
esc_p6_wf_throws(static fn () => $service->replaceDraftGraph(81, 91, [esc_p6_wf_state('only', true, true, 1)], []), InvalidArgumentException::class, 'published Workflow graph is immutable');
esc_p6_wf_assert($GLOBALS['sc_test_queries'] === [], 'published graph rejection occurs before mutation');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_wf_actor(), esc_p6_wf_workflow(), esc_p6_wf_type(), esc_p6_wf_version(),
    [['id' => '91']], [esc_p6_wf_db_state(301, 'only', true, true, 1)], [],
];
$service->publishVersion(81, 91);
esc_p6_wf_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION', 'Workflow publication starts transaction');
$publishUpdate = (string) ($GLOBALS['sc_test_queries'][1] ?? '');
esc_p6_wf_assert(str_contains($publishUpdate, "SET version_status = 'published'"), 'Workflow publication is one-way draft to published');
esc_p6_wf_assert(($GLOBALS['sc_test_queries'][2] ?? '') === 'COMMIT', 'valid Workflow publication commits');
$publishLock = (string) ($GLOBALS['sc_test_read_queries'][4] ?? '');
esc_p6_wf_assert(str_contains($publishLock, "w.status = 'active' AND ct.status = 'active'") && str_contains($publishLock, 'FOR UPDATE'), 'publication atomically locks/revalidates active Workflow and Contract Type');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p6_wf_actor(), esc_p6_wf_workflow(), esc_p6_wf_type(), esc_p6_wf_version(),
    [['id' => '91']], [], [],
];
esc_p6_wf_throws(static fn () => $service->publishVersion(81, 91), InvalidArgumentException::class, 'empty draft graph cannot be published');
esc_p6_wf_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'invalid graph publication rolls back after authoritative lock');
esc_p6_wf_assert(! in_array('COMMIT', $GLOBALS['sc_test_queries'], true), 'invalid graph publication never commits');

$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p6_wf_actor(), []];
esc_p6_wf_assert($service->findWorkflow(999) === null, 'foreign Workflow ID returns no current-tenant object');
esc_p6_wf_assert(str_contains((string) ($GLOBALS['sc_test_read_queries'][1] ?? ''), 'id = 999 AND tenant_id = 17'), 'Workflow lookup is tenant scoped');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = false;
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
esc_p6_wf_throws(static fn () => $service->createDraftVersion(81), DomainException::class, 'Workflow mutation requires MANAGE_REFERENCE_DATA');
esc_p6_wf_assert($GLOBALS['sc_test_queries'] === [] && $GLOBALS['sc_test_read_queries'] === [], 'global Workflow mutation denial occurs before data access');
$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_REFERENCE_DATA] = true;
$GLOBALS['sc_test_result_queue'] = [esc_p6_wf_actor('viewer')];
esc_p6_wf_throws(static fn () => $service->createDraftVersion(81), DomainException::class, 'tenant viewer cannot bypass Workflow mutation ceiling');

$originalWpdb = $GLOBALS['wpdb'];
$failingWpdb = new ESC_P6_WF_Failing_Wpdb();
$GLOBALS['wpdb'] = $failingWpdb;
$repository = new WorkflowDefinitionRepository();
$normalized = WorkflowDefinitionPolicy::normalizeGraph($states, $transitions);
esc_p6_wf_throws(static fn () => $repository->replaceDraftGraph(81, 91, $normalized['states'], $normalized['transitions'], 42), RuntimeException::class, 'transition persistence failure aborts graph replacement');
esc_p6_wf_assert(in_array('ROLLBACK', $failingWpdb->queries, true), 'partial Workflow graph replacement rolls back');
esc_p6_wf_assert(! in_array('COMMIT', $failingWpdb->queries, true), 'partial Workflow graph replacement never commits');

$draftFailWpdb = new ESC_P6_WF_Draft_Failing_Wpdb();
$GLOBALS['wpdb'] = $draftFailWpdb;
$repository = new WorkflowDefinitionRepository();
esc_p6_wf_throws(static fn () => $repository->createDraftVersion(81, 42), RuntimeException::class, 'concurrent inactive/foreign Workflow lock failure aborts version creation');
esc_p6_wf_assert(in_array('ROLLBACK', $draftFailWpdb->queries, true), 'failed draft version lock rolls back');
esc_p6_wf_assert(! in_array('COMMIT', $draftFailWpdb->queries, true), 'failed draft version lock never commits');
$GLOBALS['wpdb'] = $originalWpdb;

esc_p6_wf_assert(str_contains($repositorySource, 'CoreTenantEnforcement::isEnabled()') && str_contains($repositorySource, 'requireTenantId()'), 'Workflow repository has no unscoped tenant fallback');
esc_p6_wf_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'Workflow service enforces tenant-role authorization ceiling');
esc_p6_wf_assert(str_contains($repositorySource, 'START TRANSACTION') && str_contains($repositorySource, 'FOR UPDATE') && str_contains($repositorySource, 'ROLLBACK'), 'Workflow version/graph writes use transactional locking');
esc_p6_wf_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'expression') && ! str_contains($policySource, 'condition'), 'P6-001 introduces no executable condition/expression engine');
esc_p6_wf_assert(! str_contains($migrationSource, 'safecontracts_contracts') && ! str_contains($repositorySource, 'safecontracts_contracts'), 'P6-001 does not bind or mutate runtime contracts');
esc_p6_wf_assert(! str_contains($migrationSource, 'custom_field') && ! str_contains($repositorySource, 'custom_field'), 'P6-001 does not mutate P5 storage');
esc_p6_wf_assert(! str_contains($migrationSource, 'contract_templates') && ! str_contains($repositorySource, 'contract_templates'), 'P6-001 does not mutate P4 Template storage');
esc_p6_wf_assert(str_contains($statusSource, 'final class ContractStatus') && ! str_contains($statusSource, 'WorkflowDefinition'), 'legacy ContractStatus remains independent');
esc_p6_wf_assert(! str_contains($contractSource, 'WorkflowDefinition'), 'legacy ContractService lifecycle remains untouched');
esc_p6_wf_assert(str_contains($gateSource, 'enterprise_workflow_definitions_p6_001.php'), 'P6-001 regression is explicitly wired into ESC backend Gate');

CoreTenantEnforcement::disable();
TenantContextStore::reset();

echo "P6-001 Enterprise Workflow Definition checks passed ({$assertions} assertions).\n";
