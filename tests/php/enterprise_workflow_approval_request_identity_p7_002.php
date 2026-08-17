<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Approvals\ApprovalRequestPolicy;
use SafeContracts\Approvals\ApprovalRequestRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

$assertions = 0;
function esc_p7_identity_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Approvals/ApprovalRequestRepository.php');

esc_p7_identity_assert(str_contains($source, 'PUBLIC_REQUEST_COLUMNS'), 'Approval Request repository has a public read projection');
esc_p7_identity_assert(str_contains($source, 'INTERNAL_REQUEST_COLUMNS'), 'Approval Request repository keeps a separate internal idempotency projection');

preg_match("/PUBLIC_REQUEST_COLUMNS = '([^']+)'/", $source, $publicMatch);
preg_match("/INTERNAL_REQUEST_COLUMNS = '([^']+)'/", $source, $internalMatch);
esc_p7_identity_assert(isset($publicMatch[1]) && ! str_contains($publicMatch[1], 'request_key_hash'), 'public Approval Request projection excludes request_key_hash');
esc_p7_identity_assert(isset($internalMatch[1]) && str_contains($internalMatch[1], 'request_key_hash'), 'internal Approval Request projection retains request_key_hash for idempotency matching');
esc_p7_identity_assert(str_contains($source, "unset(\$request['request_key_hash'])"), 'internal idempotency hash is stripped before retry response');

update_option(CoreTenantEnforcement::OPTION, '1', false);
TenantContextStore::reset();
TenantContextStore::context()->setTenantId(17);

$hash = ApprovalRequestPolicy::requestKeyHash('identity-retry');
$GLOBALS['sc_test_queries'] = [];
$GLOBALS['sc_test_read_queries'] = [];
$GLOBALS['sc_test_result_queue'] = [
    [[
        'contract_id' => '71',
        'accountant_user_id' => '42',
        'is_archived' => '0',
        'instance_id' => '501',
        'workflow_id' => '81',
        'workflow_version_id' => '91',
        'current_state_id' => '301',
        'current_state_code_snapshot' => 'draft',
    ]],
    [[
        'id' => '2001',
        'instance_id' => '501',
        'contract_id' => '71',
        'workflow_id' => '81',
        'workflow_version_id' => '91',
        'transition_id' => '701',
        'transition_code_snapshot' => 'submit',
        'from_state_id' => '301',
        'from_state_code_snapshot' => 'draft',
        'to_state_id' => '302',
        'to_state_code_snapshot' => 'review',
        'route_id_snapshot' => '1001',
        'request_key_hash' => $hash,
        'status' => 'pending',
        'requester_user_id' => '42',
        'requested_at' => '2026-08-17 03:45:00',
    ]],
];

$retry = (new ApprovalRequestRepository())->createRequest(71, 501, 'submit', $hash, 42);
esc_p7_identity_assert(($retry['created'] ?? true) === false, 'exact retry returns the existing request');
esc_p7_identity_assert(! array_key_exists('request_key_hash', $retry['request'] ?? []), 'exact retry response does not expose request_key_hash');
esc_p7_identity_assert($GLOBALS['sc_test_queries'] === ['START TRANSACTION', 'COMMIT'], 'identity-only retry performs no duplicate write');

CoreTenantEnforcement::disable();
TenantContextStore::reset();
echo "P7-002 Approval Request internal identity checks passed ({$assertions} assertions).\n";
