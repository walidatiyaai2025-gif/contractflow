<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Approvals\ApprovalDecisionPolicy;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0041EnterpriseWorkflowApprovalDecisions;

$assertions = 0;
function esc_p7_decision_foundation_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function esc_p7_decision_foundation_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p7_decision_foundation_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p7_decision_foundation_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0041EnterpriseWorkflowApprovalDecisions.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalDecisionPolicy.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0041EnterpriseWorkflowApprovalDecisions())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);

esc_p7_decision_foundation_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_approval_decisions'), 'P7-003 creates dedicated decision table');
esc_p7_decision_foundation_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'decision rows are tenant owned');
esc_p7_decision_foundation_assert(str_contains($schema, 'request_id bigint(20) unsigned NOT NULL') && str_contains($schema, 'request_stage_id bigint(20) unsigned NOT NULL'), 'decision binds exact request and stage');
esc_p7_decision_foundation_assert(str_contains($schema, 'user_id bigint(20) unsigned NOT NULL'), 'decision snapshots actor user identity');
esc_p7_decision_foundation_assert(str_contains($schema, 'decision_key_hash char(64) NOT NULL'), 'only hashed decision idempotency identity is stored');
esc_p7_decision_foundation_assert(! str_contains($schema, 'decision_key varchar'), 'raw decision idempotency key is not stored');
esc_p7_decision_foundation_assert(str_contains($schema, 'UNIQUE KEY tenant_decision_key (tenant_id, decision_key_hash)'), 'decision idempotency key cannot be reused across tenant operations');
esc_p7_decision_foundation_assert(str_contains($schema, 'UNIQUE KEY tenant_stage_user (tenant_id, request_stage_id, user_id)'), 'one immutable effective decision exists per candidate and stage');
esc_p7_decision_foundation_assert(str_contains($schema, 'KEY tenant_stage_action'), 'stage action aggregation has an indexed access path');
esc_p7_decision_foundation_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P7-003 decision schema is additive');
esc_p7_decision_foundation_assert(version_compare(Migrator::LATEST_VERSION, '1.40.0', '>='), 'P7-003 migration remains at or before current schema version');
esc_p7_decision_foundation_assert(str_contains($migratorSource, "'1.40.0' => Migration0041EnterpriseWorkflowApprovalDecisions::class"), 'P7-003 migration is registered exactly at 1.40.0');

esc_p7_decision_foundation_assert(ApprovalDecisionPolicy::normalizeAction(' APPROVE ') === 'approve', 'approve action canonicalizes');
esc_p7_decision_foundation_assert(ApprovalDecisionPolicy::normalizeAction('Reject') === 'reject', 'reject action canonicalizes');
esc_p7_decision_foundation_throws(static fn () => ApprovalDecisionPolicy::normalizeAction('delegate'), InvalidArgumentException::class, 'unsupported decision action rejected');

$key = ApprovalDecisionPolicy::normalizeIdempotencyKey(' decision-1 ');
esc_p7_decision_foundation_assert($key === 'decision-1', 'decision idempotency key is trimmed');
esc_p7_decision_foundation_assert(strlen(ApprovalDecisionPolicy::decisionKeyHash($key)) === 64, 'decision idempotency key hashes to SHA-256');
esc_p7_decision_foundation_throws(static fn () => ApprovalDecisionPolicy::normalizeIdempotencyKey(''), InvalidArgumentException::class, 'empty decision idempotency key rejected');
esc_p7_decision_foundation_throws(static fn () => ApprovalDecisionPolicy::normalizeIdempotencyKey(str_repeat('x', 192)), InvalidArgumentException::class, 'oversized decision idempotency key rejected');
esc_p7_decision_foundation_throws(static fn () => ApprovalDecisionPolicy::normalizeIdempotencyKey("bad\nkey"), InvalidArgumentException::class, 'decision idempotency key rejects controls');

esc_p7_decision_foundation_assert(ApprovalDecisionPolicy::normalizeComment(null) === null, 'missing decision comment remains null');
esc_p7_decision_foundation_assert(ApprovalDecisionPolicy::normalizeComment('  approved after review  ') === 'approved after review', 'decision comment is bounded and trimmed');
esc_p7_decision_foundation_assert(ApprovalDecisionPolicy::normalizeComment('   ') === null, 'empty decision comment canonicalizes to null');
esc_p7_decision_foundation_throws(static fn () => ApprovalDecisionPolicy::normalizeComment(str_repeat('x', 2001)), InvalidArgumentException::class, 'oversized decision comment rejected');
esc_p7_decision_foundation_throws(static fn () => ApprovalDecisionPolicy::normalizeComment("bad\0comment"), InvalidArgumentException::class, 'decision comment rejects null bytes');

esc_p7_decision_foundation_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'exec('), 'decision policy introduces no executable expression surface');
esc_p7_decision_foundation_assert(! str_contains($migrationSource, 'contract_workflow_instances') && ! str_contains($migrationSource, 'transition_history'), 'P7-003 foundation adds no P6 state/history mutation surface');

echo "P7-003 Approval Decision foundation checks passed ({$assertions} assertions).\n";
