<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\NotificationPaymentScheduleReconciler;
use SafeContracts\Notifications\NotificationPaymentScope;
use SafeContracts\Notifications\NotificationRule;
use SafeContracts\Roles\RoleRegistrar;

$tests = 0;

function sc_665_supplier_assert(bool $ok, string $message): void
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
$dueDate = $today->modify('+1 day')->format('Y-m-d');

final class SC_665_SupplierScheduleWpdb
{
    public string $prefix = 'wp_';
    /** @var list<string> */
    public array $mutations = [];
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

        if (str_contains($sql, 'FROM wp_safecontracts_contracts') && str_contains($sql, 'financial_direction')) {
            return [[
                'id' => '9',
                'financial_direction' => 'payable',
                'counterparty_type' => 'supplier',
            ]];
        }

        if (str_contains($sql, 'FROM wp_safecontracts_scheduled_payments p')) {
            return [[
                'id' => '77',
                'contract_id' => '9',
                'reference' => 'SUP-PAY-77',
                'due_date' => $this->dueDate,
                'remaining_amount' => '100.0000',
                'status' => 'upcoming',
                // Simulate a historical duplicated field that drifted from the
                // authoritative contract direction.
                'financial_direction' => 'receivable',
                'currency_code' => 'KWD',
                'accountant_user_id' => null,
                'contract_number' => 'SUP-2026-009',
                'counterparty_type' => 'supplier',
                'counterparty_id' => '5',
                'counterparty_name' => 'Supplier A',
                'customer_name' => null,
                'supplier_name' => 'Supplier A',
            ]];
        }

        if (str_contains($sql, 'FROM wp_safecontracts_notification_rules')) {
            return [[
                'id' => '15',
                'code' => 'supplier_payable_due_1_day',
                'name' => 'Supplier payable - 1 day before due',
                'trigger_type' => 'before_due',
                'counterparty_type' => 'supplier',
                'financial_direction' => 'payable',
                'days_before' => '1',
                'days_after' => '0',
                'repeat_interval_days' => '0',
                'max_repeats' => '0',
                'recipient_roles_json' => '["safecontracts_manager"]',
                'recipient_user_ids_json' => '[]',
                'escalation_roles_json' => '[]',
                'target_assigned_accountant' => '0',
                'push_enabled' => '1',
                'email_enabled' => '1',
                'template_code' => 'supplier_payment_due_soon',
                'is_active' => '1',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => '2026-08-27 00:00:00',
                'updated_at' => '2026-08-27 00:00:00',
            ]];
        }

        if (str_contains($sql, 'FROM wp_safecontracts_notification_suppressions')) {
            return [];
        }

        if (str_contains($sql, 'FROM wp_safecontracts_notification_templates')) {
            if (str_contains($sql, "'supplier_payment_due_soon'")) {
                return [];
            }
            if (str_contains($sql, "'payment_due_soon'")) {
                return [[
                    'id' => '1',
                    'code' => 'payment_due_soon',
                    'title_template' => 'Payment due soon',
                    'body_template' => '{{counterparty_name}} payment {{payment_reference}} is due {{due_date}}.',
                    'email_subject_template' => 'Payment due soon',
                    'email_body_template' => '{{contract_number}} payment {{payment_reference}} has {{remaining_amount}} remaining.',
                    'icon_key' => 'warning',
                    'is_active' => '1',
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => '2026-08-27 00:00:00',
                    'updated_at' => '2026-08-27 00:00:00',
                ]];
            }
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
$fakeWpdb = new SC_665_SupplierScheduleWpdb($dueDate);
$GLOBALS['wpdb'] = $fakeWpdb;
$GLOBALS['sc_test_users_by_role'][RoleRegistrar::MANAGER] = [42];

$stalePayment = [
    'id' => 77,
    'contract_id' => 9,
    'counterparty_type' => 'supplier',
    'financial_direction' => 'receivable',
    'due_date' => $dueDate,
    'remaining_amount' => '100.0000',
    'status' => 'upcoming',
];
$supplierRule = NotificationRule::normalizeInput([
    'code' => 'supplier_payable_due_1_day',
    'name' => 'Supplier payable - 1 day before due',
    'trigger_type' => NotificationRule::TRIGGER_BEFORE_DUE,
    'counterparty_type' => 'supplier',
    'financial_direction' => 'payable',
    'days_before' => 1,
    'recipient_roles' => [RoleRegistrar::MANAGER],
    'recipient_user_ids' => [],
    'escalation_roles' => [],
    'target_assigned_accountant' => false,
    'push_enabled' => true,
    'email_enabled' => true,
    'repeat_interval_days' => 0,
    'max_repeats' => 0,
    'template_code' => 'supplier_payment_due_soon',
    'is_active' => true,
]);

sc_665_supplier_assert(
    ! NotificationRule::matchesPayment($supplierRule, $stalePayment, $today),
    'the stale duplicated payment direction demonstrates the historical supplier schedule mismatch'
);
$canonical = NotificationPaymentScope::canonicalize($stalePayment);
sc_665_supplier_assert($canonical['financial_direction'] === 'payable', 'contract-owned payable direction repairs the supplier notification scope');
sc_665_supplier_assert($canonical['counterparty_type'] === 'supplier', 'contract-owned supplier type remains authoritative');
sc_665_supplier_assert(($canonical['notification_scope_source'] ?? '') === 'contract', 'diagnostic scope source identifies contract reconciliation');
sc_665_supplier_assert(NotificationRule::matchesPayment($supplierRule, $canonical, $today), 'supplier payable rule matches after contract-scope reconciliation');

$reconciled = (new NotificationPaymentScheduleReconciler())->reconcile(77);
sc_665_supplier_assert($reconciled === 1, 'supplier due-soon reconciliation materializes one real schedule occurrence');
$mutationSql = implode("\n", $fakeWpdb->mutations);
sc_665_supplier_assert(str_contains($mutationSql, 'INSERT INTO wp_safecontracts_notification_schedule'), 'supplier occurrence is persisted into the real notification schedule table');
sc_665_supplier_assert(str_contains($mutationSql, "'supplier_payment_due_soon'"), 'persisted supplier occurrence retains supplier_payment_due_soon for downstream sound routing');
sc_665_supplier_assert(str_contains($mutationSql, "'pending'"), 'supplier occurrence enters the pending dispatch state');

$GLOBALS['wpdb'] = $originalWpdb;

echo "SafeContracts supplier notification schedule #665 passed ({$tests} assertions).\n";
