<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentState;
use SafeContracts\Roles\Capabilities;

$tests = 0;

function sc_payment_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_payment_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_payment_assert($error instanceof $class, $message);
        return;
    }
    sc_payment_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_payment_contract(array $overrides = []): array
{
    return array_merge([
        'id' => '501',
        'contract_number' => 'SC-501',
        'customer_id' => '7',
        'accountant_user_id' => '42',
        'status' => 'active',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'base_value' => '1000.0000',
        'notes' => '',
        'is_archived' => '0',
    ], $overrides);
}

/** @return array<string, mixed> */
function sc_payment_row(array $overrides = []): array
{
    return array_merge([
        'id' => '7001',
        'contract_id' => '501',
        'sequence_no' => '1',
        'reference' => 'JAN-001',
        'original_amount' => '500.0000',
        'due_date' => '2026-08-20',
        'expected_payment_date' => null,
        'is_cancelled' => '0',
        'contract_accountant_user_id' => '42',
        'contract_status' => 'active',
        'contract_is_archived' => '0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_payment_assert(is_callable($activate), 'plugin activation hook is available');
$activate();

sc_payment_assert(Migrator::LATEST_VERSION === '1.6.0', 'payment schedule migration is current');
sc_payment_assert(get_option(Migrator::VERSION_OPTION) === '1.6.0', 'payment schedule migration version is stored');
sc_payment_assert(count($GLOBALS['sc_test_dbdelta']) === 9, 'payment schedule adds one schema after contract history');
$schema = $GLOBALS['sc_test_dbdelta'][8];
sc_payment_assert(str_contains($schema, 'wp_safecontracts_scheduled_payments'), 'scheduled payments use a dedicated prefixed table');
sc_payment_assert(str_contains($schema, 'contract_id bigint(20) unsigned NOT NULL'), 'scheduled payment belongs to a contract');
sc_payment_assert(str_contains($schema, 'sequence_no int(11) unsigned NOT NULL'), 'scheduled payment stores a sequence number');
sc_payment_assert(str_contains($schema, 'reference varchar(100) NULL'), 'scheduled payment supports optional reference');
sc_payment_assert(str_contains($schema, 'original_amount decimal(20,4) NOT NULL'), 'scheduled amount uses exact fixed-point precision');
sc_payment_assert(str_contains($schema, 'due_date date NOT NULL'), 'contractual due date is required');
sc_payment_assert(str_contains($schema, 'expected_payment_date date NULL'), 'operational expected date is stored separately');
sc_payment_assert(str_contains($schema, 'UNIQUE KEY contract_sequence (contract_id, sequence_no)'), 'contract payment sequence is unique');
sc_payment_assert(str_contains($schema, 'KEY due_state (is_cancelled, due_date, id)'), 'portfolio due-date lifecycle queries are indexed');
sc_payment_assert(str_contains($schema, 'KEY expected_state (is_cancelled, expected_payment_date, id)'), 'expected-date queries are indexed');
sc_payment_assert(! str_contains($schema, 'currency_code'), 'scheduled payments do not introduce per-payment currency');
sc_payment_assert(! str_contains($schema, 'paid_amount'), 'paid amount is not duplicated before collection-transaction source of truth exists');

$today = new DateTimeImmutable('2026-08-15');
sc_payment_assert(PaymentState::temporal('2026-09-01', $today, 10) === PaymentState::UPCOMING, 'far-future payment is upcoming');
sc_payment_assert(PaymentState::temporal('2026-08-25', $today, 10) === PaymentState::DUE_SOON, 'payment exactly ten days away is due soon');
sc_payment_assert(PaymentState::temporal('2026-08-15', $today, 10) === PaymentState::DUE, 'payment due today is due');
sc_payment_assert(PaymentState::temporal('2026-08-14', $today, 10) === PaymentState::OVERDUE, 'past contractual due date is overdue');
sc_payment_assert(PaymentState::derive('2026-08-14', '500', '125', $today) === PaymentState::PARTIALLY_PAID, 'partial collection produces partially-paid financial state');
sc_payment_assert(PaymentState::temporal('2026-08-14', $today) === PaymentState::OVERDUE, 'temporal state remains available alongside partial-payment state');
sc_payment_assert(PaymentState::derive('2026-08-14', '500', '500', $today) === PaymentState::PAID, 'full collection produces paid state');
sc_payment_assert(PaymentState::derive('2026-09-01', '500', '0', $today, 10, true) === PaymentState::CANCELLED, 'cancelled schedule has terminal cancelled state');
sc_payment_expect(InvalidArgumentException::class, fn () => PaymentState::derive('2026-08-20', '500', '501', $today), 'overpayment is rejected by lifecycle integrity check');
sc_payment_expect(InvalidArgumentException::class, fn () => PaymentState::temporal('2026-02-30', $today), 'invalid due date is rejected by lifecycle engine');

$service = new PaymentService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_PAYMENTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract()]];
$GLOBALS['wpdb']->insert_id = 6001;
$paymentId = $service->create([
    'contract_id' => 501,
    'sequence_no' => '1',
    'reference' => ' Q3-01 ',
    'original_amount' => '750.5',
    'due_date' => '2026-09-30',
    'expected_payment_date' => '2026-10-05',
]);
sc_payment_assert($paymentId === 6001, 'payment create returns inserted ID');
$createSql = (string) end($GLOBALS['sc_test_queries']);
sc_payment_assert(str_contains($createSql, 'wp_safecontracts_scheduled_payments'), 'payment create writes dedicated schedule table');
sc_payment_assert(str_contains($createSql, '501, 1'), 'payment create persists contract and sequence');
sc_payment_assert(str_contains($createSql, "'Q3-01'"), 'payment reference is trimmed and prepared');
sc_payment_assert(str_contains($createSql, "'750.5000'"), 'payment amount is normalized to four decimals');
sc_payment_assert(str_contains($createSql, "'2026-09-30'"), 'payment due date is persisted');
sc_payment_assert(str_contains($createSql, "'2026-10-05'"), 'expected payment date is persisted separately');
sc_payment_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_scheduled']), 'payment create emits domain event');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::MANAGE_PAYMENTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract(['accountant_user_id' => '42'])]];
$GLOBALS['wpdb']->insert_id = 6002;
sc_payment_assert($service->create([
    'contract_id' => 501,
    'sequence_no' => 2,
    'original_amount' => '100',
    'due_date' => '2026-10-31',
]) === 6002, 'Accountant can schedule payment on own assigned contract');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract(['accountant_user_id' => '99'])]];
$mutationsBeforeScopeDenial = count($GLOBALS['sc_test_queries']);
sc_payment_expect(DomainException::class, fn () => $service->create([
    'contract_id' => 501,
    'sequence_no' => 3,
    'original_amount' => '100',
    'due_date' => '2026-11-30',
]), 'Accountant cannot schedule another Accountant contract');
sc_payment_assert(count($GLOBALS['sc_test_queries']) === $mutationsBeforeScopeDenial, 'out-of-scope schedule causes no mutation');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::MANAGE_PAYMENTS => true];
$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract(['status' => 'completed'])]];
sc_payment_expect(DomainException::class, fn () => $service->create([
    'contract_id' => 501,
    'sequence_no' => 3,
    'original_amount' => '100',
    'due_date' => '2026-11-30',
]), 'terminal contract rejects new scheduled payments');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract()]];
sc_payment_expect(InvalidArgumentException::class, fn () => $service->create([
    'contract_id' => 501,
    'sequence_no' => 0,
    'original_amount' => '100',
    'due_date' => '2026-11-30',
]), 'payment sequence must be positive');
$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract()]];
sc_payment_expect(InvalidArgumentException::class, fn () => $service->create([
    'contract_id' => 501,
    'sequence_no' => 3,
    'original_amount' => '0',
    'due_date' => '2026-11-30',
]), 'zero scheduled amount is rejected');
$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract()]];
sc_payment_expect(InvalidArgumentException::class, fn () => $service->create([
    'contract_id' => 501,
    'sequence_no' => 3,
    'original_amount' => '100',
    'due_date' => '2026-02-30',
]), 'invalid contractual due date is rejected');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['expected_payment_date' => '2026-08-22'])]];
$service->updateDates(7001, '2026-08-25', '2026-08-28');
$dateSql = (string) end($GLOBALS['sc_test_queries']);
sc_payment_assert(str_contains($dateSql, "due_date = '2026-08-25'"), 'authorized due-date change is persisted');
sc_payment_assert(str_contains($dateSql, "expected_payment_date = '2026-08-28'"), 'expected date remains a separate operational field');
sc_payment_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_dates_changed']), 'payment date change emits audit-ready domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['expected_payment_date' => '2026-08-28'])]];
$service->updateDates(7001, '2026-08-25', null);
sc_payment_assert(str_contains((string) end($GLOBALS['sc_test_queries']), 'expected_payment_date = NULL'), 'expected payment date can be cleared without altering due date');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['is_cancelled' => '1'])]];
$mutationsBeforeCancelledEdit = count($GLOBALS['sc_test_queries']);
sc_payment_expect(DomainException::class, fn () => $service->updateDates(7001, '2026-08-30'), 'cancelled payment dates are frozen');
sc_payment_assert(count($GLOBALS['sc_test_queries']) === $mutationsBeforeCancelledEdit, 'cancelled payment date attempt causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row()]];
$service->cancel(7001);
$cancelSql = (string) end($GLOBALS['sc_test_queries']);
sc_payment_assert(str_contains($cancelSql, 'is_cancelled = 1'), 'payment cancellation persists explicit terminal flag');
sc_payment_assert(str_contains($cancelSql, 'cancelled_at = UTC_TIMESTAMP()'), 'payment cancellation is timestamped');
sc_payment_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_cancelled']), 'payment cancellation emits domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['due_date' => '2026-08-14'])]];
sc_payment_assert($service->state(7001, '125', $today) === PaymentState::PARTIALLY_PAID, 'server payment service derives partial state from scoped payment data');
$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['due_date' => '2026-08-14'])]];
sc_payment_assert($service->state(7001, '0', $today) === PaymentState::OVERDUE, 'server payment service uses contractual due date for overdue state');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['contract_accountant_user_id' => '99'])]];
sc_payment_expect(DomainException::class, fn () => $service->state(7001, '0', $today), 'payment state read cannot bypass Accountant scope');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_payment_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'payment schedule migration is idempotent after current version');

echo "SafeContracts payment foundation SC-P3-001..003 passed ({$tests} assertions).\n";
