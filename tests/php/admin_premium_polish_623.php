<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;
use SafeContracts\Settings\MobileLandingContent;

$assertions = 0;
$root = dirname(__DIR__, 2);

$landing = new MobileLandingContent();
$saved = $landing->save([
    'brand_name' => 'Alkenzy Premium',
    'agency_name' => ['ar' => 'الكنزي بريميوم', 'en' => 'Alkenzy Premium Agency'],
    'headline' => ['ar' => 'عنوان تجريبي', 'en' => 'Test headline'],
    'highlight' => ['ar' => 'قيمة واضحة', 'en' => 'Clear value'],
    'summary' => ['ar' => 'ملخص الصفحة', 'en' => 'Landing summary'],
    'experience_years' => 14,
    'services' => [[
        'key' => 'strategy',
        'title' => ['ar' => 'استراتيجية مخصصة', 'en' => 'Custom strategy'],
        'subtitle' => ['ar' => 'وصف مخصص', 'en' => 'Custom description'],
    ]],
    'phones' => ['+20 100 123 4567'],
    'office_address' => ['ar' => 'الجيزة', 'en' => 'Giza'],
    'sign_in_label' => ['ar' => 'دخول الآن', 'en' => 'Sign in now'],
    'learn_more_label' => ['ar' => 'المزيد', 'en' => 'More'],
]);

$checks = [
    ($saved['brand_name'] ?? null) === 'Alkenzy Premium' => 'custom landing brand was not persisted',
    ($saved['headline']['ar'] ?? null) === 'عنوان تجريبي' => 'Arabic landing headline was not persisted',
    ($saved['headline']['en'] ?? null) === 'Test headline' => 'English landing headline was not persisted',
    ($saved['experience_years'] ?? null) === 14 => 'experience years were not persisted',
    ($saved['services'][0]['title']['en'] ?? null) === 'Custom strategy' => 'landing service content was not persisted',
    ($saved['contact']['phones'][0] ?? null) === '+20 100 123 4567' => 'landing phone was not persisted',
    ($saved['sign_in_label']['ar'] ?? null) === 'دخول الآن' => 'landing CTA was not persisted',
];
foreach ($checks as $ok => $message) {
    if (! $ok) {
        fwrite(STDERR, 'FAIL: ' . $message . ".\n");
        exit(1);
    }
    $assertions++;
}

$files = [
    'shell' => $root . '/wordpress-plugin/safecontracts/src/Admin/AdminShell.php',
    'dashboard' => $root . '/wordpress-plugin/safecontracts/src/Admin/AdminPremiumDashboardEnhancements.php',
    'finance' => $root . '/wordpress-plugin/safecontracts/src/Admin/AdminFinancePremiumEnhancements.php',
    'mobile' => $root . '/wordpress-plugin/safecontracts/src/Admin/MobileConfigurationPage.php',
    'css' => $root . '/wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-polish.css',
];
$sources = [];
foreach ($files as $key => $path) {
    $source = file_get_contents($path);
    if (! is_string($source) || $source === '') {
        fwrite(STDERR, "FAIL: missing premium polish source {$key}.\n");
        exit(1);
    }
    $sources[$key] = $source;
    $assertions++;
}

$required = [
    'shell' => [
        'safecontracts-admin-polish.css',
        'POLISH_STYLE_HANDLE',
        '[self::PREMIUM_STYLE_HANDLE]',
    ],
    'dashboard' => [
        'safecontracts-premium-actions',
        'dashicons-smartphone',
        'safecontracts-mobile-landing-content',
        'dashboard.appendChild(chart)',
        "document.addEventListener('click'",
        "document.addEventListener('keydown'",
    ],
    'finance' => [
        "document.createElementNS(svgNs, 'polyline')",
        'safecontracts-cash-flow-chart__line--in',
        'safecontracts-cash-flow-chart__line--out',
        'finance.appendChild(chart)',
        'groups = new Map()',
    ],
    'mobile' => [
        'MobileLandingContent',
        'safecontracts-mobile-landing-content',
        'landing_service_',
        'Save Mobile & Landing Configuration',
    ],
    'css' => [
        'body.rtl .safecontracts-dashboard-v2__kpis',
        '.safecontracts-navigation-group__cards{',
        'display:grid!important',
        'min-height:44px!important',
        '.safecontracts-landing-editor__grid',
        '.safecontracts-cash-flow-chart__line--in',
    ],
];
foreach ($required as $key => $markers) {
    foreach ($markers as $marker) {
        if (! str_contains($sources[$key], $marker)) {
            fwrite(STDERR, "FAIL: {$key} is missing premium marker: {$marker}.\n");
            exit(1);
        }
        $assertions++;
    }
}

// The old finance bar renderer and early dashboard chart insertion would
// regress the exact screenshot defects reported for this pass.
$forbidden = [
    'finance' => ['safecontracts-cash-flow-chart__bars', "cashCard.insertAdjacentElement('beforebegin', chart)"],
    'dashboard' => ["kpiGrid?.insertAdjacentElement('afterend', chart)"],
];
foreach ($forbidden as $key => $markers) {
    foreach ($markers as $marker) {
        if (str_contains($sources[$key], $marker)) {
            fwrite(STDERR, "FAIL: {$key} contains obsolete premium marker: {$marker}.\n");
            exit(1);
        }
        $assertions++;
    }
}

$_GET = ['page' => AdminShell::SLUG];
AdminShell::enqueueAssets();
$polish = $GLOBALS['sc_test_enqueued_styles'][AdminShell::POLISH_STYLE_HANDLE] ?? null;
if (! is_array($polish) || ($polish['deps'][0] ?? null) !== AdminShell::PREMIUM_STYLE_HANDLE) {
    fwrite(STDERR, "FAIL: final polish stylesheet is not loaded after premium CSS.\n");
    exit(1);
}
$assertions++;

fwrite(STDOUT, "Alkenzy admin premium polish #623 passed ({$assertions} assertions).\n");
