<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminFeedback;
use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\DashboardPage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Audit\ContractArchiveAuditRecorder;
use SafeContracts\Contracts\ContractArchiveRepository;
use SafeContracts\Contracts\ContractArchiveService;
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
sc_p6final_assert(str_contains($reportsPage, 'current_user_can(Capabilities::EXPORT_REPORTS)') && str_contains($reportsPage, "empty(\$filters['date_range_error'])"), 'SC-P6-039 users without export capability or with an invalid period do not receive the export form');
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

// Issue #390 — user-visible locale-aware validation feedback and safe dashboard deletion.
// Issue #404 centralizes EN/AR copy, so the screen now renders one selected language
// instead of hard-coding both languages into each control.
$feedbackSource = file_get_contents((string) (new ReflectionClass(AdminFeedback::class))->getFileName()) ?: '';
$dashboardSource = file_get_contents((string) (new ReflectionClass(DashboardPage::class))->getFileName()) ?: '';
$archiveRepositorySource = file_get_contents((string) (new ReflectionClass(ContractArchiveRepository::class))->getFileName()) ?: '';
$archiveServiceSource = file_get_contents((string) (new ReflectionClass(ContractArchiveService::class))->getFileName()) ?: '';
$archiveAuditSource = file_get_contents((string) (new ReflectionClass(ContractArchiveAuditRecorder::class))->getFileName()) ?: '';
$pluginSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Plugin.php') ?: '';
$translationCatalogSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Translations/TranslationCatalog.php') ?: '';
$adminArabicSource = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Translations/AdminArabicDefaults.php') ?: '';
$feedbackJs = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-feedback.js') ?: '';
$feedbackCss = file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-feedback.css') ?: '';

sc_p6final_assert(str_contains($feedbackSource, "safecontracts_status") && str_contains($feedbackSource, "'safecontracts'") && str_contains($feedbackSource, "'saved'") && str_contains($feedbackSource, "'invalid'"), '#390 server redirects have visible SafeContracts-localized success/error feedback');
sc_p6final_assert(str_contains($feedbackSource, 'Check the form') && str_contains($adminArabicSource, 'راجع البيانات') && str_contains($feedbackSource, 'aria-live'), '#390 feedback contract retains English/Arabic accessible toast copy through the central catalog');
sc_p6final_assert(str_contains($translationCatalogSource, 'get_user_locale') && str_contains($translationCatalogSource, 'gettext_safecontracts'), '#390/#404 locale selection is centralized and scoped to the SafeContracts text domain');
sc_p6final_assert(str_contains($feedbackJs, 'checkValidity()') && str_contains($feedbackJs, "aria-invalid") && str_contains($feedbackJs, 'first.focus'), '#390 client validation marks and focuses invalid fields without replacing server authority');
sc_p6final_assert(str_contains($feedbackJs, 'data-safecontracts-delete-form') && str_contains($feedbackJs, 'window.confirm'), '#390 dashboard delete requires an explicit browser confirmation');
sc_p6final_assert(str_contains($feedbackCss, '.safecontracts-toast--error') && str_contains($feedbackCss, '.safecontracts-field-invalid'), '#390 validation toast and invalid-field states have dedicated visual treatment');

sc_p6final_assert(str_contains($dashboardSource, 'ContractArchiveService') && str_contains($dashboardSource, 'Capabilities::MANAGE_SYSTEM'), '#390 dashboard delete delegates to a capability-protected domain service');
sc_p6final_assert(str_contains($dashboardSource, "check_admin_referer(self::ARCHIVE_ACTION . '_' . \$contractId)") && str_contains($dashboardSource, 'data-safecontracts-delete-form'), '#390 dashboard archive mutation is nonce protected and wired to confirmation UX');
sc_p6final_assert(str_contains($dashboardSource, 'is_archived') && str_contains($dashboardSource, "esc_html__('Delete', 'safecontracts')") && ! str_contains($dashboardSource, '$wpdb'), '#390 dashboard exposes localized delete while persistence remains outside presentation');
sc_p6final_assert(! str_contains($dashboardSource, ' / حذف') && ! str_contains($dashboardSource, ' / فتح'), '#404 dashboard no longer hard-codes mixed-language action labels');

sc_p6final_assert(str_contains($archiveRepositorySource, 'SET is_archived = 1') && ! str_contains(strtoupper($archiveRepositorySource), 'DELETE FROM'), '#390 delete is a soft archive and cannot physically delete contract rows');
sc_p6final_assert(str_contains($archiveServiceSource, 'Capabilities::MANAGE_SYSTEM') && str_contains($archiveServiceSource, 'VIEW_ALL') && str_contains($archiveServiceSource, 'VIEW_ASSIGNED'), '#390 archive service enforces admin capability and data scope');
sc_p6final_assert(str_contains($archiveServiceSource, "do_action('safecontracts_contract_archived'") && str_contains($archiveAuditSource, "'contract_archived'") && str_contains($archiveAuditSource, "'is_archived' => true"), '#390 safe archive emits durable audit evidence');
sc_p6final_assert(str_contains($pluginSource, "AdminFeedback::class, 'enqueueAssets'") && str_contains($pluginSource, "AdminFeedback::class, 'render'") && str_contains($pluginSource, 'DashboardPage::ARCHIVE_ACTION') && str_contains($pluginSource, 'ContractArchiveAuditRecorder::register'), '#390 plugin bootstrap wires feedback, archive endpoint and audit recorder');

printf("SafeContracts P6 final validation SC-P6-039..040 + UX #390/#404 passed (%d assertions).\n", $tests);
