<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Approvals\ApprovalReleasePolicy;
use SafeContracts\Database\Migrator;
use SafeContracts\Database\Migrations\Migration0042EnterpriseWorkflowApprovalReleases;

$assertions = 0;
function esc_p7_release_foundation_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function esc_p7_release_foundation_throws(callable $callback, string $class, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p7_release_foundation_assert($error instanceof $class, $message . ' (unexpected ' . get_class($error) . ': ' . $error->getMessage() . ')');
        return;
    }
    esc_p7_release_foundation_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$migrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0042EnterpriseWorkflowApprovalReleases.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalReleasePolicy.php');

$GLOBALS['sc_test_dbdelta'] = [];
(new Migration0042EnterpriseWorkflowApprovalReleases())->up($GLOBALS['wpdb']);
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);

esc_p7_release_foundation_assert(str_contains($schema, 'CREATE TABLE wp_safecontracts_workflow_approval_releases'), 'P7-004 creates dedicated Approval Release evidence table');
esc_p7_release_foundation_assert(str_contains($schema, 'tenant_id bigint(20) unsigned NOT NULL'), 'release evidence is tenant owned');
esc_p7_release_foundation_assert(str_contains($schema, 'request_id bigint(20) unsigned NOT NULL'), 'release binds exact Approval Request');
esc_p7_release_foundation_assert(str_contains($schema, 'instance_id bigint(20) unsigned NOT NULL'), 'release snapshots exact Workflow Instance');
esc_p7_release_foundation_assert(str_contains($schema, 'transition_history_id bigint(20) unsigned NOT NULL'), 'release binds exact immutable P6 history row');
esc_p7_release_foundation_assert(str_contains($schema, 'release_key_hash char(64) NOT NULL'), 'only hashed release identity is stored');
esc_p7_release_foundation_assert(! str_contains($schema, 'release_key varchar'), 'raw release idempotency key is not stored');
esc_p7_release_foundation_assert(str_contains($schema, 'UNIQUE KEY tenant_request_release (tenant_id, request_id)'), 'one release exists per Approval Request');
esc_p7_release_foundation_assert(str_contains($schema, 'UNIQUE KEY tenant_release_key (tenant_id, release_key_hash)'), 'release key cannot be reused in a tenant');
esc_p7_release_foundation_assert(str_contains($schema, 'UNIQUE KEY tenant_transition_history_release (tenant_id, transition_history_id)'), 'one P6 transition history row links to one Approval Release');
esc_p7_release_foundation_assert(! str_contains($migrationSource, 'ALTER TABLE'), 'P7-004 schema is additive');
esc_p7_release_foundation_assert(! str_contains($migrationSource, 'contract_workflow_instances') && ! str_contains($migrationSource, 'safecontracts_contracts'), 'P7-004 migration does not alter P6/legacy tables');
esc_p7_release_foundation_assert(version_compare(Migrator::LATEST_VERSION, '1.41.0', '>='), 'P7-004 migration remains at or before current schema version');
esc_p7_release_foundation_assert(str_contains($migratorSource, "'1.41.0' => Migration0042EnterpriseWorkflowApprovalReleases::class"), 'P7-004 migration is registered exactly at 1.41.0');

$key = ApprovalReleasePolicy::normalizeIdempotencyKey(' release-1 ');
esc_p7_release_foundation_assert($key === 'release-1', 'release idempotency key canonicalizes by trimming');
$releaseHash = ApprovalReleasePolicy::releaseKeyHash($key);
$transitionHash = ApprovalReleasePolicy::transitionRequestKeyHash($key);
esc_p7_release_foundation_assert(strlen($releaseHash) === 64 && strlen($transitionHash) === 64, 'release and P6 identities are SHA-256 hashes');
esc_p7_release_foundation_assert($releaseHash !== $transitionHash, 'P6 transition idempotency is domain-separated from Approval Release identity');
esc_p7_release_foundation_assert($transitionHash === ApprovalReleasePolicy::transitionRequestKeyHash($key), 'derived P6 transition identity is deterministic');
esc_p7_release_foundation_throws(static fn () => ApprovalReleasePolicy::normalizeIdempotencyKey(''), InvalidArgumentException::class, 'empty release idempotency key rejected');
esc_p7_release_foundation_throws(static fn () => ApprovalReleasePolicy::normalizeIdempotencyKey(str_repeat('x', 192)), InvalidArgumentException::class, 'oversized release idempotency key rejected');
esc_p7_release_foundation_throws(static fn () => ApprovalReleasePolicy::normalizeIdempotencyKey("bad\nkey"), InvalidArgumentException::class, 'release idempotency key rejects control characters');
esc_p7_release_foundation_assert(! str_contains($policySource, 'eval(') && ! str_contains($policySource, 'exec('), 'release policy introduces no executable expression surface');

echo "P7-004 Approval Release foundation checks passed ({$assertions} assertions).\n";
