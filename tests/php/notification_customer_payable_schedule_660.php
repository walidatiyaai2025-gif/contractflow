<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\NotificationPaymentScheduleReconciler;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Notifications\NotificationScheduler;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;
function sc_660_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value): string|false
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }
}

$today = new DateTimeImmutable('today');
$rule = NotificationRule::normalizeInput([
    'code' => 'customer_payable_due_today',
    'name' => 'Customer payable due today',
    'trigger_type' => NotificationRule::TRIGGER_DUE_DAY,
    'counterparty_type' => 'customer',
    'financial_direction' => 'payable',
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'recipient_user_ids' => [],
    'escalation_roles' => [],
    'target_assigned_accountant' => false,
    'push_enabled' => true,
    'email_enabled' => false,
    'repeat_interval_days' => 0,
    'max_repeats' => 0,
    'template_code' => 'payment_due_today',
    'is_active' => true,
]);
$payment = [
    'id' => 77,
    'contract_id' => 9,
    'counterparty_type' => 'customer',
    'financial_direction' => 'payable',
    'due_date' => $today->format('Y-m-d'),
    'remaining_amount' => '100.0000',
    'status' => 'upcoming',
];
sc_660_assert(NotificationRule::matchesPayment($rule, $payment, $today), 'customer payable due payment matches explicit customer/payable rule scope');

$receivablePayment = $payment;
$receivablePayment['financial_direction'] = 'receivable';
sc_660_assert(! NotificationRule::matchesPayment($rule, $receivablePayment, $today), 'customer receivable payment cannot leak into customer/payable rule');

$supplierPayment = $payment;
$supplierPayment['counterparty_type'] = 'supplier';
sc_660_assert(! NotificationRule::matchesPayment($rule, $supplierPayment, $today), 'supplier payable payment cannot leak into customer/payable rule');

$legacyRule = $rule;
unset($legacyRule['counterparty_type'], $legacyRule['financial_direction']);
sc_660_assert(NotificationRule::matchesPayment($legacyRule, $payment, $today), 'legacy notification rules remain all/all and continue covering customer payable payments');

$legacyRow = NotificationRule::fromRow([
    'id' => 3,
    'code' => 'legacy_due',
    'name' => 'Legacy due',
    'trigger_type' => 'due_day',
    'recipient_roles_json' => '["safecontracts_manager"]',
    'recipient_user_ids_json' => '[]',
    'escalation_roles_json' => '[]',
    'target_assigned_accountant' => 0,
    'push_enabled' => 1,
    'email_enabled' => 0,
    'template_code' => 'payment_due_today',
    'is_active' => 1,
]);
sc_660_assert($legacyRow['counterparty_type'] === 'all' && $legacyRow['financial_direction'] === 'all', 'legacy persisted rows normalize to backward-compatible all/all scope');

NotificationScheduler::register();
foreach (['safecontracts_payment_created', 'safecontracts_payment_dates_changed', 'safecontracts_payment_status_changed', 'safecontracts_payment_settled'] as $hook) {
    $callbacks = $GLOBALS['sc_test_actions'][$hook] ?? [];
    $found = false;
    foreach ($callbacks as $callback) {
        if ($callback === [NotificationScheduler::class, 'reconcilePayment']) {
            $found = true;
            break;
        }
    }
    sc_660_assert($found, "{$hook} triggers immediate payment notification schedule reconciliation");
}

final class SC_Notification660_Wpdb
{
    public string $prefix = 'wp_';
    /** @var list<string> */
    public array $mutations = [];
    public string $ruleDirection = 'payable';
    public string $dueDate;

    public function __construct(string $dueDate)
    {
        $this->dueDate = $dueDate;
    }

    public function prepare(string $query, mixed ...$args): string
    {
        $prepared = array_map(
            static fn (mixed $value): mixed => is_int($value) ? $value : "'" . addslashes((string) $value) . "'",
            $args
        );
        return vsprintf($query, $prepared);
    }

    public function get_var(string $sql): int
    {
        unset($sql);
        return 0;
    }

    /** @return list<array<string,mixed>> */
    public function get_results(string $sql, mixed $output = null): array
    {
        unset($output);
        if (str_contains($sql, 'FROM wp_safecontracts_notification_suppressions')) {
            return [];
        }
        if (str_contains($sql, 'FROM wp_safecontracts_notification_templates')) {
            return [[
                'id' => '1',
                'code' => 'payment_due_today',
                'title_template' => 'Payment due today',
                'body_template' => '{{customer_name}} payment {{payment_reference}} is due {{due_date}}.',
                'email_subject_template' => 'Payment due today',
                'email_body_template' => '{{contract_number}} payment {{payment_reference}} has {{remaining_amount}} remaining.',
                'icon_key' => 'payment',
                'is_active' => '1',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => '2026-08-26 00:00:00',
                'updated_at' => '2026-08-26 00:00:00',
            ]];
        }
        if (str_contains($sql, 'FROM wp_safecontracts_notification_rules')) {
            return [[
                'id' => '11',
                'code' => 'customer_payable_due_today',
                'name' => 'Customer payable due today',
                'trigger_type' => 'due_day',
                'counterparty_type' => 'customer',
                'financial_direction' => $this->ruleDirection,
                'days_before' => '0',
                'days_after' => '0',
                'repeat_interval_days' => '0',
                'max_repeats' => '0',
                'recipient_roles_json' => '["safecontracts_manager"]',
                'recipient_user_ids_json' => '[]',
                'escalation_roles_json' => '[]',
                'target_assigned_accountant' => '0',
                'push_enabled' => '1',
                'email_enabled' => '0',
                'template_code' => 'payment_due_today',
                'is_active' => '1',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => '2026-08-26 00:00:00',
                'updated_at' => '2026-08-26 00:00:00',
            ]];
        }
        if (str_contains($sql, 'FROM wp_safecontracts_scheduled_payments p')) {
            return [[
                'id' => '77',
                'contract_id' => '9',
                'reference' => 'PAY-77',
                'due_date' => $this->dueDate,
                'remaining_amount' => '100.0000',
                'status' => 'upcoming',
                'financial_direction' => 'payable',
                'currency_code' => 'KWD',
                'accountant_user_id' => null,
                'contract_number' => 'CUST-PAY-77',
                'counterparty_type' => 'customer',
                'counterparty_id' => '5',
                'counterparty_name' => 'Customer A',
                'customer_name' => 'Customer A',
                'supplier_name' => null,
            ]];
        }
        return [];
    }

    public function query(string $sql): int|false
    {
        $this->mutations[] = $sql;
        return 1;
    }
}

$originalWpdb = $GLOBALS['wpdb'];
$fakeWpdb = new SC_Notification660_Wpdb($today->format('Y-m-d'));
$GLOBALS['wpdb'] = $fakeWpdb;
$GLOBALS['sc_test_users_by_role'][RoleRegistrar::MANAGER] = [42];

$reconciled = (new NotificationPaymentScheduleReconciler())->reconcile(77);
sc_660_assert($reconciled === 1, 'event-driven reconciliation materializes one customer payable due occurrence immediately');
$mutationSql = implode("\n", $fakeWpdb->mutations);
sc_660_assert(str_contains($mutationSql, "DELETE FROM wp_safecontracts_notification_schedule") && str_contains($mutationSql, "status IN ('pending','failed','skipped')"), 'reconciliation clears only mutable historical schedule occurrences');
sc_660_assert(str_contains($mutationSql, 'INSERT INTO wp_safecontracts_notification_schedule'), 'reconciliation persists the customer payable occurrence into Notification Schedule');
sc_660_assert(str_contains($mutationSql, "'payment_due_today'") && str_contains($mutationSql, "'pending'"), 'persisted schedule occurrence retains template and pending state');

$insertCount = substr_count($mutationSql, 'INSERT INTO wp_safecontracts_notification_schedule');
$fakeWpdb->ruleDirection = 'receivable';
$reconciledMismatch = (new NotificationPaymentScheduleReconciler())->reconcile(77);
$afterMismatchSql = implode("\n", $fakeWpdb->mutations);
sc_660_assert($reconciledMismatch === 0, 'mismatched receivable rule does not schedule a payable customer payment');
sc_660_assert(substr_count($afterMismatchSql, 'INSERT INTO wp_safecontracts_notification_schedule') === $insertCount, 'direction mismatch creates no extra persisted occurrence');

$GLOBALS['wpdb'] = $originalWpdb;

echo "SafeContracts customer payable notification schedule #660 passed ({$tests} assertions).\n";
