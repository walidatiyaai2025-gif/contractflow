<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Audit\AuditService;
use SafeContracts\Collections\CollectionService;
use SafeContracts\FollowUps\FollowUpService;
use SafeContracts\FollowUps\FollowUpState;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$tests = 0;

function sc_p4v_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p4v_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_p4v_assert($error instanceof $class, $message);
        return;
    }

    sc_p4v_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_p4v_payment(array $overrides = []): array
{
    return array_merge([
        'id' => '7001',
        'contract_id' => '501',
        'sequence_no' => '1',
        'reference' => 'INST-001',
        'due_date' => '2026-08-20',
        'expected_payment_date' => '2026-08-22',
        'original_amount' => '500.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '500.0000',
        'status' => PaymentStatus::UPCOMING,
        'accountant_user_id' => '42',
        'contract_is_archived' => '0',
    ], $overrides);
}

/** @return list<string> */
function sc_p4v_mutations_since(int $offset): array
{
    return array_slice($GLOBALS['sc_test_queries'], $offset);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p4v_assert(is_callable($activate), 'P4 final validation can activate the plugin');
$activate();
do_action('plugins_loaded');

$followupAccepted = $GLOBALS['sc_test_action_accepted_args']['safecontracts_followup_recorded'] ?? [];
$settlementAccepted = $GLOBALS['sc_test_action_accepted_args']['safecontracts_payment_settled'] ?? [];
$assignmentAccepted = $GLOBALS['sc_test_action_accepted_args']['safecontracts_contract_customer_assigned'] ?? [];
sc_p4v_assert($followupAccepted !== [] && max($followupAccepted) >= 6, 'P4 audit hook explicitly accepts the complete follow-up event payload');
sc_p4v_assert($settlementAccepted !== [] && max($settlementAccepted) >= 9, 'P4 audit hook explicitly accepts settlement before/after payloads under WordPress semantics');
sc_p4v_assert($assignmentAccepted !== [] && max($assignmentAccepted) >= 4, 'P4 audit hook explicitly accepts assignment old/new payloads under WordPress semantics');

$followups = new FollowUpService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_FOLLOWUPS => true,
];

// SC-P4-010: notes are normalized, append-only, scoped and audited.
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()]];
$GLOBALS['wpdb']->insert_id = 9401;
$beforeNote = count($GLOBALS['sc_test_queries']);
$noteId = $followups->addNote(7001, '  Called finance and confirmed receipt  ');
$noteMutations = sc_p4v_mutations_since($beforeNote);
$noteSql = implode("\n", $noteMutations);
sc_p4v_assert($noteId === 9401, 'SC-P4-010 normalized follow-up note returns appended history ID');
sc_p4v_assert(str_contains($noteSql, 'INSERT INTO wp_safecontracts_payment_followups'), 'SC-P4-010 note appends a follow-up row');
sc_p4v_assert(str_contains($noteSql, "'Called finance and confirmed receipt'") && ! str_contains($noteSql, "'  Called finance"), 'SC-P4-010 note whitespace is normalized before persistence');
sc_p4v_assert(str_contains($noteSql, "'contacted'"), 'SC-P4-010 note uses contacted operational state');
sc_p4v_assert(str_contains($noteSql, 'INSERT INTO wp_safecontracts_audit_log') && str_contains($noteSql, 'followup_recorded'), 'SC-P4-010 note produces audit evidence');
sc_p4v_assert(! str_contains($noteSql, 'UPDATE wp_safecontracts_payment_followups') && ! str_contains($noteSql, 'DELETE FROM wp_safecontracts_payment_followups'), 'SC-P4-010 follow-up note path is append-only');

$beforeBlank = count($GLOBALS['sc_test_queries']);
sc_p4v_expect(InvalidArgumentException::class, fn () => $followups->addNote(7001, '   '), 'SC-P4-010 blank follow-up note is rejected');
sc_p4v_assert(count($GLOBALS['sc_test_queries']) === $beforeBlank, 'SC-P4-010 blank note rejection cannot mutate persistence');
$beforeLong = count($GLOBALS['sc_test_queries']);
sc_p4v_expect(InvalidArgumentException::class, fn () => $followups->addNote(7001, str_repeat('x', 5001)), 'SC-P4-010 oversized follow-up note is rejected');
sc_p4v_assert(count($GLOBALS['sc_test_queries']) === $beforeLong, 'SC-P4-010 oversized note rejection cannot mutate persistence');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true, Capabilities::MANAGE_FOLLOWUPS => true];
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment(['accountant_user_id' => '99'])]];
$beforeScopeNote = count($GLOBALS['sc_test_queries']);
sc_p4v_expect(DomainException::class, fn () => $followups->addNote(7001, 'Outside assignment'), 'SC-P4-010 Accountant cannot append notes outside assigned scope');
sc_p4v_assert(count($GLOBALS['sc_test_queries']) === $beforeScopeNote, 'SC-P4-010 scope denial cannot mutate follow-up history');

// SC-P4-011: promise-to-pay is operational only and never rewrites payment dates.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::MANAGE_FOLLOWUPS => true];
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()]];
$beforePromise = count($GLOBALS['sc_test_queries']);
$followups->promiseToPay(7001, '2026-08-28', 'Transfer promised');
$promiseSql = implode("\n", sc_p4v_mutations_since($beforePromise));
sc_p4v_assert(str_contains($promiseSql, "'promised_to_pay'") && str_contains($promiseSql, "'2026-08-28'"), 'SC-P4-011 promise state and promised date are persisted');
sc_p4v_assert(str_contains($promiseSql, 'promised_date') && str_contains($promiseSql, 'followup_recorded'), 'SC-P4-011 promise date reaches structured audit context');
sc_p4v_assert(! str_contains($promiseSql, 'UPDATE wp_safecontracts_scheduled_payments'), 'SC-P4-011 promise-to-pay never rewrites contractual due or expected payment dates');
$beforeBadPromise = count($GLOBALS['sc_test_queries']);
sc_p4v_expect(InvalidArgumentException::class, fn () => $followups->promiseToPay(7001, '2026-02-30'), 'SC-P4-011 invalid promised calendar date is rejected');
sc_p4v_assert(count($GLOBALS['sc_test_queries']) === $beforeBadPromise, 'SC-P4-011 invalid promise date cannot mutate persistence');

// SC-P4-012: issue/deferred states stay append-only and respect paid/archive boundaries.
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()]];
$beforeIssue = count($GLOBALS['sc_test_queries']);
$followups->markIssue(7001, 'Invoice disputed by customer');
$issueSql = implode("\n", sc_p4v_mutations_since($beforeIssue));
sc_p4v_assert(str_contains($issueSql, "'issue'") && str_contains($issueSql, 'Invoice disputed by customer'), 'SC-P4-012 issue state is appended with required note');
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()]];
$beforeDeferred = count($GLOBALS['sc_test_queries']);
$followups->defer(7001, '2026-09-05', 'Awaiting management approval');
$deferredSql = implode("\n", sc_p4v_mutations_since($beforeDeferred));
sc_p4v_assert(str_contains($deferredSql, "'deferred'") && str_contains($deferredSql, "'2026-09-05'"), 'SC-P4-012 deferred state stores operational resume date');
sc_p4v_assert(! str_contains($deferredSql, 'UPDATE wp_safecontracts_scheduled_payments') && ! str_contains($deferredSql, 'DELETE FROM'), 'SC-P4-012 deferred state is non-destructive and does not alter payment contract data');
$beforeBadDefer = count($GLOBALS['sc_test_queries']);
sc_p4v_expect(InvalidArgumentException::class, fn () => $followups->defer(7001, 'bad-date'), 'SC-P4-012 invalid deferred-until date is rejected');
sc_p4v_assert(count($GLOBALS['sc_test_queries']) === $beforeBadDefer, 'SC-P4-012 invalid defer date cannot mutate persistence');

$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment(['contract_is_archived' => '1'])]];
$beforeArchived = count($GLOBALS['sc_test_queries']);
sc_p4v_expect(DomainException::class, fn () => $followups->markIssue(7001, 'Archived'), 'SC-P4-012 archived contracts reject new follow-up states');
sc_p4v_assert(count($GLOBALS['sc_test_queries']) === $beforeArchived, 'SC-P4-012 archive rejection cannot append history');
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment(['status' => PaymentStatus::PAID, 'paid_amount' => '500.0000', 'remaining_amount' => '0.0000'])]];
$beforePaid = count($GLOBALS['sc_test_queries']);
sc_p4v_expect(DomainException::class, fn () => $followups->defer(7001, '2026-09-05'), 'SC-P4-012 paid payments reject deferred follow-up');
sc_p4v_assert(count($GLOBALS['sc_test_queries']) === $beforePaid, 'SC-P4-012 paid-payment rejection cannot append history');

// SC-P4-013: history reads are deterministic, bounded, read-only and assignment scoped.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_p4v_payment()],
    [
        ['id' => '9502', 'payment_id' => '7001', 'state' => FollowUpState::DEFERRED, 'note' => 'Awaiting management approval', 'promised_date' => null, 'deferred_until' => '2026-09-05', 'created_by' => '42', 'created_at' => '2026-08-15 11:30:00'],
        ['id' => '9501', 'payment_id' => '7001', 'state' => FollowUpState::ISSUE, 'note' => 'Invoice disputed by customer', 'promised_date' => null, 'deferred_until' => null, 'created_by' => '42', 'created_at' => '2026-08-15 11:29:00'],
    ],
];
$mutationsBeforeHistory = count($GLOBALS['sc_test_queries']);
$history = $followups->history(7001, 999);
$historySql = (string) end($GLOBALS['sc_test_read_queries']);
sc_p4v_assert(count($history) === 2 && $history[0]['state'] === FollowUpState::DEFERRED, 'SC-P4-013 history preserves newest-first operational timeline');
sc_p4v_assert(str_contains($historySql, 'ORDER BY created_at DESC, id DESC'), 'SC-P4-013 history ordering is deterministic');
sc_p4v_assert(str_contains($historySql, 'LIMIT 500'), 'SC-P4-013 history hard-clamps oversized reads to 500 rows');
sc_p4v_assert(count($GLOBALS['sc_test_queries']) === $mutationsBeforeHistory, 'SC-P4-013 history read is non-destructive');

$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()], [['id' => '9502', 'payment_id' => '7001', 'state' => FollowUpState::DEFERRED]]];
$followups->history(7001, 0);
sc_p4v_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'LIMIT 1'), 'SC-P4-013 history clamps non-positive requested limits to one row');
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment(['accountant_user_id' => '99'])]];
sc_p4v_expect(DomainException::class, fn () => $followups->history(7001), 'SC-P4-013 history enforces Accountant assignment scope');

// SC-P4-014: representative financial events retain actor/time and structured before/after state.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$beforeBaseAudit = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_base_value_changed', 501, '650.0000', 42, '500.0000');
$baseAuditSql = implode("\n", sc_p4v_mutations_since($beforeBaseAudit));
sc_p4v_assert(str_contains($baseAuditSql, 'contract_base_value_changed') && str_contains($baseAuditSql, '500.0000') && str_contains($baseAuditSql, '650.0000'), 'SC-P4-014 base-value audit retains structured before/after values');
sc_p4v_assert(str_contains($baseAuditSql, 'actor_user_id') && str_contains($baseAuditSql, 'UTC_TIMESTAMP()'), 'SC-P4-014 financial audit persists actor and server timestamp');

$collections = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::MANAGE_COLLECTIONS => true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_p4v_payment()],
    [['id' => '2']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9601;
$beforeSettlement = count($GLOBALS['sc_test_queries']);
$collections->record([
    'payment_id' => 7001,
    'amount' => '125.5000',
    'collection_date' => '2026-08-15',
    'payment_method_id' => 2,
]);
$settlementArgs = end($GLOBALS['sc_test_fired_actions']['safecontracts_payment_settled']);
$settlementSql = implode("\n", sc_p4v_mutations_since($beforeSettlement));
sc_p4v_assert(is_array($settlementArgs) && count($settlementArgs) === 9, 'SC-P4-014 settlement event emits complete new and prior financial state');
sc_p4v_assert($settlementArgs[2] === '125.5000' && $settlementArgs[3] === '374.5000' && $settlementArgs[4] === PaymentStatus::PARTIALLY_PAID, 'SC-P4-014 settlement event exposes new paid/remaining/status');
sc_p4v_assert($settlementArgs[6] === '0.0000' && $settlementArgs[7] === '500.0000' && $settlementArgs[8] === PaymentStatus::UPCOMING, 'SC-P4-014 settlement event exposes prior paid/remaining/status');
sc_p4v_assert(str_contains($settlementSql, 'payment_settled') && str_contains($settlementSql, '500.0000') && str_contains($settlementSql, '374.5000'), 'SC-P4-014 settlement audit persists before/after financial values');

$audit = new AuditService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p4v_expect(DomainException::class, fn () => $audit->forEntity('payment', 7001), 'SC-P4-014 audit reads require dedicated VIEW_AUDIT capability');
$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_AUDIT => true];
$GLOBALS['sc_test_result_queue'] = [[['id' => '1', 'entity_type' => 'payment', 'entity_id' => '7001', 'event_type' => 'payment_settled', 'actor_user_id' => '42', 'created_at' => '2026-08-15 11:31:00']]];
$auditRows = $audit->forEntity('payment', 7001, 999);
sc_p4v_assert(count($auditRows) === 1, 'SC-P4-014 authorized audit read succeeds');
sc_p4v_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'LIMIT 500'), 'SC-P4-014 audit reads are server-bounded');

// SC-P4-015: assignment audit retains old/new IDs plus actor and server timestamp.
$GLOBALS['sc_test_results'] = [];
$GLOBALS['sc_test_result_queue'] = [];
$beforeCustomerAssignment = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_customer_assigned', 501, 22, 42, 11);
$customerAuditSql = implode("\n", sc_p4v_mutations_since($beforeCustomerAssignment));
sc_p4v_assert(str_contains($customerAuditSql, 'contract_customer_assigned') && str_contains($customerAuditSql, 'customer_id'), 'SC-P4-015 customer reassignment produces structured audit event');
sc_p4v_assert(str_contains($customerAuditSql, '11') && str_contains($customerAuditSql, '22'), 'SC-P4-015 customer audit retains old/new customer IDs');
sc_p4v_assert(str_contains($customerAuditSql, '42') && str_contains($customerAuditSql, 'UTC_TIMESTAMP()'), 'SC-P4-015 customer assignment audit retains actor and server timestamp');

$beforeAccountantAssignment = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_accountant_assigned', 501, 77, 42, 42);
$accountantAuditSql = implode("\n", sc_p4v_mutations_since($beforeAccountantAssignment));
sc_p4v_assert(str_contains($accountantAuditSql, 'contract_accountant_assigned') && str_contains($accountantAuditSql, 'accountant_user_id'), 'SC-P4-015 Accountant reassignment produces structured audit event');
sc_p4v_assert(str_contains($accountantAuditSql, '42') && str_contains($accountantAuditSql, '77'), 'SC-P4-015 Accountant audit retains old/new user IDs');

$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_AUDIT => true];
$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '2', 'entity_type' => 'contract', 'entity_id' => '501', 'event_type' => 'contract_accountant_assigned', 'actor_user_id' => '42', 'created_at' => '2026-08-15 11:32:00'],
    ['id' => '1', 'entity_type' => 'contract', 'entity_id' => '501', 'event_type' => 'contract_customer_assigned', 'actor_user_id' => '42', 'created_at' => '2026-08-15 11:31:00'],
]];
$assignmentHistory = $audit->forEntity('contract', 501, 20);
sc_p4v_assert(count($assignmentHistory) === 2 && $assignmentHistory[0]['event_type'] === 'contract_accountant_assigned', 'SC-P4-015 assignment audit timeline is readable in deterministic newest-first order');
sc_p4v_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'ORDER BY created_at DESC, id DESC'), 'SC-P4-015 audit repository enforces deterministic timeline ordering');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_p4v_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'P4 final validation leaves migrations idempotent');

echo "SafeContracts P4 final validation SC-P4-010..015 passed ({$tests} assertions).\n";
