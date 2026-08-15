<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Audit\AuditService;
use SafeContracts\Database\Migrator;
use SafeContracts\FollowUps\FollowUpService;
use SafeContracts\FollowUps\FollowUpState;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p4_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function sc_p4_expect(string $class, callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { sc_p4_assert($e instanceof $class, $message); return; } sc_p4_assert(false, $message); }
function sc_p4_payment(array $overrides = []): array { return array_merge([
    'id'=>'7001','contract_id'=>'501','sequence_no'=>'1','reference'=>'P-1','due_date'=>'2026-08-20','expected_payment_date'=>null,
    'original_amount'=>'500.0000','paid_amount'=>'0.0000','remaining_amount'=>'500.0000','status'=>PaymentStatus::UPCOMING,
    'accountant_user_id'=>'42','contract_is_archived'=>'0',
], $overrides); }

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p4_assert(is_callable($activate), 'P4 activation hook is available');
$activate();
sc_p4_assert(version_compare(Migrator::LATEST_VERSION, '1.8.0', '>='), 'P4 follow-up/audit migration remains registered after later schema versions');
sc_p4_assert(count($GLOBALS['sc_test_dbdelta']) >= 12, 'P4 follow-up/audit persistence tables remain present after later migrations');
$followupSchema = $GLOBALS['sc_test_dbdelta'][10];
$auditSchema = $GLOBALS['sc_test_dbdelta'][11];
sc_p4_assert(str_contains($followupSchema, 'wp_safecontracts_payment_followups'), 'SC-P4-002 follow-ups use dedicated prefixed table');
sc_p4_assert(str_contains($followupSchema, 'promised_date date NULL') && str_contains($followupSchema, 'deferred_until date NULL'), 'SC-P4-003/004 promise and defer dates remain operational fields');
sc_p4_assert(str_contains($auditSchema, 'wp_safecontracts_audit_log') && str_contains($auditSchema, 'before_json longtext NULL') && str_contains($auditSchema, 'after_json longtext NULL'), 'SC-P4-006/007 audit table stores structured before/after context');

do_action('plugins_loaded');
$followups = new FollowUpService();

// SC-P4-001 / SC-P4-009: Manager sees all outstanding; Accountant gets server-side assignment filter.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [[['payment_id'=>'7001','contract_id'=>'501','customer_id'=>'12','accountant_user_id'=>'42','due_date'=>'2026-08-20','remaining_amount'=>'500.0000','status'=>'upcoming']]];
$managerQueue = $followups->queue(50);
sc_p4_assert(count($managerQueue) === 1, 'SC-P4-001 Manager can read outstanding follow-up queue');
$managerSql = (string) end($GLOBALS['sc_test_read_queries']);
sc_p4_assert(str_contains($managerSql, "p.remaining_amount > 0") && str_contains($managerSql, "p.status <> 'paid'") && str_contains($managerSql, 'c.is_archived = 0'), 'SC-P4-001 queue excludes settled/archived work server-side');
sc_p4_assert(str_contains($managerSql, 'ORDER BY p.due_date ASC'), 'SC-P4-009 queue is ordered by contractual due date');
sc_p4_assert(! str_contains($managerSql, 'c.accountant_user_id = 42'), 'SC-P4-001 Manager queue is not restricted to one accountant');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [[['payment_id'=>'7001','contract_id'=>'501','customer_id'=>'12','accountant_user_id'=>'42','due_date'=>'2026-08-20','remaining_amount'=>'500.0000','status'=>'upcoming']]];
$accountantQueue = $followups->queue(50);
sc_p4_assert(count($accountantQueue) === 1, 'SC-P4-001 Accountant can read own follow-up queue');
sc_p4_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'c.accountant_user_id = 42'), 'SC-P4-001/009 Accountant queue is assignment-scoped in SQL');

// SC-P4-002: note append + audit event.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_FOLLOWUPS=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p4_payment()]];
$GLOBALS['wpdb']->insert_id = 9201;
$beforeNote = count($GLOBALS['sc_test_queries']);
$id = $followups->addNote(7001, ' Called finance team ');
sc_p4_assert($id === 9201, 'SC-P4-002 follow-up note returns append ID');
$noteSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeNote));
sc_p4_assert(str_contains($noteSql, 'INSERT INTO wp_safecontracts_payment_followups') && str_contains($noteSql, "'contacted'") && str_contains($noteSql, "'Called finance team'"), 'SC-P4-002 note is normalized and appended');
sc_p4_assert(str_contains($noteSql, 'INSERT INTO wp_safecontracts_audit_log'), 'SC-P4-005 operational follow-up also emits audit timeline event');

// SC-P4-003: promise is operational and does not rewrite contractual/expected payment fields.
$GLOBALS['sc_test_result_queue'] = [[sc_p4_payment()]];
$beforePromise = count($GLOBALS['sc_test_queries']);
$followups->promiseToPay(7001, '2026-08-28', 'Customer promised transfer');
$promiseSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforePromise));
sc_p4_assert(str_contains($promiseSql, "'promised_to_pay'") && str_contains($promiseSql, "'2026-08-28'"), 'SC-P4-003 promise-to-pay state and date are persisted');
sc_p4_assert(! str_contains($promiseSql, 'UPDATE wp_safecontracts_scheduled_payments'), 'SC-P4-003 promise does not overwrite due/expected payment dates');

// SC-P4-004: issue and deferred states are independent timeline entries.
$GLOBALS['sc_test_result_queue'] = [[sc_p4_payment()]];
$followups->markIssue(7001, 'Invoice dispute');
sc_p4_assert(str_contains((string) $GLOBALS['sc_test_queries'][count($GLOBALS['sc_test_queries']) - 2], "'issue'"), 'SC-P4-004 issue state is appended');
$GLOBALS['sc_test_result_queue'] = [[sc_p4_payment()]];
$followups->defer(7001, '2026-09-05', 'Awaiting approval');
sc_p4_assert(str_contains((string) $GLOBALS['sc_test_queries'][count($GLOBALS['sc_test_queries']) - 2], "'deferred'") && str_contains((string) $GLOBALS['sc_test_queries'][count($GLOBALS['sc_test_queries']) - 2], "'2026-09-05'"), 'SC-P4-004 deferred state stores operational resume date');

// SC-P4-005: history is newest-first, append-only, and scope protected.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p4_payment()], [[
    'id'=>'9204','payment_id'=>'7001','state'=>FollowUpState::DEFERRED,'note'=>'Awaiting approval','promised_date'=>null,'deferred_until'=>'2026-09-05','created_by'=>'42','created_at'=>'2026-08-15 11:00:00',
], [
    'id'=>'9203','payment_id'=>'7001','state'=>FollowUpState::ISSUE,'note'=>'Invoice dispute','promised_date'=>null,'deferred_until'=>null,'created_by'=>'42','created_at'=>'2026-08-15 10:59:00',
]]];
$history = $followups->history(7001, 20);
sc_p4_assert(count($history) === 2 && $history[0]['state'] === FollowUpState::DEFERRED, 'SC-P4-005 operational status history is readable newest-first');
sc_p4_assert(str_contains((string) end($GLOBALS['sc_test_read_queries']), 'ORDER BY created_at DESC, id DESC'), 'SC-P4-005 history ordering is deterministic');
$GLOBALS['sc_test_result_queue'] = [[sc_p4_payment(['accountant_user_id'=>'99'])]];
sc_p4_expect(DomainException::class, fn () => $followups->history(7001), 'SC-P4-005 history enforces Accountant scope');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_FOLLOWUPS=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p4_payment(['status'=>PaymentStatus::PAID,'paid_amount'=>'500.0000','remaining_amount'=>'0.0000'])]];
sc_p4_expect(DomainException::class, fn () => $followups->addNote(7001, 'Should not happen'), 'SC-P4-001 paid payments reject new follow-up writes');

// SC-P4-006: financial events are persisted with structured before/after values.
$GLOBALS['sc_test_result_queue'] = [];
$GLOBALS['sc_test_results'] = [];
$beforeFinancialAudit = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_base_value_changed', 501, '600.0000', 42, '500.0000');
$financialAudit = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeFinancialAudit));
sc_p4_assert(str_contains($financialAudit, 'INSERT INTO wp_safecontracts_audit_log') && str_contains($financialAudit, 'contract_base_value_changed'), 'SC-P4-006 financial event is audited');
sc_p4_assert(str_contains($financialAudit, '500.0000') && str_contains($financialAudit, '600.0000'), 'SC-P4-006 financial audit preserves before/after values');

// SC-P4-007: assignment audit preserves old/new IDs.
$beforeAssignmentAudit = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_customer_assigned', 501, 22, 42, 11);
$assignmentAudit = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeAssignmentAudit));
sc_p4_assert(str_contains($assignmentAudit, 'contract_customer_assigned') && str_contains($assignmentAudit, 'customer_id'), 'SC-P4-007 customer assignment emits structured audit event');
sc_p4_assert(str_contains($assignmentAudit, '11') && str_contains($assignmentAudit, '22'), 'SC-P4-007 assignment audit retains old/new customer IDs');

// SC-P4-008: later export/import phases can emit bounded audit hooks; secret-looking keys are stripped.
$beforeExport = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_export_completed', ['id'=>77,'filters'=>['customer_id'=>12],'api_token'=>'SUPERSECRET'], 42);
$exportAudit = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeExport));
sc_p4_assert(str_contains($exportAudit, 'export_completed') && str_contains($exportAudit, 'customer_id'), 'SC-P4-008 export hook records non-sensitive context');
sc_p4_assert(! str_contains($exportAudit, 'SUPERSECRET'), 'SC-P4-008 audit sanitizer strips secret-like export fields');
$beforeImport = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_import_completed', ['id'=>88,'rows'=>25], 42);
sc_p4_assert(str_contains(implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeImport)), 'import_completed'), 'SC-P4-008 import hook is audit-ready');

// Audit reads require the dedicated capability.
$audit = new AuditService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true];
sc_p4_expect(DomainException::class, fn () => $audit->forEntity('contract', 501), 'P4 audit reads require VIEW_AUDIT');
$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_AUDIT=>true];
$GLOBALS['sc_test_result_queue'] = [[['id'=>'1','entity_type'=>'contract','entity_id'=>'501','event_type'=>'contract_base_value_changed']]];
sc_p4_assert(count($audit->forEntity('contract', 501)) === 1, 'P4 authorized audit read succeeds');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_p4_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'P4 migration remains idempotent');

echo "SafeContracts P4 follow-up/audit tests SC-P4-001..009 passed ({$tests} assertions).\n";
