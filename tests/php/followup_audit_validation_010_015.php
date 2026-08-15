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
function sc_p4v_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function sc_p4v_expect(string $class, callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { sc_p4v_assert($e instanceof $class, $message); return; } sc_p4v_assert(false, $message); }
function sc_p4v_payment(array $overrides = []): array { return array_merge([
    'id'=>'7001','contract_id'=>'501','sequence_no'=>'1','reference'=>'P-1','due_date'=>'2026-08-25','expected_payment_date'=>null,
    'original_amount'=>'500.0000','paid_amount'=>'0.0000','remaining_amount'=>'500.0000','status'=>PaymentStatus::DUE_SOON,
    'accountant_user_id'=>'42','contract_is_archived'=>'0',
], $overrides); }

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p4v_assert(is_callable($activate), 'P4 validation activation hook exists');
$activate();
sc_p4v_assert(version_compare(Migrator::LATEST_VERSION, '1.8.0', '>='), 'P4 follow-up/audit migration remains present after later phases');
sc_p4v_assert(count($GLOBALS['sc_test_dbdelta']) >= 12, 'P4 follow-up/audit schemas remain present');
do_action('plugins_loaded');

$followups = new FollowUpService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_FOLLOWUPS=>true];

// SC-P4-010 — follow-up notes.
$beforeEmpty = count($GLOBALS['sc_test_read_queries']);
sc_p4v_expect(InvalidArgumentException::class, fn () => $followups->addNote(7001, '   '), 'SC-P4-010 blank follow-up note is rejected');
sc_p4v_assert(count($GLOBALS['sc_test_read_queries']) === $beforeEmpty, 'SC-P4-010 invalid note is rejected before payment lookup');
sc_p4v_expect(InvalidArgumentException::class, fn () => $followups->addNote(7001, str_repeat('x', 5001)), 'SC-P4-010 note length is bounded server-side');
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()]];
$GLOBALS['wpdb']->insert_id = 9301;
$beforeNote = count($GLOBALS['sc_test_queries']);
$id = $followups->addNote(7001, '  Called customer AP  ');
$noteSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeNote));
sc_p4v_assert($id === 9301, 'SC-P4-010 valid note returns append ID');
sc_p4v_assert(str_contains($noteSql, "'Called customer AP'") && str_contains($noteSql, "'contacted'"), 'SC-P4-010 note is normalized into contacted append-only state');
sc_p4v_assert(str_contains($noteSql, 'INSERT INTO wp_safecontracts_audit_log'), 'SC-P4-010 note mutation is represented in audit timeline');

// SC-P4-011 — promise-to-pay.
sc_p4v_expect(InvalidArgumentException::class, fn () => $followups->promiseToPay(7001, '2026-02-30'), 'SC-P4-011 invalid promise date is rejected');
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment(['expected_payment_date'=>'2026-08-27'])]];
$beforePromise = count($GLOBALS['sc_test_queries']);
$followups->promiseToPay(7001, '2026-08-28', 'Confirmed by finance');
$promiseSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforePromise));
sc_p4v_assert(str_contains($promiseSql, "'promised_to_pay'") && str_contains($promiseSql, "'2026-08-28'"), 'SC-P4-011 promise state/date are appended');
sc_p4v_assert(! str_contains($promiseSql, 'UPDATE wp_safecontracts_scheduled_payments'), 'SC-P4-011 promise never rewrites contractual or expected payment dates');

// SC-P4-012 — issue/deferred.
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()]];
$followups->markIssue(7001, 'PO mismatch');
sc_p4v_assert(str_contains((string) $GLOBALS['sc_test_queries'][count($GLOBALS['sc_test_queries']) - 2], "'issue'"), 'SC-P4-012 issue state is append-only');
sc_p4v_expect(InvalidArgumentException::class, fn () => $followups->defer(7001, 'bad-date'), 'SC-P4-012 invalid deferred date is rejected');
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()]];
$followups->defer(7001, '2026-09-05', 'Awaiting PO');
$deferMutation = (string) $GLOBALS['sc_test_queries'][count($GLOBALS['sc_test_queries']) - 2];
sc_p4v_assert(str_contains($deferMutation, "'deferred'") && str_contains($deferMutation, "'2026-09-05'"), 'SC-P4-012 deferred state stores operational resume date');

// SC-P4-013 — deterministic operational history + scope.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment()], [[
    'id'=>'3','payment_id'=>'7001','state'=>FollowUpState::DEFERRED,'note'=>'Awaiting PO','promised_date'=>null,'deferred_until'=>'2026-09-05','created_by'=>'42','created_at'=>'2026-08-15 11:20:00',
], [
    'id'=>'2','payment_id'=>'7001','state'=>FollowUpState::ISSUE,'note'=>'PO mismatch','promised_date'=>null,'deferred_until'=>null,'created_by'=>'42','created_at'=>'2026-08-15 11:19:00',
]]];
$history = $followups->history(7001, 999);
sc_p4v_assert(count($history) === 2 && $history[0]['state'] === FollowUpState::DEFERRED, 'SC-P4-013 newest operational state is returned first');
$historySql = (string) end($GLOBALS['sc_test_read_queries']);
sc_p4v_assert(str_contains($historySql, 'ORDER BY created_at DESC, id DESC') && str_contains($historySql, 'LIMIT 500'), 'SC-P4-013 history ordering and maximum page size are deterministic');
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment(['accountant_user_id'=>'99'])]];
sc_p4v_expect(DomainException::class, fn () => $followups->history(7001), 'SC-P4-013 Accountant cannot read another assignment history');

// Mutation boundaries remain enforced while validating history behavior.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_FOLLOWUPS=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment(['contract_is_archived'=>'1'])]];
sc_p4v_expect(DomainException::class, fn () => $followups->addNote(7001, 'Blocked'), 'SC-P4-013 archived contract remains immutable for follow-up');
$GLOBALS['sc_test_result_queue'] = [[sc_p4v_payment(['status'=>PaymentStatus::PAID,'paid_amount'=>'500.0000','remaining_amount'=>'0.0000'])]];
sc_p4v_expect(DomainException::class, fn () => $followups->addNote(7001, 'Blocked'), 'SC-P4-013 paid payment rejects irrelevant follow-up');

// SC-P4-014 — financial audit trail.
$beforeFinancial = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_base_value_changed', 501, '1250.0000', 42, '1000.0000');
$financialSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeFinancial));
sc_p4v_assert(str_contains($financialSql, 'contract_base_value_changed'), 'SC-P4-014 financial value change emits audit event');
sc_p4v_assert(str_contains($financialSql, '1000.0000') && str_contains($financialSql, '1250.0000'), 'SC-P4-014 audit preserves financial before/after values');
$beforeSettlement = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_payment_settled', 7001, '100.0000', '100.0000', '400.0000', 'partially_paid', 42, '0.0000', '500.0000', 'due_soon');
$settlementSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeSettlement));
sc_p4v_assert(str_contains($settlementSql, 'payment_settled') && str_contains($settlementSql, '400.0000'), 'SC-P4-014 payment settlement audit preserves resulting balance');

$audit = new AuditService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true];
sc_p4v_expect(DomainException::class, fn () => $audit->forEntity('payment', 7001), 'SC-P4-014 audit read requires dedicated VIEW_AUDIT capability');
$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_AUDIT=>true];
$GLOBALS['sc_test_result_queue'] = [[['id'=>'10','entity_type'=>'payment','entity_id'=>'7001','event_type'=>'payment_settled']]];
sc_p4v_assert(count($audit->forEntity('payment', 7001)) === 1, 'SC-P4-014 authorized audit read succeeds');

// SC-P4-015 — assignment audit trail.
$beforeCustomer = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_customer_assigned', 501, 22, 42, 11);
$customerAudit = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeCustomer));
sc_p4v_assert(str_contains($customerAudit, 'contract_customer_assigned') && str_contains($customerAudit, 'customer_id'), 'SC-P4-015 customer assignment is audited structurally');
sc_p4v_assert(str_contains($customerAudit, '11') && str_contains($customerAudit, '22'), 'SC-P4-015 customer assignment retains old/new IDs');
$beforeAccountant = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_contract_accountant_assigned', 501, 77, 42, 66);
$accountantAudit = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeAccountant));
sc_p4v_assert(str_contains($accountantAudit, 'contract_accountant_assigned') && str_contains($accountantAudit, 'accountant_user_id'), 'SC-P4-015 Accountant assignment is audited structurally');
sc_p4v_assert(str_contains($accountantAudit, '66') && str_contains($accountantAudit, '77'), 'SC-P4-015 Accountant assignment retains old/new IDs');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_p4v_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'P4 validations remain migration-idempotent after later phases');

echo "SafeContracts P4 final validation SC-P4-010..015 passed ({$tests} assertions).\n";
