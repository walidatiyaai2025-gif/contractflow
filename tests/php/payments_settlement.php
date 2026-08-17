<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Database\Migrator;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$settlementTests = 0;

function sc_settle_assert(bool $condition, string $message): void
{
    global $settlementTests;
    $settlementTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_settle_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_settle_assert($error instanceof $class, $message);
        return;
    }

    sc_settle_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_settle_payment(array $overrides = []): array
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
        'original_amount' => '500.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '500.0000',
        'status' => PaymentStatus::UPCOMING,
        'accountant_user_id' => '42',
        'contract_is_archived' => '0',
        'counterparty_type' => 'customer',
        'counterparty_id' => '7',
    ], $overrides);
}

/** @return list<string> */
function sc_settle_mutations_since(int $offset): array
{
    return array_slice($GLOBALS['sc_test_queries'], $offset);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_settle_assert(is_callable($activate), 'plugin activation hook is available');
$activate();
sc_settle_assert(version_compare(Migrator::LATEST_VERSION, '1.7.0', '>='), 'SC-P3-013 payment/collection schema remains available after later migrations');

$paymentSchema = $GLOBALS['sc_test_dbdelta'][8];
sc_settle_assert(str_contains($paymentSchema, 'wp_safecontracts_scheduled_payments'), 'SC-P3-013 payment schedule uses dedicated prefixed table');
sc_settle_assert(str_contains($paymentSchema, 'contract_id bigint(20) unsigned NOT NULL'), 'SC-P3-013 payment schedule belongs to a contract');
sc_settle_assert(str_contains($paymentSchema, 'sequence_no int(11) unsigned NOT NULL'), 'SC-P3-013 payment sequence is explicit');
sc_settle_assert(str_contains($paymentSchema, 'due_date date NOT NULL'), 'SC-P3-013 contractual due date is required');
sc_settle_assert(str_contains($paymentSchema, 'expected_payment_date date NULL'), 'SC-P3-013 operational expected date is nullable');
sc_settle_assert(str_contains($paymentSchema, 'original_amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'SC-P3-013 original amount is fixed precision');
sc_settle_assert(str_contains($paymentSchema, 'paid_amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'SC-P3-013 paid amount is fixed precision');
sc_settle_assert(str_contains($paymentSchema, 'remaining_amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'SC-P3-013 remaining amount is fixed precision');
sc_settle_assert(str_contains($paymentSchema, "status varchar(32) NOT NULL DEFAULT 'upcoming'"), 'SC-P3-013 payment lifecycle state is persisted');
sc_settle_assert(str_contains($paymentSchema, 'UNIQUE KEY contract_sequence (contract_id, sequence_no)'), 'SC-P3-013 duplicate sequence per contract is prevented');
sc_settle_assert(str_contains($paymentSchema, 'KEY contract_status_due (contract_id, status, due_date)'), 'SC-P3-013 contract/status/due reads are indexed');

sc_settle_assert(ContractMoney::add('0.1000', '0.2000') === '0.3000', 'SC-P3-011 settlement addition is exact without float drift');
sc_settle_assert(ContractMoney::subtract('500', '125.5') === '374.5000', 'SC-P3-011 settlement subtraction preserves four decimals');
sc_settle_assert(ContractMoney::compare('374.5000', '374.5') === 0, 'SC-P3-011 money comparison normalizes fixed precision');
sc_settle_assert(ContractMoney::difference('500', '501') === '-1.0000', 'SC-P3-012 reconciliation exposes negative over-collection variance');
sc_settle_expect(InvalidArgumentException::class, fn () => ContractMoney::subtract('1', '1.0001'), 'SC-P3-011 negative remaining balances are rejected');

$collections = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_COLLECTIONS => true,
];

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment()],
    [['id' => '2']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9001;
$beforePartial = count($GLOBALS['sc_test_queries']);
$partialId = $collections->record([
    'payment_id' => 7001,
    'amount' => '125.5',
    'collection_date' => '2026-08-15',
    'payment_method_id' => 2,
]);
sc_settle_assert($partialId === 9001, 'SC-P3-009 partial collection returns ledger ID');
$partialMutations = sc_settle_mutations_since($beforePartial);
sc_settle_assert($partialMutations[0] === 'START TRANSACTION', 'SC-P3-011 settlement starts an explicit transaction');
sc_settle_assert(end($partialMutations) === 'COMMIT', 'SC-P3-011 successful settlement commits transaction');
$partialSql = implode("\n", $partialMutations);
sc_settle_assert(str_contains($partialSql, 'INSERT INTO wp_safecontracts_payment_collections'), 'SC-P3-009 partial collection appends ledger row');
sc_settle_assert(str_contains($partialSql, "paid_amount = '125.5000'"), 'SC-P3-009 partial collection updates cumulative paid amount');
sc_settle_assert(str_contains($partialSql, "remaining_amount = '374.5000'"), 'SC-P3-009 partial collection reduces remaining balance exactly');
sc_settle_assert(str_contains($partialSql, "status = 'partially_paid'"), 'SC-P3-009 partial collection moves payment to partially paid');
sc_settle_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_settled']), 'SC-P3-009 settlement emits payment settlement event');

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount' => '125.5000',
        'remaining_amount' => '374.5000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id' => '2']],
    [['total' => '125.5000']],
];
$GLOBALS['wpdb']->insert_id = 9002;
$beforeFull = count($GLOBALS['sc_test_queries']);
$fullId = $collections->record([
    'payment_id' => 7001,
    'amount' => '374.5',
    'collection_date' => '2026-08-16',
    'payment_method_id' => 2,
]);
sc_settle_assert($fullId === 9002, 'SC-P3-010 full settlement returns second ledger ID');
$fullSql = implode("\n", sc_settle_mutations_since($beforeFull));
sc_settle_assert(str_contains($fullSql, "paid_amount = '500.0000'"), 'SC-P3-010 full settlement reaches original amount exactly');
sc_settle_assert(str_contains($fullSql, "remaining_amount = '0.0000'"), 'SC-P3-010 full settlement zeroes remaining balance');
sc_settle_assert(str_contains($fullSql, "status = 'paid'"), 'SC-P3-010 full settlement moves payment to paid');
sc_settle_assert(str_ends_with(trim($fullSql), 'COMMIT'), 'SC-P3-010 full settlement commits atomically');

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount' => '125.5000',
        'remaining_amount' => '374.5000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id' => '2']],
    [['total' => '125.5000']],
];
$beforeOver = count($GLOBALS['sc_test_queries']);
sc_settle_expect(
    DomainException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '374.5001',
        'collection_date' => '2026-08-16',
        'payment_method_id' => 2,
    ]),
    'SC-P3-011 amount above remaining balance is rejected'
);
$overMutations = sc_settle_mutations_since($beforeOver);
sc_settle_assert($overMutations === ['START TRANSACTION', 'ROLLBACK'], 'SC-P3-011 over-collection writes neither ledger nor payment balances');

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount' => '100.0000',
        'remaining_amount' => '400.0000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id' => '2']],
    [['total' => '125.5000']],
];
$beforeMismatch = count($GLOBALS['sc_test_queries']);
sc_settle_expect(
    DomainException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '1',
        'collection_date' => '2026-08-16',
        'payment_method_id' => 2,
    ]),
    'SC-P3-011 stored paid amount must reconcile with ledger before mutation'
);
sc_settle_assert(sc_settle_mutations_since($beforeMismatch) === ['START TRANSACTION', 'ROLLBACK'], 'SC-P3-011 integrity mismatch rolls back cleanly');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount' => '125.5000',
        'remaining_amount' => '374.5000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['total' => '125.5000']],
];
$reconciliation = $collections->reconcilePayment(7001);
sc_settle_assert($reconciliation['original_amount'] === '500.0000', 'SC-P3-012 reconciliation exposes original amount');
sc_settle_assert($reconciliation['ledger_collected'] === '125.5000', 'SC-P3-012 reconciliation exposes collection-ledger total');
sc_settle_assert($reconciliation['expected_remaining_amount'] === '374.5000', 'SC-P3-012 reconciliation computes expected remaining balance');
sc_settle_assert($reconciliation['expected_financial_status'] === PaymentStatus::PARTIALLY_PAID, 'SC-P3-012 reconciliation computes expected partial status');
sc_settle_assert($reconciliation['is_balanced'] === true, 'SC-P3-012 matching ledger and stored balances reconcile cleanly');

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount' => '100.0000',
        'remaining_amount' => '400.0000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['total' => '125.5000']],
];
$mismatch = $collections->reconcilePayment(7001);
sc_settle_assert($mismatch['is_balanced'] === false, 'SC-P3-012 reconciliation flags stored/ledger mismatch');
sc_settle_assert($mismatch['ledger_collected'] === '125.5000' && $mismatch['stored_paid_amount'] === '100.0000', 'SC-P3-012 mismatch remains transparent in output');

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount' => '500.0000',
        'remaining_amount' => '0.0000',
        'status' => PaymentStatus::PAID,
    ])],
    [['total' => '501.0000']],
];
$overReconciliation = $collections->reconcilePayment(7001);
sc_settle_assert($overReconciliation['over_collected'] === true, 'SC-P3-012 reconciliation explicitly flags ledger over-collection');
sc_settle_assert($overReconciliation['expected_remaining_amount'] === '-1.0000', 'SC-P3-012 over-collection variance is transparent');
sc_settle_assert($overReconciliation['is_balanced'] === false, 'SC-P3-012 over-collected payment cannot report balanced');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[sc_settle_payment(['accountant_user_id' => '99'])]];
sc_settle_expect(DomainException::class, fn () => $collections->reconcilePayment(7001), 'SC-P3-012 reconciliation respects Accountant assigned scope');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_settle_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'SC-P3-013 validation confirms schema migrations remain idempotent');

echo "SafeContracts P3 settlement/reconciliation tests SC-P3-009..013 passed ({$settlementTests} assertions).\n";
