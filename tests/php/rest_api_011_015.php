<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\DashboardFilters;
use SafeContracts\Rest\DashboardController;
use SafeContracts\Rest\ExcelExportController;
use SafeContracts\Rest\MobileConfigController;
use SafeContracts\Rest\ReferenceDataController;
use SafeContracts\Rest\RequestGuard;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\MobileConfiguration;

final class SC_P8_Request extends WP_REST_Request
{
    public function __construct(private array $params = [])
    {
        parent::__construct([]);
    }

    public function get_params(): array
    {
        return $this->params;
    }
}

$tests = 0;
function sc_p8_011_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

Router::register();

// SC-P8-011..014 — canonical routes are registered under the v1 namespace.
foreach (['/dashboard', '/mobile-config', '/reference-data', '/reports/excel'] as $route) {
    sc_p8_011_assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . $route]), "P8 route {$route} is registered");
}

// SC-P8-015 — malformed request filters fail closed without scalar-cast notices/widening.
$malformed = RequestGuard::dashboardFilters(new SC_P8_Request([
    'customer_id' => ['7'],
    'contract_id' => true,
    'accountant_user_id' => '1.5',
    'status' => ['overdue'],
    'due_from' => ['2026-08-01'],
]));
sc_p8_011_assert($malformed['customer_id'] === 0 && $malformed['contract_id'] === 0 && $malformed['accountant_user_id'] === 0, 'SC-P8-015 malformed IDs fail closed');
sc_p8_011_assert($malformed['status'] === '' && $malformed['due_from'] === null, 'SC-P8-015 malformed status/date fail closed');
$invalid = RequestGuard::invalid(new InvalidArgumentException('bad field'), 'safecontracts_bad_input');
sc_p8_011_assert($invalid->code === 'safecontracts_bad_input' && ($invalid->data['status'] ?? 0) === 422, 'SC-P8-015 invalid input uses stable 422 WP_Error envelope');
$failure = RequestGuard::failure(new RuntimeException('SQL secret detail'), 'safecontracts_failed');
sc_p8_011_assert($failure->code === 'safecontracts_failed' && ! str_contains($failure->message, 'SQL secret detail'), 'SC-P8-015 internal failures do not leak exception details');

// SC-P8-011 — dashboard values and dependent filters reuse server-side scoped read models.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [
    [['contract_count' => '2', 'scheduled_total' => '500.0000', 'remaining_total' => '125.0000', 'overdue_exposure' => '75.0000', 'collected_total' => '375.0000']],
    [['id' => '7', 'internal_code' => null, 'name' => 'Scoped Customer', 'contact_name' => '', 'email' => '', 'phone' => '', 'notes' => '', 'is_active' => '1']],
    [['id' => '51', 'contract_number' => 'SC-51', 'customer_id' => '7', 'customer_name' => 'Scoped Customer', 'accountant_user_id' => '42', 'status' => 'active', 'start_date' => null, 'end_date' => null, 'base_value' => '100.0000', 'notes' => '', 'is_archived' => '0']],
];
$before = count($GLOBALS['sc_test_read_queries']);
$dashboard = DashboardController::show(new SC_P8_Request(['customer_id' => '7', 'accountant_user_id' => '999', 'status' => 'overdue']));
$dashboardSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $before));
sc_p8_011_assert($dashboard instanceof WP_REST_Response && $dashboard->status === 200, 'SC-P8-011 dashboard returns successful REST response');
sc_p8_011_assert(($dashboard->data['data']['kpis']['overdue_exposure'] ?? '') === '75.0000', 'SC-P8-011 dashboard exposes authoritative KPI output');
sc_p8_011_assert(str_contains($dashboardSql, 'accountant_user_id = 42') && ! str_contains($dashboardSql, 'accountant_user_id = 999'), 'SC-P8-011 assigned dashboard scope cannot be widened by request accountant');
sc_p8_011_assert(count($dashboard->data['data']['contracts'] ?? []) === 1 && ($dashboard->data['data']['contracts'][0]['customer_id'] ?? 0) === 7, 'SC-P8-011 dependent contracts stay customer-scoped');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
$dashboardDenied = DashboardController::canView();
sc_p8_011_assert($dashboardDenied instanceof WP_Error && $dashboardDenied->code === 'safecontracts_dashboard_scope_forbidden', 'SC-P8-011 ACCESS without data scope fails closed');

// SC-P8-012 — mobile config exposes only the normalized non-secret bootstrap contract.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
$GLOBALS['sc_test_options'][MobileConfiguration::OPTION] = [
    'support_text' => 'Support desk',
    'default_page_size' => 50,
    'excel_export_enabled' => true,
    'push_notifications_enabled' => true,
    'collection_entry_enabled' => false,
    'firebase_service_account' => '{secret}',
    'access_token' => 'secret-token',
];
$mobile = MobileConfigController::show(new SC_P8_Request());
$mobileData = $mobile->data['data'] ?? [];
sc_p8_011_assert(($mobileData['support_text'] ?? '') === 'Support desk' && ($mobileData['default_page_size'] ?? 0) === 50, 'SC-P8-012 mobile config returns normalized bootstrap values');
sc_p8_011_assert(($mobileData['features']['excel_export'] ?? false) === true && ($mobileData['features']['collection_entry'] ?? true) === false, 'SC-P8-012 mobile feature flags are explicit booleans');
sc_p8_011_assert(! array_key_exists('firebase_service_account', $mobileData) && ! str_contains(json_encode($mobileData) ?: '', 'secret-token'), 'SC-P8-012 mobile config never leaks secret-looking stored extras');

// SC-P8-013 — reference-data endpoint projects active mobile-safe fields only.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '3', 'code' => 'bank_transfer', 'name' => 'Bank Transfer', 'display_order' => '20', 'is_active' => '1',
]]];
$reference = ReferenceDataController::show(new SC_P8_Request());
$method = $reference->data['data']['payment_methods'][0] ?? [];
sc_p8_011_assert($method === ['id' => 3, 'code' => 'bank_transfer', 'name' => 'Bank Transfer', 'display_order' => 20], 'SC-P8-013 payment methods expose stable mobile-safe projection');
sc_p8_011_assert(! array_key_exists('is_active', $method), 'SC-P8-013 active-only endpoint omits redundant/internal active flag');

// SC-P8-014 — export capability is distinct and the REST download reuses the server XLSX service.
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::VIEW_REPORTS => true];
$exportDenied = ExcelExportController::canExport();
sc_p8_011_assert($exportDenied instanceof WP_Error && $exportDenied->code === 'safecontracts_export_forbidden', 'SC-P8-014 VIEW_REPORTS alone cannot export');
$GLOBALS['sc_test_current_caps'][Capabilities::EXPORT_REPORTS] = true;
$GLOBALS['sc_test_result_queue'] = array_fill(0, 8, []);
$export = ExcelExportController::download(new SC_P8_Request(['customer_id' => '0']));
sc_p8_011_assert($export instanceof WP_REST_Response && $export->status === 200, 'SC-P8-014 authorized Excel endpoint returns download response');
$exportData = $export->data['data'] ?? [];
sc_p8_011_assert(($exportData['encoding'] ?? '') === 'base64' && str_ends_with((string) ($exportData['filename'] ?? ''), '.xlsx'), 'SC-P8-014 export response describes deterministic XLSX download payload');
$binary = base64_decode((string) ($exportData['content_base64'] ?? ''), true);
sc_p8_011_assert(is_string($binary) && str_starts_with($binary, 'PK'), 'SC-P8-014 exported payload is a real XLSX ZIP package');
sc_p8_011_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_export_completed']), 'SC-P8-014 REST export preserves server-side export audit evidence');

// Static separation checks: REST presentation owns no SQL/business recomputation.
foreach ([DashboardController::class, MobileConfigController::class, ReferenceDataController::class, ExcelExportController::class] as $class) {
    $source = file_get_contents((string) (new ReflectionClass($class))->getFileName()) ?: '';
    sc_p8_011_assert(! str_contains($source, '$wpdb'), $class . ' contains no direct REST presentation SQL');
}
$exportSource = file_get_contents((string) (new ReflectionClass(ExcelExportController::class))->getFileName()) ?: '';
sc_p8_011_assert(str_contains($exportSource, 'ReportExportService') && str_contains($exportSource, 'EXPORT_REPORTS'), 'SC-P8-014 endpoint delegates generation and enforces export capability');

printf("SafeContracts P8 REST SC-P8-011..015 passed (%d assertions).\n", $tests);
