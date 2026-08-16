<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Notifications\NotificationScheduleSettings;

$tests = 0;
function sc_416_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_416_assert(is_callable($activate), 'Issue #416 can activate plugin migrations');
$activate();
sc_416_assert(version_compare(Migrator::LATEST_VERSION, '1.13.0', '>='), 'Issue #416 registers notification schedule migration');
$schema = implode("\n", $GLOBALS['sc_test_dbdelta']);
sc_416_assert(str_contains($schema, 'wp_safecontracts_notification_schedule'), 'Issue #416 creates persistent notification schedule ledger');
sc_416_assert(str_contains($schema, 'UNIQUE KEY rule_payment_attempt (rule_id, payment_id, attempt_no)'), 'Issue #416 prevents duplicate occurrences');
sc_416_assert(str_contains($schema, 'scheduled_date date NOT NULL') && str_contains($schema, 'scheduled_for datetime NOT NULL'), 'Issue #416 stores local business date and exact UTC dispatch timestamp');
sc_416_assert(str_contains($schema, 'manual_attempts int(11) unsigned NOT NULL DEFAULT 0'), 'Issue #416 tracks manual dispatch attempts');

$settings = new NotificationScheduleSettings();
sc_416_assert($settings->dispatchTime() === '09:00', 'Issue #416 has deterministic default dispatch time');
sc_416_assert($settings->saveDispatchTime('14:30') === '14:30' && $settings->dispatchTime() === '14:30', 'Issue #416 dispatch time is configurable');
try {
    $settings->saveDispatchTime('25:99');
    sc_416_assert(false, 'Issue #416 rejects invalid schedule times');
} catch (InvalidArgumentException $error) {
    sc_416_assert(true, 'Issue #416 rejects invalid schedule times');
}

$root = dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/';
$page = (string) file_get_contents($root . 'Admin/NotificationSchedulePage.php');
$repo = (string) file_get_contents($root . 'Notifications/NotificationScheduleRepository.php');
$service = (string) file_get_contents($root . 'Notifications/NotificationScheduleService.php');
$scheduler = (string) file_get_contents($root . 'Notifications/NotificationScheduler.php');
$delivery = (string) file_get_contents($root . 'Notifications/DeliveryLogRepository.php');
$audit = (string) file_get_contents($root . 'Audit/NotificationScheduleAuditRecorder.php');
$i18n = (string) file_get_contents($root . 'Translations/NotificationScheduleArabicDefaults.php');
$plugin = (string) file_get_contents($root . 'Plugin.php');

sc_416_assert(str_contains($page, 'Capabilities::MANAGE_NOTIFICATIONS') && str_contains($page, 'check_admin_referer(self::MANUAL_SEND_ACTION . \'_\' . $scheduleId)'), 'Issue #416 manual send is capability and nonce protected');
sc_416_assert(str_contains($page, 'date_from') && str_contains($page, 'date_to') && str_contains($page, 'notification_status'), 'Issue #416 schedule page has date-range and status filters');
sc_416_assert(str_contains($page, 'outcomesForOccurrence') && str_contains($page, "RuntimeLabels::text('Not sent')"), 'Issue #416 exposes per-recipient sent/not-sent state');
sc_416_assert(str_contains($page, 'Firebase Cloud Messaging') && str_contains($page, "__('Send manually'") && str_contains($page, "__('Resend manually'"), 'Issue #416 shows delivery method and manual action beside schedules');
sc_416_assert(str_contains($page, 'dispatchManual($scheduleId, get_current_user_id())'), 'Issue #416 manual send uses server dispatch service and actor identity');

sc_416_assert(str_contains($repo, "status = 'pending' AND scheduled_for <= UTC_TIMESTAMP()") && str_contains($repo, "status = 'processing'"), 'Issue #416 automatic dispatch claims only due pending rows');
sc_416_assert(str_contains($repo, "['pending','processing','sent','partial','failed','skipped']"), 'Issue #416 exposes canonical schedule states');
sc_416_assert(str_contains($delivery, 'GROUP BY user_id') && str_contains($delivery, "MAX(CASE WHEN status = 'sent' THEN 1 ELSE 0 END)"), 'Issue #416 derives actual recipient outcomes from transport delivery log');

sc_416_assert(str_contains($service, 'NotificationRule::targetDate') && str_contains($service, '$this->engine->plan('), 'Issue #416 schedule materialization and dispatch reuse canonical notification rule engine');
sc_416_assert(str_contains($service, 'new PushDeliveryService(new FirebasePushTransport())'), 'Issue #416 real dispatch uses existing Firebase push pipeline');
sc_416_assert(str_contains($service, "'suppressed_or_rule_mismatch'") && str_contains($service, "'no_active_devices'"), 'Issue #416 records suppression and no-device failures instead of reporting false success');
sc_416_assert(str_contains($scheduler, "'interval' => 300") && str_contains($scheduler, 'wp_schedule_event') && str_contains($scheduler, 'dispatchDue()'), 'Issue #416 scheduler runs on a five-minute WP-Cron cadence');
sc_416_assert(str_contains($audit, "'notification_manual_dispatch'") && str_contains($audit, "'notification_automatic_dispatch'"), 'Issue #416 audits manual and automatic dispatches');
sc_416_assert(str_contains($plugin, 'NotificationSchedulePage::MANUAL_SEND_ACTION') && str_contains($plugin, 'NotificationScheduler::register()'), 'Issue #416 plugin boot wires admin actions and scheduler');
sc_416_assert(str_contains($plugin, 'NotificationScheduleArabicDefaults::register()'), 'Issue #416 plugin boots Arabic defaults for the new schedule page');
foreach (['جدولة الإشعارات', 'تم الإرسال', 'لم يتم الإرسال', 'إرسال يدوي', 'وقت الإرسال اليومي'] as $arabic) {
    sc_416_assert(str_contains($i18n, $arabic), 'Issue #416 Arabic operations vocabulary contains: ' . $arabic);
}

echo "OK: {$tests} notification schedule assertions passed\n";
