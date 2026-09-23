<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_expiry_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$rule = NotificationRule::normalizeInput([
    'code' => 'contract_expiry_30_days',
    'name' => 'Contract expiry - 30 day renewal reminder',
    'trigger_type' => NotificationRule::TRIGGER_CONTRACT_EXPIRY,
    'days_before' => 30,
    'days_after' => 0,
    'repeat_interval_days' => 0,
    'max_repeats' => 0,
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'recipient_user_ids' => [],
    'escalation_roles' => [],
    'target_assigned_accountant' => true,
    'push_enabled' => true,
    'email_enabled' => false,
    'template_code' => 'contract_expiry_soon',
    'is_active' => true,
]);

sc_expiry_assert($rule['trigger_type'] === NotificationRule::TRIGGER_CONTRACT_EXPIRY, 'contract expiry is a first-class notification trigger');
sc_expiry_assert($rule['days_before'] === 30, 'contract expiry reminder persists the 30-day offset');
sc_expiry_assert($rule['template_code'] === 'contract_expiry_soon', 'contract expiry rule uses its dedicated template');
sc_expiry_assert(
    NotificationRule::targetDate($rule, '2026-10-31')->format('Y-m-d') === '2026-10-01',
    '30-day contract expiry target is calculated from contract end_date'
);

$payment = [
    'id' => 10,
    'contract_id' => 2,
    'due_date' => '2026-10-31',
    'remaining_amount' => '100.0000',
    'status' => 'upcoming',
    'counterparty_type' => 'customer',
    'financial_direction' => 'receivable',
];
sc_expiry_assert(
    NotificationRule::matchesPayment($rule, $payment, new DateTimeImmutable('2026-10-01')) === false,
    'contract expiry rules can never be evaluated against payment due_date'
);

$root = dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/src/';
$template = (string) file_get_contents($root . 'Notifications/NotificationTemplate.php');
$scheduler = (string) file_get_contents($root . 'Notifications/NotificationScheduler.php');
$schedule = (string) file_get_contents($root . 'Notifications/NotificationScheduleService.php');
$service = (string) file_get_contents($root . 'Notifications/ContractExpiryNotificationService.php');
$migration = (string) file_get_contents($root . 'Database/Migrations/Migration0026ContractExpiryNotifications.php');
$schedulePage = (string) file_get_contents($root . 'Admin/NotificationSchedulePage.php');

foreach (['contract_end_date', 'days_until_expiry'] as $placeholder) {
    sc_expiry_assert(str_contains($template, "'{$placeholder}'"), "expiry template supports {$placeholder}");
}
sc_expiry_assert(str_contains($scheduler, 'new ContractExpiryNotificationService()'), 'five-minute scheduler invokes contract expiry processing');
sc_expiry_assert(str_contains($schedule, 'NotificationRule::TRIGGER_CONTRACT_EXPIRY') && str_contains($schedule, 'return 0;'), 'contract expiry is isolated from payment schedule materialization');
sc_expiry_assert(str_contains($service, "c.status = 'active'"), 'only active contracts are expiry candidates');
sc_expiry_assert(str_contains($service, "c.end_date IS NOT NULL"), 'expiry processing requires a contract end date');
sc_expiry_assert(str_contains($service, 'add_option($occurrenceKey'), 'expiry reminders claim a durable idempotency key before dispatch');
sc_expiry_assert(str_contains($service, "'resource_type' => 'contract'"), 'expiry push context deep-links to the contract');
sc_expiry_assert(str_contains($service, 'public function scheduledRows('), 'contract expiry exposes pending occurrences to the schedule screen');
sc_expiry_assert(str_contains($schedulePage, 'new ContractExpiryNotificationService())->scheduledRows('), 'notification schedule merges contract-expiry occurrences');
sc_expiry_assert(str_contains($schedulePage, "\$isContractExpiry"), 'notification schedule distinguishes contract reminders from payment reminders');
sc_expiry_assert(str_contains($schedulePage, "self::text('Contract ends', 'ينتهي العقد')"), 'contract-expiry schedule row shows end date instead of Payment #0');
sc_expiry_assert(str_contains($migration, "'contract_expiry_soon'"), 'production migration seeds the expiry template');
sc_expiry_assert(str_contains($migration, "'contract_expiry_30_days'"), 'production migration seeds the 30-day rule');
sc_expiry_assert(str_contains($migration, '30, 0, 0, 0'), 'seeded rule is exactly 30 days before expiry with no repeat by default');

echo "Contract expiry 30-day notification coverage passed ({$tests} assertions).\n";
