<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\ContractEditController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p9_013_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p9_013_contract(string $status = 'active', int $accountant = 42, bool $archived = false): array
{
    return [
        'id' => '11',
        'contract_number' => 'SC-11',
        'customer_id' => '7',
        'accountant_user_id' => (string) $accountant,
        'status' => $status,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'base_value' => '100.0000',
        'notes' => 'private',
        'is_archived' => $archived ? '1' : '0',
    ];
}

SafeContracts\Plugin::instance()->boot();
Router::register();

$route = $GLOBALS['sc_test_routes'][Router::NAMESPACE . '/contracts/(?P<id>\d+)/edit'] ?? null;
sc_p9_013_assert(is_array($route) && ($route['methods'] ?? '') === WP_REST_Server::CREATABLE, 'SC-P9-013 registers a command-style contract edit endpoint');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
];
$denied = ContractEditController::permission();
sc_p9_013_assert($denied instanceof WP_Error && ($denied->data['status'] ?? 0) === 403, 'SC-P9-013 edit capability is required before mutation');

$GLOBALS['sc_test_current_caps'][Capabilities::EDIT_CONTRACTS] = true;
sc_p9_013_assert(ContractEditController::permission() === true, 'SC-P9-013 granted edit capability passes the operation guard');

$GLOBALS['sc_test_result_queue'] = [[sc_p9_013_contract()]];
$beforeQueries = count($GLOBALS['sc_test_queries']);
$number = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'contract_number', 'contract_number' => 'SC-11-REV',
]));
$numberSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeQueries));
sc_p9_013_assert($number instanceof WP_REST_Response && ($number->data['data']['operation'] ?? '') === 'contract_number', 'SC-P9-013 contract-number command succeeds');
sc_p9_013_assert(str_contains($numberSql, 'SC-11-REV') && str_contains($numberSql, 'UPDATE'), 'SC-P9-013 contract-number command delegates to repository mutation');
sc_p9_013_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_edited']), 'SC-P9-013 contract-number edit preserves domain audit hook');

$GLOBALS['sc_test_result_queue'] = [[sc_p9_013_contract()]];
$dates = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'dates', 'start_date' => '2026-02-01', 'end_date' => '2026-11-30',
]));
sc_p9_013_assert($dates instanceof WP_REST_Response && ($dates->data['data']['operation'] ?? '') === 'dates', 'SC-P9-013 date command succeeds atomically');
sc_p9_013_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_dates_changed']), 'SC-P9-013 date edit preserves audit hook');

$GLOBALS['sc_test_result_queue'] = [[sc_p9_013_contract()]];
$base = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'base_value', 'base_value' => '250.1250',
]));
sc_p9_013_assert($base instanceof WP_REST_Response && ($base->data['data']['operation'] ?? '') === 'base_value', 'SC-P9-013 base-value command succeeds');
sc_p9_013_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_base_value_changed']), 'SC-P9-013 base-value edit preserves audit hook');

$GLOBALS['sc_test_result_queue'] = [[sc_p9_013_contract('active')]];
$status = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'status', 'status' => 'completed',
]));
sc_p9_013_assert($status instanceof WP_REST_Response && ($status->data['data']['operation'] ?? '') === 'status', 'SC-P9-013 valid lifecycle command succeeds');
sc_p9_013_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_status_changed']), 'SC-P9-013 status edit preserves lifecycle audit hook');

$GLOBALS['sc_test_result_queue'] = [[sc_p9_013_contract('completed')]];
$conflict = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'status', 'status' => 'active',
]));
sc_p9_013_assert($conflict instanceof WP_Error && $conflict->code === 'safecontracts_contract_edit_conflict' && ($conflict->data['status'] ?? 0) === 409, 'SC-P9-013 invalid lifecycle transition is an explicit conflict');

$GLOBALS['sc_test_result_queue'] = [[sc_p9_013_contract('active', 42, true)]];
$archived = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'base_value', 'base_value' => '300',
]));
sc_p9_013_assert($archived instanceof WP_Error && ($archived->data['status'] ?? 0) === 409, 'SC-P9-013 archived contract edit is an explicit conflict');

$GLOBALS['sc_test_result_queue'] = [[sc_p9_013_contract('active', 99)]];
$foreign = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'contract_number', 'contract_number' => 'NOPE',
]));
sc_p9_013_assert($foreign instanceof WP_Error && ($foreign->data['status'] ?? 0) === 403, 'SC-P9-013 accountant scope remains server-authoritative on writes');

$GLOBALS['sc_test_result_queue'] = [[]];
$missing = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'contract_number', 'contract_number' => 'MISSING',
]));
sc_p9_013_assert($missing instanceof WP_Error && ($missing->data['status'] ?? 0) === 404, 'SC-P9-013 missing contract maps to not-found');

$GLOBALS['sc_test_result_queue'] = [[sc_p9_013_contract()]];
$invalid = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'dates', 'start_date' => '2026-12-31', 'end_date' => '2026-01-01',
]));
sc_p9_013_assert($invalid instanceof WP_Error && ($invalid->data['status'] ?? 0) === 400, 'SC-P9-013 invalid edit input maps to validation error');

$unrelated = ContractEditController::edit(new WP_REST_Request([
    'id' => '11', 'operation' => 'base_value', 'base_value' => '100', 'status' => 'completed',
]));
sc_p9_013_assert($unrelated instanceof WP_Error && ($unrelated->data['status'] ?? 0) === 400, 'SC-P9-013 one command cannot smuggle unrelated edit fields');

echo "SC-P9-013 backend contract edit checks passed: {$tests}\n";
