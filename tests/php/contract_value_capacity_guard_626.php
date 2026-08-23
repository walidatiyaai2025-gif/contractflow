<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$tests = 0;

function sc_626_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_626_expect_domain(callable $callback, string $needle, string $message): void
{
    try {
        $callback();
    } catch (DomainException $error) {
        sc_626_assert(str_contains($error->getMessage(), $needle), $message . ' (message)');
        return;
    }
    sc_626_assert(false, $message . ' (no DomainException)');
}

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_PAYMENTS => true,
    Capabilities::MANAGE_COLLECTIONS => true,
    Capabilities::MANAGE_FINANCE => true,
];

$payments = new PaymentService();
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '6260',
    'accountant_user_id' => null,
    'is_archived' => '0',
    'counterparty_type' => 'customer',
    'counterparty_id' => '100',
    'financial_direction' => FinancialDirection::RECEIVABLE,
    'currency_code' => 'KWD',
    'base_value' => '100.0000',
    'scheduled_total' => '80.0000',
]]];
sc_626_expect_domain(
    fn () => $payments->create([
        'contract_id' => 6260,
        'reference' => 'Would exceed contract',
        'due_date' => '2026-09-01',
        'expected_payment_date' => '',
        'original_amount' => '25',
    ]),
    'maximum additional amount: 20.0000',
    'new scheduled payment is blocked when aggregate scheduled value would exceed contract value'
);

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '6261',
    'contract_id' => '6260',
    'financial_direction' => FinancialDirection::RECEIVABLE,
    'currency_code' => 'KWD',
    'sequence_no' => '1',
    'reference' => 'Editable payment',
    'due_date' => '2026-09-01',
    'expected_payment_date' => null,
    'original_amount' => '40.0000',
    'paid_amount' => '0.0000',
    'remaining_amount' => '40.0000',
    'status' => PaymentStatus::UPCOMING,
    'is_archived' => '0',
    'accountant_user_id' => null,
    'contract_is_archived' => '0',
    'counterparty_type' => 'customer',
    'counterparty_id' => '100',
    'contract_base_value' => '100.0000',
    'contract_scheduled_total' => '90.0000',
]]];
sc_626_expect_domain(
    fn () => $payments->updateEditable(6261, [
        'reference' => 'Still editable',
        'due_date' => '2026-09-02',
        'expected_payment_date' => null,
        'original_amount' => '60',
    ]),
    'maximum value for this payment: 50.0000',
    'payment amount edit is blocked when other scheduled payments plus edited value would exceed contract value'
);

$collections = new CollectionService();
$GLOBALS['sc_test_result_queue'] = [
    [[
        'id' => '6262',
        'contract_id' => '6260',
        'financial_direction' => FinancialDirection::RECEIVABLE,
        'currency_code' => 'KWD',
        'original_amount' => '60.0000',
        'paid_amount' => '20.0000',
        'remaining_amount' => '40.0000',
        'status' => PaymentStatus::PARTIALLY_PAID,
        'is_archived' => '0',
        'accountant_user_id' => null,
        'contract_is_archived' => '0',
        'contract_base_value' => '100.0000',
        'contract_settled_total' => '90.0000',
    ]],
    [['id' => '1']],
    [['total' => '20.0000']],
];
sc_626_expect_domain(
    fn () => $collections->record([
        'payment_id' => 6262,
        'amount' => '15',
        'collection_date' => '2026-08-23',
        'payment_method_id' => 1,
        'reference' => 'Contract cap',
        'details' => '',
        'proof_media_id' => null,
    ]),
    'maximum additional settlement: 10.0000',
    'collection or supplier settlement is blocked when contract-wide settled total would exceed contract value'
);

$paymentRepository = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php');
$collectionRepository = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Collections/CollectionRepository.php');
$arabic = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Translations/FeatureArabicDefaults.php');

sc_626_assert(str_contains($paymentRepository, 'scheduled_total') && str_contains($paymentRepository, 'c.base_value'), 'payment repository reads contract value and aggregate scheduled value in the same production context query');
sc_626_assert(str_contains($collectionRepository, 'contract_settled_total') && str_contains($collectionRepository, 'FOR UPDATE'), 'settlement repository carries contract-wide settled value through the locked payment context');
sc_626_assert(str_contains($arabic, 'إجمالي الدفعات المجدولة يتجاوز قيمة العقد') && str_contains($arabic, 'لا يمكن أن يتجاوز المتبقي في الدفعة أو قيمة العقد'), 'Arabic admin validation explains the contract and payment limits clearly');
sc_626_assert(defined('SAFECONTRACTS_VERSION') && version_compare((string) SAFECONTRACTS_VERSION, '0.2.3', '>='), 'plugin version remains at or above the contract-capacity guard release');

echo "SafeContracts contract value capacity guard regression passed ({$tests} assertions).\n";