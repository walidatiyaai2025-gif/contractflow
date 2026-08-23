<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\PaymentsPage;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\FinancialDirection;

$tests = 0;

function sc_625_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$payments = [
    ['financial_direction' => FinancialDirection::RECEIVABLE, 'currency_code' => 'EGP', 'original_amount' => '100.0000', 'paid_amount' => '30.0000', 'remaining_amount' => '70.0000'],
    ['financial_direction' => FinancialDirection::RECEIVABLE, 'currency_code' => 'EGP', 'original_amount' => '50.0000', 'paid_amount' => '0.0000', 'remaining_amount' => '50.0000'],
    ['financial_direction' => FinancialDirection::PAYABLE, 'currency_code' => 'EGP', 'original_amount' => '40.0000', 'paid_amount' => '10.0000', 'remaining_amount' => '30.0000'],
    ['financial_direction' => FinancialDirection::PAYABLE, 'currency_code' => 'USD', 'original_amount' => '25.0000', 'paid_amount' => '5.0000', 'remaining_amount' => '20.0000'],
];

$summaryMethod = new ReflectionMethod(PaymentsPage::class, 'paymentSummary');
$summaryMethod->setAccessible(true);
/** @var array<string,mixed> $summary */
$summary = $summaryMethod->invoke(null, $payments);

sc_625_assert($summary['EGP'][FinancialDirection::RECEIVABLE]['count'] === 2, 'receivable payment count is aggregated from payment rows');
sc_625_assert($summary['EGP'][FinancialDirection::RECEIVABLE]['original'] === '150.0000', 'receivable original payment values are summed');
sc_625_assert($summary['EGP'][FinancialDirection::PAYABLE]['original'] === '40.0000', 'payable original payment values are summed separately');
sc_625_assert(ContractMoney::difference($summary['EGP'][FinancialDirection::RECEIVABLE]['original'], $summary['EGP'][FinancialDirection::PAYABLE]['original']) === '110.0000', 'net EGP payment value equals receivable minus payable');
sc_625_assert(ContractMoney::difference($summary['EGP'][FinancialDirection::RECEIVABLE]['remaining'], $summary['EGP'][FinancialDirection::PAYABLE]['remaining']) === '90.0000', 'net EGP remaining balance equals receivable remaining minus payable remaining');
sc_625_assert($summary['USD'][FinancialDirection::PAYABLE]['original'] === '25.0000', 'different currencies are kept in independent buckets');

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/PaymentsPage.php');
sc_625_assert(str_contains($source, "self::label('Net payment value', 'صافي قيمة الدفعات')"), 'Payments page exposes a net payment-value card');
sc_625_assert(str_contains($source, 'ContractMoney::difference($r[\'original\'], $p[\'original\'])'), 'net payment card subtracts payable payment values from receivable payment values');
sc_625_assert(str_contains($source, 'ContractMoney::difference($r[\'remaining\'], $p[\'remaining\'])'), 'payment summary also subtracts remaining balances');
sc_625_assert(str_contains($source, 'directionMoney($contractTotals[\'scheduled\']'), 'selected-contract scheduled total is direction-signed');

$plugin = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php');
sc_625_assert(str_contains($plugin, 'Version: 0.2.3') && str_contains($plugin, "SAFECONTRACTS_VERSION', '0.2.3'"), 'plugin version is bumped so new payment-summary assets and financial guards are cache-busted');

echo "SafeContracts payment summary arithmetic regression passed ({$tests} assertions).\n";