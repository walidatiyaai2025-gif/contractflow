<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\PushDeliveryService;
use SafeContracts\Notifications\PushTransport;

$tests = 0;
function sc_618_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class SC_618_FakeTransport implements PushTransport
{
    public function __construct(private bool $success = true) {}

    public function send(string $token, array $payload): array
    {
        return $this->success
            ? ['success' => true, 'status_code' => 200, 'error_code' => null]
            : ['success' => false, 'status_code' => 503, 'error_code' => 'temporary_unavailable'];
    }
}

$plan = [
    'rule_id' => 21,
    'payment_id' => 15,
    'recipient_ids' => [42],
    'template_code' => 'payment_overdue',
    'scheduled_for' => '2026-10-31',
    'payload' => [
        'title' => 'Payment overdue',
        'body' => 'Payment remains overdue.',
        'data' => [
            'payment_id' => 15,
            'rule_code' => 'customer_receivable_overdue_daily',
            'attempt_no' => 29,
        ],
    ],
];

$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '901', 'user_id' => '42', 'token' => 'VALID_' . str_repeat('V', 40), 'platform' => 'android'],
]];
$delivery = new PushDeliveryService(new SC_618_FakeTransport(true));
$result = $delivery->deliver($plan, 29, 0);
sc_618_assert($result === ['attempted' => 1, 'sent' => 1, 'failed' => 0, 'retryable' => false], '30-day occurrence 29 is accepted as business cadence and is not rejected as a transport retry');

$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '902', 'user_id' => '42', 'token' => 'TEMP_' . str_repeat('T', 40), 'platform' => 'android'],
]];
$retryDelivery = new PushDeliveryService(new SC_618_FakeTransport(false));
$retryResult = $retryDelivery->deliver($plan, 29, 0);
sc_618_assert($retryResult['retryable'] === true, 'fresh transport attempt for occurrence 29 remains retryable after a transient Firebase failure');

$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '903', 'user_id' => '42', 'token' => 'TEMP_' . str_repeat('R', 40), 'platform' => 'android'],
]];
$exhausted = $retryDelivery->deliver($plan, 29, PushDeliveryService::MAX_TRANSPORT_RETRIES);
sc_618_assert($exhausted['retryable'] === false, 'transport retry policy is still capped independently at three retries');

try {
    $delivery->deliver($plan, 29, PushDeliveryService::MAX_TRANSPORT_RETRIES + 1);
    sc_618_assert(false, 'transport attempt above retry policy must be rejected');
} catch (InvalidArgumentException) {
    sc_618_assert(true, 'transport attempt above retry policy is rejected without blocking high occurrence numbers');
}

echo "OK: {$tests} notification occurrence/transport retry assertions passed\n";
