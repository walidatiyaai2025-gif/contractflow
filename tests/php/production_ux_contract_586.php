<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\ActiveUsersPage;
use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\ArchivePage;
use SafeContracts\Admin\CollectionsPage;
use SafeContracts\Admin\ContractsPage;
use SafeContracts\Admin\CustomersPage;
use SafeContracts\Admin\FinancePage;
use SafeContracts\Admin\FirebaseSettingsPage;
use SafeContracts\Admin\FollowUpsPage;
use SafeContracts\Admin\GeneralSettingsPage;
use SafeContracts\Admin\ImportsPage;
use SafeContracts\Admin\MobileConfigurationPage;
use SafeContracts\Admin\NotificationCenterPage;
use SafeContracts\Admin\NotificationSchedulePage;
use SafeContracts\Admin\NotificationSettingsPage;
use SafeContracts\Admin\NotificationsPage;
use SafeContracts\Admin\PaymentMethodsPage;
use SafeContracts\Admin\PaymentsPage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Admin\SuppliersPage;
use SafeContracts\Admin\TranslationsPage;
use SafeContracts\Admin\UserGuideCatalog;
use SafeContracts\Admin\UserGuidePage;
use SafeContracts\Admin\UsersRolesPage;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\CapabilityPresentation;
use SafeContracts\Translations\MigrationRecoveryArabicDefaults;
use SafeContracts\Translations\ProductionUxArabicDefaults;

$tests = 0;
function sc_prod_ux_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$presentations = CapabilityPresentation::all();
sc_prod_ux_assert(array_keys($presentations) === Capabilities::all(), 'every capability has a presentation entry in canonical order');
foreach (Capabilities::all() as $capability) {
    $row = $presentations[$capability] ?? [];
    $label = trim((string) ($row['label'] ?? ''));
    $description = trim((string) ($row['description'] ?? ''));
    sc_prod_ux_assert($label !== '' && $description !== '', 'capability has non-empty label and description: ' . $capability);
    sc_prod_ux_assert(! str_contains($label, 'safecontracts_'), 'capability code is not exposed in label: ' . $capability);
    sc_prod_ux_assert(! str_contains($description, 'safecontracts_'), 'capability code is not exposed in description: ' . $capability);
    sc_prod_ux_assert(ProductionUxArabicDefaults::default($label) !== $label, 'capability label ships with Arabic default: ' . $label);
    sc_prod_ux_assert(ProductionUxArabicDefaults::default($description) !== $description, 'capability description ships with Arabic default: ' . $label);
}

$expectedGuideSlugs = [
    AdminShell::SLUG,
    CustomersPage::SLUG,
    SuppliersPage::SLUG,
    ContractsPage::SLUG,
    PaymentsPage::SLUG,
    CollectionsPage::SLUG,
    FollowUpsPage::SLUG,
    NotificationCenterPage::SLUG,
    NotificationsPage::SLUG,
    NotificationSchedulePage::SLUG,
    FinancePage::SLUG,
    ReportsPage::SLUG,
    ActiveUsersPage::SLUG,
    UsersRolesPage::SLUG,
    ArchivePage::SLUG,
    ImportsPage::SLUG,
    GeneralSettingsPage::SLUG,
    PaymentMethodsPage::SLUG,
    NotificationSettingsPage::SLUG,
    FirebaseSettingsPage::SLUG,
    MobileConfigurationPage::SLUG,
    TranslationsPage::SLUG,
    UserGuidePage::SLUG,
];
$guide = UserGuideCatalog::entries();
foreach ($expectedGuideSlugs as $slug) {
    sc_prod_ux_assert(isset($guide[$slug]), 'registered Alkenzy admin surface has guide coverage: ' . $slug);
    $entry = $guide[$slug] ?? [];
    $purpose = trim((string) ($entry['purpose'] ?? ''));
    $steps = is_array($entry['steps'] ?? null) ? $entry['steps'] : [];
    sc_prod_ux_assert($purpose !== '', 'guide has purpose: ' . $slug);
    sc_prod_ux_assert(count($steps) >= 2, 'guide has actionable steps: ' . $slug);
    sc_prod_ux_assert(ProductionUxArabicDefaults::default($purpose) !== $purpose, 'guide purpose ships with Arabic default: ' . $slug);
    foreach ($steps as $step) {
        $step = (string) $step;
        sc_prod_ux_assert(ProductionUxArabicDefaults::default($step) !== $step, 'guide step ships with Arabic default: ' . $slug . ' :: ' . $step);
    }
}

$root = dirname(__DIR__, 2);
$usersRoles = file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/UsersRolesPage.php');
sc_prod_ux_assert(is_string($usersRoles), 'UsersRolesPage source is readable');
$usersRoles = is_string($usersRoles) ? $usersRoles : '';
sc_prod_ux_assert(! str_contains($usersRoles, '<code><?php echo esc_html($capability); ?></code>'), 'users/roles UI never renders raw capability codes');
sc_prod_ux_assert(! str_contains($usersRoles, '<code><?php echo esc_html($slug); ?></code>'), 'users/roles UI never renders raw role slugs');
sc_prod_ux_assert(! str_contains($usersRoles, "return '#' . \$id"), 'user labels do not expose internal numeric user IDs');
sc_prod_ux_assert(str_contains($usersRoles, 'CapabilityPresentation::label($capability)'), 'users/roles uses friendly permission labels');
sc_prod_ux_assert(str_contains($usersRoles, 'CapabilityPresentation::description($capability)'), 'users/roles explains each permission');

$adminDir = $root . '/wordpress-plugin/safecontracts/src/Admin';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($adminDir));
$lookupNamePattern = '(?:user|customer|supplier|contract|payment|collection|accountant|counterparty|method)_id';
foreach ($iterator as $file) {
    if (! $file instanceof SplFileInfo || ! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if (! is_string($source)) {
        continue;
    }
    $hasRawLookupNumber = preg_match(
        '/<input\b(?=[^>]*type=["\']number["\'])(?=[^>]*name=["\'][^"\']*' . $lookupNamePattern . '[^"\']*["\'])[^>]*>/i',
        $source
    ) === 1;
    sc_prod_ux_assert(! $hasRawLookupNumber, 'admin lookup IDs are selected, never typed as number inputs: ' . $file->getFilename());
}

$mobileDir = $root . '/mobile/lib';
$mobileIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mobileDir));
$mobileLookupLabelPattern = '/labelText\s*:\s*l10n\.t\(\s*["\'][^"\']*(?:user|customer|supplier|contract|payment|collection|accountant|counterparty|media|method)\s+(?:user\s+)?ID\b[^"\']*["\']/i';
foreach ($mobileIterator as $file) {
    if (! $file instanceof SplFileInfo || ! $file->isFile() || strtolower($file->getExtension()) !== 'dart') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if (! is_string($source)) {
        continue;
    }
    sc_prod_ux_assert(
        preg_match($mobileLookupLabelPattern, $source) !== 1,
        'mobile lookup IDs are selected by business label, never typed as raw IDs: ' . $file->getFilename()
    );
}

$collectionDialog = file_get_contents($root . '/mobile/lib/features/payments/collection_entry_dialog.dart');
sc_prod_ux_assert(is_string($collectionDialog) && ! str_contains($collectionDialog, 'Proof media ID (optional)'), 'mobile collection form does not ask users for WordPress media IDs');

$guideSource = file_get_contents($root . '/wordpress-plugin/safecontracts/src/Translations/UserGuideTranslationSources.php');
sc_prod_ux_assert(is_string($guideSource) && str_contains($guideSource, "__('The User Guide explains every Alkenzy ADV area"), 'guide wording is statically discoverable by translation catalog');
$permissionSource = file_get_contents($root . '/wordpress-plugin/safecontracts/src/Roles/CapabilityPresentation.php');
sc_prod_ux_assert(is_string($permissionSource) && str_contains($permissionSource, "__('Access Alkenzy ADV', 'safecontracts')"), 'permission wording is statically discoverable by translation catalog');

foreach (ProductionUxArabicDefaults::all() as $source => $arabic) {
    sc_prod_ux_assert(trim($source) !== '' && trim($arabic) !== '' && $source !== $arabic, 'production UX Arabic default is complete: ' . $source);
}
foreach (MigrationRecoveryArabicDefaults::all() as $source => $arabic) {
    sc_prod_ux_assert(trim($source) !== '' && trim($arabic) !== '' && $source !== $arabic, 'migration recovery Arabic default is complete: ' . $source);
}

$plugin = file_get_contents($root . '/wordpress-plugin/safecontracts/src/Plugin.php');
sc_prod_ux_assert(is_string($plugin) && str_contains($plugin, 'UserGuidePage::registerContextualHelp()'), 'contextual guide is wired into every Alkenzy admin request');
sc_prod_ux_assert(is_string($plugin) && str_contains($plugin, "[UserGuidePage::class, 'register']"), 'full user guide page is wired into admin navigation');
sc_prod_ux_assert(is_string($plugin) && str_contains($plugin, 'MigrationRecoveryPage::register()'), 'migration failures switch plugin to recovery-only surface');

fwrite(STDOUT, "Alkenzy production UX contract #586 passed ({$tests} assertions).\n");
