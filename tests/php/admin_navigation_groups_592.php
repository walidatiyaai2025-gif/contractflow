<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminNavigationGroups;
use SafeContracts\Translations\NavigationArabicDefaults;

$assertions = 0;

$expected = [
    'safecontracts-customers' => 'contracts',
    'safecontracts-suppliers' => 'contracts',
    'safecontracts-contracts' => 'contracts',
    'safecontracts-payments' => 'finance',
    'safecontracts-collections' => 'finance',
    'safecontracts-finance' => 'finance',
    'safecontracts-reports' => 'finance',
    'safecontracts-payment-methods' => 'finance',
    'safecontracts-follow-ups' => 'operations',
    'safecontracts-archive' => 'operations',
    'safecontracts-imports' => 'operations',
    'safecontracts-notification-center' => 'notifications',
    'safecontracts-notifications' => 'notifications',
    'safecontracts-notification-schedule' => 'notifications',
    'safecontracts-notification-settings' => 'notifications',
    'safecontracts-active-users' => 'access',
    'safecontracts-users-roles' => 'access',
    'safecontracts-settings' => 'system',
    'safecontracts-firebase-settings' => 'system',
    'safecontracts-mobile-configuration' => 'system',
    'safecontracts-translations' => 'system',
    'safecontracts-user-guide' => 'help',
    'safecontracts-future-feature' => 'other',
];

foreach ($expected as $slug => $group) {
    $actual = AdminNavigationGroups::groupKeyForSlug($slug);
    if ($actual !== $group) {
        fwrite(STDERR, "FAIL: {$slug} grouped as {$actual}; expected {$group}.\n");
        exit(1);
    }
    $assertions++;
}

$definitions = AdminNavigationGroups::definitions();
foreach (['contracts', 'finance', 'operations', 'notifications', 'access', 'system', 'help', 'other'] as $key) {
    if (! isset($definitions[$key]['title'], $definitions[$key]['description'])) {
        fwrite(STDERR, "FAIL: missing navigation definition {$key}.\n");
        exit(1);
    }
    $assertions++;
}

$requiredArabic = [
    'Parties & Contracts',
    'Finance',
    'Operations',
    'Users & Access',
    'Settings & Integrations',
    'More',
    'Grouped navigation',
];
foreach ($requiredArabic as $source) {
    if (NavigationArabicDefaults::default($source) === $source) {
        fwrite(STDERR, "FAIL: navigation Arabic default missing for {$source}.\n");
        exit(1);
    }
    $assertions++;
}

$sourcePath = dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/AdminNavigationGroups.php';
$source = file_get_contents($sourcePath);
if (! is_string($source) || $source === '') {
    fwrite(STDERR, "FAIL: grouped navigation source is missing.\n");
    exit(1);
}

$requiredMarkers = [
    "current_user_can(Capabilities::ACCESS)",
    'current_user_can($capability)',
    'remove_submenu_page($parent, $slug)',
    "add_filter('submenu_file'",
    'safecontracts_group',
    "return 'other';",
    'AdminNavigationGroups::renderRequestedGroup()',
];
foreach ($requiredMarkers as $marker) {
    $haystack = $marker === 'AdminNavigationGroups::renderRequestedGroup()'
        ? (string) file_get_contents(dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/Admin/AdminShell.php')
        : $source;
    if (! str_contains($haystack, $marker)) {
        fwrite(STDERR, "FAIL: grouped navigation safety marker missing: {$marker}.\n");
        exit(1);
    }
    $assertions++;
}

$forbidden = [
    'remove_menu_page(AdminShell::SLUG)',
    'unregister_post_type(',
    'safecontracts_manage_',
];
foreach ($forbidden as $marker) {
    if (str_contains($source, $marker)) {
        fwrite(STDERR, "FAIL: grouped navigation contains forbidden marker: {$marker}.\n");
        exit(1);
    }
    $assertions++;
}

fwrite(STDOUT, "Alkenzy grouped admin navigation #592 passed ({$assertions} assertions).\n");
