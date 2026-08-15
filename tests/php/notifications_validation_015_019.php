<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\NotificationEngine;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\RecipientResolver;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_p5v2_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p5v2_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p5v2_assert($error instanceof $class, $message);
        return;
    }
    sc_p5v2_assert(false, $message);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p5v2_assert(is_callable($activate), 'P5 validation can activate SafeContracts');
$activate();

$seedSql = implode("\n", $GLOBALS['sc_test_queries']);
sc_p5v2_assert(str_contains($seedSql, 'default_due_10_days'), 'SC-P5-015 default ten-day rule remains seeded');
sc_p5v2_assert(str_contains($seedSql, "'before_due', 10"), 'SC-P5-015 default rule remains exactly ten days before due');
sc_p5v2_assert(str_contains($seedSql, 'safecontracts_manager'), 'SC-P5-015 default rule retains Manager recipient');
sc_p5v2_assert(str_contains($seedSql, 'target_assigned_accountant') && str_contains($seedSql, '1, 1, NULL, NULL'), 'SC-P5-015 default rule retains assigned-Accountant targeting');

$defaultRule = NotificationRule::normalizeInput([
    'code' => 'default_due_10_days',
    'name' => 'Default 10-day due reminder',
    'trigger_type' => NotificationRule::TRIGGER_BEFORE_DUE,
    'days_before' => 10,
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'target_assigned_accountant' => true,
    'is_active' => true,
]);
$defaultRule['id'] = 10;
sc_p5v2_assert($defaultRule['template_code'] === 'payment_due_soon', 'SC-P5-015 legacy rule keeps due-soon template default');
sc_p5v2_assert($defaultRule['repeat_interval_days'] === 0 && $defaultRule['max_repeats'] === 0, 'SC-P5-015 legacy rule remains non-repeating by default');

$today = new DateTimeImmutable('2026-08-15');
$payment = [
    'id' => 501,
    'due_date' => '2026-08-25',
    'expected_payment_date' => '2026-09-20',
    'remaining_amount' => '1250.0000',
    'status' => PaymentStatus::UPCOMING,
    'accountant_user_id' => 42,
    'reference' => 'PAY-501',
    'contract_number' => 'SC-501',
    'customer_name' => 'Example Customer',
];
sc_p5v2_assert(NotificationRule::matchesPayment($defaultRule, $payment, $today), 'SC-P5-015 exactly ten days before contractual due_date matches');
sc_p5v2_assert(! NotificationRule::matchesPayment($defaultRule, $payment, new DateTimeImmutable('2026-08-14')), 'SC-P5-015 eleven days before due does not match');
sc_p5v2_assert(! NotificationRule::matchesPayment($defaultRule, $payment, new DateTimeImmutable('2026-08-16')), 'SC-P5-015 nine days before due does not match');
$expectedShifted = $payment;
$expectedShifted['expected_payment_date'] = '2026-08-15';
sc_p5v2_assert(NotificationRule::matchesPayment($defaultRule, $expectedShifted, $today), 'SC-P5-015 expected_payment_date cannot move contractual ten-day trigger');
$paid = $payment;
$paid['status'] = PaymentStatus::PAID;
$paid['remaining_amount'] = '0.0000';
sc_p5v2_assert(! NotificationRule::matchesPayment($defaultRule, $paid, $today), 'SC-P5-015 settled payment is suppressed');

$GLOBALS['sc_test_users_by_role'] = [
    RoleRegistrar::MANAGER => [100, 101],
    RoleRegistrar::VIEWER => [101, 102],
    RoleRegistrar::ACCOUNTANT => [42, 77, 88],
    RoleRegistrar::SYSTEM_ADMIN => [1],
];
$resolver = new RecipientResolver();
$defaultRecipients = $resolver->resolve($defaultRule, 42);
sc_p5v2_assert($defaultRecipients === [42, 100, 101], 'SC-P5-015 default recipient set is Manager plus assigned Accountant');
sc_p5v2_assert(! in_array(77, $defaultRecipients, true) && ! in_array(88, $defaultRecipients, true), 'SC-P5-015 default rule never broadens to unrelated Accountants');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '1',
    'code' => 'payment_due_soon',
    'title_template' => 'Payment due for {{contract_number}}',
    'body_template' => '{{payment_reference}} is due on {{due_date}}; remaining {{remaining_amount}}.',
    'is_active' => '1',
]]];
$plan = (new NotificationEngine())->plan($defaultRule, $payment, $today);
sc_p5v2_assert($plan !== null, 'SC-P5-015 default rule produces a notification plan on the exact trigger day');
sc_p5v2_assert($plan['recipient_ids'] === [42, 100, 101], 'SC-P5-015 end-to-end plan preserves default recipient policy');
sc_p5v2_assert($plan['scheduled_for'] === '2026-08-15', 'SC-P5-015 end-to-end plan is scheduled on computed trigger date');
sc_p5v2_assert($plan['template_code'] === 'payment_due_soon', 'SC-P5-015 end-to-end plan uses expected template');

$roleRule = [
    'recipient_roles' => [RoleRegistrar::MANAGER, RoleRegistrar::VIEWER, RoleRegistrar::MANAGER],
    'target_assigned_accountant' => false,
];
$roleRecipients = $resolver->resolve($roleRule, null);
sc_p5v2_assert($roleRecipients === [100, 101, 102], 'SC-P5-016 role recipients are unique and numerically deterministic');
sc_p5v2_assert(count(array_unique($roleRecipients)) === count($roleRecipients), 'SC-P5-016 overlapping roles never duplicate a user');
sc_p5v2_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeRecipientRoles(['administrator']), 'SC-P5-016 native WordPress role is rejected from SafeContracts recipient policy');
sc_p5v2_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeRecipientRoles(['subscriber']), 'SC-P5-016 unknown role is rejected from SafeContracts recipient policy');
sc_p5v2_assert(NotificationRule::normalizeRecipientRoles([RoleRegistrar::MANAGER, RoleRegistrar::MANAGER]) === [RoleRegistrar::MANAGER], 'SC-P5-016 duplicate configured roles normalize once');

$assignedOnly = $resolver->resolve([
    'recipient_roles' => [],
    'target_assigned_accountant' => true,
], 42);
sc_p5v2_assert($assignedOnly === [42], 'SC-P5-017 assigned-only targeting resolves exactly assigned Accountant');
$assignedMerged = $resolver->resolve([
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'target_assigned_accountant' => true,
], 100);
sc_p5v2_assert($assignedMerged === [100, 101], 'SC-P5-017 assigned Accountant already in role set is not duplicated');
$missingAssigned = $resolver->resolve([
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'target_assigned_accountant' => true,
], null);
sc_p5v2_assert($missingAssigned === [100, 101], 'SC-P5-017 missing assignment retains only explicitly configured role recipients');
sc_p5v2_assert(! in_array(42, $missingAssigned, true) && ! in_array(77, $missingAssigned, true), 'SC-P5-017 missing assignment never falls back to all Accountants');
$noRecipients = $resolver->resolve([
    'recipient_roles' => [],
    'target_assigned_accountant' => true,
], null);
sc_p5v2_assert($noRecipients === [], 'SC-P5-017 missing assignment with assigned-only rule resolves no recipient instead of broadening scope');

$dueDayRule = NotificationRule::normalizeInput([
    'code' => 'due-day-validation',
    'name' => 'Due day validation',
    'trigger_type' => NotificationRule::TRIGGER_DUE_DAY,
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'target_assigned_accountant' => true,
]);
$dueToday = $payment;
$dueToday['due_date'] = '2026-08-15';
$dueToday['expected_payment_date'] = '2026-09-30';
$dueToday['status'] = PaymentStatus::DUE;
sc_p5v2_assert($dueDayRule['days_before'] === 0 && $dueDayRule['days_after'] === 0, 'SC-P5-018 due-day rule normalizes to zero date offsets');
sc_p5v2_assert(NotificationRule::matchesPayment($dueDayRule, $dueToday, $today), 'SC-P5-018 due-day rule matches contractual due_date exactly');
sc_p5v2_assert(! NotificationRule::matchesPayment($dueDayRule, $dueToday, new DateTimeImmutable('2026-08-14')), 'SC-P5-018 day before contractual due date does not match');
sc_p5v2_assert(! NotificationRule::matchesPayment($dueDayRule, $dueToday, new DateTimeImmutable('2026-08-16')), 'SC-P5-018 day after contractual due date does not match');
$expectedToday = $dueToday;
$expectedToday['due_date'] = '2026-08-20';
$expectedToday['expected_payment_date'] = '2026-08-15';
sc_p5v2_assert(! NotificationRule::matchesPayment($dueDayRule, $expectedToday, $today), 'SC-P5-018 expected_payment_date cannot falsely create due-day reminder');
$partialDue = $dueToday;
$partialDue['status'] = PaymentStatus::PARTIALLY_PAID;
$partialDue['remaining_amount'] = '1.0000';
sc_p5v2_assert(NotificationRule::matchesPayment($dueDayRule, $partialDue, $today), 'SC-P5-018 partial balance remains eligible on contractual due day');
$zeroDue = $partialDue;
$zeroDue['remaining_amount'] = '0.0000';
sc_p5v2_assert(! NotificationRule::matchesPayment($dueDayRule, $zeroDue, $today), 'SC-P5-018 zero remaining balance suppresses due-day reminder even with stale partial status');

$overdueRule = NotificationRule::normalizeInput([
    'code' => 'overdue-validation',
    'name' => 'Overdue validation',
    'trigger_type' => NotificationRule::TRIGGER_OVERDUE,
    'days_after' => 1,
    'repeat_interval_days' => 2,
    'max_repeats' => 2,
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'target_assigned_accountant' => true,
]);
$overduePayment = $payment;
$overduePayment['due_date'] = '2026-08-14';
$overduePayment['expected_payment_date'] = '2026-09-30';
$overduePayment['status'] = PaymentStatus::OVERDUE;
$overduePayment['remaining_amount'] = '500.0000';
sc_p5v2_assert(NotificationRule::matchesPayment($overdueRule, $overduePayment, $today, 0), 'SC-P5-019 first overdue reminder matches configured day-after boundary');
sc_p5v2_assert(! NotificationRule::matchesPayment($overdueRule, $overduePayment, new DateTimeImmutable('2026-08-14'), 0), 'SC-P5-019 contractual due day is not overdue day-after reminder');
sc_p5v2_assert(NotificationRule::matchesPayment($overdueRule, $overduePayment, new DateTimeImmutable('2026-08-17'), 1), 'SC-P5-019 overdue repeat follows configured cadence');
sc_p5v2_assert(NotificationRule::matchesPayment($overdueRule, $overduePayment, new DateTimeImmutable('2026-08-19'), 2), 'SC-P5-019 final overdue repeat follows configured cadence');
sc_p5v2_assert(! NotificationRule::matchesPayment($overdueRule, $overduePayment, new DateTimeImmutable('2026-08-21'), 3), 'SC-P5-019 overdue attempts beyond max repeats are suppressed');
$overdueExpected = $overduePayment;
$overdueExpected['expected_payment_date'] = '2026-08-15';
sc_p5v2_assert(NotificationRule::matchesPayment($overdueRule, $overdueExpected, $today, 0), 'SC-P5-019 expected_payment_date cannot erase contractual overdue trigger');
$partialOverdue = $overduePayment;
$partialOverdue['status'] = PaymentStatus::PARTIALLY_PAID;
$partialOverdue['remaining_amount'] = '0.0001';
sc_p5v2_assert(NotificationRule::matchesPayment($overdueRule, $partialOverdue, $today, 0), 'SC-P5-019 partial overdue payment remains eligible while balance exists');
$paidOverdue = $overduePayment;
$paidOverdue['status'] = PaymentStatus::PAID;
$paidOverdue['remaining_amount'] = '0.0000';
sc_p5v2_assert(! NotificationRule::matchesPayment($overdueRule, $paidOverdue, $today, 0), 'SC-P5-019 paid overdue payment is suppressed');
sc_p5v2_assert(NotificationRule::daysOverdue('2026-08-10', $today) === 5, 'SC-P5-019 days-overdue calculation derives from contractual due_date');
sc_p5v2_assert(NotificationRule::daysOverdue('2026-08-15', $today) === 0, 'SC-P5-019 due day itself reports zero days overdue');

sc_p5v2_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_notification_planned']), 'P5 validation confirms successful planning emits audit/domain event');

echo "SafeContracts P5 reminder targeting validation SC-P5-015..019 passed ({$tests} assertions).\n";
