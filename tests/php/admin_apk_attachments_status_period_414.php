<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . '/wordpress-plugin/safecontracts/src/Admin/AdminPeriodFilter.php';
require_once $root . '/wordpress-plugin/safecontracts/src/Contracts/ContractStatus.php';

use SafeContracts\Admin\AdminPeriodFilter;
use SafeContracts\Contracts\ContractStatus;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$valid = AdminPeriodFilter::normalize(['date_from' => '2026-08-01', 'date_to' => '2026-08-31']);
$assert($valid['date_from'] === '2026-08-01', 'valid period start must be preserved');
$assert($valid['date_to'] === '2026-08-31', 'valid period end must be preserved');
$assert($valid['date_range_error'] === false, 'valid period must not report an error');

$inverted = AdminPeriodFilter::normalize(['date_from' => '2026-08-31', 'date_to' => '2026-08-01']);
$assert($inverted['date_range_error'] === true, 'inverted period must be rejected');
$assert($inverted['date_from'] === null && $inverted['date_to'] === null, 'inverted period must not be silently swapped');

$invalid = AdminPeriodFilter::normalize(['date_from' => '2026-02-30', 'date_to' => '2026-08-31']);
$assert($invalid['date_range_error'] === true, 'invalid calendar dates must be rejected');

$assert(ContractStatus::allowedTargets(ContractStatus::DRAFT) === [ContractStatus::ACTIVE, ContractStatus::CANCELLED], 'draft transitions must stay canonical');
$assert(ContractStatus::allowedTargets(ContractStatus::ACTIVE) === [ContractStatus::COMPLETED, ContractStatus::CANCELLED], 'active transitions must stay canonical');
$assert(ContractStatus::allowedTargets(ContractStatus::COMPLETED) === [], 'completed contracts must stay terminal');
$assert(ContractStatus::allowedTargets(ContractStatus::CANCELLED) === [], 'cancelled contracts must stay terminal');

$markers = [
    'wordpress-theme/safecontracts-onepage/inc/apk-download.php' => [
        "'enabled'  => false",
        'releases/download/mobile-latest/SafeContracts-Mobile.apk',
        "'https' !== \$scheme",
        "'github.com' !== \$host",
        '/walidatiyaai2025-gif/contractflow/releases/',
        'safecontracts_render_apk_download_admin_page',
    ],
    'wordpress-theme/safecontracts-onepage/header.php' => [
        'safecontracts_apk_download_url()',
        'safecontracts_apk_download_label()',
        'sc-header-apk',
    ],
    'wordpress-plugin/safecontracts/src/Admin/ContractsPage.php' => [
        '$service->changeStatus($contractId, $targetStatus);',
        'ContractStatus::allowedTargets',
        "name=\"status\"",
        'self::renderFilters($filters, $selectedId)',
        "name=\"financial_direction\"",
        "name=\"year\"",
        'AdminYearOptions::forCurrentUser',
    ],
    'wordpress-plugin/safecontracts/src/Admin/AdminReadRepository.php' => [
        'collectorAttachments',
        'cl.proof_media_id IS NOT NULL',
        'cl.proof_media_id > 0',
        "'cl.collection_date'",
        "'p.due_date'",
        "'COALESCE(c.start_date, DATE(c.created_at))'",
    ],
    'wordpress-plugin/safecontracts/src/Admin/CollectorAttachmentView.php' => [
        "get_post_type(\$mediaId) !== 'attachment'",
        'wp_get_attachment_url',
        'wp_get_attachment_image_url',
        'noopener noreferrer',
    ],
    'wordpress-plugin/safecontracts/src/Admin/DashboardPage.php' => [
        'Collector attachments',
        'CollectorAttachmentView::render',
        'AdminPeriodFilter::renderFields',
    ],
    'wordpress-plugin/safecontracts/src/Admin/CollectionsPage.php' => [
        'CollectorAttachmentView::render',
        'AdminPeriodFilter::render',
    ],
    'wordpress-plugin/safecontracts/src/Admin/CustomersPage.php' => ['AdminPeriodFilter::render'],
    'wordpress-plugin/safecontracts/src/Admin/PaymentsPage.php' => ['AdminPeriodFilter::render'],
    'wordpress-plugin/safecontracts/src/Admin/FollowUpsPage.php' => ['AdminPeriodFilter::render'],
    'wordpress-plugin/safecontracts/src/Admin/NotificationsPage.php' => ['AdminPeriodFilter::render'],
    'wordpress-plugin/safecontracts/src/Admin/ReportsPage.php' => ['AdminPeriodFilter::renderFields'],
    'wordpress-plugin/safecontracts/src/Admin/ImportPeriodNotice.php' => ['AdminPeriodFilter::render'],
    'wordpress-plugin/safecontracts/src/Import/ImportRunRepository.php' => ['DATE(created_at) >= %s', 'DATE(created_at) <= %s'],
];

foreach ($markers as $relative => $expected) {
    $path = $root . '/' . $relative;
    $assert(is_file($path), "missing #414 file {$relative}");
    $content = (string) file_get_contents($path);
    foreach ($expected as $marker) {
        $assert(str_contains($content, $marker), "{$relative} missing #414 marker {$marker}");
    }
}

$themeStyle = (string) file_get_contents($root . '/wordpress-theme/safecontracts-onepage/style.css');
$assert(str_contains($themeStyle, 'Version: 0.5.0'), 'theme version must identify APK CTA release');

printf("Safe Contracts issue #414 APK/attachments/status/period validation passed (%d assertions).\n", $assertions);
