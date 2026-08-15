<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Reports\ReportExportService;
use SafeContracts\Reports\XlsxWorkbook;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p6final_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

SafeContracts\Plugin::instance()->boot();

// SC-P6-039 — Excel report generation final validation.
$reportsPage = file_get_contents((string) (new ReflectionClass(ReportsPage::class))->getFileName()) ?: '';
$exportService = file_get_contents((string) (new ReflectionClass(ReportExportService::class))->getFileName()) ?: '';
$workbookSource = file_get_contents((string) (new ReflectionClass(XlsxWorkbook::class))->getFileName()) ?: '';
sc_p6final_assert(str_contains($reportsPage, 'Capabilities::VIEW_REPORTS') && str_contains($reportsPage, 'Capabilities::EXPORT_REPORTS'), 'SC-P6-039 report view and export capabilities remain separate');
sc_p6final_assert(str_contains($reportsPage, 'check_admin_referer(self::EXPORT_ACTION)'), 'SC-P6-039 admin XLSX export remains nonce-protected');
sc_p6final_assert(str_contains($reportsPage, 'if (current_user_can(Capabilities::EXPORT_REPORTS))'), 'SC-P6-039 users without export capability do not receive the export form');
sc_p6final_assert(str_contains($exportService, 'DashboardFilters::normalize($input)') && str_contains($exportService, 'Capabilities::EXPORT_REPORTS'), 'SC-P6-039 export service independently normalizes filters and enforces export capability');
sc_p6final_assert(str_contains($exportService, "do_action('safecontracts_export_completed'") && str_contains($exportService, "'row_counts'"), 'SC-P6-039 export emits bounded audit evidence');
sc_p6final_assert(! str_contains($exportService, 'password') && ! str_contains($exportService, 'access_token') && ! str_contains($exportService, 'private_key'), 'SC-P6-039 export implementation contains no credential fields');
sc_p6final_assert(str_contains($workbookSource, "in_array(\$text[0], ['=', '+', '-', '@'], true)"), 'SC-P6-039 spreadsheet formula prefixes are neutralized');
$xlsx = (new XlsxWorkbook())->build(['Sheet' => [['Value'], ['=2+2'], ['+cmd'], ['@unsafe']]]);
sc_p6final_assert(str_contains($xlsx, "'=2+2") && str_contains($xlsx, "'+cmd") && str_contains($xlsx, "'@unsafe"), 'SC-P6-039 generated workbook stores formula-like cells as text');
sc_p6final_assert(str_contains($xlsx, '[Content_Types].xml') && str_contains($xlsx, 'xl/workbook.xml') && str_contains($xlsx, 'xl/worksheets/sheet1.xml'), 'SC-P6-039 generated workbook has deterministic XLSX package structure');

// SC-P6-040 — RTL/responsive admin UX final validation.
$responsivePath = dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-responsive.css';
$responsiveCss = file_get_contents($responsivePath) ?: '';
sc_p6final_assert(str_contains($responsiveCss, '[dir="rtl"]') && str_contains($responsiveCss, '.rtl '), 'SC-P6-040 responsive layer supports explicit RTL and WordPress RTL body modes');
sc_p6final_assert(str_contains($responsiveCss, '@media (max-width: 960px)') && str_contains($responsiveCss, '@media (max-width: 782px)') && str_contains($responsiveCss, '@media (max-width: 480px)'), 'SC-P6-040 desktop/tablet/mobile breakpoints remain explicit');
sc_p6final_assert(str_contains($responsiveCss, 'overflow-x: auto') && str_contains($responsiveCss, '-webkit-overflow-scrolling: touch'), 'SC-P6-040 data tables remain horizontally usable on narrow screens');
sc_p6final_assert(str_contains($responsiveCss, '.safecontracts-filter-bar') && str_contains($responsiveCss, '.safecontracts-export-form') && str_contains($responsiveCss, '.safecontracts-field-row'), 'SC-P6-040 filters/export/settings controls share responsive layout treatment');
sc_p6final_assert(str_contains($responsiveCss, 'width: 100%') && str_contains($responsiveCss, 'max-width: 100%'), 'SC-P6-040 narrow-screen controls remain within viewport width');
sc_p6final_assert(! str_contains($responsiveCss, 'safecontracts_manage_') && ! str_contains($responsiveCss, 'current_user_can'), 'SC-P6-040 authorization is not encoded in CSS');

$_GET['page'] = ReportsPage::SLUG;
AdminShell::enqueueAssets();
$chain = $GLOBALS['sc_test_enqueued_styles'];
sc_p6final_assert(isset($chain[AdminShell::RESPONSIVE_STYLE_HANDLE]), 'SC-P6-040 responsive stylesheet loads on SafeContracts pages');
sc_p6final_assert(($chain[AdminShell::RESPONSIVE_STYLE_HANDLE]['deps'] ?? []) === [AdminShell::SETTINGS_STYLE_HANDLE], 'SC-P6-040 responsive stylesheet remains the final SafeContracts CSS layer');
sc_p6final_assert(($chain[AdminShell::SETTINGS_STYLE_HANDLE]['deps'] ?? []) === [AdminShell::OPS_STYLE_HANDLE]
    && ($chain[AdminShell::OPS_STYLE_HANDLE]['deps'] ?? []) === [AdminShell::CORE_STYLE_HANDLE], 'SC-P6-040 CSS dependency order remains deterministic');

printf("SafeContracts P6 final validation SC-P6-039..040 passed (%d assertions).\n", $tests);
