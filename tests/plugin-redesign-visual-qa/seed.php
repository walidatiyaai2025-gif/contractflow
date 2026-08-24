<?php

use SafeContracts\Contracts\ContractService;
use SafeContracts\Customers\CustomerService;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Suppliers\SupplierService;

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Visual QA fixtures must run through wp eval-file.\n");
    exit(1);
}

$admin = get_user_by('login', 'visual-admin');
if (! $admin instanceof WP_User) {
    throw new RuntimeException('visual-admin fixture user does not exist.');
}
wp_set_current_user((int) $admin->ID);

update_option('blogname', 'ALKENZY ADV Visual QA', true);
update_option('timezone_string', 'Asia/Kuwait', true);
update_option('safecontracts_business_name', 'ALKENZY ADV QA Company', false);
update_option('safecontracts_currency', 'KWD', false);
update_option('safecontracts_currency_symbol', 'د.ك', false);
update_option('safecontracts_admin_page_size', 20, false);

// Secondary real WordPress users give access/role screens deterministic rows.
$fixtureUsers = [
    ['visual-manager', 'visual-manager@example.test', 'safecontracts_manager'],
    ['visual-accountant', 'visual-accountant@example.test', 'safecontracts_accountant'],
    ['visual-viewer', 'visual-viewer@example.test', 'safecontracts_viewer'],
];
foreach ($fixtureUsers as [$login, $email, $role]) {
    $user = get_user_by('login', $login);
    if (! $user instanceof WP_User) {
        $userId = wp_create_user($login, 'VisualQa-Only-2026!', $email);
        if (is_wp_error($userId)) {
            throw new RuntimeException($userId->get_error_message());
        }
        $user = get_user_by('id', (int) $userId);
    }
    if ($user instanceof WP_User) {
        $user->set_role($role);
    }
}

// Use production services and authorization paths rather than inserting mock
// rows directly. This makes screenshots exercise real migrations/domain code.
$customerService = new CustomerService();
$customerId = $customerService->save([
    'name' => 'Kuwait Horizon Trading',
    'internal_code' => 'QA-CUST-001',
    'contact_name' => 'Mona Hassan',
    'email' => 'accounts@horizon.example.test',
    'phone' => '+965 5555 0101',
    'notes' => 'Deterministic visual acceptance fixture.',
    'is_active' => true,
]);

$supplierService = new SupplierService();
$supplierId = $supplierService->save([
    'legal_name' => 'Gulf Premium Supplies W.L.L.',
    'trading_name' => 'Gulf Premium',
    'internal_code' => 'QA-SUP-001',
    'contact_name' => 'Omar Saleh',
    'email' => 'finance@gulfpremium.example.test',
    'phone' => '+965 5555 0202',
    'country_code' => 'KW',
    'default_currency' => 'KWD',
    'payment_terms' => '30 days',
    'notes' => 'Deterministic visual acceptance fixture.',
    'status' => 'active',
]);

$contractService = new ContractService();
$receivableContractId = $contractService->create([
    'contract_number' => 'QA-AR-2026-001',
    'counterparty_type' => 'customer',
    'counterparty_id' => $customerId,
    'currency_code' => 'KWD',
    'base_value' => '12500',
    'notes' => 'Annual services — receivable fixture.',
]);
$contractService->updateDates($receivableContractId, '2026-01-01', '2026-12-31');

$payableContractId = $contractService->create([
    'contract_number' => 'QA-AP-2026-001',
    'counterparty_type' => 'supplier',
    'counterparty_id' => $supplierId,
    'currency_code' => 'KWD',
    'base_value' => '8000',
    'notes' => 'Procurement services — payable fixture.',
]);
$contractService->updateDates($payableContractId, '2026-02-01', '2026-11-30');

$paymentService = new PaymentService();
$paymentService->create([
    'contract_id' => $receivableContractId,
    'reference' => 'QA-AR-INSTALLMENT-1',
    'due_date' => '2026-08-15',
    'expected_payment_date' => '2026-08-18',
    'original_amount' => '2500',
]);
$paymentService->create([
    'contract_id' => $receivableContractId,
    'reference' => 'QA-AR-INSTALLMENT-2',
    'due_date' => '2026-09-15',
    'expected_payment_date' => '2026-09-15',
    'original_amount' => '2500',
]);
$paymentService->create([
    'contract_id' => $payableContractId,
    'reference' => 'QA-AP-INSTALLMENT-1',
    'due_date' => '2026-08-20',
    'expected_payment_date' => '2026-08-20',
    'original_amount' => '2000',
]);

fwrite(STDOUT, sprintf(
    "Plugin redesign visual QA fixtures ready: customer=%d supplier=%d AR-contract=%d AP-contract=%d.\n",
    $customerId,
    $supplierId,
    $receivableContractId,
    $payableContractId
));
