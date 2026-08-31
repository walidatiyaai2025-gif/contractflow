<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\PushDeliveryService;
use SafeContracts\Notifications\PushTransport;

$tests = 0;

function sc_661_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_661_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_661_assert($error instanceof $class, $message);
        return;
    }
    sc_661_assert(false, $message);
}

final class SC_661_ScheduledPushTransport implements PushTransport
{
    public int $calls = 0;

    /** @var array<string,mixed> */
    public array $lastPayload = [];

    public function send(string $token, array $payload): array
    {
        $this->calls++;
        $this->lastPayload = $payload;
        return ['success' => true, 'status_code' => 200, 'error_code' => null];
    }
}

$transport = new SC_661_ScheduledPushTransport();
$delivery = new PushDeliveryService($transport);
$token = 'SCHEDULED_' . str_repeat('S', 40);

$GLOBALS['sc_test_result_queue'] = [[
    ['id' => '901', 'user_id' => '42', 'token' => $token, 'platform' => 'android'],
]];

$plan = [
    'rule_id' => 9,
    'payment_id' => 7001,
    'recipient_ids' => [42],
    'template_code' => 'supplier_payment_due_soon',
    'scheduled_for' => '2026-08-26',
    'payload' => [
        'title' => 'Supplier payment due soon',
        'body' => 'Scheduled supplier payment reminder.',
        'data' => [
            'payment_id' => 7001,
            'rule_code' => 'supplier_payment_due_soon',
            'template_code' => 'supplier_payment_due_soon',
            'attempt_no' => 29,
            'icon_key' => 'warning',
            'financial_direction' => 'payable',
        ],
    ],
];

$result = $delivery->deliver($plan, 29, 0);
sc_661_assert($result === ['attempted' => 1, 'sent' => 1, 'failed' => 0, 'retryable' => false], 'Scheduled push accepts the complete six-field metadata payload and reaches transport.');
sc_661_assert($transport->calls === 1, 'Scheduled push invokes Firebase transport after metadata validation.');
sc_661_assert(($transport->lastPayload['data']['financial_direction'] ?? null) === 'payable', 'Scheduled push preserves receivable/payable direction metadata for the mobile client.');
sc_661_assert(($transport->lastPayload['data']['template_code'] ?? null) === 'supplier_payment_due_soon', 'Scheduled push preserves the supplier due-soon template code for sound/category routing.');

$invalid = $plan;
$invalid['payload']['data']['financial_direction'] = 'customer';
sc_661_expect(
    InvalidArgumentException::class,
    static fn () => $delivery->deliver($invalid, 29, 0),
    'Scheduled push rejects non-canonical financial direction metadata.'
);

$root = dirname(__DIR__, 2);
$engine = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Notifications/NotificationEngine.php');
$repository = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Notifications/NotificationScheduleRepository.php');
$templateService = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Notifications/NotificationTemplateService.php');
$soundSettings = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Notifications/NotificationSoundSettings.php');
sc_661_assert(str_contains($engine, "in_array(\$direction, ['receivable', 'payable'], true)"), 'Notification engine only emits canonical financial direction metadata.');
sc_661_assert(str_contains($engine, "'template_code' => \$templateCode"), 'Notification engine carries the selected template code into scheduled push metadata.');
sc_661_assert(str_contains($repository, "p.financial_direction IN ('receivable','payable')"), 'Scheduled payment source guarantees canonical financial direction values.');
sc_661_assert(str_contains($templateService, "'supplier_payment_due_soon' => 'payment_due_soon'"), 'Supplier due-soon rules can render through the compatible due-soon template when no dedicated template row exists.');
sc_661_assert(str_contains($soundSettings, "'due_soon'"), 'Notification sound routing classifies supplier due-soon metadata as the due-reminder category.');

echo "SafeContracts scheduled FCM metadata regression passed ({$tests} assertions).\n";
