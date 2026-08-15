<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$tests = 0;

function sc_audit_hook_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @return array<string, mixed> */
function sc_audit_hook_payment(array $overrides = []): array
{
    return array_merge([
        'id' => '7001',
        'contract_id' => '501',
        'sequence_no' => '1',
        'reference' => 'INST-001',
        'due_date' => '2026-08-20',
        'expected_payment_date' => null,
        'original_amount' => '500.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '500.0000',
        'status' => PaymentStatus::UPCOMING,
        'accountant_user_id' => '42',
        'contract_is_archived' => '0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_audit_hook_assert(is_callable($activate), 'audit hook regression can activate the plugin');
$activate();
do_action('plugins_loaded');

$followupAccepted = $GLOBALS['sc_test_action_accepted_args']['safecontracts_followup_recorded'] ?? [];
$settledAccepted = $GLOBALS['sc_test_action_accepted_args']['safecontracts_payment_settled'] ?? [];
$assignmentAccepted = $GLOBALS['sc_test_action_accepted_args']['safecontracts_contract_customer_assigned'] ?? [];
sc_audit_hook_assert($followupAccepted !== [] && max($followupAccepted) >= 6, 'AuditRecorder explicitly accepts the complete follow-up payload');
sc_audit_hook_assert($settledAccepted !== [] && max($settledAccepted) >= 9, 'AuditRecorder explicitly accepts settlement before/after payload');
sc_audit_hook_assert($assignmentAccepted !== [] && max($assignmentAccepted) >= 4, 'AuditRecorder explicitly accepts assignment old/new payload');

// Prove the stub now behaves like WordPress: a listener that omits accepted_args receives only one argument.
$defaultReceived = [];
add_action('safecontracts_test_default_args', static function (mixed ...$args) use (&$defaultReceived): void {
    $defaultReceived = $args;
});
do_action('safecontracts_test_default_args', 'first', 'second', 'third');
sc_audit_hook_assert($defaultReceived === ['first'], 'test harness enforces WordPress default accepted_args=1');

// Prove AuditRecorder receives the trailing actor/before values under those same semantics.
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$beforeBaseValue = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_base_value_changed', 501, '650.0000', 42, '500.0000');
$baseValueSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeBaseValue));
sc_audit_hook_assert(str_contains($baseValueSql, 'INSERT INTO wp_safecontracts_audit_log'), 'multi-argument financial event reaches audit persistence');
sc_audit_hook_assert(str_contains($baseValueSql, 'contract_base_value_changed'), 'financial audit event type is preserved');
sc_audit_hook_assert(str_contains($baseValueSql, '500.0000') && str_contains($baseValueSql, '650.0000'), 'financial audit retains real before/after values');
sc_audit_hook_assert(str_contains($baseValueSql, '42'), 'financial audit retains the actor from the trailing event payload');

// Prove CollectionService emits the prior settlement state expected by AuditRecorder.
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_COLLECTIONS => true,
];
$GLOBALS['sc_test_result_queue'] = [
    [sc_audit_hook_payment()],
    [['id' => '2']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9701;
$beforeSettlement = count($GLOBALS['sc_test_queries']);
$collectionId = (new CollectionService())->record([
    'payment_id' => 7001,
    'amount' => '125.5000',
    'collection_date' => '2026-08-15',
    'payment_method_id' => 2,
]);
sc_audit_hook_assert($collectionId === 9701, 'settlement regression records the collection transaction');
$settlementArgs = end($GLOBALS['sc_test_fired_actions']['safecontracts_payment_settled']);
sc_audit_hook_assert(is_array($settlementArgs) && count($settlementArgs) === 9, 'settlement event emits six existing plus three prior-state arguments');
sc_audit_hook_assert($settlementArgs[2] === '125.5000' && $settlementArgs[3] === '374.5000' && $settlementArgs[4] === PaymentStatus::PARTIALLY_PAID, 'settlement event preserves new paid/remaining/status positions');
sc_audit_hook_assert($settlementArgs[5] === 42, 'settlement event preserves the existing actor position');
sc_audit_hook_assert($settlementArgs[6] === '0.0000' && $settlementArgs[7] === '500.0000' && $settlementArgs[8] === PaymentStatus::UPCOMING, 'settlement event appends the prior paid/remaining/status state');

$settlementSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeSettlement));
sc_audit_hook_assert(str_contains($settlementSql, 'payment_settled'), 'settlement event produces an audit row');
sc_audit_hook_assert(str_contains($settlementSql, '0.0000') && str_contains($settlementSql, '500.0000'), 'settlement audit contains prior financial state');
sc_audit_hook_assert(str_contains($settlementSql, '125.5000') && str_contains($settlementSql, '374.5000'), 'settlement audit contains new financial state');

echo "SafeContracts WordPress audit event regression passed ({$tests} assertions).\n";
