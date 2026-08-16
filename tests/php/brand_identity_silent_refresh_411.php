<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'wordpress-plugin/safecontracts/safecontracts.php' => [
        'Plugin Name: Safe Contracts',
        'Contract receivables tracking backend and administration foundation for Safe Contracts.',
    ],
    'wordpress-plugin/safecontracts/src/Support/Brand.php' => [
        "public const NAME = 'Safe Contracts'",
        'data:image/jpeg;base64,',
    ],
    'wordpress-plugin/safecontracts/src/Admin/AdminShell.php' => [
        'use SafeContracts\\Support\\Brand;',
        'Brand::iconDataUri()',
        'Brand::NAME',
    ],
    'wordpress-theme/safecontracts-onepage/inc/brand.php' => [
        "return 'Safe Contracts';",
        'data:image/jpeg;base64,',
        "add_action( 'wp_head', 'safecontracts_brand_favicon', 1 );",
    ],
    'wordpress-theme/safecontracts-onepage/header.php' => [
        'safecontracts_brand_icon_data_uri()',
        'safecontracts_brand_name()',
    ],
    'wordpress-theme/safecontracts-onepage/footer.php' => [
        'safecontracts_brand_icon_data_uri()',
        'safecontracts_brand_name()',
    ],
    'wordpress-theme/safecontracts-onepage/inc/admin-branding.php' => [
        'sc-adminbar-brand-image',
        'sc-dashboard-brand-image',
        'safecontracts_brand_icon_data_uri()',
        'safecontracts_login_logo_title',
    ],
    'scripts/bootstrap_android.sh' => [
        'android:label="Safe Contracts"',
        '@drawable/safe_contracts_brand',
        'Safe Contracts brand JPEG is missing from mobile brand source',
    ],
    'mobile/lib/core/branding/safe_contracts_brand.dart' => [
        "static const name = 'Safe Contracts';",
        'static const jpegBase64',
        'SafeContractsBrandMark',
    ],
    'mobile/lib/features/navigation/app_shell.dart' => [
        'SafeContractsBrand.name',
        'refreshSilently()',
        'DashboardContextScreen(',
    ],
    'mobile/lib/features/dashboard/dashboard_context_screen.dart' => [
        "isArabic ? 'بيانات الجهة' : 'Dashboard entity'",
        'selectedCustomerName',
        'All figures and indicators below are filtered for this entity.',
    ],
    'mobile/lib/features/refresh/silent_refresh.dart' => [
        'extension DashboardSilentRefresh',
        'keep the last good snapshot',
        'refreshSilently()',
    ],
];

$count = 0;
foreach ($checks as $relative => $markers) {
    $path = $root . '/' . $relative;
    if (! is_file($path)) {
        fwrite(STDERR, "FAIL: missing #411 regression file {$relative}\n");
        exit(1);
    }
    $content = (string) file_get_contents($path);
    foreach ($markers as $marker) {
        if (! str_contains($content, $marker)) {
            fwrite(STDERR, "FAIL: {$relative} missing #411 marker {$marker}\n");
            exit(1);
        }
        $count++;
    }
}

// Validate the actual runtime brand API instead of scraping a long Base64
// literal from PHP source. This is the exact value WordPress admin/login use.
require_once $root . '/wordpress-plugin/safecontracts/src/Support/Brand.php';
$brandUri = \SafeContracts\Support\Brand::iconDataUri();
$prefix = 'data:image/jpeg;base64,';
if (! str_starts_with($brandUri, $prefix)) {
    fwrite(STDERR, "FAIL: plugin brand API does not return a JPEG data URI\n");
    exit(1);
}
$decoded = base64_decode(substr($brandUri, strlen($prefix)), true);
if (! is_string($decoded) || strlen($decoded) < 1024 || ! str_starts_with($decoded, "\xFF\xD8\xFF")) {
    $length = is_string($decoded) ? strlen($decoded) : 0;
    fwrite(STDERR, "FAIL: plugin brand API returned invalid JPEG bytes (length={$length})\n");
    exit(1);
}
$count++;

$appShell = (string) file_get_contents($root . '/mobile/lib/features/navigation/app_shell.dart');
$forbiddenAutomaticCalls = [
    'await widget.dashboardController.refresh();',
    'await widget.customersController.refresh();',
    'await widget.contractsController.refresh();',
    'await widget.notificationsController.refresh();',
    'await widget.profileController.load();',
];
foreach ($forbiddenAutomaticCalls as $forbidden) {
    if (str_contains($appShell, $forbidden)) {
        fwrite(STDERR, "FAIL: automatic app-shell refresh still uses visible loading path {$forbidden}\n");
        exit(1);
    }
    $count++;
}

printf("Safe Contracts #411 brand + silent refresh validation passed (%d checks).\n", $count);
