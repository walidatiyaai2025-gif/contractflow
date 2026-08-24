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
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
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

// SC-P6-034 — SafeContracts settings.
$generalSource = file_get_contents((string) (new ReflectionClass(GeneralSettingsPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($generalSource, 'Capabilities::MANAGE_SYSTEM') >= 3, 'SC-P6-034 registration/read/write require manage-system capability');
sc_p6v6_assert(str_contains($generalSource, 'check_admin_referer') && str_contains($generalSource, 'GeneralSettings'), 'SC-P6-034 writes retain nonce and domain boundary');
sc_p6v6_assert(! str_contains($generalSource, '$wpdb') && ! str_contains($generalSource, 'private_key'), 'SC-P6-034 general settings stay non-secret and contain no presentation SQL');
$general = new GeneralSettings();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6v6_expect(DomainException::class, fn () => $general->save(['organization_name'=>'Denied','currency_code'=>'KWD','admin_page_size'=>50]), 'SC-P6-034 unauthorized write fails closed');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM => true];
sc_p6v6_expect(InvalidArgumentException::class, fn () => $general->save(['organization_name'=>['Bad'],'currency_code'=>'KWD','admin_page_size'=>50]), 'SC-P6-034 non-scalar organization input is rejected');
$savedGeneral = $general->save(['organization_name'=>' <b>Safe Ops</b> ','currency_code'=>'kwd','admin_page_size'=>'60']);
sc_p6v6_assert($savedGeneral['organization_name'] === 'Safe Ops' && $savedGeneral['currency_code'] === 'KWD' && $savedGeneral['admin_page_size'] === 60, 'SC-P6-034 valid settings normalize before persistence');

// SC-P6-035 — Payment-method settings.
$methodSource = file_get_contents((string) (new ReflectionClass(PaymentMethodsPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($methodSource, 'Capabilities::MANAGE_REFERENCE_DATA') >= 3, 'SC-P6-035 read/write/register require reference-data capability');
sc_p6v6_assert(str_contains($methodSource, 'original_code') && str_contains($methodSource, 'Stable code'), 'SC-P6-035 existing method code stays stable');
sc_p6v6_assert(str_contains($methodSource, 'is_active') && ! str_contains($methodSource, 'DELETE FROM'), 'SC-P6-035 method lifecycle is activation/deactivation, not hard delete');
$methods = new PaymentMethodRepository();
sc_p6v6_expect(InvalidArgumentException::class, fn () => $methods->save(['code'=>['cash'],'name'=>'Cash','display_order'=>1,'is_active'=>true]), 'SC-P6-035 array code input is rejected');
sc_p6v6_expect(InvalidArgumentException::class, fn () => $methods->save(['code'=>'cash','name'=>'Cash','display_order'=>['1'],'is_active'=>true]), 'SC-P6-035 array order input is rejected');

// SC-P6-036 — Notification settings.
$notificationSource = file_get_contents((string) (new ReflectionClass(NotificationSettingsPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($notificationSource, 'Capabilities::MANAGE_NOTIFICATIONS') >= 3, 'SC-P6-036 notification settings require notification capability');
sc_p6v6_assert(str_contains($notificationSource, 'NotificationRuleService') && str_contains($notificationSource, 'NotificationRule::allowedTriggers()'), 'SC-P6-036 writes/triggers stay behind domain boundaries');
sc_p6v6_assert(str_contains($notificationSource, 'normalizeRoleInput') && str_contains($notificationSource, "'days_before' => \$_POST['days_before']"), 'SC-P6-036 roles are strict and cadence reaches domain validation without lossy casts');
sc_p6v6_assert(str_contains($notificationSource, 'NotificationTemplateRepository') && str_contains($notificationSource, 'all(true)') && str_contains($notificationSource, 'original_code'), 'SC-P6-036 active template selection and stable codes are preserved');
sc_p6v6_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad','name'=>'Bad','trigger_type'=>'before_due','days_before'=>['10'],
    'recipient_roles'=>[RoleRegistrar::MANAGER],'target_assigned_accountant'=>false,
]), 'SC-P6-036 malformed cadence array fails closed');

// SC-P6-037 — Firebase settings use the stronger system-settings boundary.
$firebaseSource = file_get_contents((string) (new ReflectionClass(FirebaseSettingsPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($firebaseSource, 'Capabilities::MANAGE_SYSTEM') >= 2, 'SC-P6-037 Firebase registration and shared authorization helper require system capability');
sc_p6v6_assert(substr_count($firebaseSource, 'self::assertManage();') >= 6, 'SC-P6-037 Firebase read/write actions consistently pass through the system-capability guard');
sc_p6v6_assert(str_contains($firebaseSource, 'check_admin_referer') && str_contains($firebaseSource, 'savePublic') && str_contains($firebaseSource, 'saveCredentialReference'), 'SC-P6-037 Firebase writes retain nonce and constrained APIs');
sc_p6v6_assert(! str_contains($firebaseSource, "\$_POST['service_account_json']") && ! str_contains($firebaseSource, "\$_POST['private_key']") && ! str_contains($firebaseSource, "\$_POST['access_token']"), 'SC-P6-037 page has no raw credential/token input');
$firebase = new FirebaseSettings();
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_NOTIFICATIONS => true];
sc_p6v6_expect(DomainException::class, fn () => $firebase->savePublic(['project_id'=>'p','messaging_sender_id'=>'123','app_id'=>'a']), 'SC-P6-037 notification manager alone cannot change Firebase system settings');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM => true];
$firebase->savePublic(['project_id'=>'safecontracts-prod','messaging_sender_id'=>'123456789','app_id'=>'1:123456789:web:abc']);
$reference = $firebase->saveCredentialReference('safecontracts_firebase_service_account');
sc_p6v6_assert($reference === 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT', 'SC-P6-037 credential reference normalizes to identifier only');
sc_p6v6_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference(['SECRET']), 'SC-P6-037 non-scalar credential reference is rejected');
sc_p6v6_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference('{"private_key":"SECRET"}'), 'SC-P6-037 raw secret JSON is rejected');

// SC-P6-038 — Mobile configuration.
$mobileSource = file_get_contents((string) (new ReflectionClass(MobileConfigurationPage::class))->getFileName()) ?: '';
sc_p6v6_assert(substr_count($mobileSource, 'Capabilities::MANAGE_SYSTEM') >= 3, 'SC-P6-038 mobile config requires manage-system capability');
sc_p6v6_assert(str_contains($mobileSource, 'MobileConfiguration') && ! str_contains($mobileSource, 'register_rest_route') && ! str_contains($mobileSource, '$wpdb'), 'SC-P6-038 remains a settings boundary and does not implement REST/SQL in presentation');
$mobile = new MobileConfiguration();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6v6_expect(DomainException::class, fn () => $mobile->save(['default_page_size'=>25]), 'SC-P6-038 unauthorized write fails closed');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM => true];
sc_p6v6_expect(InvalidArgumentException::class, fn () => $mobile->save(['support_text'=>['Bad'],'default_page_size'=>25]), 'SC-P6-038 non-scalar support text is rejected');
$savedMobile = $mobile->save(['support_text'=>'<b>Support desk</b>','default_page_size'=>'40','excel_export_enabled'=>true,'push_notifications_enabled'=>false,'collection_entry_enabled'=>true]);
sc_p6v6_assert($savedMobile['support_text'] === 'Support desk' && $savedMobile['default_page_size'] === 40, 'SC-P6-038 valid client-safe values normalize correctly');

GeneralSettingsPage::register();
PaymentMethodsPage::register();
NotificationSettingsPage::register();
FirebaseSettingsPage::register();
MobileConfigurationPage::register();
$expected = [
    GeneralSettingsPage::SLUG => Capabilities::MANAGE_SYSTEM,
    PaymentMethodsPage::SLUG => Capabilities::MANAGE_REFERENCE_DATA,
    NotificationSettingsPage::SLUG => Capabilities::MANAGE_NOTIFICATIONS,
    FirebaseSettingsPage::SLUG => Capabilities::MANAGE_SYSTEM,
    MobileConfigurationPage::SLUG => Capabilities::MANAGE_SYSTEM,
];
foreach ($expected as $slug => $capability) {
    sc_p6v6_assert(($GLOBALS['sc_test_admin_pages'][$slug]['parent'] ?? '') === AdminShell::SLUG, $slug . ' remains under SafeContracts shell');
    sc_p6v6_assert(($GLOBALS['sc_test_admin_pages'][$slug]['capability'] ?? '') === $capability, $slug . ' retains hardened server-side capability');
}

printf("SafeContracts P6 validation SC-P6-034..038 passed (%d assertions).\n", $tests);
