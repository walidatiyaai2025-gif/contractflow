<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$tests = 0;

function sc_vp_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_vp_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_vp_assert($error instanceof $class, $message);
        return;
    }

    sc_vp_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_vp_payment(array $overrides = []): array
{
    return array_merge([
        'id' => '7001',
        'contract_id' => '501',
        'sequence_no' => '1',
        'reference' => 'VAL-1',
        'due_date' => '2026-08-25',
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
sc_vp_assert(is_callable($activate), 'plugin activation hook is available');
$activate();

$payments = new PaymentService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_PAYMENTS => true,
];

// SC-P3-014 — Payment lifecycle validation.
sc_vp_assert(
    PaymentStatus::all() === ['upcoming', 'due_soon', 'due', 'overdue', 'partially_paid', 'paid'],
    'SC-P3-014 lifecycle values match approved baseline'
);
PaymentStatus::assertTransition(PaymentStatus::OVERDUE, PaymentStatus::DUE_SOON);
sc_vp_assert(true, 'SC-P3-014 temporal lifecycle can recalculate after contractual due-date changes');
sc_vp_expect(
    DomainException::class,
    fn () => PaymentStatus::assertTransition(PaymentStatus::PAID, PaymentStatus::OVERDUE),
    'SC-P3-014 paid state is terminal without explicit reversal workflow'
);
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['status' => PaymentStatus::UPCOMING])]];
$payments->changeStatus(7001, PaymentStatus::DUE_SOON);
sc_vp_assert(
    str_contains((string) end($GLOBALS['sc_test_queries']), "status = 'due_soon'"),
    'SC-P3-014 authorized lifecycle change persists'
);
sc_vp_assert(
    isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_status_changed']),
    'SC-P3-014 lifecycle change emits domain event'
);
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['status' => PaymentStatus::PAID])]];
$beforePaidExit = count($GLOBALS['sc_test_queries']);
sc_vp_expect(
    DomainException::class,
    fn () => $payments->changeStatus(7001, PaymentStatus::OVERDUE),
    'SC-P3-014 service prevents leaving paid without reversal workflow'
);
sc_vp_assert(count($GLOBALS['sc_test_queries']) === $beforePaidExit, 'SC-P3-014 rejected paid-state exit causes no mutation');

// SC-P3-015 — Due and expected dates validation.
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['due_date' => '2026-08-25', 'expected_payment_date' => null])]];
$payments->updateDates(7001, '2026-08-30', '2026-08-28');
$dateSql = (string) end($GLOBALS['sc_test_queries']);
sc_vp_assert(
    str_contains($dateSql, "due_date = '2026-08-30'")
    && str_contains($dateSql, "expected_payment_date = '2026-08-28'"),
    'SC-P3-015 due and expected dates persist independently'
);
sc_vp_assert(
    isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_dates_changed']),
    'SC-P3-015 date change emits auditable domain event'
);
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['due_date' => '2026-08-30', 'expected_payment_date' => '2026-08-28'])]];
sc_vp_assert($payments->effectiveDate(7001) === '2026-08-28', 'SC-P3-015 expected date remains available for operational follow-up');
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['due_date' => '2026-08-30', 'expected_payment_date' => null])]];
sc_vp_assert($payments->effectiveDate(7001) === '2026-08-30', 'SC-P3-015 operational effective date falls back to contractual due date');
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment()]];
$beforeInvalidDate = count($GLOBALS['sc_test_queries']);
sc_vp_expect(
    InvalidArgumentException::class,
    fn () => $payments->updateDates(7001, '2026-08-30', '2026-02-30'),
    'SC-P3-015 invalid expected date is rejected'
);
sc_vp_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalidDate, 'SC-P3-015 invalid date causes no mutation');

// SC-P3-016 — Due Soon validation.
$today = new DateTimeImmutable('2026-08-15');
sc_vp_assert(
    PaymentStatus::temporalForDueDate('2026-08-25', $today, 10) === PaymentStatus::DUE_SOON,
    'SC-P3-016 ten-day contractual boundary is Due Soon'
);
sc_vp_assert(
    PaymentStatus::temporalForDueDate('2026-08-26', $today, 10) === PaymentStatus::UPCOMING,
    'SC-P3-016 eleven days remains Upcoming'
);
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['due_date' => '2026-08-25', 'expected_payment_date' => '2026-09-30'])]];
sc_vp_assert($payments->isDueSoon(7001, $today, 10), 'SC-P3-016 later expected date cannot delay contractual Due Soon');
sc_vp_expect(
    InvalidArgumentException::class,
    fn () => PaymentStatus::temporalForDueDate('2026-08-25', $today, -1),
    'SC-P3-016 negative due-soon window is rejected'
);

// SC-P3-017 — Overdue validation.
sc_vp_assert(
    PaymentStatus::temporalForDueDate('2026-08-14', $today, 10) === PaymentStatus::OVERDUE,
    'SC-P3-017 past contractual due date is Overdue'
);
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['due_date' => '2026-08-14', 'expected_payment_date' => '2026-09-30'])]];
sc_vp_assert($payments->isOverdue(7001, $today), 'SC-P3-017 later expected date cannot erase contractual Overdue');
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['due_date' => '2026-09-01', 'expected_payment_date' => '2026-08-01'])]];
sc_vp_assert(
    $payments->temporalStatus(7001, $today) === PaymentStatus::UPCOMING,
    'SC-P3-017 early expected date cannot create false contractual Overdue'
);
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['due_date' => '2026-08-01', 'status' => PaymentStatus::PARTIALLY_PAID])]];
sc_vp_assert(
    $payments->temporalStatus(7001, $today) === PaymentStatus::PARTIALLY_PAID,
    'SC-P3-017 financial partially-paid state remains distinct from temporal aging'
);

// SC-P3-018 — Collection transaction model validation after settlement integration.
$collections = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_COLLECTIONS => true,
];
$GLOBALS['sc_test_result_queue'] = [
    [sc_vp_payment()],
    [['id' => '1']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9101;
$readBefore = count($GLOBALS['sc_test_read_queries']);
$writeBefore = count($GLOBALS['sc_test_queries']);
$collectionId = $collections->record([
    'payment_id' => 7001,
    'amount' => '100',
    'collection_date' => '2026-08-15',
    'payment_method_id' => 1,
    'reference' => 'RCPT-VAL',
]);
$reads = array_slice($GLOBALS['sc_test_read_queries'], $readBefore);
$writes = array_slice($GLOBALS['sc_test_queries'], $writeBefore);
$writeSql = implode("\n", $writes);
sc_vp_assert($collectionId === 9101, 'SC-P3-018 collection returns immutable ledger ID');
sc_vp_assert(str_contains((string) ($reads[0] ?? ''), 'FOR UPDATE'), 'SC-P3-018 collection locks payment row before settlement');
sc_vp_assert(str_contains($writeSql, 'INSERT INTO wp_safecontracts_payment_collections'), 'SC-P3-018 collection persists append-only ledger row');
sc_vp_assert(str_contains($writeSql, 'UPDATE wp_safecontracts_scheduled_payments'), 'SC-P3-018 collection reconciles payment balance cache in same transaction');
sc_vp_assert($writes[0] === 'START TRANSACTION' && end($writes) === 'COMMIT', 'SC-P3-018 collection transaction is atomic');
sc_vp_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_collection_recorded']), 'SC-P3-018 collection emits ledger domain event');
sc_vp_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_settled']), 'SC-P3-018 collection emits settlement domain event');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment(['accountant_user_id' => '99'])]];
sc_vp_expect(DomainException::class, fn () => $collections->forPayment(7001), 'SC-P3-018 ledger reads enforce Accountant scope');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[sc_vp_payment()], [[
    'id' => '9101',
    'payment_id' => '7001',
    'amount' => '100.0000',
    'collection_date' => '2026-08-15',
    'payment_method_id' => '1',
    'reference' => 'RCPT-VAL',
    'details' => null,
    'proof_media_id' => null,
    'created_by' => '42',
    'updated_by' => '42',
    'created_at' => '2026-08-15 10:45:00',
    'updated_at' => '2026-08-15 10:45:00',
]]];
$ledger = $collections->forPayment(7001);
sc_vp_assert(count($ledger) === 1 && $ledger[0]['amount'] === '100.0000', 'SC-P3-018 scoped ledger read returns normalized transaction data');
sc_vp_assert($ledger[0]['reference'] === 'RCPT-VAL', 'SC-P3-018 collection reference survives normalized read');

echo "SafeContracts P3 validation SC-P3-014..018 passed ({$tests} assertions).\n";
