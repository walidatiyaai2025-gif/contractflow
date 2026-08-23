<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\FeatureArabicDefaults;

$tests = 0;

function sc_622_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_622_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_622_assert($error instanceof $class, $message . ' (' . get_class($error) . ')');
        return;
    }
    sc_622_assert(false, $message . ' (no exception)');
}

function sc_622_payment(array $overrides = []): array
{
    return array_merge([
        'id' => '6221',
        'contract_id' => '6220',
        'financial_direction' => 'receivable',
        'currency_code' => 'USD',
        'sequence_no' => '1',
        'reference' => 'AR-1',
        'due_date' => '2026-09-15',
        'expected_payment_date' => null,
        'original_amount' => '1000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '1000.0000',
        'status' => PaymentStatus::UPCOMING,
        'is_archived' => '0',
        'accountant_user_id' => '42',
        'contract_is_archived' => '0',
        'counterparty_type' => 'customer',
        'counterparty_id' => '100',
    ], $overrides);
}

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_PAYMENTS => true,
];

$service = new PaymentService();
$GLOBALS['sc_test_result_queue'] = [[sc_622_payment()]];
$before = count($GLOBALS['sc_test_queries']);
$service->updateEditable(6221, [
    'reference' => 'AR-UPDATED',
    'due_date' => '2026-09-20',
    'expected_payment_date' => '2026-09-18',
    'original_amount' => '1200',
]);
$editSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $before));
sc_622_assert(str_contains($editSql, "reference = 'AR-UPDATED'"), 'unsettled payment reference is editable');
sc_622_assert(str_contains($editSql, "due_date = '2026-09-20'") && str_contains($editSql, "expected_payment_date = '2026-09-18'"), 'payment dates are editable together');
sc_622_assert(substr_count($editSql, "'1200.0000'") === 2, 'unsettled amount edit updates original and remaining balances together');
sc_622_assert(! str_contains($editSql, 'financial_direction =') && ! str_contains($editSql, 'currency_code ='), 'payment edit cannot mutate inherited direction or currency');
sc_622_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_details_changed']), 'payment detail edit emits auditable domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_622_payment([
    'financial_direction' => 'payable',
    'currency_code' => 'KWD',
    'reference' => 'AP-1',
    'original_amount' => '1000.0000',
    'paid_amount' => '250.0000',
    'remaining_amount' => '750.0000',
    'status' => PaymentStatus::PARTIALLY_PAID,
    'counterparty_type' => 'supplier',
])]];
$before = count($GLOBALS['sc_test_queries']);
sc_622_expect(DomainException::class, fn () => $service->updateEditable(6221, [
    'reference' => 'AP-2',
    'due_date' => '2026-09-20',
    'expected_payment_date' => null,
    'original_amount' => '1100',
]), 'settled payable amount cannot be rewritten');
sc_622_assert(count($GLOBALS['sc_test_queries']) === $before, 'rejected settled amount edit performs no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_622_payment([
    'financial_direction' => 'payable',
    'currency_code' => 'KWD',
    'reference' => 'AP-1',
    'original_amount' => '1000.0000',
    'paid_amount' => '250.0000',
    'remaining_amount' => '750.0000',
    'status' => PaymentStatus::PARTIALLY_PAID,
    'counterparty_type' => 'supplier',
])]];
$service->updateEditable(6221, [
    'reference' => 'AP-UPDATED',
    'due_date' => '2026-10-01',
    'expected_payment_date' => '2026-09-28',
    'original_amount' => '1000',
]);
$partialSql = (string) end($GLOBALS['sc_test_queries']);
sc_622_assert(str_contains($partialSql, "reference = 'AP-UPDATED'") && str_contains($partialSql, "remaining_amount = '750.0000'"), 'settled payable keeps reconciled balances while reference/dates change');

$paymentsPage = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/PaymentsPage.php');
sc_622_assert(str_contains($paymentsPage, "__('Contract filter', 'safecontracts')") && str_contains($paymentsPage, "name=\"contract_id\""), 'Payments admin exposes a contract filter above the grid');
sc_622_assert(str_contains($paymentsPage, "__('Contract summary', 'safecontracts')") && str_contains($paymentsPage, "__('Outstanding total', 'safecontracts')"), 'Payments admin renders selected-contract summary cards');
sc_622_assert(str_contains($paymentsPage, "__('Edit payment', 'safecontracts')"), 'Payments grid exposes an explicit edit action');
sc_622_assert(str_contains($paymentsPage, "Accounts Payable · we will pay it") && str_contains($paymentsPage, "Accounts Receivable · will be paid to us"), 'payment UI distinguishes outgoing payable from incoming receivable');

$contractsPage = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/ContractsPage.php');
sc_622_assert(str_contains($contractsPage, "__('Scheduled total', 'safecontracts')") && str_contains($contractsPage, 'scheduledTotalsForContracts'), 'Contracts grid shows the scheduled payment total for each visible contract');
sc_622_assert(str_contains($contractsPage, "__('Add payment', 'safecontracts')") && str_contains($contractsPage, 'PaymentsPage::SLUG') && str_contains($contractsPage, "'contract_id' => $contractId"), 'Contracts grid links directly to a preselected payment-create flow');
sc_622_assert(str_contains($contractsPage, 'safecontracts-payment-action--') && str_contains($contractsPage, 'safecontracts-direction-pill--'), 'Contracts grid applies directional styling hooks to receivable/payable actions');

$paymentRepo = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php');
sc_622_assert(str_contains($paymentRepo, 'scheduledTotalsForContracts') && str_contains($paymentRepo, 'SUM(original_amount)') && str_contains($paymentRepo, 'is_archived = 0'), 'scheduled totals aggregate only active scheduled-payment rows');
$adminCss = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin.css');
sc_622_assert(str_contains($adminCss, '.safecontracts-direction-pill--receivable') && str_contains($adminCss, '.safecontracts-direction-pill--payable'), 'direction badges distinguish receivable green from payable red');
sc_622_assert(str_contains($adminCss, '.safecontracts-payment-action--receivable') && str_contains($adminCss, '.safecontracts-payment-action--payable'), 'add-payment shortcut follows the same green/red accounting direction');

$contractRepo = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Contracts/ContractRepository.php');
sc_622_assert(str_contains($contractRepo, 'ContractStatus::ACTIVE') && str_contains($contractRepo, 'Contract base value must be greater than zero.'), 'new contracts are inserted active only with positive base value');
$rest = (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Rest/CounterpartyContractsController.php');
sc_622_assert(str_contains($rest, "'base_value'") && str_contains($rest, 'Contract base value is required.'), 'REST contract creation requires base value');
sc_622_assert(FeatureArabicDefaults::default('Accounts Payable · we will pay it') === 'مديونية علينا · سندفعها', 'Arabic payable direction is explicit');
sc_622_assert(FeatureArabicDefaults::default('Accounts Receivable · will be paid to us') === 'مستحق لنا · سيتم دفعه لنا', 'Arabic receivable direction is explicit');
sc_622_assert(FeatureArabicDefaults::default('Add payment') === 'إضافة دفعة', 'Arabic add-payment shortcut is explicit');

echo "SafeContracts contract/payment directional edit regression passed ({$tests} assertions).\n";
