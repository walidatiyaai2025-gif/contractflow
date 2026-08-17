<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$paymentTests = 0;

function sc_payment_assert(bool $condition, string $message): void
{
    global $paymentTests;
    $paymentTests++;
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
        'accountant_user_id' => '42',
        'is_archived' => '0',
        'counterparty_type' => 'customer',
        'counterparty_id' => '7',
        'financial_direction' => 'receivable',
        'currency_code' => 'XXX',
    ], $overrides);
}

/** @return array<string, mixed> */
function sc_payment_row(array $overrides = []): array
{
    return array_merge([
        'id' => '7001',
        'contract_id' => '501',
        'financial_direction' => 'receivable',
        'currency_code' => 'XXX',
        'sequence_no' => '1',
        'reference' => 'INST-001',
        'due_date' => '2026-09-15',
        'expected_payment_date' => null,
        'original_amount' => '1000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '1000.0000',
        'status' => PaymentStatus::UPCOMING,
        'accountant_user_id' => '42',
        'contract_is_archived' => '0',
        'counterparty_type' => 'customer',
        'counterparty_id' => '7',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_payment_assert(is_callable($activate), 'plugin activation hook is available');
$activate();
sc_payment_assert(version_compare(Migrator::LATEST_VERSION, '1.7.0', '>='), 'collection ledger schema migration remains available after later migrations');
sc_payment_assert(count($GLOBALS['sc_test_dbdelta']) >= 10, 'collection migration extends the existing scheduled-payment schema before later schemas');

$schema = $GLOBALS['sc_test_dbdelta'][8];
sc_payment_assert(str_contains($schema, 'wp_safecontracts_scheduled_payments'), 'SC-P3-001 scheduled-payment table uses WordPress prefix');
sc_payment_assert(str_contains($schema, 'sequence_no int(11) unsigned NOT NULL'), 'SC-P3-001 payment sequence is explicit');
sc_payment_assert(str_contains($schema, 'due_date date NOT NULL'), 'SC-P3-003 due date is required in schema');
sc_payment_assert(str_contains($schema, 'expected_payment_date date NULL'), 'SC-P3-003 expected payment date is optional in schema');
sc_payment_assert(str_contains($schema, "status varchar(32) NOT NULL DEFAULT 'upcoming'"), 'SC-P3-002 lifecycle starts upcoming');
sc_payment_assert(str_contains($schema, 'original_amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'SC-P3-001 original amount uses fixed-point precision');
sc_payment_assert(str_contains($schema, 'paid_amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'SC-P3-001 paid amount uses fixed-point precision');
sc_payment_assert(str_contains($schema, 'remaining_amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'SC-P3-001 remaining amount uses fixed-point precision');
sc_payment_assert(str_contains($schema, 'UNIQUE KEY contract_sequence (contract_id, sequence_no)'), 'SC-P3-001 duplicate sequence within a contract is prevented');
sc_payment_assert(str_contains($schema, 'KEY contract_status_due (contract_id, status, due_date)'), 'SC-P3-001 contract/status/due reporting is indexed');

sc_payment_assert(PaymentStatus::all() === ['upcoming', 'due_soon', 'due', 'overdue', 'partially_paid', 'paid'], 'SC-P3-002 controlled lifecycle matches approved baseline');
sc_payment_assert(PaymentStatus::normalize(' DUE_SOON ') === 'due_soon', 'SC-P3-002 lifecycle input is normalized');
sc_payment_expect(InvalidArgumentException::class, fn () => PaymentStatus::normalize('cancelled'), 'SC-P3-002 unsupported lifecycle state is rejected');
sc_payment_expect(DomainException::class, fn () => PaymentStatus::assertTransition('paid', 'overdue'), 'SC-P3-002 paid state is terminal without explicit reversal');
PaymentStatus::assertTransition('overdue', 'upcoming');
sc_payment_assert(true, 'SC-P3-002 temporal lifecycle can move after due-date recalculation');

$service = new PaymentService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_PAYMENTS => true,
];

$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract()]];
$GLOBALS['wpdb']->insert_id = 7001;
$paymentId = $service->create([
    'contract_id' => 501,
    'sequence_no' => 1,
    'reference' => ' INST-001 ',
    'due_date' => '2026-09-15',
    'expected_payment_date' => null,
    'original_amount' => '1000.5',
]);
sc_payment_assert($paymentId === 7001, 'SC-P3-001 create returns inserted payment ID');
$createSql = (string) end($GLOBALS['sc_test_queries']);
sc_payment_assert(str_contains($createSql, 'wp_safecontracts_scheduled_payments'), 'SC-P3-001 create writes scheduled-payment table');
sc_payment_assert(str_contains($createSql, "'1000.5000'"), 'SC-P3-001 original amount is normalized to DECIMAL(20,4)');
sc_payment_assert(substr_count($createSql, "'1000.5000'") === 2, 'SC-P3-001 initial remaining balance equals original amount');
sc_payment_assert(str_contains($createSql, "'0.0000'"), 'SC-P3-001 initial paid amount is zero');
sc_payment_assert(str_contains($createSql, "'upcoming'"), 'SC-P3-002 new payment starts upcoming');
sc_payment_assert(str_contains($createSql, 'expected_payment_date') && str_contains($createSql, 'NULL'), 'SC-P3-003 expected date can start unset');
sc_payment_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_created']), 'SC-P3-001 create emits payment domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract()]];
$beforeZero = count($GLOBALS['sc_test_queries']);
sc_payment_expect(InvalidArgumentException::class, fn () => $service->create([
    'contract_id' => 501,
    'sequence_no' => 2,
    'due_date' => '2026-10-15',
    'original_amount' => '0',
]), 'SC-P3-001 zero-value scheduled payment is rejected');
sc_payment_assert(count($GLOBALS['sc_test_queries']) === $beforeZero, 'SC-P3-001 invalid amount cannot mutate data');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::MANAGE_PAYMENTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract(['accountant_user_id' => '99'])]];
$beforeScopeCreate = count($GLOBALS['sc_test_queries']);
sc_payment_expect(DomainException::class, fn () => $service->create([
    'contract_id' => 501,
    'sequence_no' => 2,
    'due_date' => '2026-10-15',
    'original_amount' => '100',
]), 'SC-P3-001 Accountant cannot create payment outside assigned contract scope');
sc_payment_assert(count($GLOBALS['sc_test_queries']) === $beforeScopeCreate, 'SC-P3-001 scope denial cannot mutate payment data');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_contract(['accountant_user_id' => '42', 'is_archived' => '1'])]];
sc_payment_expect(DomainException::class, fn () => $service->create([
    'contract_id' => 501,
    'sequence_no' => 2,
    'due_date' => '2026-10-15',
    'original_amount' => '100',
]), 'SC-P3-001 archived contract cannot receive new scheduled payments');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_PAYMENTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_payment_row()]];
$service->changeStatus(7001, 'due_soon');
$statusSql = (string) end($GLOBALS['sc_test_queries']);
sc_payment_assert(str_contains($statusSql, "status = 'due_soon'"), 'SC-P3-002 authorized lifecycle change is persisted');
sc_payment_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_status_changed']), 'SC-P3-002 lifecycle change emits domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['status' => 'due_soon'])]];
$beforeIdempotent = count($GLOBALS['sc_test_queries']);
$service->changeStatus(7001, 'due_soon');
sc_payment_assert(count($GLOBALS['sc_test_queries']) === $beforeIdempotent, 'SC-P3-002 same-state lifecycle update is idempotent');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['status' => 'paid'])]];
$beforePaidTerminal = count($GLOBALS['sc_test_queries']);
sc_payment_expect(DomainException::class, fn () => $service->changeStatus(7001, 'overdue'), 'SC-P3-002 paid lifecycle cannot regress without reversal workflow');
sc_payment_assert(count($GLOBALS['sc_test_queries']) === $beforePaidTerminal, 'SC-P3-002 terminal-state rejection cannot mutate data');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['contract_is_archived' => '1'])]];
sc_payment_expect(DomainException::class, fn () => $service->changeStatus(7001, 'due'), 'SC-P3-002 payment lifecycle is frozen when contract is archived');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row()]];
$service->updateDates(7001, '2026-09-20', '2026-09-18');
$dateSql = (string) end($GLOBALS['sc_test_queries']);
sc_payment_assert(str_contains($dateSql, "due_date = '2026-09-20'"), 'SC-P3-003 due-date change is persisted');
sc_payment_assert(str_contains($dateSql, "expected_payment_date = '2026-09-18'"), 'SC-P3-003 expected date may differ independently from due date');
sc_payment_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_dates_changed']), 'SC-P3-003 date change emits auditable domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['expected_payment_date' => '2026-09-18'])]];
$service->updateDates(7001, '2026-09-20', null);
sc_payment_assert(str_contains((string) end($GLOBALS['sc_test_queries']), 'expected_payment_date = NULL'), 'SC-P3-003 expected date can be cleared back to due-date fallback');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row()]];
$beforeInvalidDate = count($GLOBALS['sc_test_queries']);
sc_payment_expect(InvalidArgumentException::class, fn () => $service->updateDates(7001, '2026-02-30', null), 'SC-P3-003 invalid calendar due date is rejected');
sc_payment_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalidDate, 'SC-P3-003 invalid date cannot mutate data');

$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['expected_payment_date' => '2026-09-18'])]];
sc_payment_assert($service->effectiveDate(7001) === '2026-09-18', 'SC-P3-003 expected date overrides due date for operational effective-date reads');
$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['expected_payment_date' => null])]];
sc_payment_assert($service->effectiveDate(7001) === '2026-09-15', 'SC-P3-003 due date is effective date when no expected date exists');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[sc_payment_row(['accountant_user_id' => '99'])]];
sc_payment_expect(DomainException::class, fn () => $service->effectiveDate(7001), 'SC-P3-003 effective-date read respects Accountant scope');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_payment_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'payment/collection migrations are idempotent after current version is stored');

echo "SafeContracts payment core tests passed ({$paymentTests} assertions).\n";