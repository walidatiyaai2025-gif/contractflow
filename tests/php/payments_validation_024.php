<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p324_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function sc_p324_expect(string $class, callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { sc_p324_assert($e instanceof $class, $message); return; } sc_p324_assert(false, $message); }
function sc_p324_payment(array $overrides = []): array { return array_merge([
    'id'=>'7001','contract_id'=>'501','sequence_no'=>'1','reference'=>'P-1','due_date'=>'2026-08-20','expected_payment_date'=>null,
    'original_amount'=>'500.0000','paid_amount'=>'125.5000','remaining_amount'=>'374.5000','status'=>PaymentStatus::PARTIALLY_PAID,
    'accountant_user_id'=>'42','contract_is_archived'=>'0',
], $overrides); }

$collections = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p324_payment()], [['total'=>'125.5000']]];
$balanced = $collections->reconcilePayment(7001);
sc_p324_assert($balanced['ledger_collected'] === '125.5000', 'SC-P3-024 exposes authoritative ledger total');
sc_p324_assert($balanced['expected_remaining_amount'] === '374.5000', 'SC-P3-024 calculates exact remaining amount');
sc_p324_assert($balanced['expected_financial_status'] === PaymentStatus::PARTIALLY_PAID, 'SC-P3-024 calculates expected financial state');
sc_p324_assert($balanced['is_balanced'] === true, 'SC-P3-024 balanced payment is reported balanced');

$GLOBALS['sc_test_result_queue'] = [[sc_p324_payment(['paid_amount'=>'100.0000','remaining_amount'=>'400.0000'])], [['total'=>'125.5000']]];
$mismatch = $collections->reconcilePayment(7001);
sc_p324_assert($mismatch['is_balanced'] === false, 'SC-P3-024 cache/ledger drift is visible');
sc_p324_assert($mismatch['stored_paid_amount'] === '100.0000' && $mismatch['ledger_collected'] === '125.5000', 'SC-P3-024 never hides reconciliation variance');

$GLOBALS['sc_test_result_queue'] = [[sc_p324_payment(['paid_amount'=>'500.0000','remaining_amount'=>'0.0000','status'=>PaymentStatus::PAID])], [['total'=>'501.0000']]];
$over = $collections->reconcilePayment(7001);
sc_p324_assert($over['over_collected'] === true && $over['expected_remaining_amount'] === '-1.0000', 'SC-P3-024 exposes over-collection explicitly');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ASSIGNED=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_p324_payment(['accountant_user_id'=>'99'])]];
sc_p324_expect(DomainException::class, fn () => $collections->reconcilePayment(7001), 'SC-P3-024 reconciliation enforces Accountant scope');

echo "SafeContracts P3 financial reconciliation validation SC-P3-024 passed ({$tests} assertions).\n";
