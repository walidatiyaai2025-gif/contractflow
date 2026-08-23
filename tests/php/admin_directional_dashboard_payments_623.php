<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Translations\FeatureArabicDefaults;

$tests = 0;

function sc_623_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$dashboard = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/DashboardPage.php');
$payments = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/PaymentsPage.php');
$paymentService = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Payments/PaymentService.php');
$paymentRepository = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php');
$userGuide = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/UserGuidePage.php');
$css = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin.css');

sc_623_assert(str_contains($userGuide, "add_action('in_admin_footer'") && ! str_contains($userGuide, "add_action('admin_notices', [self::class, 'renderContextualHelp']"), 'contextual page guide is rendered in the admin footer instead of above page content');
sc_623_assert(str_contains($css, '.toplevel_page_safecontracts .safecontracts-summary-injector') && str_contains($css, '.safecontracts-user-guide-footer'), 'legacy dashboard help/summary area is removed from prime content while footer guide remains styled');

sc_623_assert(str_contains($dashboard, 'safecontracts-direction-dashboard') && str_contains($dashboard, 'FinancialDirection::RECEIVABLE') && str_contains($dashboard, 'FinancialDirection::PAYABLE'), 'dashboard has separate receivable and payable contract lanes');
sc_623_assert(str_contains($dashboard, "'counterparty_type' => \$type") && str_contains($dashboard, "'contract_id' => (int) (\$contract['id'] ?? 0)"), 'dashboard contract cards link to the Contracts page with direction/type and contract filters');
sc_623_assert(str_contains($dashboard, 'accountingByDirectionAndCurrency') && str_contains($dashboard, "__('Accounting totals by currency', 'safecontracts')"), 'dashboard builds direction-aware currency-separated accounting totals');
sc_623_assert(str_contains($dashboard, 'ContractMoney::add') && str_contains($dashboard, 'ContractMoney::normalizeNonNegative'), 'dashboard accounting aggregation uses decimal contract money arithmetic rather than floating-point accumulation');
sc_623_assert(str_contains($dashboard, "__('Collected from customers', 'safecontracts')") && str_contains($dashboard, "__('Paid to suppliers', 'safecontracts')"), 'dashboard clearly distinguishes incoming collections from outgoing supplier payments');

sc_623_assert(! str_contains($payments, 'name="sequence_no"'), 'payment scheduling does not ask the user for an internal sequence number');
sc_623_assert(str_contains($payments, "__('Payment description', 'safecontracts')") && str_contains($payments, "__('Description', 'safecontracts')"), 'payment reference UI is presented as a business description');
sc_623_assert(str_contains($payments, 'safecontracts-payment-panel--') && str_contains($payments, "__('Receivable payments · we will receive', 'safecontracts')") && str_contains($payments, "__('Payable payments · we will pay', 'safecontracts')"), 'Payments page renders separate green receivable and red payable sections');
sc_623_assert(str_contains($payments, 'paymentsForDirection') && str_contains($payments, 'FinancialDirection::RECEIVABLE') && str_contains($payments, 'FinancialDirection::PAYABLE'), 'payment rows are split by server-authoritative financial direction');

sc_623_assert(str_contains($paymentService, 'createAutoSequenced') && str_contains($paymentService, "\$sequenceNo = (int) (\$input['sequence_no'] ?? 0)"), 'PaymentService preserves explicit integration sequences while auto-sequencing admin creation');
sc_623_assert(str_contains($paymentRepository, 'nextSequenceNo') && str_contains($paymentRepository, 'MAX(sequence_no)') && str_contains($paymentRepository, "str_contains(\$lastError, 'duplicate')"), 'PaymentRepository allocates the next contract sequence and retries duplicate races');

sc_623_assert(FeatureArabicDefaults::default('Payment description') === 'وصف الدفعة', 'Arabic payment-description label is explicit');
sc_623_assert(FeatureArabicDefaults::default('Receivable payments · we will receive') === 'دفعات مستحقة لنا · سنستلمها', 'Arabic receivable lane explains that money is incoming');
sc_623_assert(FeatureArabicDefaults::default('Payable payments · we will pay') === 'دفعات مستحقة علينا · سندفعها', 'Arabic payable lane explains that money is outgoing');
sc_623_assert(FeatureArabicDefaults::default('Accounting totals by currency') === 'الإجماليات المحاسبية حسب العملة', 'Arabic accounting-total heading is available');

echo "SafeContracts directional dashboard/payment admin regression passed ({$tests} assertions).\n";
