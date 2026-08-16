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
        "assets/brand/safe-contracts-identity.jpg",
        'base64_encode($bytes)',
    ],
    'wordpress-theme/safecontracts-onepage/inc/brand.php' => [
        "return 'Safe Contracts';",
        "assets/images/safe-contracts-identity.jpg",
        'base64_encode( $bytes )',
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
        'android:label="Alkenzy ADV"',
        '@drawable/alkenzy_launcher',
        'mobile/android-release/alkenzy_launcher.png',
        'Alkenzy launcher icon is not a valid PNG resource',
    ],
    'mobile/lib/core/branding/safe_contracts_brand.dart' => [
        "static const name = 'Alkenzy ADV';",
        "static const assetPath = 'assets/brand/alkenzy_adv.png';",
        'SafeContractsBrandMark',
        'Image.asset(',
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

$brandAssets = [
    $root . '/mobile/assets/brand/safe_contracts_identity.jpg',
    $root . '/wordpress-plugin/safecontracts/assets/brand/safe-contracts-identity.jpg',
    $root . '/wordpress-theme/safecontracts-onepage/assets/images/safe-contracts-identity.jpg',
];
$brandHashes = [];
foreach ($brandAssets as $brandAsset) {
    $bytes = @file_get_contents($brandAsset);
    if (! is_string($bytes) || strlen($bytes) < 1024 || ! str_starts_with($bytes, "\xFF\xD8\xFF")) {
        fwrite(STDERR, "FAIL: packaged Safe Contracts identity is not a valid JPEG: {$brandAsset}\n");
        exit(1);
    }
    $brandHashes[] = hash('sha256', $bytes);
    $count++;
}
if (count(array_unique($brandHashes)) !== 1) {
    fwrite(STDERR, "FAIL: mobile/plugin/theme Safe Contracts identity assets diverged\n");
    exit(1);
}
$count++;

$mobileAlkenzy = @file_get_contents($root . '/mobile/assets/brand/alkenzy_adv.png');
if (! is_string($mobileAlkenzy) || strlen($mobileAlkenzy) < 4096 || ! str_starts_with($mobileAlkenzy, "\x89PNG\r\n\x1a\n")) {
    fwrite(STDERR, "FAIL: packaged Alkenzy ADV mobile identity is not a valid PNG\n");
    exit(1);
}
if (hash('sha256', $mobileAlkenzy) !== 'e703241650eeb984791c4715b4243bf96ba5b273b78eb2e25cd3640c188c57c9') {
    fwrite(STDERR, "FAIL: packaged Alkenzy ADV mobile identity does not match the approved supplied-logo rendition\n");
    exit(1);
}
$count += 2;

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
