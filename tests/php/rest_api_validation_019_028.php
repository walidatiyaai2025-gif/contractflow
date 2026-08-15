<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\DashboardController;
use SafeContracts\Rest\DataController;
use SafeContracts\Rest\ExcelExportController;
use SafeContracts\Rest\MobileConfigController;
use SafeContracts\Rest\PaymentMethodsController;
use SafeContracts\Rest\ReferenceDataController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p8v_final_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p8v_final_payment_row(int $accountantUserId = 42): array
{
    return [
        'id' => '21',
        'contract_id' => '11',
        'sequence_no' => '1',
        'reference' => 'P-1',
        'due_date' => '2026-08-10',
        'expected_payment_date' => '2026-08-20',
        'original_amount' => '100.0000',
        'paid_amount' => '60.0000',
        'remaining_amount' => '40.0000',
        'status' => 'overdue',
        'accountant_user_id' => (string) $accountantUserId,
        'contract_is_archived' => '0',
    ];
}

SafeContracts\Plugin::instance()->boot();
Router::register();

// SC-P8-019 — Authentication/session validation.
$GLOBALS['sc_test_current_caps'] = [];
$sessionDenied = Router::me(new WP_REST_Request());
sc_p8v_final_assert($sessionDenied instanceof WP_Error, 'SC-P8-019 direct protected session callback fails closed without access');
sc_p8v_final_assert(($sessionDenied->data['status'] ?? 0) === 403 && ($sessionDenied->data['api_version'] ?? '') === Router::API_VERSION, 'SC-P8-019 session denial keeps versioned 403 envelope');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$session = Router::me(new WP_REST_Request());
sc_p8v_final_assert($session instanceof WP_REST_Response && ($session->data['data']['authenticated'] ?? false) === true, 'SC-P8-019 authenticated session returns success envelope');
sc_p8v_final_assert(($session->data['data']['user_id'] ?? 0) === 42 && ($session->data['data']['scope'] ?? '') === 'assigned', 'SC-P8-019 session exposes current user and authoritative scope');
$sessionJson = strtolower(json_encode($session->data) ?: '');
foreach (['password', 'cookie', 'access_token', 'private_key', 'session_secret'] as $secret) {
    sc_p8v_final_assert(! str_contains($sessionJson, $secret), 'SC-P8-019 session omits secret material: ' . $secret);
}

// SC-P8-020 — Capability enforcement validation, including direct callback defense-in-depth.
$GLOBALS['sc_test_current_caps'] = [];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$activeDenied = PaymentMethodsController::active(new WP_REST_Request());
sc_p8v_final_assert($activeDenied instanceof WP_Error && ($activeDenied->data['status'] ?? 0) === 403, 'SC-P8-020 protected reference callback denies missing base access');
sc_p8v_final_assert(count($GLOBALS['sc_test_read_queries']) === $readsBefore, 'SC-P8-020 denied reference callback performs no data read');
$mobileDenied = MobileConfigController::show(new WP_REST_Request());
$referenceDenied = ReferenceDataController::show(new WP_REST_Request());
sc_p8v_final_assert($mobileDenied instanceof WP_Error && $referenceDenied instanceof WP_Error, 'SC-P8-020 mobile/reference callbacks recheck base access internally');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$queriesBefore = count($GLOBALS['sc_test_queries']);
$allDenied = PaymentMethodsController::all(new WP_REST_Request());
$saveDenied = PaymentMethodsController::save(new WP_REST_Request(['code' => 'card', 'name' => 'Card']));
$exportDenied = ExcelExportController::download(new WP_REST_Request());
sc_p8v_final_assert($allDenied instanceof WP_Error && $saveDenied instanceof WP_Error && $exportDenied instanceof WP_Error, 'SC-P8-020 operation callbacks require their explicit capabilities');
sc_p8v_final_assert(count($GLOBALS['sc_test_read_queries']) === $readsBefore && count($GLOBALS['sc_test_queries']) === $queriesBefore, 'SC-P8-020 capability denial occurs before reads or mutations');

// SC-P8-021 — Accountant scope validation; caller filter cannot widen assigned scope.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '11', 'contract_number' => 'SC-11', 'customer_id' => '7', 'customer_name' => 'Acme',
    'accountant_user_id' => '42', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    'base_value' => '100.0000', 'notes' => 'hidden', 'is_archived' => '0',
]]];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$assignedContracts = DataController::contracts(new WP_REST_Request(['accountant_user_id' => '99']));
$scopeSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $readsBefore));
sc_p8v_final_assert($assignedContracts instanceof WP_REST_Response, 'SC-P8-021 assigned contract read remains available');
sc_p8v_final_assert(str_contains($scopeSql, 'accountant_user_id = 42') && ! str_contains($scopeSql, 'accountant_user_id = 99'), 'SC-P8-021 caller accountant filter cannot widen assigned SQL scope');
$GLOBALS['sc_test_result_queue'] = [[[...sc_p8v_final_payment_row(99)]]];
$foreignPayment = DataController::payment(new WP_REST_Request(['id' => '21']));
sc_p8v_final_assert($foreignPayment instanceof WP_Error && ($foreignPayment->data['status'] ?? 0) === 403, 'SC-P8-021 foreign direct payment is forbidden for assigned user');

// SC-P8-022 — Customer endpoint validation.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '7', 'internal_code' => 'C7', 'name' => 'Acme', 'contact_name' => 'Ops', 'email' => 'ops@example.test',
    'phone' => '123', 'notes' => 'PRIVATE NOTE', 'is_active' => '1',
]]];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$customers = DataController::customers(new WP_REST_Request(['customer_id' => '7', 'sort' => 'name']));
$customerSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $readsBefore));
sc_p8v_final_assert($customers instanceof WP_REST_Response && count($customers->data['data']) === 1, 'SC-P8-022 customer list returns scoped row');
sc_p8v_final_assert(! array_key_exists('notes', $customers->data['data'][0]), 'SC-P8-022 customer projection excludes private notes');
sc_p8v_final_assert(str_contains($customerSql, 'cu.id = 7') && str_contains($customerSql, 'accountant_user_id = 42'), 'SC-P8-022 customer filters remain server-side and assigned scoped');
$GLOBALS['sc_test_result_queue'] = [[]];
$missingCustomer = DataController::customer(new WP_REST_Request(['id' => '999']));
sc_p8v_final_assert($missingCustomer instanceof WP_Error && ($missingCustomer->data['status'] ?? 0) === 404, 'SC-P8-022 missing customer returns versioned 404');

// SC-P8-023 — Dependent contract filter validation.
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$badDependent = DataController::contractOptions(new WP_REST_Request(['customer_id' => ['7']]));
sc_p8v_final_assert($badDependent instanceof WP_Error && ($badDependent->data['status'] ?? 0) === 422, 'SC-P8-023 malformed customer selector fails closed');
sc_p8v_final_assert(count($GLOBALS['sc_test_read_queries']) === $readsBefore, 'SC-P8-023 malformed dependent selector performs no data read');
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '11', 'contract_number' => 'SC-11', 'customer_id' => '7', 'customer_name' => 'Acme',
    'accountant_user_id' => '42', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    'base_value' => '100.0000', 'notes' => 'hidden', 'is_archived' => '0',
]]];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$options = DataController::contractOptions(new WP_REST_Request(['customer_id' => '7']));
$optionsSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $readsBefore));
sc_p8v_final_assert(($options->data['meta']['customer_id'] ?? 0) === 7 && ($options->data['meta']['client_may_offer_all_option'] ?? false) === true, 'SC-P8-023 dependent selector returns explicit customer/all-option metadata');
sc_p8v_final_assert(str_contains($optionsSql, 'c.customer_id = 7') && str_contains($optionsSql, 'c.accountant_user_id = 42'), 'SC-P8-023 dependent contract lookup preserves customer plus accountant scope');

// SC-P8-024 — Contract endpoint validation.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '11', 'contract_number' => 'SC-11', 'customer_id' => '7', 'customer_name' => 'Acme',
    'accountant_user_id' => '42', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    'base_value' => '100.0000', 'notes' => 'SECRET CONTRACT NOTE', 'is_archived' => '0',
]]];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$contracts = DataController::contracts(new WP_REST_Request(['customer_id' => '7', 'status' => 'active', 'sort' => 'contract_number']));
$contractSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $readsBefore));
sc_p8v_final_assert($contracts instanceof WP_REST_Response && ($contracts->data['data'][0]['contract_number'] ?? '') === 'SC-11', 'SC-P8-024 contract list returns safe domain row');
sc_p8v_final_assert(! array_key_exists('notes', $contracts->data['data'][0]), 'SC-P8-024 contract projection excludes internal notes');
sc_p8v_final_assert(str_contains($contractSql, 'c.customer_id = 7') && str_contains($contractSql, "c.status = 'active'") && str_contains($contractSql, 'c.accountant_user_id = 42'), 'SC-P8-024 contract filters and scope remain server-side');
$GLOBALS['sc_test_result_queue'] = [[]];
$missingContract = DataController::contract(new WP_REST_Request(['id' => '999']));
sc_p8v_final_assert($missingContract instanceof WP_Error && ($missingContract->data['status'] ?? 0) === 404, 'SC-P8-024 missing contract returns 404');

// SC-P8-025 — Payment endpoint validation.
$GLOBALS['sc_test_result_queue'] = [[[
    ...sc_p8v_final_payment_row(42),
    'contract_number' => 'SC-11', 'customer_id' => '7', 'customer_name' => 'Acme',
]]];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$payments = DataController::payments(new WP_REST_Request([
    'status' => 'overdue', 'due_from' => '2026-08-01', 'due_to' => '2026-08-31',
]));
$paymentSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $readsBefore));
$payment = $payments->data['data'][0] ?? [];
sc_p8v_final_assert(($payment['due_date'] ?? '') === '2026-08-10' && ($payment['expected_payment_date'] ?? '') === '2026-08-20', 'SC-P8-025 contractual and expected payment dates stay distinct');
sc_p8v_final_assert(($payment['paid_amount'] ?? '') === '60.0000' && ($payment['remaining_amount'] ?? '') === '40.0000', 'SC-P8-025 canonical payment balances are preserved');
sc_p8v_final_assert(str_contains($paymentSql, "p.status = 'overdue'") && str_contains($paymentSql, "p.due_date >= '2026-08-01'") && str_contains($paymentSql, 'c.accountant_user_id = 42'), 'SC-P8-025 payment status/date/accountant filters are server-side');
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$reversedRange = DataController::payments(new WP_REST_Request(['due_from' => '2026-09-01', 'due_to' => '2026-08-01']));
sc_p8v_final_assert($reversedRange instanceof WP_Error && ($reversedRange->data['status'] ?? 0) === 422, 'SC-P8-025 reversed due range fails closed instead of being silently swapped');
sc_p8v_final_assert(count($GLOBALS['sc_test_read_queries']) === $readsBefore, 'SC-P8-025 invalid due range performs no data read');

// SC-P8-026 — Collection endpoint validation.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '31', 'payment_id' => '21', 'amount' => '60.0000', 'collection_date' => '2026-08-01',
    'payment_method_id' => '1', 'payment_method_name' => 'Cash', 'reference' => 'COL-1', 'details' => 'PRIVATE DETAILS',
    'proof_media_id' => null, 'created_by' => '42', 'created_at' => '2026-08-01 10:00:00',
    'payment_reference' => 'P-1', 'sequence_no' => '1', 'due_date' => '2026-08-10', 'payment_status' => 'overdue',
    'remaining_amount' => '40.0000', 'contract_id' => '11', 'contract_number' => 'SC-11', 'accountant_user_id' => '42',
    'customer_id' => '7', 'customer_name' => 'Acme',
]]];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$collections = DataController::collections(new WP_REST_Request(['contract_id' => '11']));
$collectionSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $readsBefore));
sc_p8v_final_assert(($collections->data['data'][0]['payment_method_name'] ?? '') === 'Cash', 'SC-P8-026 collection list exposes safe payment-method display data');
sc_p8v_final_assert(! array_key_exists('details', $collections->data['data'][0]), 'SC-P8-026 collection projection excludes internal free text');
sc_p8v_final_assert(str_contains($collectionSql, 'c.id = 11') && str_contains($collectionSql, 'c.accountant_user_id = 42'), 'SC-P8-026 collection list preserves contract plus accountant scope');
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '31', 'payment_id' => '21', 'contract_id' => '11', 'accountant_user_id' => '99', 'amount' => '60.0000',
    'collection_date' => '2026-08-01', 'payment_method_id' => '1', 'payment_method_name' => 'Cash', 'reference' => 'COL-1',
    'proof_media_id' => null, 'created_by' => '42', 'created_at' => '2026-08-01 10:00:00', 'updated_at' => '2026-08-01 10:00:00',
]]];
$foreignCollection = DataController::collection(new WP_REST_Request(['id' => '31']));
sc_p8v_final_assert($foreignCollection instanceof WP_Error && ($foreignCollection->data['status'] ?? 0) === 403, 'SC-P8-026 foreign direct collection is forbidden');

// SC-P8-027 — Follow-up endpoint validation.
$GLOBALS['sc_test_result_queue'] = [[]];
$missingHistory = DataController::followUpHistory(new WP_REST_Request(['payment_id' => '999']));
sc_p8v_final_assert($missingHistory instanceof WP_Error && ($missingHistory->data['status'] ?? 0) === 404, 'SC-P8-027 missing payment follow-up history returns 404 instead of validation 422');
$GLOBALS['sc_test_result_queue'] = [
    [[...sc_p8v_final_payment_row(42)]],
    [[...sc_p8v_final_payment_row(42)]],
    [[
        'id' => '501', 'payment_id' => '21', 'state' => 'contacted', 'note' => 'Called customer',
        'promised_date' => null, 'deferred_until' => null, 'created_by' => '42', 'created_at' => '2026-08-15 10:00:00',
    ]],
];
$history = DataController::followUpHistory(new WP_REST_Request(['payment_id' => '21']));
sc_p8v_final_assert($history instanceof WP_REST_Response && ($history->data['data'][0]['state'] ?? '') === 'contacted', 'SC-P8-027 authorized follow-up history returns operational state');
sc_p8v_final_assert(($history->data['data'][0]['note'] ?? '') === 'Called customer' && ($history->data['meta']['scope'] ?? '') === 'assigned', 'SC-P8-027 authorized history preserves note with explicit assigned scope metadata');

// SC-P8-028 — Dashboard endpoint validation.
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$unknownDashboard = DashboardController::show(new WP_REST_Request(['unexpected' => '1']));
sc_p8v_final_assert($unknownDashboard instanceof WP_Error && ($unknownDashboard->data['status'] ?? 0) === 422, 'SC-P8-028 dashboard rejects unsupported parameters');
$badDashboardStatus = DashboardController::show(new WP_REST_Request(['status' => 'not-a-status']));
sc_p8v_final_assert($badDashboardStatus instanceof WP_Error && ($badDashboardStatus->data['status'] ?? 0) === 422, 'SC-P8-028 dashboard rejects invalid status instead of silently clearing it');
$badDashboardRange = DashboardController::show(new WP_REST_Request(['due_from' => '2026-09-01', 'due_to' => '2026-08-01']));
sc_p8v_final_assert($badDashboardRange instanceof WP_Error && ($badDashboardRange->data['status'] ?? 0) === 422, 'SC-P8-028 dashboard rejects reversed due range');
sc_p8v_final_assert(count($GLOBALS['sc_test_read_queries']) === $readsBefore, 'SC-P8-028 malformed dashboard filters perform no data reads');

$GLOBALS['sc_test_result_queue'] = [
    [[
        'contract_count' => '1', 'scheduled_total' => '100.0000', 'remaining_total' => '40.0000',
        'overdue_exposure' => '40.0000', 'collected_total' => '60.0000',
    ]],
    [[
        'id' => '7', 'internal_code' => 'C7', 'name' => 'Acme', 'contact_name' => 'Ops', 'email' => 'ops@example.test',
        'phone' => '123', 'notes' => 'hidden', 'is_active' => '1',
    ]],
    [[
        'id' => '11', 'contract_number' => 'SC-11', 'customer_id' => '7', 'customer_name' => 'Acme',
        'accountant_user_id' => '42', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'base_value' => '100.0000', 'notes' => 'hidden', 'is_archived' => '0',
    ]],
];
$readsBefore = count($GLOBALS['sc_test_read_queries']);
$dashboard = DashboardController::show(new WP_REST_Request(['customer_id' => '7', 'accountant_user_id' => '99']));
$dashboardSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $readsBefore));
sc_p8v_final_assert($dashboard instanceof WP_REST_Response && ($dashboard->data['data']['kpis']['contract_count'] ?? '') === '1', 'SC-P8-028 valid dashboard returns KPI payload');
sc_p8v_final_assert(($dashboard->data['data']['filters']['customer_id'] ?? 0) === 7 && count($dashboard->data['data']['customers'] ?? []) === 1 && count($dashboard->data['data']['contracts'] ?? []) === 1, 'SC-P8-028 dashboard returns filters plus dependent customer/contract options');
sc_p8v_final_assert(str_contains($dashboardSql, 'accountant_user_id = 42') && ! str_contains($dashboardSql, 'accountant_user_id = 99'), 'SC-P8-028 dashboard cannot widen assigned scope with caller accountant ID');
sc_p8v_final_assert(($dashboard->data['meta']['api_version'] ?? '') === Router::API_VERSION, 'SC-P8-028 dashboard response remains versioned');

printf("SafeContracts P8 final validation SC-P8-019..028 passed (%d assertions).\n", $tests);
