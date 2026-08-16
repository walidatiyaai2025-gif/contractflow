<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\LoginBranding;
use SafeContracts\Admin\NavigationCleanup;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Reports\ReportExportService;
use SafeContracts\Reports\XlsxWorkbook;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p6export_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p6export_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p6export_assert($error instanceof $class, $message);
        return;
    }
    sc_p6export_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();

// SC-P6-019 — real XLSX generation, safe cells, scoped data and audit evidence.
$workbook = new XlsxWorkbook();
$sample = $workbook->build([
    'Summary' => [['Metric', 'Value'], ['customer', '=CMD()'], ['rtl', 'عقد آمن']],
    'Bad:/Name*?' => [['A', 'B'], ['1', '@external']],
]);
sc_p6export_assert(str_starts_with($sample, "PK\x03\x04"), 'SC-P6-019 workbook is a ZIP-based XLSX package');
sc_p6export_assert(str_contains($sample, '[Content_Types].xml') && str_contains($sample, 'xl/workbook.xml') && str_contains($sample, 'xl/worksheets/sheet1.xml'), 'SC-P6-019 XLSX package contains required OOXML parts');
sc_p6export_assert(str_contains($sample, "'=CMD()") && str_contains($sample, "'@external"), 'SC-P6-019 formula-like user cells are forced to text');
sc_p6export_assert(str_contains($sample, 'عقد آمن'), 'SC-P6-019 workbook preserves UTF-8 Arabic content');
sc_p6export_expect(InvalidArgumentException::class, fn () => $workbook->build([]), 'SC-P6-019 empty workbook is rejected');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_REPORTS => true,
    Capabilities::EXPORT_REPORTS => true,
    Capabilities::VIEW_ALL => true,
];
$GLOBALS['sc_test_result_queue'] = [
    [[
        'contract_count' => '1', 'scheduled_total' => '100.0000', 'remaining_total' => '40.0000',
        'overdue_exposure' => '40.0000', 'collected_total' => '60.0000',
    ]],
    [['collection_transactions' => '1', 'collection_ledger_total' => '60.0000']],
    [['followup_events' => '2', 'followed_up_payments' => '1']],
    [[
        'id' => '9', 'internal_code' => null, 'name' => '=Injected Customer', 'contact_name' => 'Ops',
        'email' => 'ops@example.test', 'phone' => '123', 'notes' => '', 'is_active' => '1',
    ]],
    [[
        'id' => '11', 'contract_number' => 'SC-11', 'customer_id' => '9', 'customer_name' => '=Injected Customer',
        'accountant_user_id' => '42', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'base_value' => '100.0000', 'notes' => '', 'is_archived' => '0',
    ]],
    [[
        'id' => '21', 'contract_id' => '11', 'sequence_no' => '1', 'reference' => 'P-1', 'due_date' => '2026-08-10',
        'expected_payment_date' => '2026-08-20', 'original_amount' => '100.0000', 'paid_amount' => '60.0000',
        'remaining_amount' => '40.0000', 'status' => 'overdue', 'contract_number' => 'SC-11', 'accountant_user_id' => '42',
        'contract_is_archived' => '0', 'customer_id' => '9', 'customer_name' => '=Injected Customer',
    ]],
    [[
        'id' => '31', 'payment_id' => '21', 'amount' => '60.0000', 'collection_date' => '2026-08-01',
        'payment_method_id' => '1', 'reference' => 'COL-1', 'details' => '', 'proof_media_id' => null, 'created_by' => '42',
        'created_at' => '2026-08-01 10:00:00', 'payment_reference' => 'P-1', 'sequence_no' => '1', 'due_date' => '2026-08-10',
        'payment_status' => 'overdue', 'remaining_amount' => '40.0000', 'contract_id' => '11', 'contract_number' => 'SC-11',
        'accountant_user_id' => '42', 'customer_id' => '9', 'customer_name' => '=Injected Customer', 'payment_method_name' => 'Cash',
    ]],
    [[
        'payment_id' => '21', 'contract_id' => '11', 'customer_id' => '9', 'accountant_user_id' => '42',
        'reference' => 'P-1', 'due_date' => '2026-08-10', 'expected_payment_date' => '2026-08-20',
        'original_amount' => '100.0000', 'paid_amount' => '60.0000', 'remaining_amount' => '40.0000',
        'status' => 'overdue', 'followup_state' => 'contacted',
    ]],
];
$export = (new ReportExportService())->generate([
    'customer_id' => 9,
    'contract_id' => 11,
    'accountant_user_id' => 42,
    'status' => 'overdue',
    'due_from' => '2026-08-01',
    'due_to' => '2026-08-31',
]);
sc_p6export_assert($export['content_type'] === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'SC-P6-019 export uses XLSX MIME type');
sc_p6export_assert(str_ends_with($export['filename'], '.xlsx'), 'SC-P6-019 export filename uses xlsx extension');
sc_p6export_assert($export['row_counts'] === ['customers' => 1, 'contracts' => 1, 'payments' => 1, 'collections' => 1, 'followups' => 1], 'SC-P6-019 export reports deterministic row counts');
sc_p6export_assert(str_contains($export['content'], "'=Injected Customer"), 'SC-P6-019 service keeps spreadsheet injection protection');
$exportEvents = $GLOBALS['sc_test_fired_actions']['safecontracts_export_completed'] ?? [];
sc_p6export_assert(count($exportEvents) >= 1, 'SC-P6-019 successful export emits audit hook');
$lastExport = $exportEvents[array_key_last($exportEvents)] ?? [];
sc_p6export_assert(($lastExport[0]['type'] ?? '') === 'admin_report_xlsx' && ($lastExport[0]['filters']['contract_id'] ?? 0) === 11, 'SC-P6-019 audit context records export type and authorized filters');
$exportSource = file_get_contents((string) (new ReflectionClass(ReportExportService::class))->getFileName()) ?: '';
sc_p6export_assert(str_contains($exportSource, 'AdminReadRepository') && str_contains($exportSource, 'FollowUpService'), 'SC-P6-019 export reuses scoped server-side read/follow-up boundaries');
sc_p6export_assert(! str_contains($exportSource, '$wpdb'), 'SC-P6-019 export service contains no direct SQL');
sc_p6export_assert(isset($GLOBALS['sc_test_actions']['admin_post_' . ReportsPage::EXPORT_ACTION]), 'SC-P6-019 XLSX handler is registered in plugin lifecycle');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_REPORTS => true];
sc_p6export_expect(DomainException::class, fn () => (new ReportExportService())->generate([]), 'SC-P6-019 report viewing alone cannot grant export');

// SC-P6-020 — responsive/RTL admin layer is scoped to SafeContracts assets and covers narrow screens.
$responsivePath = dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-responsive.css';
$responsiveCss = file_get_contents($responsivePath) ?: '';
sc_p6export_assert(str_contains($responsiveCss, '[dir="rtl"]') && str_contains($responsiveCss, '.rtl '), 'SC-P6-020 responsive layer supports document and WordPress RTL modes');
sc_p6export_assert(str_contains($responsiveCss, '@media (max-width: 782px)') && str_contains($responsiveCss, '@media (max-width: 480px)'), 'SC-P6-020 responsive layer covers tablet/mobile breakpoints');
sc_p6export_assert(str_contains($responsiveCss, 'overflow-x: auto') && str_contains($responsiveCss, '-webkit-overflow-scrolling: touch'), 'SC-P6-020 wide data tables remain usable on narrow screens');
$_GET['page'] = ReportsPage::SLUG;
AdminShell::enqueueAssets();
sc_p6export_assert(isset($GLOBALS['sc_test_enqueued_styles'][AdminShell::RESPONSIVE_STYLE_HANDLE]), 'SC-P6-020 responsive stylesheet loads on SafeContracts pages');
sc_p6export_assert(($GLOBALS['sc_test_enqueued_styles'][AdminShell::RESPONSIVE_STYLE_HANDLE]['deps'][0] ?? '') === AdminShell::SETTINGS_STYLE_HANDLE, 'SC-P6-020 responsive stylesheet layers after existing identity/styles');
unset($_GET['page']);

// SC-P6-021 — admin shell stays capability-gated, scoped and Safe Contracts-branded.
$GLOBALS['sc_test_current_caps'] = [];
sc_p6export_expect(RuntimeException::class, fn () => AdminShell::render(), 'SC-P6-021 admin shell denies users without ACCESS capability');
AdminShell::register();
sc_p6export_assert(($GLOBALS['sc_test_admin_pages'][AdminShell::SLUG]['capability'] ?? '') === Capabilities::ACCESS, 'SC-P6-021 top-level shell remains ACCESS capability-gated');
$_GET['page'] = 'safecontracts-reports';
sc_p6export_assert(AdminShell::isSafeContractsPage(), 'SC-P6-021 SafeContracts child pages are recognized for scoped assets');
$_GET['page'] = 'plugins.php';
sc_p6export_assert(! AdminShell::isSafeContractsPage(), 'SC-P6-021 SafeContracts assets do not load on unrelated WordPress pages');
$adminShellSource = file_get_contents((string) (new ReflectionClass(AdminShell::class))->getFileName()) ?: '';
sc_p6export_assert(str_contains($adminShellSource, 'SafeContracts') && ! str_contains($adminShellSource, 'Ethereum'), 'SC-P6-021 shell preserves locked SafeContracts internal identity');
unset($_GET['page']);

// SC-P6-022 — login branding changes presentation only, not authentication/session behavior.
sc_p6export_assert(LoginBranding::headerUrl('https://wordpress.org/') === 'https://example.test/', 'SC-P6-022 login logo URL resolves to site home');
sc_p6export_assert(str_contains(LoginBranding::headerText('WordPress'), 'Safe Contracts'), 'SC-P6-022 login header text uses Safe Contracts identity');
sc_p6export_assert(isset($GLOBALS['sc_test_actions']['login_enqueue_scripts']) && isset($GLOBALS['sc_test_filters']['login_headerurl']) && isset($GLOBALS['sc_test_filters']['login_headertext']), 'SC-P6-022 branding hooks are registered');
$loginSource = file_get_contents((string) (new ReflectionClass(LoginBranding::class))->getFileName()) ?: '';
foreach (['authenticate', 'wp_authenticate', 'set_auth_cookie', 'clear_auth_cookie'] as $authHook) {
    sc_p6export_assert(! str_contains($loginSource, $authHook), 'SC-P6-022 login branding does not alter auth/session hook ' . $authHook);
}
sc_p6export_assert(! preg_match('/password|credential|token/i', $loginSource), 'SC-P6-022 login branding exposes no credential/token material');

// SC-P6-023 — menu cleanup is UX-only and leaves system managers/administrators untouched.
$GLOBALS['sc_test_removed_admin_menus'] = [];
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
NavigationCleanup::cleanup();
sc_p6export_assert(in_array('plugins.php', $GLOBALS['sc_test_removed_admin_menus'], true) && in_array('options-general.php', $GLOBALS['sc_test_removed_admin_menus'], true), 'SC-P6-023 operational users get irrelevant WordPress menus hidden');
sc_p6export_assert(! in_array(AdminShell::SLUG, $GLOBALS['sc_test_removed_admin_menus'], true), 'SC-P6-023 SafeContracts menu can never be hidden by cleanup');
$removedCount = count($GLOBALS['sc_test_removed_admin_menus']);
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_SYSTEM => true];
NavigationCleanup::cleanup();
sc_p6export_assert(count($GLOBALS['sc_test_removed_admin_menus']) === $removedCount, 'SC-P6-023 system managers retain WordPress administration navigation');
$navSource = file_get_contents((string) (new ReflectionClass(NavigationCleanup::class))->getFileName()) ?: '';
sc_p6export_assert(str_contains($navSource, 'current_user_can') && str_contains($navSource, 'MANAGE_SYSTEM'), 'SC-P6-023 navigation cleanup is capability-driven rather than role-name driven');
sc_p6export_assert(! str_contains($navSource, 'wp_die') && ! str_contains($navSource, 'permission_callback'), 'SC-P6-023 menu cleanup does not pretend to be an authorization boundary');

printf("Safe Contracts P6 Excel/RTL/validation SC-P6-019..023 passed (%d assertions).\n", $tests);
