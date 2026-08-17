<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Approvals\ApprovalDecisionPolicy;
use SafeContracts\Approvals\ApprovalDecisionRepository;
use SafeContracts\Database\Migrator;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p7_dec_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function esc_p7_dec_throws(callable $callback, string $class, string $needle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p7_dec_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        esc_p7_dec_assert(str_contains($error->getMessage(), $needle), $message . ' (message mismatch: ' . $error->getMessage() . ')');
        return;
    }
    esc_p7_dec_assert(false, $message . ' (no exception)');
}
function esc_p7_dec_request(string $status = 'pending'): array
{
    return [[
        'id'=>'2001','contract_id'=>'71','instance_id'=>'501','status'=>$status,
        'transition_id'=>'701','from_state_id'=>'301',
    ]];
}
function esc_p7_dec_contract(): array
{
    return [['id'=>'71']];
}
function esc_p7_dec_stages(): array
{
    return [
        ['id'=>'1101','position_no'=>'1','stage_code_snapshot'=>'finance_review','decision_policy_snapshot'=>'all','required_approvals_snapshot'=>'0'],
        ['id'=>'1102','position_no'=>'2','stage_code_snapshot'=>'legal_review','decision_policy_snapshot'=>'quorum','required_approvals_snapshot'=>'2'],
    ];
}
function esc_p7_dec_candidates(): array
{
    return [
        ['request_stage_id'=>'1101','user_id'=>'42'],
        ['request_stage_id'=>'1101','user_id'=>'55'],
        ['request_stage_id'=>'1102','user_id'=>'42'],
        ['request_stage_id'=>'1102','user_id'=>'66'],
        ['request_stage_id'=>'1102','user_id'=>'77'],
    ];
}
function esc_p7_dec_row(int $id, int $stageId, int $userId, string $action = 'approve', string $hash = 'hash'): array
{
    return [
        'id'=>(string)$id,'request_id'=>'2001','request_stage_id'=>(string)$stageId,
        'user_id'=>(string)$userId,'action'=>$action,'decision_key_hash'=>$hash,
        'comment'=>null,'decided_at'=>'2026-08-17 04:10:00',
    ];
}

$root = dirname(__DIR__, 2);
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalDecisionRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalDecisionService.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

esc_p7_dec_assert(version_compare(Migrator::LATEST_VERSION, '1.40.0', '>='), 'P7-003 runtime remains at or before current schema version');
esc_p7_dec_assert(str_contains($migratorSource, "'1.40.0' => Migration0041EnterpriseWorkflowApprovalDecisions::class"), 'P7-003 decision migration remains registered');
esc_p7_dec_assert(str_contains($serviceSource, 'Capabilities::EDIT_CONTRACTS'), 'decision mutation requires contract edit capability');
esc_p7_dec_assert(str_contains($serviceSource, 'Capabilities::ACCESS'), 'decision history read requires Enterprise access');
esc_p7_dec_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'decision service preserves tenant-role narrowing');
esc_p7_dec_assert(str_contains($repositorySource, 'PUBLIC_DECISION_COLUMNS') && str_contains($repositorySource, 'INTERNAL_DECISION_COLUMNS'), 'decision repository separates public and internal read models');
esc_p7_dec_assert(! str_contains(substr($repositorySource, strpos($repositorySource, 'PUBLIC_DECISION_COLUMNS'), 180), 'decision_key_hash'), 'public decision projection excludes idempotency hash');
esc_p7_dec_assert(! str_contains($repositorySource, 'UPDATE {$stages}') && ! str_contains($repositorySource, 'UPDATE wp_safecontracts_workflow_approval_request_stages'), 'stage progression is derived without mutable stage status');
esc_p7_dec_assert(! str_contains($repositorySource, 'contract_workflow_transition_history'), 'P7-003 repository does not write P6 transition history');
esc_p7_dec_assert(! str_contains($repositorySource, 'UPDATE {$instances}') && ! str_contains($repositorySource, 'UPDATE wp_safecontracts_contract_workflow_instances'), 'P7-003 repository does not move P6 workflow state');
esc_p7_dec_assert(str_contains($gateSource, 'enterprise_workflow_approval_decision_foundation_p7_003.php') && str_contains($gateSource, 'enterprise_workflow_approval_decisions_p7_003.php'), 'P7-003 regressions are explicitly wired');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$repository = new ApprovalDecisionRepository();

// First all-policy approval: request + active contract + immutable snapshots are locked; stage remains incomplete.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(), []];
$first = $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('d-1'), null);
esc_p7_dec_assert($first['request_status'] === 'pending' && $first['stage_position'] === 1, 'first all-policy approval stays on stage one');
esc_p7_dec_assert($first['stage_completed'] === false && $first['request_completed'] === false, 'first all-policy approval does not complete stage or request');
$writes = implode("\n", $GLOBALS['sc_test_queries']); $reads = implode("\n", $GLOBALS['sc_test_read_queries']);
esc_p7_dec_assert(($GLOBALS['sc_test_queries'][0] ?? '') === 'START TRANSACTION' && end($GLOBALS['sc_test_queries']) === 'COMMIT', 'decision recording is transactional');
esc_p7_dec_assert(str_contains($reads, 'safecontracts_workflow_approval_requests') && str_contains($reads, 'LIMIT 2 FOR UPDATE'), 'decision locks exact request first');
esc_p7_dec_assert(str_contains($reads, 'safecontracts_contracts') && str_contains($reads, 'is_archived = 0') && str_contains($reads, 'FOR UPDATE'), 'new decision re-locks active contract in same transaction');
esc_p7_dec_assert(str_contains($reads, 'safecontracts_workflow_approval_request_stages') && str_contains($reads, 'FOR UPDATE'), 'decision locks immutable stage snapshots');
esc_p7_dec_assert(str_contains($reads, 'safecontracts_workflow_approval_request_candidates') && str_contains($reads, 'FOR UPDATE'), 'decision locks immutable candidate snapshots');
esc_p7_dec_assert(str_contains($writes, 'INSERT INTO wp_safecontracts_workflow_approval_decisions'), 'immutable decision row is inserted');
esc_p7_dec_assert(str_contains($writes, ', NULL,'), 'missing optional comment persists as SQL NULL');
esc_p7_dec_assert(! str_contains($writes, 'UPDATE wp_safecontracts_workflow_approval_requests'), 'incomplete stage does not mutate request status');

// Second distinct approval completes stage one but not the two-stage request.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(),
    [esc_p7_dec_row(3001,1101,42,'approve','old-1')],
];
$stageOneDone = $repository->recordDecision(2001, 55, 'approve', ApprovalDecisionPolicy::decisionKeyHash('d-2'), null);
esc_p7_dec_assert($stageOneDone['stage_position'] === 1 && $stageOneDone['stage_completed'] === true, 'second all-policy approval completes first stage');
esc_p7_dec_assert($stageOneDone['request_status'] === 'pending' && $stageOneDone['request_completed'] === false, 'completing non-final stage leaves request pending');
esc_p7_dec_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'UPDATE wp_safecontracts_workflow_approval_requests'), 'non-final stage advancement is derived without mutable stage/request marker');

// Stage one derived complete activates stage two; first quorum approval remains pending.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(),
    [esc_p7_dec_row(3001,1101,42,'approve','old-1'), esc_p7_dec_row(3002,1101,55,'approve','old-2')],
];
$quorumOne = $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('d-3'), 'reviewed');
esc_p7_dec_assert($quorumOne['stage_position'] === 2 && $quorumOne['stage_code'] === 'legal_review', 'derived progression activates second stage');
esc_p7_dec_assert($quorumOne['stage_completed'] === false && $quorumOne['request_status'] === 'pending', 'first quorum approval is not enough');

// Second quorum approval completes final stage and CAS-updates only Approval Request status.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(),
    [
        esc_p7_dec_row(3001,1101,42,'approve','old-1'), esc_p7_dec_row(3002,1101,55,'approve','old-2'),
        esc_p7_dec_row(3003,1102,42,'approve','old-3'),
    ],
];
$approved = $repository->recordDecision(2001, 66, 'approve', ApprovalDecisionPolicy::decisionKeyHash('d-4'), null);
esc_p7_dec_assert($approved['stage_position'] === 2 && $approved['stage_completed'] === true, 'second distinct quorum approval completes final stage');
esc_p7_dec_assert($approved['request_status'] === 'approved' && $approved['request_completed'] === true, 'final quorum approval marks request approved');
$approvedWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p7_dec_assert(str_contains($approvedWrites, "UPDATE wp_safecontracts_workflow_approval_requests SET status = 'approved'"), 'final approval CAS-updates request status');
esc_p7_dec_assert(! str_contains($approvedWrites, 'contract_workflow_instances') && ! str_contains($approvedWrites, 'transition_history'), 'final request approval still does not move P6 state/history');

// Reject is immediately terminal and never advances a stage.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(), []];
$rejected = $repository->recordDecision(2001, 42, 'reject', ApprovalDecisionPolicy::decisionKeyHash('reject-1'), 'not acceptable');
esc_p7_dec_assert($rejected['request_status'] === 'rejected' && $rejected['request_completed'] === true, 'valid reject terminates request');
esc_p7_dec_assert($rejected['stage_completed'] === false, 'reject does not mark approval stage complete');
esc_p7_dec_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "UPDATE wp_safecontracts_workflow_approval_requests SET status = 'rejected'"), 'reject CAS-updates request status');

// Contract archival/concurrent disappearance is revalidated after idempotency but before stage/candidate reads.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [], []];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('archived-race'), null),
    RuntimeException::class,
    'no longer decisionable',
    'archived or concurrently missing contract rejects new decision'
);
esc_p7_dec_assert(count($GLOBALS['sc_test_read_queries']) === 3, 'contract drift fails before stage/candidate reads');
esc_p7_dec_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO wp_safecontracts_workflow_approval_decisions'), 'contract drift writes no decision');

// Future-stage-only candidate cannot decide while stage one is active.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(), []];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 66, 'approve', ApprovalDecisionPolicy::decisionKeyHash('future'), null),
    RuntimeException::class,
    'not an immutable candidate of the active',
    'future-stage candidate cannot decide early'
);
esc_p7_dec_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true), 'future-stage attempt rolls back');
esc_p7_dec_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO wp_safecontracts_workflow_approval_decisions'), 'future-stage attempt writes no decision');

// Non-candidate cannot decide.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(), []];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 99, 'approve', ApprovalDecisionPolicy::decisionKeyHash('outsider'), null),
    RuntimeException::class,
    'not an immutable candidate',
    'non-candidate actor rejected'
);

// Orphan and duplicate candidate snapshots fail closed instead of being ignored/de-duplicated at decision time.
$orphanCandidates = esc_p7_dec_candidates(); $orphanCandidates[] = ['request_stage_id'=>'9999','user_id'=>'88'];
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), $orphanCandidates, []];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('orphan-candidate'), null),
    RuntimeException::class,
    'orphaned or invalid',
    'orphan candidate stage rejected'
);
$duplicateCandidates = esc_p7_dec_candidates(); $duplicateCandidates[] = ['request_stage_id'=>'1101','user_id'=>'42'];
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), $duplicateCandidates, []];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('duplicate-candidate'), null),
    RuntimeException::class,
    'duplicate stage/user',
    'duplicate candidate stage/user rejected'
);

// Same actor/stage with another key cannot create a second effective decision.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(),
    [esc_p7_dec_row(3001,1101,42,'approve','different-key')],
];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('new-key'), null),
    RuntimeException::class,
    'already recorded',
    'second effective decision with another key rejected'
);

// Exact retry is idempotent even after the request became terminal; hash stays internal and no contract revalidation is needed.
$retryHash = ApprovalDecisionPolicy::decisionKeyHash('retry-key');
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_dec_request('approved'),
    [esc_p7_dec_row(3004,1102,66,'approve',$retryHash)],
    [['id'=>'1102','position_no'=>'2','stage_code_snapshot'=>'legal_review','decision_policy_snapshot'=>'quorum','required_approvals_snapshot'=>'2']],
    [['user_id'=>'42'],['user_id'=>'66'],['user_id'=>'77']],
    [['user_id'=>'42','action'=>'approve'],['user_id'=>'66','action'=>'approve']],
];
$retry = $repository->recordDecision(2001, 66, 'approve', $retryHash, 'ignored on retry');
esc_p7_dec_assert($retry['idempotent'] === true && $retry['request_status'] === 'approved', 'exact retry returns original decision after terminal completion');
esc_p7_dec_assert(! array_key_exists('decision_key_hash', $retry['decision']), 'exact retry does not expose internal decision hash');
esc_p7_dec_assert($GLOBALS['sc_test_queries'] === ['START TRANSACTION','COMMIT'], 'exact retry performs no duplicate persistence/status mutation');
esc_p7_dec_assert(! str_contains(implode("\n", $GLOBALS['sc_test_read_queries']), 'safecontracts_contracts'), 'exact retry remains idempotency-first before mutable contract revalidation');

// Reusing the key for another action fails closed before stage derivation.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [esc_p7_dec_row(3004,1101,42,'approve',$retryHash)]];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 42, 'reject', $retryHash, null),
    RuntimeException::class,
    'different operation',
    'decision idempotency key action conflict rejected'
);

// A new key cannot mutate an already terminal request.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request('rejected'), []];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('after-terminal'), null),
    RuntimeException::class,
    'already terminal',
    'terminal request rejects new decision key'
);

// Pending request with a stored reject is inconsistent and fails closed rather than reopening progression.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_dec_request(), [], esc_p7_dec_contract(), esc_p7_dec_stages(), esc_p7_dec_candidates(),
    [esc_p7_dec_row(3005,1101,55,'reject','old-reject')],
];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('after-inconsistent-reject'), null),
    RuntimeException::class,
    'terminal rejection',
    'pending request containing reject fails closed'
);

// Malformed quorum threshold fails closed before insert.
$badStages = esc_p7_dec_stages(); $badStages[0]['decision_policy_snapshot'] = 'quorum'; $badStages[0]['required_approvals_snapshot'] = '3';
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_dec_request(), [], esc_p7_dec_contract(), $badStages, esc_p7_dec_candidates(), []];
esc_p7_dec_throws(
    static fn () => $repository->recordDecision(2001, 42, 'approve', ApprovalDecisionPolicy::decisionKeyHash('bad-quorum'), null),
    RuntimeException::class,
    'decision policy snapshot is invalid',
    'quorum above immutable candidate count rejected'
);

// Source-level isolation: only request terminal state and new decision table are mutable in P7-003 runtime.
esc_p7_dec_assert(substr_count($repositorySource, 'UPDATE {$requests} SET status') === 1, 'P7-003 has one bounded request-status mutation path');
esc_p7_dec_assert(! str_contains($repositorySource, 'safecontracts_contracts SET') && ! str_contains($repositorySource, 'safecontracts_contract_configuration_bindings SET'), 'P7-003 does not mutate legacy/P4 contract storage');
esc_p7_dec_assert(! str_contains($repositorySource, 'safecontracts_workflow_transition_approval_routes SET'), 'P7-003 does not rewrite P7-001 route definitions');
esc_p7_dec_assert(str_contains($serviceSource, 'safecontracts_enterprise_workflow_approval_decided'), 'new non-idempotent decision emits bounded domain event for later notification integration');

echo "P7-003 Approval Decision runtime checks passed ({$assertions} assertions).\n";
