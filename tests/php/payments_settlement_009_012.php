<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_settle_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function sc_settle_expect(string $class, callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { sc_settle_assert($e instanceof $class, $message); return; } sc_settle_assert(false, $message); }
function sc_settle_payment(array $overrides = []): array { return array_merge([
    'id'=>'7001','contract_id'=>'501','sequence_no'=>'1','reference'=>'P-001','due_date'=>'2026-08-25',
    'expected_payment_date'=>null,'original_amount'=>'500.0000','paid_amount'=>'0.0000','remaining_amount'=>'500.0000',
    'status'=>PaymentStatus::DUE_SOON,'accountant_user_id'=>'42','contract_is_archived'=>'0',
], $overrides); }

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_settle_assert(is_callable($activate), 'plugin activation hook is available');
$activate();

sc_settle_assert(ContractMoney::compareNonNegative('10.0000', '9.9999') > 0, 'SC-P3-011 exact money comparison avoids floating point');
sc_settle_assert(ContractMoney::compareNonNegative('10', '10.0000') === 0, 'SC-P3-011 normalized money comparison treats equivalent values as equal');
sc_settle_assert(ContractMoney::subtractNonNegative('500', '125.5') === '374.5000', 'SC-P3-011 exact remaining-balance subtraction works');
sc_settle_expect(InvalidArgumentException::class, fn () => ContractMoney::subtractNonNegative('1', '2'), 'SC-P3-011 negative remaining balance is rejected');

$service = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS=>true,
    Capabilities::VIEW_ALL=>true,
    Capabilities::MANAGE_COLLECTIONS=>true,
];

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment()],
    [['id'=>'2']],
    [['total'=>'0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9001;
$beforePartial = count($GLOBALS['sc_test_queries']);
$partialId = $service->record([
    'payment_id'=>7001,
    'amount'=>'125.5000',
    'collection_date'=>'2026-08-15',
    'payment_method_id'=>2,
]);
$partialMutations = array_slice($GLOBALS['sc_test_queries'], $beforePartial);
$partialSql = implode("\n", $partialMutations);
sc_settle_assert($partialId === 9001, 'SC-P3-009 partial collection returns ledger transaction ID');
sc_settle_assert($partialMutations[0] === 'START TRANSACTION' && end($partialMutations) === 'COMMIT', 'SC-P3-009 partial collection is atomic');
sc_settle_assert(str_contains($partialSql, "paid_amount = '125.5000'"), 'SC-P3-009 partial collection updates paid amount from ledger total');
sc_settle_assert(str_contains($partialSql, "remaining_amount = '374.5000'"), 'SC-P3-009 partial collection updates remaining amount exactly');
sc_settle_assert(str_contains($partialSql, "status = 'partially_paid'"), 'SC-P3-009 partial collection sets partially-paid financial state');
sc_settle_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_balance_changed']), 'SC-P3-009 balance change emits domain event');

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount'=>'125.5000',
        'remaining_amount'=>'374.5000',
        'status'=>PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id'=>'2']],
    [['total'=>'125.5000']],
];
$GLOBALS['wpdb']->insert_id = 9002;
$beforeFull = count($GLOBALS['sc_test_queries']);
$fullId = $service->record([
    'payment_id'=>7001,
    'amount'=>'374.5000',
    'collection_date'=>'2026-08-16',
    'payment_method_id'=>2,
]);
$fullSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeFull));
sc_settle_assert($fullId === 9002, 'SC-P3-010 full settlement returns ledger transaction ID');
sc_settle_assert(str_contains($fullSql, "paid_amount = '500.0000'"), 'SC-P3-010 full settlement paid amount equals original amount');
sc_settle_assert(str_contains($fullSql, "remaining_amount = '0.0000'"), 'SC-P3-010 full settlement clears remaining balance');
sc_settle_assert(str_contains($fullSql, "status = 'paid'"), 'SC-P3-010 full settlement sets paid status');

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount'=>'125.0000',
        'remaining_amount'=>'375.0000',
        'status'=>PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id'=>'1']],
    [['total'=>'125.0000']],
];
$beforeOver = count($GLOBALS['sc_test_queries']);
sc_settle_expect(DomainException::class, fn () => $service->record([
    'payment_id'=>7001,
    'amount'=>'375.0001',
    'collection_date'=>'2026-08-17',
    'payment_method_id'=>1,
]), 'SC-P3-011 over-collection is rejected before ledger insert');
$overMutations = array_slice($GLOBALS['sc_test_queries'], $beforeOver);
sc_settle_assert($overMutations === ['START TRANSACTION', 'ROLLBACK'], 'SC-P3-011 over-collection rolls back without changing ledger or balance');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount'=>'125.0000',
        'remaining_amount'=>'375.0000',
        'status'=>PaymentStatus::PARTIALLY_PAID,
    ])],
    [['total'=>'125.0000']],
];
$consistent = $service->reconcilePayment(7001);
sc_settle_assert($consistent['is_consistent'] === true, 'SC-P3-012 reconciliation accepts balance cache matching ledger');
sc_settle_assert($consistent['expected_paid_amount'] === '125.0000' && $consistent['expected_remaining_amount'] === '375.0000', 'SC-P3-012 reconciliation exposes derived financial values');
sc_settle_assert($consistent['expected_status'] === PaymentStatus::PARTIALLY_PAID, 'SC-P3-012 reconciliation derives partially-paid status');

$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount'=>'100.0000',
        'remaining_amount'=>'400.0000',
        'status'=>PaymentStatus::UPCOMING,
    ])],
    [['total'=>'125.0000']],
];
$drift = $service->reconcilePayment(7001);
sc_settle_assert($drift['is_consistent'] === false, 'SC-P3-012 reconciliation detects stored balance drift');
sc_settle_assert($drift['expected_paid_amount'] === '125.0000', 'SC-P3-012 ledger sum is authoritative for expected paid amount');
sc_settle_assert($drift['expected_remaining_amount'] === '375.0000', 'SC-P3-012 original minus ledger sum is authoritative remaining balance');
sc_settle_assert($drift['expected_status'] === PaymentStatus::PARTIALLY_PAID, 'SC-P3-012 reconciliation detects financial-state drift');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_COLLECTIONS=>true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment([
        'paid_amount'=>'100.0000',
        'remaining_amount'=>'400.0000',
        'status'=>PaymentStatus::UPCOMING,
    ])],
    [['total'=>'125.0000']],
];
$beforeRepair = count($GLOBALS['sc_test_queries']);
$repaired = $service->repairPaymentBalance(7001);
$repairMutations = array_slice($GLOBALS['sc_test_queries'], $beforeRepair);
$repairSql = implode("\n", $repairMutations);
sc_settle_assert($repaired === ['paid_amount'=>'125.0000','remaining_amount'=>'375.0000','status'=>PaymentStatus::PARTIALLY_PAID], 'SC-P3-012 repair returns reconciled values');
sc_settle_assert($repairMutations[0] === 'START TRANSACTION' && end($repairMutations) === 'COMMIT', 'SC-P3-012 repair is transaction-bounded');
sc_settle_assert(str_contains($repairSql, "paid_amount = '125.0000'") && str_contains($repairSql, "remaining_amount = '375.0000'"), 'SC-P3-012 repair persists ledger-derived balances');
sc_settle_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_balance_repaired']), 'SC-P3-012 repair emits auditable domain event');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment(['paid_amount'=>'500.0000','remaining_amount'=>'0.0000','status'=>PaymentStatus::PAID])],
    [['total'=>'600.0000']],
];
$overCollected = $service->reconcilePayment(7001);
sc_settle_assert($overCollected['over_collected'] === true, 'SC-P3-011 reconciliation flags historical over-collection');
sc_settle_assert($overCollected['is_consistent'] === false, 'SC-P3-011 over-collected ledger can never be reported consistent');
sc_settle_assert($overCollected['expected_remaining_amount'] === '0.0000', 'SC-P3-011 over-collection never creates negative remaining balance');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true, Capabilities::MANAGE_COLLECTIONS=>true];
$GLOBALS['sc_test_result_queue'] = [
    [sc_settle_payment(['paid_amount'=>'500.0000','remaining_amount'=>'0.0000','status'=>PaymentStatus::PAID])],
    [['total'=>'600.0000']],
];
$beforeUnsafeRepair = count($GLOBALS['sc_test_queries']);
sc_settle_expect(DomainException::class, fn () => $service->repairPaymentBalance(7001), 'SC-P3-012 automatic repair refuses over-collected ledger without reversal workflow');
sc_settle_assert(array_slice($GLOBALS['sc_test_queries'], $beforeUnsafeRepair) === ['START TRANSACTION', 'ROLLBACK'], 'SC-P3-012 unsafe reconciliation repair rolls back');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_settle_payment(['accountant_user_id'=>'99'])]];
sc_settle_expect(DomainException::class, fn () => $service->reconcilePayment(7001), 'SC-P3-012 reconciliation respects Accountant assignment scope');

echo "SafeContracts P3 settlement tests SC-P3-009..012 passed ({$tests} assertions).\n";
