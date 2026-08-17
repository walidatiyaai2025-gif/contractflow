<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Approvals\ApprovalReleasePolicy;
use SafeContracts\Approvals\ApprovalReleaseRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Workflows\ContractWorkflowTransitionRepository;

$assertions = 0;
function esc_p7_release_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function esc_p7_release_throws(callable $callback, string $class, string $needle, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p7_release_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        esc_p7_release_assert(str_contains($error->getMessage(), $needle), $message . ' (message mismatch: ' . $error->getMessage() . ')');
        return;
    }
    esc_p7_release_assert(false, $message . ' (no exception)');
}
function esc_p7_release_instance(int $stateId = 301, string $stateCode = 'draft'): array
{
    return [[
        'contract_id'=>'71','accountant_user_id'=>'42','is_archived'=>'0','instance_id'=>'501',
        'workflow_id'=>'81','workflow_version_id'=>'91',
        'current_state_id'=>(string)$stateId,'current_state_code_snapshot'=>$stateCode,
    ]];
}
function esc_p7_release_transition(?int $routeId = 901, int $toStateId = 302, string $toStateCode = 'review'): array
{
    return [[
        'transition_id'=>'701','workflow_id'=>'81','workflow_version_id'=>'91','transition_code'=>'submit',
        'source_state_id'=>'301','source_state_code'=>'draft',
        'destination_state_id'=>(string)$toStateId,'destination_state_code'=>$toStateCode,
        'approval_route_id'=>$routeId === null ? null : (string)$routeId,
    ]];
}
function esc_p7_release_request(string $status = 'approved', int $fromStateId = 301, string $fromCode = 'draft'): array
{
    return [[
        'id'=>'2001','instance_id'=>'501','contract_id'=>'71','workflow_id'=>'81','workflow_version_id'=>'91',
        'transition_id'=>'701','transition_code_snapshot'=>'submit',
        'from_state_id'=>(string)$fromStateId,'from_state_code_snapshot'=>$fromCode,
        'to_state_id'=>'302','to_state_code_snapshot'=>'review','route_id_snapshot'=>'901','status'=>$status,
        'current_route_id'=>'901','route_transition_code'=>'submit',
        'route_source_state_id'=>'301','route_source_state_code'=>'draft',
        'route_destination_state_id'=>'302','route_destination_state_code'=>'review',
    ]];
}
function esc_p7_release_joined_result(string $releaseHash): array
{
    return [[
        'release_id'=>'4001','request_id'=>'2001','instance_id'=>'501','transition_history_id'=>'3001',
        'release_key_hash'=>$releaseHash,'released_by'=>'42','released_at'=>'2026-08-17 04:30:00',
        'history_id'=>'3001','contract_id'=>'71','workflow_id'=>'81','workflow_version_id'=>'91',
        'transition_id'=>'701','transition_code_snapshot'=>'submit',
        'from_state_id'=>'301','from_state_code_snapshot'=>'draft',
        'to_state_id'=>'302','to_state_code_snapshot'=>'review','actor_user_id'=>'42','occurred_at'=>'2026-08-17 04:30:00',
    ]];
}

$root = dirname(__DIR__, 2);
$p6RepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionRepository.php');
$releaseRepositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalReleaseRepository.php');
$releaseServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalReleaseService.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

esc_p7_release_assert(str_contains($p6RepositorySource, '?callable $afterInstanceLock = null'), 'P6 transaction exposes post-instance-lock integration callback');
esc_p7_release_assert(str_contains($p6RepositorySource, '?callable $afterMutation = null'), 'P6 transaction exposes post-history/CAS pre-commit callback');
esc_p7_release_assert(str_contains($p6RepositorySource, 'bool $allowApprovalRouted = false'), 'direct P6 routed execution is denied by default');
esc_p7_release_assert(str_contains($p6RepositorySource, 'LEFT JOIN {$approvalRoutes}'), 'P6 exact Transition resolution includes exact Approval Route identity');
esc_p7_release_assert(str_contains($p6RepositorySource, 'requires an approved Approval Request release'), 'P6 direct routed execution has explicit fail-closed boundary');
esc_p7_release_assert(strpos($p6RepositorySource, '$afterMutation($historyRow') < strrpos($p6RepositorySource, "query('COMMIT')"), 'release evidence callback executes before final P6 commit');
esc_p7_release_assert(! str_contains($releaseRepositorySource, 'START TRANSACTION') && ! str_contains($releaseServiceSource, 'START TRANSACTION'), 'P7-004 does not create a nested transaction');
esc_p7_release_assert(str_contains($releaseServiceSource, 'transitionRequestKeyHash'), 'release uses domain-separated P6 transition idempotency identity');
esc_p7_release_assert(str_contains($releaseServiceSource, '$this->guards->assertAllowed'), 'release performs fresh P6-004 guard evaluation');
esc_p7_release_assert(str_contains($releaseServiceSource, "true\n        );"), 'release explicitly opts into approval-routed P6 execution');
esc_p7_release_assert(! str_contains($releaseRepositorySource, 'UPDATE wp_safecontracts_contract_workflow_instances') && ! str_contains($releaseRepositorySource, 'contract_workflow_transition_history ('), 'Approval Release repository does not duplicate P6 state/history mutation SQL');
esc_p7_release_assert(str_contains($gateSource, 'enterprise_workflow_approval_release_foundation_p7_004.php'), 'P7-004 foundation regression remains explicitly wired');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);
$p6 = new ContractWorkflowTransitionRepository();
$releases = new ApprovalReleaseRepository();

// Existing P6 no-route behavior remains executable with default direct path.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_release_instance(), [], esc_p7_release_transition(null)];
$direct = $p6->execute(71, 501, 'submit', hash('sha256', 'direct-no-route'), 42);
esc_p7_release_assert($direct['created'] === true, 'direct P6 no-route transition remains executable');
esc_p7_release_assert(end($GLOBALS['sc_test_queries']) === 'COMMIT', 'direct no-route transition commits normally');

// A routed transition cannot bypass Approval through direct P6 execution.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_release_instance(), [], esc_p7_release_transition(901)];
esc_p7_release_throws(
    static fn () => $p6->execute(71, 501, 'submit', hash('sha256', 'direct-routed'), 42),
    RuntimeException::class,
    'requires an approved Approval Request release',
    'direct routed P6 execution is blocked'
);
$routedWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p7_release_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true) && ! in_array('COMMIT', $GLOBALS['sc_test_queries'], true), 'direct routed bypass rolls back');
esc_p7_release_assert(! str_contains($routedWrites, 'INSERT INTO wp_safecontracts_contract_workflow_transition_history') && ! str_contains($routedWrites, 'UPDATE wp_safecontracts_contract_workflow_instances'), 'direct routed bypass creates no P6 history/state mutation');

// Happy release reuses one P6 transaction and inserts evidence after P6 CAS but before commit.
$releaseKey = ApprovalReleasePolicy::normalizeIdempotencyKey('release-happy');
$releaseHash = ApprovalReleasePolicy::releaseKeyHash($releaseKey);
$p6Hash = ApprovalReleasePolicy::transitionRequestKeyHash($releaseKey);
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    esc_p7_release_instance(), [], // P6 instance + P6 idempotency
    [], esc_p7_release_request(), // release identity lock + approved request/route lock
    esc_p7_release_transition(901), // exact P6 Transition
];
$lockedRequest = null; $releaseRow = null; $guardChecked = false;
$released = $p6->execute(
    71, 501, 'submit', $p6Hash, 42,
    static function (array $transition) use (&$lockedRequest, &$guardChecked, $releases): void {
        if (! is_array($lockedRequest)) {
            throw new RuntimeException('test lock missing');
        }
        $releases->assertTransitionMatchesRequest($lockedRequest, $transition);
        $guardChecked = true;
    },
    static function (array $instance) use (&$lockedRequest, $releases, $releaseHash): void {
        $lockedRequest = $releases->lockApprovedRequestForRelease(2001, $releaseHash, $instance);
    },
    static function (array $history) use (&$releaseRow, $releases, $releaseHash): void {
        $releaseRow = $releases->insertRelease(2001, 501, (int)$history['id'], $releaseHash, 42);
    },
    true
);
esc_p7_release_assert($released['created'] === true && $guardChecked, 'approved release resolves exact routed transition and executes fresh pre-mutation validation');
esc_p7_release_assert(is_array($releaseRow) && (int)($releaseRow['transition_history_id'] ?? 0) === (int)($released['history']['id'] ?? 0), 'release evidence links the exact newly created P6 history row');
$happyWrites = $GLOBALS['sc_test_queries']; $happySql = implode("\n", $happyWrites);
$historyIndex = array_search(true, array_map(static fn(string $sql): bool => str_starts_with(ltrim($sql), 'INSERT INTO wp_safecontracts_contract_workflow_transition_history'), $happyWrites), true);
$stateIndex = array_search(true, array_map(static fn(string $sql): bool => str_starts_with(ltrim($sql), 'UPDATE wp_safecontracts_contract_workflow_instances'), $happyWrites), true);
$releaseIndex = array_search(true, array_map(static fn(string $sql): bool => str_starts_with(ltrim($sql), 'INSERT INTO wp_safecontracts_workflow_approval_releases'), $happyWrites), true);
$commitIndex = array_search('COMMIT', $happyWrites, true);
esc_p7_release_assert(is_int($historyIndex) && is_int($stateIndex) && is_int($releaseIndex) && is_int($commitIndex) && $historyIndex < $stateIndex && $stateIndex < $releaseIndex && $releaseIndex < $commitIndex, 'P6 history then CAS then release evidence then commit are one ordered transaction');
esc_p7_release_assert(substr_count($happySql, 'START TRANSACTION') === 1 && substr_count($happySql, 'COMMIT') === 1, 'approved release uses exactly one transaction');

// Fresh guard failure occurs before P6 history/state and rolls back the whole transaction.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_release_instance(), [], [], esc_p7_release_request(), esc_p7_release_transition(901)];
$lockedRequest = null;
esc_p7_release_throws(
    static function () use ($p6, $releases, $releaseHash, &$lockedRequest): void {
        $p6->execute(
            71, 501, 'submit', hash('sha256', 'guard-fail'), 42,
            static function (array $transition) use ($releases, &$lockedRequest): void {
                $releases->assertTransitionMatchesRequest($lockedRequest ?? [], $transition);
                throw new RuntimeException('fresh guard failed');
            },
            static function (array $instance) use ($releases, $releaseHash, &$lockedRequest): void {
                $lockedRequest = $releases->lockApprovedRequestForRelease(2001, $releaseHash, $instance);
            },
            null,
            true
        );
    },
    RuntimeException::class,
    'fresh guard failed',
    'fresh guard failure blocks final release'
);
$guardFailWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p7_release_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true) && ! str_contains($guardFailWrites, 'INSERT INTO wp_safecontracts_contract_workflow_transition_history'), 'guard failure rolls back before P6 history/state');

// Release evidence failure happens after P6 history/CAS but before commit and therefore forces rollback.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_release_instance(), [], esc_p7_release_transition(901)];
esc_p7_release_throws(
    static fn () => $p6->execute(
        71, 501, 'submit', hash('sha256', 'evidence-fail'), 42,
        null,
        null,
        static function (array $history): void {
            if ((int)($history['id'] ?? 0) <= 0) {
                throw new RuntimeException('missing history');
            }
            throw new RuntimeException('release evidence failed');
        },
        true
    ),
    RuntimeException::class,
    'release evidence failed',
    'release evidence failure aborts P6 transaction'
);
$evidenceWrites = implode("\n", $GLOBALS['sc_test_queries']);
esc_p7_release_assert(str_contains($evidenceWrites, 'INSERT INTO wp_safecontracts_contract_workflow_transition_history') && str_contains($evidenceWrites, 'UPDATE wp_safecontracts_contract_workflow_instances'), 'evidence callback runs after P6 history and state CAS');
esc_p7_release_assert(in_array('ROLLBACK', $GLOBALS['sc_test_queries'], true) && ! in_array('COMMIT', $GLOBALS['sc_test_queries'], true), 'release evidence failure rolls back P6 history/state atomically');

// Stale approved request source state is rejected after authoritative P6 lock and before Transition lookup.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [esc_p7_release_instance(302, 'review'), [], [], esc_p7_release_request('approved', 301, 'draft')];
esc_p7_release_throws(
    static fn () => $p6->execute(
        71, 501, 'submit', hash('sha256', 'stale-release'), 42,
        null,
        static fn (array $instance) => $releases->lockApprovedRequestForRelease(2001, $releaseHash, $instance),
        null,
        true
    ),
    RuntimeException::class,
    'no longer matches the locked P6 Workflow instance',
    'stale approved request cannot move independently advanced P6 state'
);
esc_p7_release_assert(count($GLOBALS['sc_test_read_queries']) === 4, 'stale request fails before P6 Transition resolution');

// Pending/rejected requests cannot release.
foreach (['pending','rejected'] as $status) {
    $GLOBALS['sc_test_result_queue'] = [[], esc_p7_release_request($status)];
    esc_p7_release_throws(
        static fn () => $releases->lockApprovedRequestForRelease(2001, $releaseHash, esc_p7_release_instance()[0]),
        RuntimeException::class,
        'Only an approved Approval Request',
        $status . ' Approval Request cannot release'
    );
}

// Exact release result returns public identities only; conflicting retry key fails closed.
$GLOBALS['sc_test_result_queue'] = [esc_p7_release_joined_result($releaseHash)];
$existing = $releases->findReleaseResult(2001, $releaseHash);
esc_p7_release_assert(is_array($existing) && (int)($existing['release']['id'] ?? 0) === 4001 && (int)($existing['history']['id'] ?? 0) === 3001, 'exact release retry resolves immutable release plus P6 history');
esc_p7_release_assert(! array_key_exists('release_key_hash', $existing['release']) && ! array_key_exists('request_key_hash', $existing['history']), 'release and P6 idempotency hashes remain internal');
$GLOBALS['sc_test_result_queue'] = [esc_p7_release_joined_result($releaseHash)];
esc_p7_release_throws(
    static fn () => $releases->findReleaseResult(2001, ApprovalReleasePolicy::releaseKeyHash('another-key')),
    RuntimeException::class,
    'different idempotency key',
    'different release key on already released request fails closed'
);

// Same key cannot be used for another request and same request cannot be released with another key.
$GLOBALS['sc_test_result_queue'] = [[['id'=>'1','request_id'=>'9999','release_key_hash'=>$releaseHash,'transition_history_id'=>'3001']]];
esc_p7_release_throws(
    static fn () => $releases->lockApprovedRequestForRelease(2001, $releaseHash, esc_p7_release_instance()[0]),
    RuntimeException::class,
    'already used for another Approval Request',
    'release key reuse across requests fails closed'
);
$GLOBALS['sc_test_result_queue'] = [[['id'=>'1','request_id'=>'2001','release_key_hash'=>ApprovalReleasePolicy::releaseKeyHash('old-key'),'transition_history_id'=>'3001']]];
esc_p7_release_throws(
    static fn () => $releases->lockApprovedRequestForRelease(2001, $releaseHash, esc_p7_release_instance()[0]),
    RuntimeException::class,
    'already released with a different idempotency key',
    'one Approval Request cannot produce a second release'
);

// Snapshot mismatch in resolved destination fails before history/state mutation.
$GLOBALS['wpdb']->insert_id = 0; $GLOBALS['sc_test_queries'] = []; $GLOBALS['sc_test_result_queue'] = [esc_p7_release_instance(), [], [], esc_p7_release_request(), esc_p7_release_transition(901, 399, 'wrong')];
$lockedRequest = null;
esc_p7_release_throws(
    static function () use ($p6, $releases, $releaseHash, &$lockedRequest): void {
        $p6->execute(
            71, 501, 'submit', hash('sha256', 'snapshot-mismatch'), 42,
            static function (array $transition) use ($releases, &$lockedRequest): void {
                $releases->assertTransitionMatchesRequest($lockedRequest ?? [], $transition);
            },
            static function (array $instance) use (&$lockedRequest, $releases, $releaseHash): void {
                $lockedRequest = $releases->lockApprovedRequestForRelease(2001, $releaseHash, $instance);
            },
            null,
            true
        );
    },
    RuntimeException::class,
    'does not match the resolved P6 Transition',
    'approved request destination snapshot mismatch fails closed'
);
esc_p7_release_assert(! str_contains(implode("\n", $GLOBALS['sc_test_queries']), 'INSERT INTO wp_safecontracts_contract_workflow_transition_history'), 'snapshot mismatch creates no P6 history');

esc_p7_release_assert(str_contains($releaseServiceSource, 'safecontracts_enterprise_workflow_approval_released'), 'new committed release emits bounded approval release domain event');
esc_p7_release_assert(! str_contains($releaseRepositorySource, 'safecontracts_contracts.status') && ! str_contains($releaseServiceSource, 'ContractStatus'), 'P7-004 does not synchronize legacy ContractStatus');

echo "P7-004 Approval Release runtime checks passed ({$assertions} assertions).\n";
