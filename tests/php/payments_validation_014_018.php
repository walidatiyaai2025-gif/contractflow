<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$validationTests = 0;
function sc_p3v_assert(bool $condition, string $message): void
{
    global $validationTests;
    $validationTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p3v_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_p3v_assert($error instanceof $class, $message);
        return;
    }
    sc_p3v_assert(false, $message);
}
function sc_p3v_payment(array $overrides = []): array
{
    return array_merge([
        'id'=>'7001','contract_id'=>'501','sequence_no'=>'1','reference'=>'VAL-001',
        'due_date'=>'2026-08-25','expected_payment_date'=>null,
        'original_amount'=>'500.0000','paid_amount'=>'0.0000','remaining_amount'=>'500.0000',
        'status'=>PaymentStatus::UPCOMING,'accountant_user_id'=>'42','contract_is_archived'=>'0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p3v_assert(is_callable($activate), 'plugin activation hook is available');
$activate();

$payments = new PaymentService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS=>true,
    Capabilities::VIEW_ALL=>true,
    Capabilities::MANAGE_PAYMENTS=>true,
];

// SC-P3-014 — Payment lifecycle validation.
sc_p3v_assert(
    PaymentStatus::all() === [
        'upcoming','due_soon','due','overdue',
        'partially_paid','paid','partially_received','received',
    ],
    'SC-P3-014 approved timing/AP lifecycle remains ordered and P11 adds explicit AR settlement states'
);
PaymentStatus::assertTransition(PaymentStatus::OVERDUE, PaymentStatus::DUE_SOON);
sc_p3v_assert(true, 'SC-P3-014 temporal states may recalculate after contractual date changes');
sc_p3v_expect(
    DomainException::class,
    fn () => PaymentStatus::assertTransition(PaymentStatus::PAID, PaymentStatus::OVERDUE),
    'SC-P3-014 paid remains terminal without explicit reversal workflow'
);
sc_p3v_expect(
    DomainException::class,
    fn () => PaymentStatus::assertTransition(PaymentStatus::RECEIVED, PaymentStatus::OVERDUE),
    'SC-P11 received remains terminal without explicit reversal workflow'
);
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment(['status'=>PaymentStatus::UPCOMING])]];
$payments->changeStatus(7001, PaymentStatus::DUE_SOON);
sc_p3v_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "status = 'due_soon'"), 'SC-P3-014 authorized lifecycle update persists');
sc_p3v_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_status_changed']), 'SC-P3-014 lifecycle update emits domain event');

// SC-P3-015 — Due date and expected date remain independent.
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment(['due_date'=>'2026-08-25','expected_payment_date'=>null])]];
$payments->updateDates(7001, '2026-08-30', '2026-09-05');
$dateSql = (string) end($GLOBALS['sc_test_queries']);
sc_p3v_assert(str_contains($dateSql, "due_date = '2026-08-30'"), 'SC-P3-015 contractual due date persists independently');
sc_p3v_assert(str_contains($dateSql, "expected_payment_date = '2026-09-05'"), 'SC-P3-015 operational expected date persists independently');
sc_p3v_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_dates_changed']), 'SC-P3-015 date changes emit auditable event');
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment(['due_date'=>'2026-08-30','expected_payment_date'=>'2026-09-05'])]];
sc_p3v_assert($payments->effectiveDate(7001) === '2026-09-05', 'SC-P3-015 expected date remains available for operational follow-up');
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment()]];
sc_p3v_expect(InvalidArgumentException::class, fn () => $payments->updateDates(7001, '2026-02-30', null), 'SC-P3-015 invalid calendar due date is rejected');

// SC-P3-016 — Due Soon uses contractual due_date and inclusive ten-day boundary.
$today = new DateTimeImmutable('2026-08-15');
sc_p3v_assert(PaymentStatus::temporalForDueDate('2026-08-25', $today, 10) === PaymentStatus::DUE_SOON, 'SC-P3-016 ten-day boundary is Due Soon');
sc_p3v_assert(PaymentStatus::temporalForDueDate('2026-08-26', $today, 10) === PaymentStatus::UPCOMING, 'SC-P3-016 eleven days remains Upcoming');
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment(['due_date'=>'2026-08-25','expected_payment_date'=>'2026-10-01'])]];
sc_p3v_assert($payments->isDueSoon(7001, $today, 10), 'SC-P3-016 later expected date cannot delay contractual Due Soon');

// SC-P3-017 — Overdue uses contractual due_date regardless of expected date.
sc_p3v_assert(PaymentStatus::temporalForDueDate('2026-08-14', $today, 10) === PaymentStatus::OVERDUE, 'SC-P3-017 past contractual due date is Overdue');
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment(['due_date'=>'2026-08-14','expected_payment_date'=>'2026-09-30'])]];
sc_p3v_assert($payments->isOverdue(7001, $today), 'SC-P3-017 later expected date cannot erase contractual Overdue');
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment(['due_date'=>'2026-09-01','expected_payment_date'=>'2026-08-01'])]];
sc_p3v_assert($payments->temporalStatus(7001, $today) === PaymentStatus::UPCOMING, 'SC-P3-017 early expected date cannot create false Overdue');

// SC-P3-018 — Collection transaction model: row lock, ledger append, cache update, commit and scope.
$collections = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS=>true,
    Capabilities::VIEW_ALL=>true,
    Capabilities::MANAGE_COLLECTIONS=>true,
];
$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v_payment()],
    [['id'=>'1']],
    [['total'=>'0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9201;
$readOffset = count($GLOBALS['sc_test_read_queries']);
$writeOffset = count($GLOBALS['sc_test_queries']);
$collectionId = $collections->record([
    'payment_id'=>7001,
    'amount'=>'100',
    'collection_date'=>'2026-08-15',
    'payment_method_id'=>1,
    'reference'=>'VALIDATION-001',
]);
$reads = array_slice($GLOBALS['sc_test_read_queries'], $readOffset);
$writes = array_slice($GLOBALS['sc_test_queries'], $writeOffset);
$writeSql = implode("\n", $writes);
sc_p3v_assert($collectionId === 9201, 'SC-P3-018 collection ledger returns immutable transaction ID');
sc_p3v_assert(str_contains((string) ($reads[0] ?? ''), 'FOR UPDATE'), 'SC-P3-018 scheduled payment row is locked before settlement');
sc_p3v_assert($writes[0] === 'START TRANSACTION' && end($writes) === 'COMMIT', 'SC-P3-018 collection and settlement are atomic');
sc_p3v_assert(str_contains($writeSql, 'INSERT INTO wp_safecontracts_payment_collections'), 'SC-P3-018 collection appends dedicated ledger row');
sc_p3v_assert(str_contains($writeSql, 'UPDATE wp_safecontracts_scheduled_payments'), 'SC-P3-018 payment balance cache updates in same transaction');
sc_p3v_assert(str_contains($writeSql, "paid_amount = '100.0000'") && str_contains($writeSql, "remaining_amount = '400.0000'"), 'SC-P3-018 ledger-derived balances are persisted exactly');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment(['accountant_user_id'=>'99'])]];
sc_p3v_expect(DomainException::class, fn () => $collections->forPayment(7001), 'SC-P3-018 Accountant cannot read another Accountant collection ledger');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p3v_payment()], [[
    'id'=>'9201','payment_id'=>'7001','amount'=>'100.0000','collection_date'=>'2026-08-15','payment_method_id'=>'1',
    'reference'=>'VALIDATION-001','details'=>null,'proof_media_id'=>null,'created_by'=>'42','updated_by'=>'42',
    'created_at'=>'2026-08-15 10:50:00','updated_at'=>'2026-08-15 10:50:00',
]]];
$ledger = $collections->forPayment(7001);
sc_p3v_assert(count($ledger) === 1 && $ledger[0]['amount'] === '100.0000', 'SC-P3-018 Manager/all-data scope can read normalized collection ledger');

echo "SafeContracts P3 validation SC-P3-014..018 passed ({$validationTests} assertions).\n";
