<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Database\Migrator;
use SafeContracts\Notifications\DeviceTokenService;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Notifications\NotificationEngine;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Notifications\NotificationTemplate;
use SafeContracts\Notifications\NotificationTemplateService;
use SafeContracts\Notifications\PushDeliveryService;
use SafeContracts\Notifications\PushTransport;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_p5d_assert(bool $ok, string $message): void { global $tests; $tests++; if (! $ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
function sc_p5d_expect(string $class, callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { sc_p5d_assert($e instanceof $class, $message); return; } sc_p5d_assert(false, $message); }

final class SC_P5_FakePushTransport implements PushTransport
{
    public int $calls = 0;

    public function send(string $token, array $payload): array
    {
        $this->calls++;
        if (str_contains($token, 'FAIL')) {
            return ['success' => false, 'status_code' => 503, 'error_code' => 'temporary_unavailable'];
        }
        return ['success' => true, 'status_code' => 200, 'error_code' => null];
    }
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p5d_assert(is_callable($activate), 'P5 delivery validation can activate plugin');
$activate();
sc_p5d_assert(version_compare(Migrator::LATEST_VERSION, '1.10.0', '>='), 'P5 delivery migration 1.10.0 is registered');
$schemaSql = implode("\n", $GLOBALS['sc_test_dbdelta']);
sc_p5d_assert(str_contains($schemaSql, 'wp_safecontracts_notification_rules') && str_contains($schemaSql, 'days_after int(11) unsigned NOT NULL DEFAULT 0'), 'SC-P5-005/006 rule schema stores trigger offsets');
sc_p5d_assert(str_contains($schemaSql, 'repeat_interval_days int(11) unsigned NOT NULL DEFAULT 0') && str_contains($schemaSql, 'max_repeats int(11) unsigned NOT NULL DEFAULT 0'), 'SC-P5-007 repeat cadence is persisted');
sc_p5d_assert(str_contains($schemaSql, 'escalation_roles_json longtext NOT NULL'), 'SC-P5-007 escalation roles are persisted server-side');
sc_p5d_assert(str_contains($schemaSql, 'wp_safecontracts_notification_templates'), 'SC-P5-008 notification template table exists');
sc_p5d_assert(str_contains($schemaSql, 'wp_safecontracts_device_tokens') && str_contains($schemaSql, 'UNIQUE KEY token_hash (token_hash)'), 'SC-P5-010 device-token registry is unique by token hash');
sc_p5d_assert(str_contains($schemaSql, 'wp_safecontracts_notification_deliveries') && str_contains($schemaSql, 'retry_lookup'), 'SC-P5-012 delivery log supports bounded retry lookup');
$seedSql = implode("\n", $GLOBALS['sc_test_queries']);
sc_p5d_assert(str_contains($seedSql, 'payment_due_soon') && str_contains($seedSql, 'payment_due_today') && str_contains($seedSql, 'payment_overdue'), 'SC-P5-008 baseline notification templates are seeded idempotently');

$dueDay = NotificationRule::normalizeInput([
    'code'=>'due-today','name'=>'Due today','trigger_type'=>'due_day',
    'recipient_roles'=>[RoleRegistrar::MANAGER],'target_assigned_accountant'=>true,
]);
sc_p5d_assert($dueDay['days_before'] === 0 && $dueDay['days_after'] === 0, 'SC-P5-005 due-day rule has zero date offset');
sc_p5d_assert($dueDay['template_code'] === 'payment_due_today', 'SC-P5-005 due-day rule selects due-today template by default');
$payment = [
    'id'=>7001,'due_date'=>'2026-08-15','expected_payment_date'=>'2026-09-30','remaining_amount'=>'500.0000',
    'status'=>PaymentStatus::DUE,'accountant_user_id'=>42,'reference'=>'P-7001','contract_number'=>'SC-77','customer_name'=>'Acme',
];
sc_p5d_assert(NotificationRule::matchesPayment($dueDay, $payment, new DateTimeImmutable('2026-08-15')), 'SC-P5-005 due-day trigger uses contractual due_date');
sc_p5d_assert(! NotificationRule::matchesPayment($dueDay, $payment, new DateTimeImmutable('2026-09-30')), 'SC-P5-005 expected payment date cannot move due-day reminder');

$overdue = NotificationRule::normalizeInput([
    'code'=>'overdue-repeat','name'=>'Overdue repeat','trigger_type'=>'overdue','days_after'=>1,
    'repeat_interval_days'=>2,'max_repeats'=>2,
    'recipient_roles'=>[RoleRegistrar::MANAGER],'escalation_roles'=>[RoleRegistrar::VIEWER],
    'target_assigned_accountant'=>true,
]);
$partial = $payment;
$partial['due_date'] = '2026-08-10';
$partial['status'] = PaymentStatus::PARTIALLY_PAID;
$partial['remaining_amount'] = '300.0000';
sc_p5d_assert(NotificationRule::matchesPayment($overdue, $partial, new DateTimeImmutable('2026-08-11'), 0), 'SC-P5-006 first overdue reminder fires one day after contractual due date');
sc_p5d_assert(NotificationRule::matchesPayment($overdue, $partial, new DateTimeImmutable('2026-08-13'), 1), 'SC-P5-007 first repeat follows configured cadence');
sc_p5d_assert(NotificationRule::matchesPayment($overdue, $partial, new DateTimeImmutable('2026-08-15'), 2), 'SC-P5-007 final bounded repeat follows configured cadence');
sc_p5d_assert(! NotificationRule::matchesPayment($overdue, $partial, new DateTimeImmutable('2026-08-17'), 3), 'SC-P5-007 attempts beyond max repeats are suppressed');
sc_p5d_assert(NotificationRule::daysOverdue('2026-08-10', new DateTimeImmutable('2026-08-15')) === 5, 'SC-P5-006 overdue day count is contractual-date based');
$paid = $partial;
$paid['status'] = PaymentStatus::PAID;
$paid['remaining_amount'] = '0.0000';
sc_p5d_assert(! NotificationRule::matchesPayment($overdue, $paid, new DateTimeImmutable('2026-08-11')), 'SC-P5-013 settled payments are suppressed before notification generation');
sc_p5d_assert(NotificationRule::matchesPayment($overdue, $partial, new DateTimeImmutable('2026-08-11')), 'SC-P5-013 partially paid payments remain eligible when balance remains');
sc_p5d_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code'=>'bad-repeat','name'=>'Bad repeat','trigger_type'=>'overdue','days_after'=>1,'repeat_interval_days'=>2,'max_repeats'=>0,
    'recipient_roles'=>[RoleRegistrar::MANAGER],
]), 'SC-P5-007 incomplete repeat configuration is rejected');

$template = NotificationTemplate::normalizeInput([
    'code'=>'custom-overdue','title_template'=>'{{contract_number}} overdue',
    'body_template'=>'Payment {{payment_reference}} is {{days_overdue}} days overdue; remaining {{remaining_amount}}.',
]);
$rendered = NotificationTemplate::render($template, [
    'contract_number'=>'SC-77','payment_reference'=>'P-7001','days_overdue'=>5,'remaining_amount'=>'300.0000',
]);
sc_p5d_assert($rendered['title'] === 'SC-77 overdue' && str_contains($rendered['body'], '5 days overdue'), 'SC-P5-008 approved placeholders render deterministically');
sc_p5d_expect(InvalidArgumentException::class, fn () => NotificationTemplate::normalizeInput([
    'code'=>'unsafe','title_template'=>'{{private_key}}','body_template'=>'Nope',
]), 'SC-P5-008 unknown template placeholders are rejected');

$templateService = new NotificationTemplateService();
$GLOBALS['sc_test_current_caps'] = [];
sc_p5d_expect(DomainException::class, fn () => $templateService->save([
    'code'=>'x','title_template'=>'Title','body_template'=>'Body',
]), 'SC-P5-008 template administration requires notification capability');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_NOTIFICATIONS=>true];
$beforeTemplateSave = count($GLOBALS['sc_test_queries']);
$templateService->save(['code'=>'custom','title_template'=>'Title {{due_date}}','body_template'=>'Body {{remaining_amount}}']);
$templateSaveSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeTemplateSave));
sc_p5d_assert(str_contains($templateSaveSql, 'wp_safecontracts_notification_templates') && str_contains($templateSaveSql, 'ON DUPLICATE KEY UPDATE'), 'SC-P5-008 template writes are server-owned and idempotent');

$firebase = new FirebaseSettings();
$GLOBALS['sc_test_current_caps'] = [];
sc_p5d_expect(DomainException::class, fn () => $firebase->savePublic(['project_id'=>'safecontracts','messaging_sender_id'=>'123456','app_id'=>'app']), 'SC-P5-009 Firebase settings require system administration');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM=>true];
$config = $firebase->savePublic(['project_id'=>'safecontracts-prod','messaging_sender_id'=>'123456789','app_id'=>'1:123:web:abc']);
sc_p5d_assert($config['project_id'] === 'safecontracts-prod', 'SC-P5-009 public Firebase metadata is normalized and stored server-side');
$reference = $firebase->saveCredentialReference('SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT');
sc_p5d_assert($reference === 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT', 'SC-P5-009 credential storage uses a secret/environment reference only');
$summary = $firebase->safeSummary();
sc_p5d_assert($summary['configured'] === true && ! array_key_exists('credential_reference', $summary), 'SC-P5-009 safe Firebase summary never exposes credential reference or secret content');
sc_p5d_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference('{"private_key":"secret"}'), 'SC-P5-009 raw credential JSON cannot be stored as the credential reference');

$devices = new DeviceTokenService();
$deviceToken = 'ANDROID_TOKEN_' . str_repeat('A', 40);
$GLOBALS['sc_test_current_caps'] = [];
sc_p5d_expect(DomainException::class, fn () => $devices->register($deviceToken, 'android'), 'SC-P5-010 device registration requires SafeContracts access');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true];
$beforeDevice = count($GLOBALS['sc_test_queries']);
$devices->register($deviceToken, 'android');
$deviceSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeDevice));
sc_p5d_assert(str_contains($deviceSql, 'wp_safecontracts_device_tokens') && str_contains($deviceSql, 'ON DUPLICATE KEY UPDATE'), 'SC-P5-010 device registration is deduplicated by token hash');
sc_p5d_assert(str_contains($deviceSql, hash('sha256', $deviceToken)) && str_contains($deviceSql, '42'), 'SC-P5-010 registered device is bound to authenticated user and stable token hash');
$registeredEvent = end($GLOBALS['sc_test_fired_actions']['safecontracts_device_token_registered']);
sc_p5d_assert(is_array($registeredEvent) && ! str_contains(json_encode($registeredEvent) ?: '', $deviceToken), 'SC-P5-010 device registration event never emits raw token material');
$beforeRevoke = count($GLOBALS['sc_test_queries']);
$devices->revoke($deviceToken);
$revokeSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeRevoke));
sc_p5d_assert(str_contains($revokeSql, 'user_id = 42') && str_contains($revokeSql, hash('sha256', $deviceToken)) && ! str_contains($revokeSql, $deviceToken), 'SC-P5-010 token revoke is owner-scoped and uses only token hash');
sc_p5d_expect(InvalidArgumentException::class, fn () => $devices->register('short', 'android'), 'SC-P5-010 short device token is rejected');
sc_p5d_expect(InvalidArgumentException::class, fn () => $devices->register($deviceToken, 'windows'), 'SC-P5-010 unsupported device platform is rejected');

$transport = new SC_P5_FakePushTransport();
$delivery = new PushDeliveryService($transport);
$goodToken = 'GOOD_' . str_repeat('G', 40);
$failToken = 'FAIL_' . str_repeat('F', 40);
$GLOBALS['sc_test_result_queue'] = [[
    ['id'=>'501','user_id'=>'42','token'=>$goodToken,'platform'=>'android'],
    ['id'=>'502','user_id'=>'100','token'=>$failToken,'platform'=>'ios'],
]];
$beforeDelivery = count($GLOBALS['sc_test_queries']);
$result = $delivery->deliver([
    'rule_id'=>9,'payment_id'=>7001,'recipient_ids'=>[42,100],'template_code'=>'payment_due_today','scheduled_for'=>'2026-08-15',
    'payload'=>['title'=>'Payment due today','body'=>'SC-77 payment is due today.','data'=>['payment_id'=>7001]],
], 0);
$deliverySql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeDelivery));
sc_p5d_assert($result === ['attempted'=>2,'sent'=>1,'failed'=>1,'retryable'=>true], 'SC-P5-011/012 push delivery reports sent/failed outcomes and retryability');
sc_p5d_assert($transport->calls === 2, 'SC-P5-011 active device tokens are delivered through transport exactly once per attempt');
sc_p5d_assert(substr_count($deliverySql, 'wp_safecontracts_notification_deliveries') === 2, 'SC-P5-012 every transport attempt is appended to delivery log');
sc_p5d_assert(! str_contains($deliverySql, $goodToken) && ! str_contains($deliverySql, $failToken), 'SC-P5-012 delivery log never stores raw device-token material');
sc_p5d_assert($delivery->retryDelaySeconds(0) === 60 && $delivery->retryDelaySeconds(1) === 120 && $delivery->retryDelaySeconds(2) === 240, 'SC-P5-012 retry backoff is deterministic and bounded');
sc_p5d_assert(! $delivery->canRetry(3) && $delivery->retryDelaySeconds(3) === 0, 'SC-P5-012 transport retries stop after three retries');

$GLOBALS['sc_test_users_by_role'] = [
    RoleRegistrar::MANAGER => [100],
    RoleRegistrar::VIEWER => [101],
];
$engine = new NotificationEngine();
$dueDay['id'] = 9;
$dueDay['is_active'] = true;
$GLOBALS['sc_test_result_queue'] = [[[
    'id'=>'20','code'=>'payment_due_today','title_template'=>'Payment due today','body_template'=>'{{contract_number}} {{payment_reference}} due {{due_date}} remaining {{remaining_amount}}',
    'is_active'=>'1','created_by'=>null,'updated_by'=>null,'created_at'=>'','updated_at'=>'',
]]];
$plan = $engine->plan($dueDay, $payment, new DateTimeImmutable('2026-08-15'));
sc_p5d_assert(is_array($plan) && $plan['recipient_ids'] === [42,100], 'SC-P5-005 engine resolves Manager plus assigned Accountant on due day');
sc_p5d_assert($plan['payload']['data']['payment_id'] === 7001 && str_contains($plan['payload']['body'], '500.0000'), 'SC-P5-008/011 engine builds push payload from server template and payment context');

$paidPlan = $engine->plan($dueDay, $paid, new DateTimeImmutable('2026-08-11'));
sc_p5d_assert($paidPlan === null, 'SC-P5-013 engine suppresses settled payment before template or transport work');
sc_p5d_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_notification_suppressed']), 'SC-P5-013 suppression emits operational evidence event');

$overdue['id'] = 10;
$overdue['is_active'] = true;
$GLOBALS['sc_test_result_queue'] = [[[
    'id'=>'21','code'=>'payment_overdue','title_template'=>'Overdue','body_template'=>'{{contract_number}} is {{days_overdue}} days overdue',
    'is_active'=>'1','created_by'=>null,'updated_by'=>null,'created_at'=>'','updated_at'=>'',
]]];
$escalatedPlan = $engine->plan($overdue, $partial, new DateTimeImmutable('2026-08-15'), 2);
sc_p5d_assert(is_array($escalatedPlan) && $escalatedPlan['recipient_ids'] === [42,100,101], 'SC-P5-007 final repeat adds configured escalation role without dropping normal recipients');
sc_p5d_assert(str_contains($escalatedPlan['payload']['body'], '5 days overdue'), 'SC-P5-006 overdue template receives contractual days-overdue context');

$ruleService = new NotificationRuleService();
$GLOBALS['sc_test_result_queue'] = [[[
    'id'=>'9','code'=>'due-today','name'=>'Due today','trigger_type'=>'due_day','days_before'=>'0','days_after'=>'0',
    'repeat_interval_days'=>'0','max_repeats'=>'0','recipient_roles_json'=>'["safecontracts_manager"]','escalation_roles_json'=>'[]',
    'target_assigned_accountant'=>'1','template_code'=>'payment_due_today','is_active'=>'1',
]]];
sc_p5d_assert(count($ruleService->activeDueDay()) === 1 && str_contains((string) end($GLOBALS['sc_test_read_queries']), "trigger_type = 'due_day'"), 'SC-P5-005 active due-day rules are selected server-side');
$GLOBALS['sc_test_result_queue'] = [[[
    'id'=>'10','code'=>'overdue-repeat','name'=>'Overdue','trigger_type'=>'overdue','days_before'=>'0','days_after'=>'1',
    'repeat_interval_days'=>'2','max_repeats'=>'2','recipient_roles_json'=>'["safecontracts_manager"]','escalation_roles_json'=>'["safecontracts_viewer"]',
    'target_assigned_accountant'=>'1','template_code'=>'payment_overdue','is_active'=>'1',
]]];
sc_p5d_assert(count($ruleService->activeOverdue()) === 1 && str_contains((string) end($GLOBALS['sc_test_read_queries']), "trigger_type = 'overdue'"), 'SC-P5-006 active overdue rules are selected server-side');

echo "SafeContracts P5 notification delivery SC-P5-005..013 passed ({$tests} assertions).\n";
