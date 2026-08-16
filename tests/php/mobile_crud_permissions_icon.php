<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrations\Migration0016MobileCrudCapabilities;
use SafeContracts\Rest\MobileCrudController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();

do_action('plugins_loaded');
do_action('rest_api_init');

foreach ([
    Capabilities::CREATE_CUSTOMERS,
    Capabilities::EDIT_CUSTOMERS,
    Capabilities::CREATE_CONTRACTS,
    Capabilities::EDIT_CONTRACTS,
    Capabilities::CREATE_PAYMENTS,
    Capabilities::EDIT_PAYMENTS,
] as $capability) {
    $assert(in_array($capability, Capabilities::all(), true), "{$capability} is exposed to Users & Roles and session capability payloads");
}

$manager = $GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER]->capabilities;
$assert(isset($manager[Capabilities::CREATE_CUSTOMERS]), 'manager can create customers by default');
$assert(isset($manager[Capabilities::EDIT_CUSTOMERS]), 'manager can edit customers by default');
$assert(isset($manager[Capabilities::CREATE_PAYMENTS]), 'manager can create payments by default');
$assert(isset($manager[Capabilities::EDIT_PAYMENTS]), 'manager can edit payments by default');

$accountant = $GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT]->capabilities;
$assert(! isset($accountant[Capabilities::CREATE_CUSTOMERS]), 'accountant customer creation remains opt-in');
$assert(! isset($accountant[Capabilities::EDIT_CUSTOMERS]), 'accountant customer editing remains opt-in');
$assert(isset($accountant[Capabilities::CREATE_PAYMENTS]), 'accountant can create scoped payments by default');
$assert(isset($accountant[Capabilities::EDIT_PAYMENTS]), 'accountant can edit scoped payments by default');

unset(
    $GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER]->capabilities[Capabilities::CREATE_CUSTOMERS],
    $GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER]->capabilities[Capabilities::EDIT_CUSTOMERS],
    $GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT]->capabilities[Capabilities::CREATE_PAYMENTS],
    $GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT]->capabilities[Capabilities::EDIT_PAYMENTS]
);
(new Migration0016MobileCrudCapabilities())->up($GLOBALS['wpdb']);
$assert(isset($GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER]->capabilities[Capabilities::CREATE_CUSTOMERS]), 'upgrade migration restores manager customer create baseline');
$assert(isset($GLOBALS['sc_test_roles'][RoleRegistrar::MANAGER]->capabilities[Capabilities::EDIT_CUSTOMERS]), 'upgrade migration restores manager customer edit baseline');
$assert(isset($GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT]->capabilities[Capabilities::CREATE_PAYMENTS]), 'upgrade migration derives payment create from legacy manage-payments grant');
$assert(isset($GLOBALS['sc_test_roles'][RoleRegistrar::ACCOUNTANT]->capabilities[Capabilities::EDIT_PAYMENTS]), 'upgrade migration derives payment edit from legacy manage-payments grant');

$routes = [
    '/mobile/customers/create',
    '/mobile/customers/(?P<id>\\d+)/edit',
    '/mobile/contracts/create',
    '/mobile/contracts/(?P<id>\\d+)/edit',
    '/mobile/payments/create',
    '/mobile/payments/(?P<id>\\d+)/edit',
];
foreach ($routes as $route) {
    $assert(isset($GLOBALS['sc_test_routes'][Router::NAMESPACE . $route]), "mobile CRUD route {$route} is registered");
}

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
foreach ([
    [MobileCrudController::class, 'canCreateCustomers'],
    [MobileCrudController::class, 'canEditCustomers'],
    [MobileCrudController::class, 'canCreateContracts'],
    [MobileCrudController::class, 'canEditContracts'],
    [MobileCrudController::class, 'canCreatePayments'],
    [MobileCrudController::class, 'canEditPayments'],
] as $callback) {
    $assert($callback() instanceof WP_Error, 'CRUD operation remains forbidden without its explicit capability');
}

foreach ([
    Capabilities::CREATE_CUSTOMERS => [MobileCrudController::class, 'canCreateCustomers'],
    Capabilities::EDIT_CUSTOMERS => [MobileCrudController::class, 'canEditCustomers'],
    Capabilities::CREATE_CONTRACTS => [MobileCrudController::class, 'canCreateContracts'],
    Capabilities::EDIT_CONTRACTS => [MobileCrudController::class, 'canEditContracts'],
    Capabilities::CREATE_PAYMENTS => [MobileCrudController::class, 'canCreatePayments'],
    Capabilities::EDIT_PAYMENTS => [MobileCrudController::class, 'canEditPayments'],
] as $capability => $callback) {
    $GLOBALS['sc_test_current_caps'] = [
        Capabilities::ACCESS => true,
        Capabilities::VIEW_ALL => true,
        $capability => true,
    ];
    $assert($callback() === true, "{$capability} authorizes only its matching mobile operation");
}

$root = dirname(__DIR__, 2);
$editor = (string) file_get_contents($root . '/mobile/lib/features/records/mobile_record_editor_screen.dart');
foreach ([
    'safecontracts_create_customers',
    'safecontracts_edit_customers',
    'safecontracts_create_contracts',
    'safecontracts_edit_contracts',
    'safecontracts_create_payments',
    'safecontracts_edit_payments',
    'mobile/customers/create',
    'mobile/contracts/create',
    'mobile/payments/create',
    "RegExp(r'^\\d+(?:\\.\\d{1,2})?\$')",
] as $marker) {
    $assert(str_contains($editor, $marker), "mobile record editor contains {$marker}");
}

$profile = (string) file_get_contents($root . '/mobile/lib/features/profile/profile_screen.dart');
$assert(str_contains($profile, 'MobileRecordEditorScreen'), 'profile exposes the authorized data-management screen');
$assert(str_contains($profile, 'إضافة / تعديل العملاء والعقود والدفعات'), 'Arabic data-management entry is present');

$bootstrap = (string) file_get_contents($root . '/scripts/bootstrap_android.sh');
$assert(str_contains($bootstrap, 'alkenzy_launcher.png'), 'Android bootstrap packages the supplied Alkenzy PNG launcher resource');
$assert(str_contains($bootstrap, 'android:label="Alkenzy ADV"'), 'Android manifest label is changed to Alkenzy ADV');
$assert(str_contains($bootstrap, 'android:icon="@drawable/alkenzy_launcher"'), 'Android manifest launcher icon is changed to Alkenzy');
$assert(str_contains($bootstrap, 'android:roundIcon="@drawable/alkenzy_launcher"'), 'Android manifest round launcher icon is changed to Alkenzy');
$assert(! str_contains($bootstrap, 'android:icon="@drawable/safe_contracts_brand"'), 'old Safe Contracts launcher icon is no longer used');
$icon = (string) file_get_contents($root . '/mobile/android-release/alkenzy_launcher.png');
$assert(str_starts_with($icon, "\x89PNG\r\n\x1a\n"), 'Alkenzy launcher is a valid PNG');
$assert(hash('sha256', $icon) === 'e703241650eeb984791c4715b4243bf96ba5b273b78eb2e25cd3640c188c57c9', 'Alkenzy launcher bytes match the approved supplied-logo rendition');

printf("SafeContracts mobile CRUD permissions + Alkenzy ADV identity regression passed (%d checks).\n", $checks);
