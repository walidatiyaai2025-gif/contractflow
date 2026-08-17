<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

$assertions = 0;
function esc_p7_release_service_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalReleaseService.php');
$repository = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalReleaseRepository.php');
$p6 = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Workflows/ContractWorkflowTransitionRepository.php');

$authorizePos = strpos($service, '$this->authorize(Capabilities::EDIT_CONTRACTS)');
$scopePos = strpos($service, '$this->assertScope($contract)');
$retryPos = strpos($service, '$existing = $this->releases->findReleaseResult($requestId, $releaseKeyHash)');
$archivePos = strpos($service, "if ((int) (\$contract['is_archived'] ?? 0) === 1)");
$statusPos = strpos($service, "if ((string) (\$request['status'] ?? '') !== ApprovalDecisionPolicy::REQUEST_STATUS_APPROVED)");

esc_p7_release_service_assert(is_int($authorizePos) && is_int($scopePos) && is_int($retryPos) && is_int($archivePos) && is_int($statusPos), 'release service exposes explicit auth/scope/retry/mutation boundaries');
esc_p7_release_service_assert($authorizePos < $scopePos && $scopePos < $retryPos, 'authorization and contract data scope precede exact release retry lookup');
esc_p7_release_service_assert($retryPos < $archivePos && $retryPos < $statusPos, 'exact committed release retry precedes later mutable archive/request-status guards');
esc_p7_release_service_assert(str_contains($service, 'Capabilities::EDIT_CONTRACTS') && str_contains($service, 'TenantAuthorization::allowsCapability'), 'new release requires edit capability with tenant-role narrowing');
esc_p7_release_service_assert(str_contains($service, 'Capabilities::ACCESS'), 'release evidence read requires Enterprise access');
esc_p7_release_service_assert(str_contains($service, 'transitionRequestKeyHash') && str_contains($service, 'releaseKeyHash'), 'release and P6 transition identities remain domain separated');
esc_p7_release_service_assert(str_contains($service, '$this->guards->assertAllowed($contractId, $transition)'), 'fresh P6-004 guards remain in the final release transaction');
esc_p7_release_service_assert(! str_contains($service, 'START TRANSACTION') && ! str_contains($repository, 'START TRANSACTION'), 'P7-004 service/repository never opens a nested transaction');
esc_p7_release_service_assert(str_contains($p6, '$afterMutation($historyRow, $transition, $instance)') && strrpos($p6, "query('COMMIT')") > strpos($p6, '$afterMutation($historyRow, $transition, $instance)'), 'release evidence callback remains before final P6 commit');
esc_p7_release_service_assert(! str_contains($service, 'ContractStatus') && ! str_contains($repository, 'ContractStatus'), 'release service introduces no legacy ContractStatus synchronization');

echo "P7-004 Approval Release service boundary checks passed ({$assertions} assertions).\n";
