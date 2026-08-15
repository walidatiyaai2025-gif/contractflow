<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\CollectionsPage;
use SafeContracts\Admin\DashboardFilters;
use SafeContracts\Admin\FirebaseSettingsPage;
use SafeContracts\Admin\FollowUpsPage;
use SafeContracts\Admin\GeneralSettingsPage;
use SafeContracts\Admin\MobileConfigurationPage;
use SafeContracts\Admin\NotificationSettingsPage;
use SafeContracts\Admin\NotificationsPage;
use SafeContracts\Admin\PaymentMethodsPage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Admin\UsersRolesPage;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Reports\ReportExportService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Settings\MobileConfiguration;
use SafeContracts\Support\Input;

$tests = 0;
function sc_p6v_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p6v_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p6v_assert($error instanceof $class, $message);
        return;
    }
    sc_p6v_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();

// Shared strict-input boundary used by P6 mutation pages.
sc_p6v_assert(Input::int('42', 'ID', 1) === 42, 'P6 strict input accepts a valid integer string');
sc_p6v_expect(InvalidArgumentException::class, fn () => Input::int(['42'], 'ID', 1), 'P6 strict input rejects array-to-integer coercion');
sc_p6v_expect(InvalidArgumentException::class, fn () => Input::string(['x'], 'Text'), 'P6 strict input rejects array-to-string coercion');

// SC-P6-029 — Collections screen validation.
$collectionsSource = file_get_contents((string) (new ReflectionClass(CollectionsPage::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($collectionsSource, 'CollectionService'), 'SC-P6-029 collection writes delegate to CollectionService');
sc_p6v_assert(str_contains($collectionsSource, 'Capabilities::MANAGE_COLLECTIONS'), 'SC-P6-029 collection writes require MANAGE_COLLECTIONS');
sc_p6v_assert(str_contains($collectionsSource, "Input::int(\$_POST['payment_id']"), 'SC-P6-029 payment ID uses strict server-side integer validation');
sc_p6v_assert(str_contains($collectionsSource, "Input::int(\$_POST['payment_method_id']"), 'SC-P6-029 payment method ID uses strict validation');
sc_p6v_assert(str_contains($collectionsSource, 'Proof media ID (optional)'), 'SC-P6-029 collection proof remains explicitly optional');
sc_p6v_assert(! str_contains($collectionsSource, '$wpdb'), 'SC-P6-029 presentation layer contains no direct SQL');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[]];
$beforeReads = count($GLOBALS['sc_test_read_queries']);
(new AdminReadRepository())->collections(['accountant_user_id' => 999]);
$collectionSql = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $beforeReads));
sc_p6v_assert(str_contains($collectionSql, 'c.accountant_user_id = 42'), 'SC-P6-029 assigned collection scope is enforced server-side');
sc_p6v_assert(! str_contains($collectionSql, 'accountant_user_id = 999'), 'SC-P6-029 caller cannot widen collection scope with another Accountant ID');

// SC-P6-030 — Follow-up screen validation.
$followupSource = file_get_contents((string) (new ReflectionClass(FollowUpsPage::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($followupSource, 'FollowUpService'), 'SC-P6-030 follow-up writes stay behind FollowUpService');
sc_p6v_assert(str_contains($followupSource, 'Input::oneOf'), 'SC-P6-030 operation selection is fail-closed');
sc_p6v_assert(Input::oneOf('promise', ['note','promise','issue','defer','escalate'], 'Operation') === 'promise', 'SC-P6-030 valid promise operation is accepted');
sc_p6v_expect(InvalidArgumentException::class, fn () => Input::oneOf('delete', ['note','promise','issue','defer','escalate'], 'Operation'), 'SC-P6-030 unknown follow-up operation is rejected instead of becoming a note');
sc_p6v_assert(str_contains($followupSource, 'Contractual due date remains'), 'SC-P6-030 UI preserves contractual due-date authority');
sc_p6v_assert(str_contains($followupSource, 'Append-only history'), 'SC-P6-030 history is explicitly append-only');
sc_p6v_assert(! str_contains($followupSource, '$wpdb'), 'SC-P6-030 follow-up page contains no direct SQL');

// SC-P6-031 — Notifications screen validation.
$notificationsSource = file_get_contents((string) (new ReflectionClass(NotificationsPage::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($notificationsSource, 'Capabilities::MANAGE_NOTIFICATIONS'), 'SC-P6-031 notification operations require MANAGE_NOTIFICATIONS');
sc_p6v_assert(str_contains($notificationsSource, 'NotificationRuleService') && str_contains($notificationsSource, 'DeliveryLogRepository'), 'SC-P6-031 notification view uses server notification boundaries');
sc_p6v_assert(str_contains($notificationsSource, 'recent(100)'), 'SC-P6-031 delivery log read is bounded');
sc_p6v_assert(! str_contains($notificationsSource, 'FirebaseSettings') && ! str_contains($notificationsSource, 'private_key'), 'SC-P6-031 operational notification view exposes no Firebase credentials');
sc_p6v_assert(! str_contains($notificationsSource, 'device_token'), 'SC-P6-031 operational notification view exposes no raw device-token value');
sc_p6v_assert(! str_contains($notificationsSource, '$wpdb'), 'SC-P6-031 notification page contains no direct SQL');

// SC-P6-032 — Reports screen and export authorization validation.
$reportsSource = file_get_contents((string) (new ReflectionClass(ReportsPage::class))->getFileName()) ?: '';
$exportSource = file_get_contents((string) (new ReflectionClass(ReportExportService::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($reportsSource, 'Capabilities::VIEW_REPORTS'), 'SC-P6-032 report viewing retains VIEW_REPORTS');
sc_p6v_assert(str_contains($reportsSource, 'Capabilities::EXPORT_REPORTS'), 'SC-P6-032 report export UI/action uses dedicated EXPORT_REPORTS');
sc_p6v_assert(str_contains($exportSource, 'Capabilities::EXPORT_REPORTS'), 'SC-P6-032 export service independently enforces EXPORT_REPORTS');
$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_REPORTS => true];
sc_p6v_expect(DomainException::class, fn () => (new ReportExportService())->generate([]), 'SC-P6-032 report viewer without EXPORT_REPORTS cannot export');
sc_p6v_expect(InvalidArgumentException::class, fn () => DashboardFilters::normalize(['customer_id' => ['8']]), 'SC-P6-032 malformed report filters fail closed');
sc_p6v_assert(str_contains($exportSource, "'due_date'"), 'SC-P6-032 export includes contractual due_date as reporting field');
sc_p6v_assert(! str_contains($reportsSource, '$wpdb'), 'SC-P6-032 reports presentation contains no direct SQL');

// SC-P6-033 — Users/roles screen validation.
$usersSource = file_get_contents((string) (new ReflectionClass(UsersRolesPage::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($usersSource, 'Capabilities::MANAGE_USERS'), 'SC-P6-033 users/roles view requires MANAGE_USERS');
sc_p6v_assert(str_contains($usersSource, 'Capabilities::all()') && str_contains($usersSource, 'get_role') && str_contains($usersSource, 'get_users'), 'SC-P6-033 screen reflects WordPress capabilities and role membership');
sc_p6v_assert(str_contains($usersSource, 'read-only'), 'SC-P6-033 authorization directory remains read-only');
sc_p6v_assert(! str_contains($usersSource, 'user_pass') && ! str_contains($usersSource, 'admin_post_'), 'SC-P6-033 screen has no password or privilege-write path');
sc_p6v_assert(str_contains($usersSource, 'esc_html'), 'SC-P6-033 displayed user/role data is escaped');
sc_p6v_assert(! str_contains($usersSource, '$wpdb'), 'SC-P6-033 users/roles page contains no direct SQL');

// SC-P6-034 — General SafeContracts settings validation.
$general = new GeneralSettings();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6v_expect(DomainException::class, fn () => $general->save(['organization_name'=>'Denied','currency_code'=>'KWD','admin_page_size'=>50]), 'SC-P6-034 settings writes require MANAGE_SYSTEM');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::MANAGE_SYSTEM => true];
sc_p6v_expect(InvalidArgumentException::class, fn () => $general->save(['organization_name'=>['Bad'],'currency_code'=>'KWD','admin_page_size'=>50]), 'SC-P6-034 organization array input is rejected');
sc_p6v_expect(InvalidArgumentException::class, fn () => $general->save(['organization_name'=>'Valid','currency_code'=>['KWD'],'admin_page_size'=>50]), 'SC-P6-034 currency array input is rejected');
$savedGeneral = $general->save(['organization_name'=>'Safe Contracts','currency_code'=>'kwd','admin_page_size'=>'60']);
sc_p6v_assert($savedGeneral['currency_code'] === 'KWD' && $savedGeneral['admin_page_size'] === 60, 'SC-P6-034 valid general settings normalize deterministically');
$generalPageSource = file_get_contents((string) (new ReflectionClass(GeneralSettingsPage::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($generalPageSource, 'Capabilities::MANAGE_SYSTEM'), 'SC-P6-034 general settings page uses system capability');

// SC-P6-035 — Payment-method settings validation.
$methods = new PaymentMethodRepository();
sc_p6v_expect(InvalidArgumentException::class, fn () => $methods->save(['code'=>['cash'],'name'=>'Cash','display_order'=>1,'is_active'=>true]), 'SC-P6-035 payment-method code array is rejected');
sc_p6v_expect(InvalidArgumentException::class, fn () => $methods->save(['code'=>'cash','name'=>['Cash'],'display_order'=>1,'is_active'=>true]), 'SC-P6-035 payment-method name array is rejected');
sc_p6v_expect(InvalidArgumentException::class, fn () => $methods->save(['code'=>'cash','name'=>'Cash','display_order'=>['1'],'is_active'=>true]), 'SC-P6-035 display-order array is rejected');
$methodPageSource = file_get_contents((string) (new ReflectionClass(PaymentMethodsPage::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($methodPageSource, 'Capabilities::MANAGE_REFERENCE_DATA'), 'SC-P6-035 payment-method settings require reference-data capability');
sc_p6v_assert(str_contains($methodPageSource, 'original_code') && str_contains($methodPageSource, 'Stable code'), 'SC-P6-035 used method code is stable through edit workflow');
sc_p6v_assert(! str_contains(strtolower($methodPageSource), 'delete payment method'), 'SC-P6-035 UI deactivates methods rather than offering hard deletion');
sc_p6v_assert(str_contains($methodPageSource, 'only active SafeContracts payment methods'), 'SC-P6-035 collection semantics remain tied to active server methods');

// SC-P6-036 — Notification settings validation.
$notificationSettingsSource = file_get_contents((string) (new ReflectionClass(NotificationSettingsPage::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($notificationSettingsSource, 'Capabilities::MANAGE_NOTIFICATIONS'), 'SC-P6-036 notification settings require MANAGE_NOTIFICATIONS');
sc_p6v_assert(str_contains($notificationSettingsSource, 'NotificationRuleService'), 'SC-P6-036 rule writes delegate to notification domain service');
sc_p6v_assert(str_contains($notificationSettingsSource, "'days_before' => \$_POST['days_before']"), 'SC-P6-036 numeric input reaches domain validation without lossy integer casting');
sc_p6v_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad','name'=>'Bad','trigger_type'=>'before_due','days_before'=>['10'],
    'recipient_roles'=>[RoleRegistrar::MANAGER], 'target_assigned_accountant'=>false,
]), 'SC-P6-036 array cadence is rejected by notification domain');
sc_p6v_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad-repeat','name'=>'Bad Repeat','trigger_type'=>'due_day','days_before'=>0,
    'repeat_interval_days'=>2,'max_repeats'=>0,'recipient_roles'=>[RoleRegistrar::MANAGER],
]), 'SC-P6-036 incomplete repeat cadence fails closed');
sc_p6v_assert(str_contains($notificationSettingsSource, 'original_code'), 'SC-P6-036 existing notification rule code stays stable');

// SC-P6-037 — Firebase settings UI validation.
$firebase = new FirebaseSettings();
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_NOTIFICATIONS => true];
sc_p6v_expect(DomainException::class, fn () => $firebase->savePublic(['project_id'=>'p','messaging_sender_id'=>'123','app_id'=>'a']), 'SC-P6-037 notification managers cannot mutate Firebase system configuration');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM => true];
sc_p6v_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference(['SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT']), 'SC-P6-037 credential reference array cannot coerce into secret identifier');
sc_p6v_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference('{"private_key":"SECRET"}'), 'SC-P6-037 raw service-account JSON remains rejected');
$public = $firebase->savePublic(['project_id'=>'safecontracts-prod','messaging_sender_id'=>'123456','app_id'=>'1:123:web:abc']);
$ref = $firebase->saveCredentialReference('safecontracts_firebase_service_account');
sc_p6v_assert($public['project_id'] === 'safecontracts-prod' && $ref === 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT', 'SC-P6-037 valid public metadata and credential reference persist separately');
$firebasePageSource = file_get_contents((string) (new ReflectionClass(FirebaseSettingsPage::class))->getFileName()) ?: '';
sc_p6v_assert(str_contains($firebasePageSource, 'Capabilities::MANAGE_SYSTEM'), 'SC-P6-037 Firebase UI requires system capability');
sc_p6v_assert(! str_contains($firebasePageSource, "\$_POST['service_account_json']") && ! str_contains($firebasePageSource, "\$_POST['access_token']"), 'SC-P6-037 Firebase page accepts no raw credential/token fields');
$summary = $firebase->safeSummary();
$summaryJson = json_encode($summary);
sc_p6v_assert(is_string($summaryJson) && ! str_contains($summaryJson, 'SECRET') && ! array_key_exists('credential_reference', $summary), 'SC-P6-037 client-safe summary exposes no credential contents/reference');

// SC-P6-038 — Mobile configuration UI validation.
$mobile = new MobileConfiguration();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
sc_p6v_expect(DomainException::class, fn () => $mobile->save(['support_text'=>'Denied','default_page_size'=>25]), 'SC-P6-038 mobile config writes require MANAGE_SYSTEM');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM => true];
sc_p6v_expect(InvalidArgumentException::class, fn () => $mobile->save(['support_text'=>['Bad'],'default_page_size'=>25]), 'SC-P6-038 mobile support array input is rejected');
sc_p6v_expect(InvalidArgumentException::class, fn () => $mobile->save(['support_text'=>'Good','default_page_size'=>25,'excel_export_enabled'=>['1']]), 'SC-P6-038 mobile feature array input is rejected');
$savedMobile = $mobile->save(['support_text'=>'Support','default_page_size'=>'30','excel_export_enabled'=>true,'push_notifications_enabled'=>false,'collection_entry_enabled'=>true]);
sc_p6v_assert($savedMobile['default_page_size'] === 30 && $savedMobile['collection_entry_enabled'] === true, 'SC-P6-038 valid mobile bootstrap config normalizes correctly');
$mobileJson = json_encode($GLOBALS['sc_test_options'][MobileConfiguration::OPTION] ?? []);
sc_p6v_assert(is_string($mobileJson) && ! str_contains(strtolower($mobileJson), 'token') && ! str_contains(strtolower($mobileJson), 'password') && ! str_contains(strtolower($mobileJson), 'credential'), 'SC-P6-038 persisted mobile config contains only client-safe non-secret fields');
$mobilePageSource = file_get_contents((string) (new ReflectionClass(MobileConfigurationPage::class))->getFileName()) ?: '';
sc_p6v_assert(! str_contains($mobilePageSource, 'register_rest_route') && ! str_contains($mobilePageSource, 'Router::'), 'SC-P6-038 mobile settings UI does not implement P8 REST early');
sc_p6v_assert(str_contains($mobilePageSource, 'Capabilities::MANAGE_SYSTEM'), 'SC-P6-038 mobile configuration UI requires system capability');

printf("SafeContracts P6 validation SC-P6-029..038 passed (%d assertions).\n", $tests);
