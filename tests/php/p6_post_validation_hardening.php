<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\FirebaseSettingsPage;
use SafeContracts\Admin\FollowUpsPage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Reports\ReportExportService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;
use SafeContracts\Settings\MobileConfiguration;
use SafeContracts\Support\Input;

$tests = 0;
function sc_p6hot_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p6hot_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p6hot_assert($error instanceof $class, $message);
        return;
    }
    sc_p6hot_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();

sc_p6hot_assert(Input::int('12', 'ID', 1) === 12, 'strict integer parser accepts valid integer strings');
sc_p6hot_expect(InvalidArgumentException::class, fn () => Input::int(['12'], 'ID', 1), 'strict integer parser rejects arrays');
sc_p6hot_expect(InvalidArgumentException::class, fn () => Input::string(['x'], 'Text'), 'strict string parser rejects arrays');
sc_p6hot_expect(InvalidArgumentException::class, fn () => Input::oneOf('delete', ['note','promise'], 'Operation'), 'strict enum parser rejects unknown operation');

$reportsSource = file_get_contents((string) (new ReflectionClass(ReportsPage::class))->getFileName()) ?: '';
$exportSource = file_get_contents((string) (new ReflectionClass(ReportExportService::class))->getFileName()) ?: '';
sc_p6hot_assert(str_contains($reportsSource, 'Capabilities::EXPORT_REPORTS'), 'report export action/UI uses EXPORT_REPORTS');
sc_p6hot_assert(str_contains($exportSource, 'Capabilities::EXPORT_REPORTS'), 'report export service independently enforces EXPORT_REPORTS');
$GLOBALS['sc_test_current_caps'] = [Capabilities::VIEW_REPORTS => true];
sc_p6hot_expect(DomainException::class, fn () => (new ReportExportService())->generate([]), 'VIEW_REPORTS alone cannot export XLSX');

$followSource = file_get_contents((string) (new ReflectionClass(FollowUpsPage::class))->getFileName()) ?: '';
sc_p6hot_assert(str_contains($followSource, 'Input::oneOf'), 'follow-up admin validates operation allow-list');
sc_p6hot_assert(str_contains($followSource, "['note', 'promise', 'issue', 'defer', 'escalate']"), 'follow-up operation vocabulary is explicit');

$firebasePageSource = file_get_contents((string) (new ReflectionClass(FirebaseSettingsPage::class))->getFileName()) ?: '';
sc_p6hot_assert(substr_count($firebasePageSource, 'Capabilities::MANAGE_SYSTEM') >= 3, 'Firebase admin UI uses system settings capability');
$firebase = new FirebaseSettings();
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_NOTIFICATIONS => true];
sc_p6hot_expect(DomainException::class, fn () => $firebase->savePublic(['project_id'=>'p','messaging_sender_id'=>'123','app_id'=>'a']), 'notification manager cannot mutate Firebase settings');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM => true];
sc_p6hot_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference(['SAFECONTRACTS_FIREBASE']), 'Firebase credential reference rejects array coercion');

$general = new GeneralSettings();
sc_p6hot_expect(InvalidArgumentException::class, fn () => $general->save(['organization_name'=>['Bad'],'currency_code'=>'KWD','admin_page_size'=>50]), 'general settings reject array text input');
$mobile = new MobileConfiguration();
sc_p6hot_expect(InvalidArgumentException::class, fn () => $mobile->save(['support_text'=>['Bad'],'default_page_size'=>25]), 'mobile settings reject array text input');
$methods = new PaymentMethodRepository();
sc_p6hot_expect(InvalidArgumentException::class, fn () => $methods->save(['code'=>'cash','name'=>'Cash','display_order'=>['1'],'is_active'=>true]), 'payment method settings reject array numeric input');

printf("SafeContracts P6 post-validation hardening passed (%d assertions).\n", $tests);
