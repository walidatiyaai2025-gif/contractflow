<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\MobileOperationsController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p9_bridge_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

SafeContracts\Plugin::instance()->boot();
Router::register();

foreach ([
    Router::NAMESPACE . '/contracts/(?P<id>\d+)/light-edit',
    Router::NAMESPACE . '/payments/(?P<id>\d+)/light-edit',
    Router::NAMESPACE . '/collections',
] as $route) {
    $definition = $GLOBALS['sc_test_routes'][$route] ?? [];
    sc_p9_bridge_assert(($definition['methods'] ?? null) === WP_REST_Server::CREATABLE, "{$route} is POST-only");
    sc_p9_bridge_assert(isset($definition['permission_callback']), "{$route} has a permission callback");
}

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
sc_p9_bridge_assert(MobileOperationsController::canEditContract() instanceof WP_Error, 'contract edit requires EDIT_CONTRACTS');
sc_p9_bridge_assert(MobileOperationsController::canEditPayment() instanceof WP_Error, 'payment edit requires MANAGE_PAYMENTS');
sc_p9_bridge_assert(MobileOperationsController::canRecordCollection() instanceof WP_Error, 'collection create requires MANAGE_COLLECTIONS');

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = true;
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '11', 'contract_number' => 'SC-11', 'customer_id' => '7', 'accountant_user_id' => '42',
    'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'base_value' => '100.0000',
    'notes' => '', 'is_archived' => '0',
]]];
$contractEdit = MobileOperationsController::editContract(new WP_REST_Request([
    'id' => '11', 'contract_number' => 'SC-11A',
]));
sc_p9_bridge_assert($contractEdit instanceof WP_REST_Response && $contractEdit->status === 200, 'contract light edit delegates successfully');
sc_p9_bridge_assert(($contractEdit->data['data']['contract_id'] ?? 0) === 11, 'contract light edit returns stable ID');
sc_p9_bridge_assert(str_contains(implode("\n", $GLOBALS['sc_test_queries']), "contract_number = 'SC-11A'"), 'contract light edit persists through ContractService repository path');

$badContractEdit = MobileOperationsController::editContract(new WP_REST_Request([
    'id' => '11', 'contract_number' => 'SC-11B', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
]));
sc_p9_bridge_assert($badContractEdit instanceof WP_Error && ($badContractEdit->data['status'] ?? 0) === 422, 'mixed contract light-edit operations fail closed');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_PAYMENTS] = true;
$paymentRow = [
    'id' => '21', 'contract_id' => '11', 'sequence_no' => '1', 'reference' => 'P-1', 'due_date' => '2026-08-10',
    'expected_payment_date' => null, 'original_amount' => '100.0000', 'paid_amount' => '0.0000', 'remaining_amount' => '100.0000',
    'status' => 'due', 'accountant_user_id' => '42', 'contract_is_archived' => '0',
];
$GLOBALS['sc_test_result_queue'] = [[$paymentRow], [$paymentRow]];
$paymentEdit = MobileOperationsController::editPayment(new WP_REST_Request([
    'id' => '21', 'expected_payment_date' => '2026-08-20',
]));
sc_p9_bridge_assert($paymentEdit instanceof WP_REST_Response && $paymentEdit->status === 200, 'payment light edit delegates successfully');
sc_p9_bridge_assert(($paymentEdit->data['data']['due_date'] ?? '') === '2026-08-10', 'payment light edit preserves contractual due date');
sc_p9_bridge_assert(($paymentEdit->data['data']['expected_payment_date'] ?? '') === '2026-08-20', 'payment light edit updates only expected date contract');

$GLOBALS['sc_test_current_caps'][Capabilities::MANAGE_COLLECTIONS] = true;
$badCollection = MobileOperationsController::recordCollection(new WP_REST_Request([
    'payment_id' => '21', 'amount' => '10.0000', 'collection_date' => '2026-08-15',
]));
sc_p9_bridge_assert($badCollection instanceof WP_Error && ($badCollection->data['status'] ?? 0) === 422, 'collection bridge keeps payment method mandatory');

$unknownField = MobileOperationsController::recordCollection(new WP_REST_Request([
    'payment_id' => '21', 'amount' => '10.0000', 'collection_date' => '2026-08-15', 'payment_method_id' => '1',
    'override_remaining_balance' => '0',
]));
sc_p9_bridge_assert($unknownField instanceof WP_Error && ($unknownField->data['status'] ?? 0) === 422, 'collection bridge rejects unknown financial override fields');

$source = file_get_contents((string) (new ReflectionClass(MobileOperationsController::class))->getFileName()) ?: '';
sc_p9_bridge_assert(str_contains($source, 'new ContractService()'), 'REST bridge delegates contract business logic');
sc_p9_bridge_assert(str_contains($source, 'new PaymentService()'), 'REST bridge delegates payment business logic');
sc_p9_bridge_assert(str_contains($source, 'new CollectionService()'), 'REST bridge delegates collection business logic');
sc_p9_bridge_assert(! str_contains($source, '$wpdb'), 'REST bridge contains no direct SQL');

printf("SafeContracts P9 mobile operations REST bridge passed (%d assertions).\n", $tests);
