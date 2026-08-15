<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\ApiRequest;
use SafeContracts\Rest\ApiScope;
use SafeContracts\Rest\DataController;
use SafeContracts\Rest\Permission;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p8_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p8_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p8_assert($error instanceof $class, $message);
        return;
    }
    sc_p8_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();
Router::register();

// SC-P8-001 — canonical v1 conventions and safe response envelope.
sc_p8_assert(Router::NAMESPACE === 'safecontracts/v1' && Router::API_VERSION === 'v1', 'SC-P8-001 keeps canonical SafeContracts v1 namespace');
$health = Router::health(new WP_REST_Request());
sc_p8_assert($health->status === 200 && ($health->data['data']['service'] ?? '') === 'SafeContracts', 'SC-P8-001 health returns canonical service data');
sc_p8_assert(($health->data['meta']['api_version'] ?? '') === 'v1', 'SC-P8-001 success envelope includes API version metadata');
foreach (['password','cookie','access_token','private_key','service_account'] as $secretKey) {
    sc_p8_assert(! str_contains(strtolower(json_encode($health->data) ?: ''), $secretKey), 'SC-P8-001 health envelope omits secret material: ' . $secretKey);
}

// SC-P8-002/003 — WordPress session + reusable capability boundary.
$GLOBALS['sc_test_current_caps'] = [];
$denied = Permission::access();
sc_p8_assert($denied instanceof WP_Error && $denied->code === 'safecontracts_forbidden' && ($denied->data['status'] ?? 0) === 403, 'SC-P8-002 unauthorised session fails closed');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p8_assert(Permission::access() instanceof WP_Error, 'SC-P8-002 access capability alone cannot bypass data-scope requirement');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
sc_p8_assert(Permission::access() === true, 'SC-P8-002 authenticated assigned session is accepted');
sc_p8_assert(Permission::capability(Capabilities::EXPORT_REPORTS) instanceof WP_Error, 'SC-P8-003 reusable guard denies missing operation capability');
$GLOBALS['sc_test_current_caps'][Capabilities::EXPORT_REPORTS] = true;
sc_p8_assert(Permission::capability(Capabilities::EXPORT_REPORTS) === true, 'SC-P8-003 reusable guard accepts granted WordPress capability');
$session = Router::me(new WP_REST_Request());
$sessionJson = strtolower(json_encode($session->data) ?: '');
sc_p8_assert(($session->data['data']['user_id'] ?? 0) === 42 && ($session->data['data']['scope'] ?? '') === 'assigned', 'SC-P8-002 session exposes safe user/scope metadata');
foreach (['password','cookie','access_token','private_key','session_secret'] as $secretKey) {
    sc_p8_assert(! str_contains($sessionJson, $secretKey), 'SC-P8-002 session omits secret field: ' . $secretKey);
}

// Strict REST request parsing fails closed instead of silently widening scope.
sc_p8_expect(InvalidArgumentException::class, fn () => ApiRequest::listQuery(new WP_REST_Request(['customer_id' => ['7']])), 'SC-P8-001 malformed array ID is rejected');
sc_p8_expect(InvalidArgumentException::class, fn () => ApiRequest::listQuery(new WP_REST_Request(['status' => 'totally_invalid'])), 'SC-P8-001 unsupported status is rejected');
sc_p8_expect(InvalidArgumentException::class, fn () => ApiRequest::listQuery(new WP_REST_Request(['due_from' => '2026-02-31'])), 'SC-P8-001 invalid calendar date is rejected');
sc_p8_expect(InvalidArgumentException::class, fn () => ApiRequest::pagination(new WP_REST_Request(['per_page' => 101])), 'SC-P8-001 excessive page size is rejected');
sc_p8_expect(InvalidArgumentException::class, fn () => ApiRequest::routeId(new WP_REST_Request(['id' => '0'])), 'SC-P8-001 direct-object IDs must be positive');

// SC-P8-004 — accountant scope is authoritative server-side.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
ApiScope::assertAccountant(42);
sc_p8_assert(ApiScope::mode() === 'assigned', 'SC-P8-004 assigned scope resolves deterministically');
sc_p8_expect(DomainException::class, fn () => ApiScope::assertAccountant(99), 'SC-P8-004 assigned user cannot access another accountant object');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
ApiScope::assertAccountant(99);
sc_p8_assert(ApiScope::mode() === 'all', 'SC-P8-004 VIEW_ALL can cross accountant assignments');

// SC-P8-005 — scoped customer list, safe projection and no notes leakage.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '7', 'internal_code' => 'C7', 'name' => 'Acme', 'contact_name' => 'Ops', 'email' => 'ops@example.test',
    'phone' => '123', 'notes' => 'PRIVATE NOTE', 'is_active' => '1',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$customers = DataController::customers(new WP_REST_Request(['customer_id' => '7', 'page' => '1', 'per_page' => '25']));
$customerSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p8_assert($customers instanceof WP_REST_Response && count($customers->data['data']) === 1, 'SC-P8-005 customer list returns scoped rows');
sc_p8_assert(! array_key_exists('notes', $customers->data['data'][0]), 'SC-P8-005 customer API excludes private notes');
sc_p8_assert(str_contains($customerSql, 'cu.id = 7') && str_contains($customerSql, 'accountant_user_id = 42'), 'SC-P8-005 customer query enforces requested customer plus assigned-user scope');
sc_p8_assert(($customers->data['meta']['per_page'] ?? 0) === 25 && ($customers->data['meta']['scope'] ?? '') === 'assigned', 'SC-P8-005 list metadata exposes bounded paging and scope');

// SC-P8-006 — customer-dependent contract lookup never widens assignment scope.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '11', 'contract_number' => 'SC-11', 'customer_id' => '7', 'customer_name' => 'Acme',
    'accountant_user_id' => '42', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    'base_value' => '100.0000', 'notes' => 'hidden', 'is_archived' => '0',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$options = DataController::contractOptions(new WP_REST_Request(['customer_id' => '7']));
$optionsSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p8_assert($options instanceof WP_REST_Response && ($options->data['data'][0]['id'] ?? 0) === 11, 'SC-P8-006 dependent contract lookup returns normalized option');
sc_p8_assert(($options->data['meta']['client_may_offer_all_option'] ?? false) === true, 'SC-P8-006 client may offer All contracts without server inventing an unauthorized record');
sc_p8_assert(str_contains($optionsSql, 'c.customer_id = 7') && str_contains($optionsSql, 'c.accountant_user_id = 42'), 'SC-P8-006 dependent lookup is customer + assignment scoped');

// SC-P8-007 — contract detail/list safe projection and direct-object scope.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '11', 'contract_number' => 'SC-11', 'customer_id' => '7', 'customer_name' => 'Acme',
    'accountant_user_id' => '42', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    'base_value' => '100.0000', 'notes' => 'SECRET CONTRACT NOTE', 'is_archived' => '0',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$contract = DataController::contract(new WP_REST_Request(['id' => '11']));
$contractSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p8_assert($contract instanceof WP_REST_Response && ($contract->data['data']['contract_number'] ?? '') === 'SC-11', 'SC-P8-007 contract detail returns safe domain data');
sc_p8_assert(! array_key_exists('notes', $contract->data['data']), 'SC-P8-007 contract detail excludes internal notes');
sc_p8_assert(str_contains($contractSql, 'c.id = 11') && str_contains($contractSql, 'c.accountant_user_id = 42'), 'SC-P8-007 direct contract read remains assignment scoped');

// SC-P8-008 — payment list preserves contractual/expected dates and canonical balances.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '21', 'contract_id' => '11', 'sequence_no' => '1', 'reference' => 'P-1', 'due_date' => '2026-08-10',
    'expected_payment_date' => '2026-08-20', 'original_amount' => '100.0000', 'paid_amount' => '60.0000',
    'remaining_amount' => '40.0000', 'status' => 'overdue', 'contract_number' => 'SC-11', 'accountant_user_id' => '42',
    'contract_is_archived' => '0', 'customer_id' => '7', 'customer_name' => 'Acme',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$payments = DataController::payments(new WP_REST_Request(['status' => 'overdue', 'due_from' => '2026-08-01', 'due_to' => '2026-08-31']));
$paymentSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
$paymentRow = $payments->data['data'][0] ?? [];
sc_p8_assert(($paymentRow['due_date'] ?? '') === '2026-08-10' && ($paymentRow['expected_payment_date'] ?? '') === '2026-08-20', 'SC-P8-008 contractual and expected payment dates remain separate');
sc_p8_assert(($paymentRow['paid_amount'] ?? '') === '60.0000' && ($paymentRow['remaining_amount'] ?? '') === '40.0000', 'SC-P8-008 canonical payment balances pass through unchanged');
sc_p8_assert(str_contains($paymentSql, "p.status = 'overdue'") && str_contains($paymentSql, "p.due_date >= '2026-08-01'") && str_contains($paymentSql, 'c.accountant_user_id = 42'), 'SC-P8-008 status/date/accountant filters are server-side');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '21', 'contract_id' => '11', 'sequence_no' => '1', 'reference' => 'P-1', 'due_date' => '2026-08-10',
    'expected_payment_date' => null, 'original_amount' => '100.0000', 'paid_amount' => '0.0000', 'remaining_amount' => '100.0000',
    'status' => 'due', 'accountant_user_id' => '99', 'contract_is_archived' => '0',
]]];
$foreignPayment = DataController::payment(new WP_REST_Request(['id' => '21']));
sc_p8_assert($foreignPayment instanceof WP_Error && ($foreignPayment->data['status'] ?? 0) === 403, 'SC-P8-004/008 foreign payment direct-object read is forbidden');

// SC-P8-009 — collection list/detail are scoped and omit internal details.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '31', 'payment_id' => '21', 'amount' => '60.0000', 'collection_date' => '2026-08-01',
    'payment_method_id' => '1', 'payment_method_name' => 'Cash', 'reference' => 'COL-1', 'details' => 'PRIVATE DETAILS',
    'proof_media_id' => null, 'created_by' => '42', 'created_at' => '2026-08-01 10:00:00',
    'payment_reference' => 'P-1', 'sequence_no' => '1', 'due_date' => '2026-08-10', 'payment_status' => 'overdue',
    'remaining_amount' => '40.0000', 'contract_id' => '11', 'contract_number' => 'SC-11', 'accountant_user_id' => '42',
    'customer_id' => '7', 'customer_name' => 'Acme',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$collections = DataController::collections(new WP_REST_Request(['contract_id' => '11']));
$collectionSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p8_assert($collections instanceof WP_REST_Response && ($collections->data['data'][0]['id'] ?? 0) === '31', 'SC-P8-009 collection list returns scoped ledger row');
sc_p8_assert(! array_key_exists('details', $collections->data['data'][0]), 'SC-P8-009 collection API excludes internal free-text details');
sc_p8_assert(str_contains($collectionSql, 'c.id = 11') && str_contains($collectionSql, 'c.accountant_user_id = 42'), 'SC-P8-009 collection list enforces contract + assignment scope');

$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '31', 'payment_id' => '21', 'contract_id' => '11', 'accountant_user_id' => '42', 'amount' => '60.0000',
    'collection_date' => '2026-08-01', 'payment_method_id' => '1', 'payment_method_name' => 'Cash', 'reference' => 'COL-1',
    'proof_media_id' => null, 'created_by' => '42', 'created_at' => '2026-08-01 10:00:00', 'updated_at' => '2026-08-01 10:00:00',
]]];
$collection = DataController::collection(new WP_REST_Request(['id' => '31']));
sc_p8_assert($collection instanceof WP_REST_Response && ($collection->data['data']['payment_method_name'] ?? '') === 'Cash', 'SC-P8-009 collection detail uses direct repository read');
sc_p8_assert(! array_key_exists('accountant_user_id', $collection->data['data']), 'SC-P8-009 authorization context is not leaked in collection detail');

// SC-P8-010 — follow-up queue/history reuse domain scope and stay read-only.
$GLOBALS['sc_test_result_queue'] = [[[
    'payment_id' => '21', 'contract_id' => '11', 'customer_id' => '7', 'accountant_user_id' => '42', 'contract_status' => 'active',
    'reference' => 'P-1', 'due_date' => '2026-08-10', 'expected_payment_date' => '2026-08-20', 'original_amount' => '100.0000',
    'paid_amount' => '60.0000', 'remaining_amount' => '40.0000', 'status' => 'overdue', 'followup_state' => 'contacted',
]]];
$before = count($GLOBALS['sc_test_read_queries']);
$followups = DataController::followUps(new WP_REST_Request(['page' => '1', 'per_page' => '20']));
$followupSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p8_assert($followups instanceof WP_REST_Response && ($followups->data['data'][0]['followup_state'] ?? '') === 'contacted', 'SC-P8-010 follow-up queue returns operational state');
sc_p8_assert(str_contains($followupSql, 'c.accountant_user_id = 42') && str_contains($followupSql, "p.status <> 'paid'"), 'SC-P8-010 queue reuses assigned/settled domain boundaries');

$GLOBALS['sc_test_result_queue'] = [
    [[
        'id' => '21', 'contract_id' => '11', 'sequence_no' => '1', 'reference' => 'P-1', 'due_date' => '2026-08-10',
        'expected_payment_date' => '2026-08-20', 'original_amount' => '100.0000', 'paid_amount' => '60.0000',
        'remaining_amount' => '40.0000', 'status' => 'overdue', 'accountant_user_id' => '42', 'contract_is_archived' => '0',
    ]],
    [[
        'id' => '91', 'payment_id' => '21', 'state' => 'promised_to_pay', 'note' => 'Call completed',
        'promised_date' => '2026-08-20', 'deferred_until' => null, 'created_by' => '42', 'created_at' => '2026-08-15 09:00:00',
    ]],
];
$history = DataController::followUpHistory(new WP_REST_Request(['payment_id' => '21', 'per_page' => '20']));
$historyRow = $history->data['data'][0] ?? [];
sc_p8_assert(($historyRow['promised_date'] ?? '') === '2026-08-20' && ! array_key_exists('due_date', $historyRow), 'SC-P8-010 promise date remains operational and cannot masquerade as contractual due date');

// Route/static contract: the first ten P8 tasks add reads only; no early mutation surface.
$expectedRoutes = [
    '/session', '/customers', '/customers/(?P<id>\\d+)', '/filters/contracts', '/contracts', '/contracts/(?P<id>\\d+)',
    '/payments', '/payments/(?P<id>\\d+)', '/collections', '/collections/(?P<id>\\d+)', '/followups', '/payments/(?P<payment_id>\\d+)/followups',
];
foreach ($expectedRoutes as $route) {
    $key = Router::NAMESPACE . $route;
    sc_p8_assert(isset($GLOBALS['sc_test_routes'][$key]), 'P8 route registered: ' . $route);
    sc_p8_assert(($GLOBALS['sc_test_routes'][$key]['methods'] ?? '') === WP_REST_Server::READABLE, 'P8 route is read-only: ' . $route);
}
$source = file_get_contents((string) (new ReflectionClass(DataController::class))->getFileName()) ?: '';
sc_p8_assert(! str_contains($source, 'WP_REST_Server::CREATABLE') && ! str_contains($source, '$wpdb'), 'SC-P8-005..010 controller exposes no mutation verb or presentation-layer SQL');
foreach (['addNote(', 'promiseToPay(', 'markIssue(', 'defer(', 'escalate(', 'recordCollection(', 'createContract('] as $mutation) {
    sc_p8_assert(! str_contains($source, $mutation), 'SC-P8-005..010 does not implement later mutation work: ' . $mutation);
}

printf("SafeContracts P8 REST implementation SC-P8-001..010 passed (%d assertions).\n", $tests);
