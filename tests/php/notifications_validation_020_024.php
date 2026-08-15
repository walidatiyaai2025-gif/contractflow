<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\DeviceTokenRepository;
use SafeContracts\Notifications\DeviceTokenService;
use SafeContracts\Notifications\FirebasePushTransport;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Notifications\NotificationEngine;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\NotificationTemplate;
use SafeContracts\Notifications\NotificationTemplateService;
use SafeContracts\Notifications\PushDeliveryService;
use SafeContracts\Notifications\PushTransport;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_p5v3_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p5v3_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p5v3_assert($error instanceof $class, $message);
        return;
    }
    sc_p5v3_assert(false, $message);
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        unset($args);
        if ($hook === 'safecontracts_firebase_access_token') {
            return $GLOBALS['sc_p5v3_access_token'] ?? $value;
        }
        return $value;
    }
}
if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $options): mixed
    {
        $GLOBALS['sc_p5v3_http_calls'][] = ['url' => $url, 'options' => $options];
        return $GLOBALS['sc_p5v3_http_response'] ?? ['response' => ['code' => 200]];
    }
}
if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
}
if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(mixed $response): int
    {
        return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0;
    }
}
if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value): string|false { return json_encode($value); }
}

final class SC_P5V3_Transport implements PushTransport
{
    /** @var list<array{token:string,payload:array}> */
    public array $calls = [];

    public function send(string $token, array $payload): array
    {
        $this->calls[] = ['token' => $token, 'payload' => $payload];
        if (str_contains($token, 'THROW')) {
            throw new RuntimeException('simulated transport failure');
        }
        if (str_contains($token, 'FAIL')) {
            return ['success' => false, 'status_code' => 503, 'error_code' => 'temporary_unavailable'];
        }
        return ['success' => true, 'status_code' => 200, 'error_code' => null];
    }
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p5v3_assert(is_callable($activate), 'P5 SC-P5-020..024 validation can activate SafeContracts');
$activate();

// SC-P5-020 — Repeat & escalation rules.
$GLOBALS['sc_test_users_by_role'] = [
    RoleRegistrar::MANAGER => [100],
    RoleRegistrar::VIEWER => [101],
];
$repeatRule = NotificationRule::normalizeInput([
    'code' => 'overdue-escalation-validation',
    'name' => 'Overdue escalation validation',
    'trigger_type' => NotificationRule::TRIGGER_OVERDUE,
    'days_after' => 1,
    'repeat_interval_days' => 2,
    'max_repeats' => 2,
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'escalation_roles' => [RoleRegistrar::VIEWER],
    'target_assigned_accountant' => false,
]);
$repeatRule['id'] = 20;
$payment = [
    'id' => 9001,
    'due_date' => '2026-08-10',
    'expected_payment_date' => '2026-09-30',
    'remaining_amount' => '250.0000',
    'status' => PaymentStatus::PARTIALLY_PAID,
    'accountant_user_id' => null,
    'reference' => 'PAY-9001',
    'contract_number' => 'SC-9001',
    'customer_name' => 'Example Customer',
];
sc_p5v3_assert(NotificationRule::matchesPayment($repeatRule, $payment, new DateTimeImmutable('2026-08-11'), 0), 'SC-P5-020 initial overdue attempt fires at configured days-after boundary');
sc_p5v3_assert(NotificationRule::matchesPayment($repeatRule, $payment, new DateTimeImmutable('2026-08-13'), 1), 'SC-P5-020 first repeat follows exact configured cadence');
sc_p5v3_assert(NotificationRule::matchesPayment($repeatRule, $payment, new DateTimeImmutable('2026-08-15'), 2), 'SC-P5-020 final repeat follows exact configured cadence');
sc_p5v3_assert(! NotificationRule::matchesPayment($repeatRule, $payment, new DateTimeImmutable('2026-08-17'), 3), 'SC-P5-020 attempts beyond max repeats are suppressed');
sc_p5v3_assert(! NotificationRule::matchesPayment($repeatRule, $payment, new DateTimeImmutable('2026-08-14'), 2), 'SC-P5-020 off-cadence dates do not match repeat attempt');
sc_p5v3_expect(InvalidArgumentException::class, fn () => NotificationRule::normalizeInput([
    'code' => 'invalid-repeat', 'name' => 'Invalid repeat', 'trigger_type' => 'overdue', 'days_after' => 1,
    'repeat_interval_days' => 2, 'max_repeats' => 0, 'recipient_roles' => [RoleRegistrar::MANAGER],
]), 'SC-P5-020 repeat interval/max repeats must be configured together');

$engine = new NotificationEngine();
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '31', 'code' => 'payment_overdue', 'title_template' => 'Overdue {{contract_number}}',
    'body_template' => '{{payment_reference}} overdue {{days_overdue}} days; remaining {{remaining_amount}}.', 'is_active' => '1',
]]];
$repeatOne = $engine->plan($repeatRule, $payment, new DateTimeImmutable('2026-08-13'), 1);
sc_p5v3_assert(is_array($repeatOne) && $repeatOne['recipient_ids'] === [100], 'SC-P5-020 escalation role is absent before final repeat');
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '31', 'code' => 'payment_overdue', 'title_template' => 'Overdue {{contract_number}}',
    'body_template' => '{{payment_reference}} overdue {{days_overdue}} days; remaining {{remaining_amount}}.', 'is_active' => '1',
]]];
$repeatFinal = $engine->plan($repeatRule, $payment, new DateTimeImmutable('2026-08-15'), 2);
sc_p5v3_assert(is_array($repeatFinal) && $repeatFinal['recipient_ids'] === [100, 101], 'SC-P5-020 escalation role joins only on final configured repeat');
$settled = $payment;
$settled['status'] = PaymentStatus::PAID;
$settled['remaining_amount'] = '0.0000';
sc_p5v3_assert($engine->plan($repeatRule, $settled, new DateTimeImmutable('2026-08-15'), 2) === null, 'SC-P5-020 settled payment remains suppressed even on escalation attempt');

// SC-P5-021 — Notification templates.
$allowed = NotificationTemplate::allowedPlaceholders();
sc_p5v3_assert($allowed === ['customer_name', 'contract_number', 'payment_reference', 'due_date', 'remaining_amount', 'days_overdue'], 'SC-P5-021 template placeholder allow-list is explicit and stable');
$template = NotificationTemplate::normalizeInput([
    'code' => 'validation-template',
    'title_template' => 'Contract {{contract_number}}',
    'body_template' => '{{customer_name}} owes {{remaining_amount}} on {{due_date}}.',
]);
$rendered = NotificationTemplate::render($template, [
    'contract_number' => '<b>SC-1</b>', 'customer_name' => '<script>x</script>Acme', 'remaining_amount' => '50.0000', 'due_date' => '2026-08-20',
]);
sc_p5v3_assert($rendered['title'] === 'Contract SC-1' && ! str_contains($rendered['body'], '<script>'), 'SC-P5-021 rendering is deterministic and strips markup from context values');
sc_p5v3_expect(InvalidArgumentException::class, fn () => NotificationTemplate::normalizeInput([
    'code' => 'unsafe-template', 'title_template' => '{{private_key}}', 'body_template' => 'Body',
]), 'SC-P5-021 unsupported placeholders are rejected at write boundary');
sc_p5v3_expect(InvalidArgumentException::class, fn () => NotificationTemplate::render($template, [
    'contract_number' => 'SC-1', 'customer_name' => 'Acme', 'due_date' => '2026-08-20',
]), 'SC-P5-021 rendering fails closed when required context is missing');
$templateService = new NotificationTemplateService();
$GLOBALS['sc_test_current_caps'] = [];
sc_p5v3_expect(DomainException::class, fn () => $templateService->save([
    'code' => 'denied', 'title_template' => 'Title', 'body_template' => 'Body',
]), 'SC-P5-021 template writes require notification-management capability');
$GLOBALS['sc_test_result_queue'] = [[]];
sc_p5v3_expect(InvalidArgumentException::class, fn () => $templateService->render('missing-template', []), 'SC-P5-021 missing/inactive template fails closed');
$seedSql = implode("\n", $GLOBALS['sc_test_queries']);
sc_p5v3_assert(str_contains($seedSql, 'payment_due_soon') && str_contains($seedSql, 'payment_due_today') && str_contains($seedSql, 'payment_overdue'), 'SC-P5-021 baseline templates remain seeded idempotently');

// SC-P5-022 — Firebase settings and fail-closed auth/config.
$firebase = new FirebaseSettings();
$GLOBALS['sc_test_current_caps'] = [];
sc_p5v3_expect(DomainException::class, fn () => $firebase->savePublic([
    'project_id' => 'safecontracts', 'messaging_sender_id' => '123456789', 'app_id' => '1:123:web:abc',
]), 'SC-P5-022 Firebase writes require notification-management capability');
$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_NOTIFICATIONS => true];
$firebase->savePublic(['project_id' => 'safecontracts-prod', 'messaging_sender_id' => '123456789', 'app_id' => '1:123:web:abc']);
sc_p5v3_expect(InvalidArgumentException::class, fn () => $firebase->saveCredentialReference('{"private_key":"secret"}'), 'SC-P5-022 raw credential JSON cannot be stored');
$firebase->saveCredentialReference('SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT');
$summary = $firebase->safeSummary();
sc_p5v3_assert($summary['configured'] === true && ! array_key_exists('credential_reference', $summary), 'SC-P5-022 safe summary never exposes credential reference or secret content');

$push = new FirebasePushTransport($firebase);
$GLOBALS['sc_p5v3_http_calls'] = [];
$GLOBALS['sc_p5v3_access_token'] = 'FILTER_TOKEN_SHOULD_NOT_BYPASS_CONFIG';
$GLOBALS['sc_test_options'][FirebaseSettings::CREDENTIAL_REFERENCE_OPTION] = '';
$missingReference = $push->send('TOKEN_' . str_repeat('A', 30), ['title' => 'Title', 'body' => 'Body', 'data' => []]);
sc_p5v3_assert($missingReference['success'] === false && $missingReference['error_code'] === 'firebase_auth_unavailable', 'SC-P5-022 missing credential reference fails closed before Firebase transport');
sc_p5v3_assert($GLOBALS['sc_p5v3_http_calls'] === [], 'SC-P5-022 missing credential reference never reaches remote HTTP transport');

$GLOBALS['sc_test_options'][FirebaseSettings::CREDENTIAL_REFERENCE_OPTION] = 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT';
$GLOBALS['sc_p5v3_access_token'] = '';
$missingAuth = $push->send('TOKEN_' . str_repeat('B', 30), ['title' => 'Title', 'body' => 'Body', 'data' => []]);
sc_p5v3_assert($missingAuth['success'] === false && $missingAuth['error_code'] === 'firebase_auth_unavailable', 'SC-P5-022 missing short-lived access token fails closed');
sc_p5v3_assert($GLOBALS['sc_p5v3_http_calls'] === [], 'SC-P5-022 missing access token never reaches remote HTTP transport');

// SC-P5-023 — Device-token registry ownership, hashing and active lookup.
$devices = new DeviceTokenService();
$deviceRepo = new DeviceTokenRepository();
$deviceToken = 'DEVICE_' . str_repeat('D', 40);
$GLOBALS['sc_test_current_caps'] = [];
sc_p5v3_expect(DomainException::class, fn () => $devices->register($deviceToken, 'android'), 'SC-P5-023 device registration requires SafeContracts access');
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true];
$beforeDevice = count($GLOBALS['sc_test_queries']);
$devices->register($deviceToken, 'android');
$deviceSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeDevice));
sc_p5v3_assert(str_contains($deviceSql, 'ON DUPLICATE KEY UPDATE') && str_contains($deviceSql, hash('sha256', $deviceToken)), 'SC-P5-023 token hash is stable uniqueness key for idempotent registration');
sc_p5v3_assert(str_contains($deviceSql, $deviceToken) && str_contains($deviceSql, '42'), 'SC-P5-023 raw token is stored only in dedicated registry and bound to authenticated user');
$registeredEvent = end($GLOBALS['sc_test_fired_actions']['safecontracts_device_token_registered']);
sc_p5v3_assert(is_array($registeredEvent) && ! str_contains(json_encode($registeredEvent) ?: '', $deviceToken), 'SC-P5-023 registration event emits token hash, never raw token');
$beforeRevoke = count($GLOBALS['sc_test_queries']);
$devices->revoke($deviceToken);
$revokeSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeRevoke));
sc_p5v3_assert(str_contains($revokeSql, 'user_id = 42') && str_contains($revokeSql, hash('sha256', $deviceToken)) && ! str_contains($revokeSql, $deviceToken), 'SC-P5-023 revoke is owner-scoped and never writes raw token to operational SQL');
$GLOBALS['sc_test_result_queue'] = [[['id' => '701', 'user_id' => '42', 'token' => $deviceToken, 'platform' => 'android']]];
$beforeReads = count($GLOBALS['sc_test_read_queries']);
$active = $deviceRepo->activeForUsers([42, 42, 0, -1]);
$activeQuery = implode("\n", array_slice($GLOBALS['sc_test_read_queries'], $beforeReads));
sc_p5v3_assert(count($active) === 1 && $active[0]['user_id'] === 42, 'SC-P5-023 active lookup returns normalized owned device rows');
sc_p5v3_assert(str_contains($activeQuery, 'is_active = 1') && substr_count($activeQuery, '42') === 1, 'SC-P5-023 active lookup deduplicates user IDs and filters revoked devices');

// SC-P5-024 — Push delivery and direct FCM v1 boundary.
$transport = new SC_P5V3_Transport();
$delivery = new PushDeliveryService($transport);
$goodToken = 'GOOD_' . str_repeat('G', 40);
$failToken = 'FAIL_' . str_repeat('F', 40);
$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '801', 'user_id' => '42', 'token' => $goodToken, 'platform' => 'android'],
    ['id' => '802', 'user_id' => '100', 'token' => $failToken, 'platform' => 'ios'],
]];
$beforeDelivery = count($GLOBALS['sc_test_queries']);
$result = $delivery->deliver([
    'rule_id' => 20, 'payment_id' => 9001, 'recipient_ids' => [42, 100], 'template_code' => 'payment_overdue', 'scheduled_for' => '2026-08-15',
    'payload' => ['title' => 'Overdue', 'body' => 'Payment overdue', 'data' => ['payment_id' => 9001]],
]);
$deliverySql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeDelivery));
sc_p5v3_assert($result === ['attempted' => 2, 'sent' => 1, 'failed' => 1, 'retryable' => true], 'SC-P5-024 push delivery returns structured success/failure counts');
sc_p5v3_assert(count($transport->calls) === 2, 'SC-P5-024 transport-independent delivery sends once per active device row');
sc_p5v3_assert(! str_contains($deliverySql, $goodToken) && ! str_contains($deliverySql, $failToken), 'SC-P5-024 append-only delivery log never stores raw device tokens');
sc_p5v3_expect(InvalidArgumentException::class, fn () => $delivery->deliver([
    'rule_id' => 0, 'payment_id' => 9001, 'recipient_ids' => [42], 'template_code' => 'payment_overdue', 'scheduled_for' => '2026-08-15',
    'payload' => ['title' => 'Overdue', 'body' => 'Payment overdue'],
]), 'SC-P5-024 invalid delivery identity is rejected before transport');

$throwTransport = new SC_P5V3_Transport();
$throwDelivery = new PushDeliveryService($throwTransport);
$throwToken = 'THROW_' . str_repeat('T', 40);
$GLOBALS['sc_test_result_queue'] = [[['id' => '803', 'user_id' => '42', 'token' => $throwToken, 'platform' => 'android']]];
$throwResult = $throwDelivery->deliver([
    'rule_id' => 20, 'payment_id' => 9001, 'recipient_ids' => [42], 'template_code' => 'payment_overdue', 'scheduled_for' => '2026-08-15',
    'payload' => ['title' => 'Overdue', 'body' => 'Payment overdue'],
]);
sc_p5v3_assert($throwResult === ['attempted' => 1, 'sent' => 0, 'failed' => 1, 'retryable' => true], 'SC-P5-024 transport exceptions become structured auditable failures');

$GLOBALS['sc_test_options'][FirebaseSettings::CREDENTIAL_REFERENCE_OPTION] = 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT';
$GLOBALS['sc_p5v3_access_token'] = 'SHORT_LIVED_ACCESS_TOKEN';
$GLOBALS['sc_p5v3_http_calls'] = [];
$GLOBALS['sc_p5v3_http_response'] = ['response' => ['code' => 200]];
$fcmToken = 'FCM_' . str_repeat('Z', 40);
$fcmResult = $push->send($fcmToken, ['title' => 'Hello', 'body' => 'SafeContracts', 'data' => ['payment_id' => 9001, 'attempt_no' => 0]]);
sc_p5v3_assert($fcmResult === ['success' => true, 'status_code' => 200, 'error_code' => null], 'SC-P5-024 FCM v1 success returns structured delivery result');
$httpCall = $GLOBALS['sc_p5v3_http_calls'][0] ?? null;
sc_p5v3_assert(is_array($httpCall) && $httpCall['url'] === 'https://fcm.googleapis.com/v1/projects/safecontracts-prod/messages:send', 'SC-P5-024 FCM v1 endpoint is project-scoped and deterministic');
sc_p5v3_assert(($httpCall['options']['headers']['Authorization'] ?? '') === 'Bearer SHORT_LIVED_ACCESS_TOKEN', 'SC-P5-024 short-lived access token is used only as Authorization header');
$httpBody = (string) ($httpCall['options']['body'] ?? '');
sc_p5v3_assert(str_contains($httpBody, $fcmToken) && str_contains($httpBody, '"payment_id":"9001"'), 'SC-P5-024 FCM request contains target token and stringified data payload');
sc_p5v3_assert(! str_contains($httpBody, 'SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT') && ! str_contains($httpBody, 'SHORT_LIVED_ACCESS_TOKEN'), 'SC-P5-024 FCM request body never leaks credential reference or access token');

fwrite(STDOUT, "SafeContracts P5 validation SC-P5-020..024 passed ({$tests} assertions).\n");
