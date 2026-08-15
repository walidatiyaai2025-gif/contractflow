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
use SafeContracts\Notifications\NotificationTemplateRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Settings\MobileConfiguration;

$tests = 0;
function sc_p6settings_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p6settings_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p6settings_assert($error instanceof $class, $message);
        return;
    }
    sc_p6settings_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();

// SC-P6-014 — general SafeContracts settings are non-secret, normalized and capability-gated.
$general = new GeneralSettings();
$defaults = $general->read();
sc_p6settings_assert($defaults['organization_name'] === 'SafeContracts', 'SC-P6-014 general settings have deterministic organization default');
sc_p6settings_assert($defaults['currency_code'] === '', 'SC-P6-014 currency is explicitly unconfigured instead of guessed');
sc_p6settings_assert($defaults['admin_page_size'] === 50, 'SC-P6-014 admin page-size default is deterministic');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6settings_expect(DomainException::class, fn () => $general->save(['organization_name' => 'Denied', 'currency_code' => 'KWD', 'admin_page_size' => 50]), 'SC-P6-014 general settings writes require manage-system capability');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_SYSTEM => true];
$savedGeneral = $general->save(['organization_name' => '  <b>Safe Contracts Co</b> ', 'currency_code' => 'kwd', 'admin_page_size' => '75']);
sc_p6settings_assert($savedGeneral['organization_name'] === 'Safe Contracts Co', 'SC-P6-014 organization name is sanitized');
sc_p6settings_assert($savedGeneral['currency_code'] === 'KWD', 'SC-P6-014 single currency code is normalized');
sc_p6settings_assert($savedGeneral['admin_page_size'] === 75, 'SC-P6-014 admin page size is normalized');
sc_p6settings_assert(($GLOBALS['sc_test_options'][GeneralSettings::OPTION]['currency_code'] ?? '') === 'KWD', 'SC-P6-014 normalized settings persist in WordPress options');
sc_p6settings_expect(InvalidArgumentException::class, fn () => $general->save(['organization_name' => 'Valid', 'currency_code' => 'KWDD', 'admin_page_size' => 50]), 'SC-P6-014 invalid currency code is rejected');
sc_p6settings_expect(InvalidArgumentException::class, fn () => $general->save(['organization_name' => 'Valid', 'currency_code' => '', 'admin_page_size' => 500]), 'SC-P6-014 unsafe page size is rejected');

// SC-P6-018 — mobile config stays non-secret and prepares a later API boundary without adding REST now.
$mobile = new MobileConfiguration();
$mobileDefaults = $mobile->read();
sc_p6settings_assert($mobileDefaults['default_page_size'] === 25, 'SC-P6-018 mobile page-size default is deterministic');
sc_p6settings_assert($mobileDefaults['excel_export_enabled'] === false && $mobileDefaults['push_notifications_enabled'] === false, 'SC-P6-018 future-facing mobile features fail closed by default');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6settings_expect(DomainException::class, fn () => $mobile->save(['default_page_size' => 25]), 'SC-P6-018 mobile configuration writes require manage-system capability');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_SYSTEM => true];
$savedMobile = $mobile->save([
    'support_text' => '<b>Support:</b> call operations',
    'default_page_size' => '40',
    'excel_export_enabled' => true,
    'push_notifications_enabled' => false,
    'collection_entry_enabled' => true,
]);
sc_p6settings_assert($savedMobile['support_text'] === 'Support: call operations', 'SC-P6-018 mobile support text is sanitized');
sc_p6settings_assert($savedMobile['default_page_size'] === 40, 'SC-P6-018 mobile page size is normalized');
sc_p6settings_assert($savedMobile['excel_export_enabled'] === true && $savedMobile['collection_entry_enabled'] === true, 'SC-P6-018 mobile feature flags persist explicitly');
sc_p6settings_expect(InvalidArgumentException::class, fn () => $mobile->save(['default_page_size' => 2]), 'SC-P6-018 invalid mobile page size is rejected');
$mobileOptionJson = json_encode($GLOBALS['sc_test_options'][MobileConfiguration::OPTION] ?? []);
sc_p6settings_assert(is_string($mobileOptionJson) && ! str_contains(strtolower($mobileOptionJson), 'token') && ! str_contains(strtolower($mobileOptionJson), 'password'), 'SC-P6-018 mobile config contains no token/password fields');

// SC-P6-017 — Firebase UI/domain accepts public metadata plus secret reference only.
$firebase = new FirebaseSettings();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6settings_expect(DomainException::class, fn () => $firebase->savePublic(['project_id' => 'p', 'messaging_sender_id' => '123', 'app_id' => 'a']), 'SC-P6-017 Firebase writes require notification-management capability');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_NOTIFICATIONS => true];
$public = $firebase->savePublic(['project_id' => 'safecontracts-prod', 'messaging_sender_id' => '123456789', 'app_id' => '1:123456789:web:abc']);
$reference = $firebase->saveCredentialReference('safecontracts_firebase_service_account');
sc_p6settings_assert($public['project_id'] === 'safecontracts-prod', 'SC-P6-017 Firebase public project metadata saves through domain boundary');
sc_p6settings_assert($reference === 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT', 'SC-P6-017 credential reference is normalized as identifier only');
sc_p6settings_assert(($GLOBALS['sc_test_options'][FirebaseSettings::CREDENTIAL_REFERENCE_OPTION] ?? '') === 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT', 'SC-P6-017 only credential reference is persisted');
sc_p6settings_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference('{"private_key":"SECRET"}'), 'SC-P6-017 raw service-account JSON is rejected');
$summary = $firebase->safeSummary();
sc_p6settings_assert($summary['configured'] === true && $summary['messaging_sender_id'] === '123456789', 'SC-P6-017 safe summary exposes readiness metadata without secret content');
$firebaseOptions = json_encode([
    $GLOBALS['sc_test_options'][FirebaseSettings::PUBLIC_OPTION] ?? [],
    $GLOBALS['sc_test_options'][FirebaseSettings::CREDENTIAL_REFERENCE_OPTION] ?? '',
]);
sc_p6settings_assert(is_string($firebaseOptions) && ! str_contains($firebaseOptions, 'private_key') && ! str_contains($firebaseOptions, 'SECRET'), 'SC-P6-017 stored Firebase options contain no raw secret material');

// SC-P6-016 — settings UI uses existing notification rule/template domain boundaries.
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '3', 'code' => 'due_default', 'title_template' => 'Due {{contract_number}}', 'body_template' => 'Due {{due_date}}',
    'is_active' => '1', 'created_by' => '1', 'updated_by' => '1', 'created_at' => '2026-08-01 00:00:00', 'updated_at' => '2026-08-01 00:00:00',
]]];
$beforeReads = count($GLOBALS['sc_test_read_queries']);
$templates = (new NotificationTemplateRepository())->all(true);
$templateSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $beforeReads));
sc_p6settings_assert(count($templates) === 1 && $templates[0]['code'] === 'due_default', 'SC-P6-016 template selector reads normalized templates');
sc_p6settings_assert(str_contains($templateSql, 'WHERE is_active = 1'), 'SC-P6-016 template selector only offers active templates');
$notificationSource = file_get_contents((string) (new ReflectionClass(NotificationSettingsPage::class))->getFileName()) ?: '';
sc_p6settings_assert(str_contains($notificationSource, 'NotificationRuleService'), 'SC-P6-016 notification settings delegates writes to NotificationRuleService');
sc_p6settings_assert(str_contains($notificationSource, 'original_code'), 'SC-P6-016 existing notification rule code is stable during edits');
sc_p6settings_assert(str_contains($notificationSource, 'target_assigned_accountant') && str_contains($notificationSource, 'escalation_roles'), 'SC-P6-016 notification settings expose targeting and escalation controls');
sc_p6settings_assert(! str_contains($notificationSource, '$wpdb'), 'SC-P6-016 notification settings page contains no direct SQL');

// SC-P6-015 — payment-method settings are integrated into SafeContracts with stable codes.
$paymentMethodSource = file_get_contents((string) (new ReflectionClass(PaymentMethodsPage::class))->getFileName()) ?: '';
sc_p6settings_assert(str_contains($paymentMethodSource, 'AdminShell::SLUG'), 'SC-P6-015 payment-method settings live under SafeContracts shell');
sc_p6settings_assert(str_contains($paymentMethodSource, 'originalCode') && str_contains($paymentMethodSource, 'original_code'), 'SC-P6-015 existing payment method code is immutable through edit workflow');
sc_p6settings_assert(str_contains($paymentMethodSource, 'only active SafeContracts payment methods'), 'SC-P6-015 UI preserves mandatory-active collection semantics');
sc_p6settings_assert(! str_contains($paymentMethodSource, 'add_options_page'), 'SC-P6-015 payment methods no longer leak into generic WordPress Settings navigation');

// Page registrations and plugin lifecycle handlers.
GeneralSettingsPage::register();
PaymentMethodsPage::register();
NotificationSettingsPage::register();
FirebaseSettingsPage::register();
MobileConfigurationPage::register();
$expectedPages = [
    GeneralSettingsPage::SLUG => Capabilities::MANAGE_SYSTEM,
    PaymentMethodsPage::SLUG => Capabilities::MANAGE_REFERENCE_DATA,
    NotificationSettingsPage::SLUG => Capabilities::MANAGE_NOTIFICATIONS,
    FirebaseSettingsPage::SLUG => Capabilities::MANAGE_NOTIFICATIONS,
    MobileConfigurationPage::SLUG => Capabilities::MANAGE_SYSTEM,
];
foreach ($expectedPages as $slug => $capability) {
    sc_p6settings_assert(($GLOBALS['sc_test_admin_pages'][$slug]['parent'] ?? '') === AdminShell::SLUG, $slug . ' registers under SafeContracts shell');
    sc_p6settings_assert(($GLOBALS['sc_test_admin_pages'][$slug]['capability'] ?? '') === $capability, $slug . ' uses the intended server-side capability');
}
$expectedActions = [
    GeneralSettingsPage::SAVE_ACTION,
    PaymentMethodsPage::SAVE_ACTION,
    NotificationSettingsPage::SAVE_ACTION,
    FirebaseSettingsPage::SAVE_ACTION,
    MobileConfigurationPage::SAVE_ACTION,
];
foreach ($expectedActions as $action) {
    sc_p6settings_assert(isset($GLOBALS['sc_test_actions']['admin_post_' . $action]), $action . ' is registered in plugin lifecycle');
}

$firebasePageSource = file_get_contents((string) (new ReflectionClass(FirebaseSettingsPage::class))->getFileName()) ?: '';
sc_p6settings_assert(! str_contains($firebasePageSource, "\$_POST['service_account_json']") && ! str_contains($firebasePageSource, "\$_POST['access_token']"), 'SC-P6-017 Firebase page has no raw secret/token input contract');
$mobilePageSource = file_get_contents((string) (new ReflectionClass(MobileConfigurationPage::class))->getFileName()) ?: '';
sc_p6settings_assert(! str_contains($mobilePageSource, 'register_rest_route') && ! str_contains($mobilePageSource, 'Router::'), 'SC-P6-018 mobile configuration does not implement P8 REST work early');
foreach ([GeneralSettingsPage::class, PaymentMethodsPage::class, NotificationSettingsPage::class, FirebaseSettingsPage::class, MobileConfigurationPage::class] as $pageClass) {
    $source = file_get_contents((string) (new ReflectionClass($pageClass))->getFileName()) ?: '';
    sc_p6settings_assert(! str_contains($source, '$wpdb'), $pageClass . ' keeps persistence out of presentation layer');
}

printf("SafeContracts P6 settings SC-P6-014..018 passed (%d assertions).\n", $tests);
