<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\NotificationEngine;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\PushDeliveryService;
use SafeContracts\Notifications\PushTransport;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_p5v4_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p5v4_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p5v4_assert($error instanceof $class, $message);
        return;
    }
    sc_p5v4_assert(false, $message);
}

final class SC_P5V4_Transport implements PushTransport
{
    public int $calls = 0;

    public function send(string $token, array $payload): array
    {
        unset($payload);
        $this->calls++;
        if (str_contains($token, 'THROW')) {
            throw new RuntimeException('simulated transport exception');
        }
        if (str_contains($token, 'FAIL')) {
            return ['success' => false, 'status_code' => 503, 'error_code' => 'temporary unavailable / unsafe detail'];
        }
        return ['success' => true, 'status_code' => 200, 'error_code' => null];
    }
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p5v4_assert(is_callable($activate), 'P5 final validation can activate SafeContracts');
$activate();

// SC-P5-025 — Delivery retry & logging.
$transport = new SC_P5V4_Transport();
$delivery = new PushDeliveryService($transport);
sc_p5v4_assert($delivery->canRetry(0) && $delivery->canRetry(1) && $delivery->canRetry(2), 'SC-P5-025 attempts 0..2 remain retryable');
sc_p5v4_assert(! $delivery->canRetry(3), 'SC-P5-025 retry policy stops after three retries');
sc_p5v4_assert($delivery->retryDelaySeconds(0) === 60, 'SC-P5-025 first retry delay is 60 seconds');
sc_p5v4_assert($delivery->retryDelaySeconds(1) === 120, 'SC-P5-025 second retry delay is 120 seconds');
sc_p5v4_assert($delivery->retryDelaySeconds(2) === 240, 'SC-P5-025 third retry delay is 240 seconds');
sc_p5v4_assert($delivery->retryDelaySeconds(3) === 0, 'SC-P5-025 exhausted retry policy schedules no further retry');
sc_p5v4_expect(InvalidArgumentException::class, fn () => $delivery->deliver([], -1), 'SC-P5-025 negative transport attempt is rejected');
sc_p5v4_expect(InvalidArgumentException::class, fn () => $delivery->deliver([], 4), 'SC-P5-025 attempt beyond retry ceiling is rejected');

$throwToken = 'THROW_' . str_repeat('T', 40);
$failToken = 'FAIL_' . str_repeat('F', 40);
$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '801', 'user_id' => '42', 'token' => $throwToken, 'platform' => 'android'],
    ['id' => '802', 'user_id' => '100', 'token' => $failToken, 'platform' => 'ios'],
]];
$beforeAttemptZero = count($GLOBALS['sc_test_queries']);
$resultZero = $delivery->deliver([
    'rule_id' => 30,
    'payment_id' => 9100,
    'recipient_ids' => [42, 100],
    'template_code' => 'payment_overdue',
    'scheduled_for' => '2026-08-15',
    'payload' => ['title' => 'Overdue', 'body' => 'Payment overdue.', 'data' => ['payment_id' => 9100]],
], 0);
$sqlZero = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeAttemptZero));
sc_p5v4_assert($resultZero === ['attempted' => 2, 'sent' => 0, 'failed' => 2, 'retryable' => true], 'SC-P5-025 exceptions and transport failures normalize into structured retryable result');
sc_p5v4_assert(substr_count($sqlZero, 'wp_safecontracts_notification_deliveries') === 2, 'SC-P5-025 every device attempt appends one delivery-log row');
sc_p5v4_assert(str_contains($sqlZero, 'transport_exception'), 'SC-P5-025 thrown transport error is normalized before logging');
sc_p5v4_assert(str_contains($sqlZero, 'temporary_unavailable___unsafe_detail'), 'SC-P5-025 error codes are sanitized before logging');
sc_p5v4_assert(! str_contains($sqlZero, $throwToken) && ! str_contains($sqlZero, $failToken), 'SC-P5-025 delivery log never contains raw device token material');

$GLOBALS['sc_test_result_queue'] = [[['id' => '801', 'user_id' => '42', 'token' => $throwToken, 'platform' => 'android']]];
$beforeAttemptOne = count($GLOBALS['sc_test_queries']);
$resultOne = $delivery->deliver([
    'rule_id' => 30,
    'payment_id' => 9100,
    'recipient_ids' => [42],
    'template_code' => 'payment_overdue',
    'scheduled_for' => '2026-08-15',
    'payload' => ['title' => 'Overdue', 'body' => 'Payment overdue.'],
], 1);
$sqlOne = implode("\n", array_slice($GLOBALS['sc_test_queries'], $beforeAttemptOne));
sc_p5v4_assert($resultOne['attempted'] === 1 && $resultOne['failed'] === 1, 'SC-P5-025 retry attempt remains independently auditable');
sc_p5v4_assert(str_contains($sqlOne, "'payment_overdue'") && str_contains($sqlOne, ', 1,'), 'SC-P5-025 retry log preserves template and attempt number');
sc_p5v4_assert($transport->calls === 3, 'SC-P5-025 transport is invoked exactly once per active device per attempt');

// SC-P5-026 — Settled-payment suppression.
$GLOBALS['sc_test_users_by_role'] = [RoleRegistrar::MANAGER => [100]];
$rule = NotificationRule::normalizeInput([
    'code' => 'settlement-suppression-validation',
    'name' => 'Settlement suppression validation',
    'trigger_type' => NotificationRule::TRIGGER_DUE_DAY,
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'target_assigned_accountant' => false,
]);
$rule['id'] = 31;
$engine = new NotificationEngine();
$today = new DateTimeImmutable('2026-08-15');
$payment = [
    'id' => 9200,
    'due_date' => '2026-08-15',
    'expected_payment_date' => '2026-09-01',
    'remaining_amount' => '100.0000',
    'status' => PaymentStatus::DUE,
    'accountant_user_id' => null,
    'reference' => 'PAY-9200',
    'contract_number' => 'SC-9200',
    'customer_name' => 'Example Customer',
];

$paidStatus = $payment;
$paidStatus['status'] = PaymentStatus::PAID;
$paidStatus['remaining_amount'] = '100.0000';
$readsBeforePaid = count($GLOBALS['sc_test_read_queries']);
sc_p5v4_assert($engine->plan($rule, $paidStatus, $today) === null, 'SC-P5-026 paid status suppresses notification even if stale remaining cache is positive');
sc_p5v4_assert(count($GLOBALS['sc_test_read_queries']) === $readsBeforePaid, 'SC-P5-026 paid status suppresses before template repository read');

$zeroBalance = $payment;
$zeroBalance['status'] = PaymentStatus::PARTIALLY_PAID;
$zeroBalance['remaining_amount'] = '0.0000';
$readsBeforeZero = count($GLOBALS['sc_test_read_queries']);
sc_p5v4_assert($engine->plan($rule, $zeroBalance, $today) === null, 'SC-P5-026 zero remaining balance suppresses stale partially-paid status');
sc_p5v4_assert(count($GLOBALS['sc_test_read_queries']) === $readsBeforeZero, 'SC-P5-026 zero balance suppresses before template repository read');

$suppressedEvents = $GLOBALS['sc_test_fired_actions']['safecontracts_notification_suppressed'] ?? [];
sc_p5v4_assert(count($suppressedEvents) >= 2, 'SC-P5-026 suppression emits operational evidence');
$lastSuppression = end($suppressedEvents);
sc_p5v4_assert(is_array($lastSuppression) && ($lastSuppression[2] ?? null) === 'settled', 'SC-P5-026 suppression reason is explicitly settled');

$partial = $payment;
$partial['status'] = PaymentStatus::PARTIALLY_PAID;
$partial['remaining_amount'] = '25.0000';
$GLOBALS['sc_test_result_queue'] = [[[
    'id' => '41',
    'code' => 'payment_due_today',
    'title_template' => 'Payment due {{contract_number}}',
    'body_template' => '{{payment_reference}} remaining {{remaining_amount}}',
    'is_active' => '1',
]]];
$partialPlan = $engine->plan($rule, $partial, $today);
sc_p5v4_assert(is_array($partialPlan), 'SC-P5-026 partial payment with positive remaining balance remains eligible');
sc_p5v4_assert(($partialPlan['payload']['data']['payment_id'] ?? null) === 9200, 'SC-P5-026 eligible partial payment still produces normal notification plan');

echo "SafeContracts P5 final validation SC-P5-025..026 passed ({$tests} assertions).\n";
