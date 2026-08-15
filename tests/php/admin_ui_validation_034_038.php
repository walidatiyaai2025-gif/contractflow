<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\FirebaseSettingsPage;
use SafeContracts\Admin\GeneralSettingsPage;
use SafeContracts\Admin\MobileConfigurationPage;
use SafeContracts\Admin\NotificationSettingsPage;
use SafeContracts\Admin\PaymentMethodsPage;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Settings\MobileConfiguration;

$tests = 0;
function sc_p6v6_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p6v6_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p6v6_assert($error instanceof $class, $message);
        return;
    }
    sc_p6v6_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();

// SC-P6-034 — SafeContracts settings: capability, normalization and non-secret boundary.
$generalSource = file_get_contents((string) (new ReflectionClass(GeneralSettingsPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($generalSource, 'Capabilities::MANAGE_SYSTEM') >= 3, 'SC-P6-034 settings registration/read/write require manage-system capability');
sc_p6v6_assert(str_contains($generalSource, 'check_admin_referer') && str_contains($generalSource, 'GeneralSettings'), 'SC-P6-034 settings writes retain nonce and domain-settings boundary');
sc_p6v6_assert(str_contains($generalSource, 'esc_attr') && str_contains($generalSource, 'esc_html') && ! str_contains($generalSource, '$wpdb'), 'SC-P6-034 settings output is escaped with no presentation-layer SQL');
sc_p6v6_assert(! str_contains($generalSource, 'private_key') && ! str_contains($generalSource, 'access_token') && ! str_contains($generalSource, 'service_account'), 'SC-P6-034 general settings do not accept secret credential material');
$general = new GeneralSettings();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6v6_expect(DomainException::class, fn () => $general->save(['organization_name' => 'Denied', 'currency_code' => 'KWD', 'admin_page_size' => 50]), 'SC-P6-034 unauthorized general-settings write fails closed');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_SYSTEM => true];
$savedGeneral = $general->save(['organization_name' => ' <b>Safe Ops</b> ', 'currency_code' => 'kwd', 'admin_page_size' => '60']);
sc_p6v6_assert($savedGeneral['organization_name'] === 'Safe Ops' && $savedGeneral['currency_code'] === 'KWD' && $savedGeneral['admin_page_size'] === 60, 'SC-P6-034 settings normalize and sanitize before persistence');
sc_p6v6_expect(InvalidArgumentException::class, fn () => $general->save(['organization_name' => 'Safe Ops', 'currency_code' => 'KWDD', 'admin_page_size' => 60]), 'SC-P6-034 malformed currency fails closed');

// SC-P6-035 — Payment-method settings: stable code, capability and collection-integrity semantics.
$methodSource = file_get_contents((string) (new ReflectionClass(PaymentMethodsPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($methodSource, 'Capabilities::MANAGE_REFERENCE_DATA') >= 3, 'SC-P6-035 payment-method registration/read/write require reference-data capability');
sc_p6v6_assert(str_contains($methodSource, 'check_admin_referer') && str_contains($methodSource, 'PaymentMethodRepository'), 'SC-P6-035 method writes retain nonce and repository boundary');
sc_p6v6_assert(str_contains($methodSource, 'original_code') && str_contains($methodSource, '$originalCode !=='), 'SC-P6-035 existing payment-method codes remain stable during edits');
sc_p6v6_assert(str_contains($methodSource, 'is_active') && str_contains($methodSource, 'only active SafeContracts payment methods'), 'SC-P6-035 active-state lifecycle remains authoritative for collection entry');
sc_p6v6_assert(! str_contains($methodSource, '$wpdb') && ! str_contains($methodSource, 'DELETE FROM'), 'SC-P6-035 presentation has no direct SQL or hard-delete path');

// SC-P6-036 — Notification settings: capability, allowed triggers, role/template normalization and due semantics.
$notificationSource = file_get_contents((string) (new ReflectionClass(NotificationSettingsPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($notificationSource, 'Capabilities::MANAGE_NOTIFICATIONS') >= 3, 'SC-P6-036 notification-settings registration/read/write require notification capability');
sc_p6v6_assert(str_contains($notificationSource, 'check_admin_referer') && str_contains($notificationSource, 'NotificationRuleService'), 'SC-P6-036 rule writes retain nonce and domain-service boundary');
sc_p6v6_assert(str_contains($notificationSource, 'NotificationRule::allowedTriggers()') && str_contains($notificationSource, "array_map('sanitize_key', \$_POST['recipient_roles'])") && str_contains($notificationSource, "array_map('sanitize_key', \$_POST['escalation_roles'])"), 'SC-P6-036 trigger and role inputs are constrained/normalized');
sc_p6v6_assert(str_contains($notificationSource, 'NotificationTemplateRepository') && str_contains($notificationSource, 'all(true)') && str_contains($notificationSource, 'original_code'), 'SC-P6-036 rule edits use active templates and stable rule codes');
sc_p6v6_assert(str_contains($notificationSource, 'contractual due-date') && str_contains($notificationSource, 'Settled-payment suppression') && ! str_contains($notificationSource, '$wpdb'), 'SC-P6-036 backend due/settlement semantics remain explicit and presentation has no SQL');

// SC-P6-037 — Firebase settings: public metadata + secret-reference only.
$firebaseSource = file_get_contents((string) (new ReflectionClass(FirebaseSettingsPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($firebaseSource, 'Capabilities::MANAGE_NOTIFICATIONS') >= 3, 'SC-P6-037 Firebase settings registration/read/write require notification capability');
sc_p6v6_assert(str_contains($firebaseSource, 'check_admin_referer') && str_contains($firebaseSource, 'savePublic') && str_contains($firebaseSource, 'saveCredentialReference'), 'SC-P6-037 Firebase writes use nonce and constrained settings APIs');
sc_p6v6_assert(! str_contains($firebaseSource, "\$_POST['service_account_json']") && ! str_contains($firebaseSource, "\$_POST['private_key']") && ! str_contains($firebaseSource, "\$_POST['access_token']"), 'SC-P6-037 page has no raw credential/token input contract');
sc_p6v6_assert(str_contains($firebaseSource, 'credential_reference') && str_contains($firebaseSource, 'esc_attr') && ! str_contains($firebaseSource, '$wpdb'), 'SC-P6-037 credential references are rendered escaped without direct SQL');
$firebase = new FirebaseSettings();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6v6_expect(DomainException::class, fn () => $firebase->savePublic(['project_id' => 'p', 'messaging_sender_id' => '123', 'app_id' => 'a']), 'SC-P6-037 unauthorized Firebase write fails closed');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_NOTIFICATIONS => true];
$firebase->savePublic(['project_id' => 'safecontracts-prod', 'messaging_sender_id' => '123456789', 'app_id' => '1:123456789:web:abc']);
$reference = $firebase->saveCredentialReference('safecontracts_firebase_service_account');
sc_p6v6_assert($reference === 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT', 'SC-P6-037 credential reference is normalized to an identifier');
sc_p6v6_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference('{"private_key":"SECRET"}'), 'SC-P6-037 secret-like JSON is rejected instead of stored');

// SC-P6-038 — Mobile configuration: bounded client-safe bootstrap only; no authorization/business-rule override.
$mobileSource = file_get_contents((string) (new ReflectionClass(MobileConfigurationPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($mobileSource, 'Capabilities::MANAGE_SYSTEM') >= 3, 'SC-P6-038 mobile configuration registration/read/write require manage-system capability');
sc_p6v6_assert(str_contains($mobileSource, 'check_admin_referer') && str_contains($mobileSource, 'MobileConfiguration'), 'SC-P6-038 mobile-config writes retain nonce and settings-domain boundary');
sc_p6v6_assert(str_contains($mobileSource, 'support_text') && str_contains($mobileSource, 'default_page_size') && str_contains($mobileSource, 'excel_export_enabled'), 'SC-P6-038 only bounded bootstrap/feature values are exposed by the page');
sc_p6v6_assert(! str_contains($mobileSource, 'register_rest_route') && ! str_contains($mobileSource, 'Router::') && ! str_contains($mobileSource, '$wpdb'), 'SC-P6-038 validation does not implement P8 REST early or add presentation SQL');
sc_p6v6_assert(! str_contains($mobileSource, 'access_token') && ! str_contains($mobileSource, 'password') && ! str_contains($mobileSource, 'private_key'), 'SC-P6-038 mobile configuration exposes no server-secret fields');
$mobile = new MobileConfiguration();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6v6_expect(DomainException::class, fn () => $mobile->save(['default_page_size' => 25]), 'SC-P6-038 unauthorized mobile-configuration write fails closed');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_SYSTEM => true];
$savedMobile = $mobile->save(['support_text' => '<b>Support desk</b>', 'default_page_size' => '40', 'excel_export_enabled' => true, 'push_notifications_enabled' => false, 'collection_entry_enabled' => true]);
sc_p6v6_assert($savedMobile['support_text'] === 'Support desk' && $savedMobile['default_page_size'] === 40 && $savedMobile['excel_export_enabled'] === true, 'SC-P6-038 mobile config sanitizes and normalizes client-safe values');
sc_p6v6_expect(InvalidArgumentException::class, fn () => $mobile->save(['default_page_size' => 2]), 'SC-P6-038 out-of-range page size fails closed');

// Registration boundaries remain under SafeContracts shell.
GeneralSettingsPage::register();
PaymentMethodsPage::register();
NotificationSettingsPage::register();
FirebaseSettingsPage::register();
MobileConfigurationPage::register();
$expected = [
    GeneralSettingsPage::SLUG => Capabilities::MANAGE_SYSTEM,
    PaymentMethodsPage::SLUG => Capabilities::MANAGE_REFERENCE_DATA,
    NotificationSettingsPage::SLUG => Capabilities::MANAGE_NOTIFICATIONS,
    FirebaseSettingsPage::SLUG => Capabilities::MANAGE_NOTIFICATIONS,
    MobileConfigurationPage::SLUG => Capabilities::MANAGE_SYSTEM,
];
foreach ($expected as $slug => $capability) {
    sc_p6v6_assert(($GLOBALS['sc_test_admin_pages'][$slug]['parent'] ?? '') === AdminShell::SLUG, $slug . ' remains under SafeContracts shell');
    sc_p6v6_assert(($GLOBALS['sc_test_admin_pages'][$slug]['capability'] ?? '') === $capability, $slug . ' retains intended server-side capability');
}

printf("SafeContracts P6 validation SC-P6-034..038 passed (%d assertions).\n", $tests);
