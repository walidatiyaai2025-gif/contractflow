<?php

declare(strict_types=1);

namespace SafeContracts\Support;

use RuntimeException;
use Throwable;

/**
 * Generates a reversible, explicitly registered load-test dataset.
 *
 * Every inserted primary key is stored in a non-autoloaded WordPress option.
 * Deletion only targets those exact IDs and never truncates a table or matches
 * broad business values, so pre-existing production rows cannot be removed.
 */
final class DemoDataService
{
    public const ROWS_PER_TABLE = 500;
    public const REGISTRY_OPTION = 'safecontracts_demo_data_registry_v1';

    /** @var list<string> */
    private const TABLES = [
        'safecontracts_meta',
        'safecontracts_customers',
        'safecontracts_suppliers',
        'safecontracts_payment_methods',
        'safecontracts_contracts',
        'safecontracts_contract_financial_items',
        'safecontracts_contract_adjustments',
        'safecontracts_contract_attachments',
        'safecontracts_contract_history',
        'safecontracts_scheduled_payments',
        'safecontracts_payment_collections',
        'safecontracts_payment_followups',
        'safecontracts_audit_log',
        'safecontracts_notification_rules',
        'safecontracts_notification_templates',
        'safecontracts_device_tokens',
        'safecontracts_notification_deliveries',
        'safecontracts_import_runs',
        'safecontracts_import_errors',
        'safecontracts_notification_schedule',
        'safecontracts_notification_suppressions',
        'safecontracts_entity_attachments',
    ];

    /** @return array{batch_id:string,created_at:string,created_by:int,rows_per_table:int,total_rows:int,tables:array<string,list<int>>} */
    public function create(): array
    {
        if ($this->registry() !== null) {
            throw new RuntimeException('A SafeContracts demo batch already exists. Delete it before creating another batch.');
        }
        $wpdb = $this->database();
        $this->assertTablesExist($wpdb);
        $batchId = gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
        $marker = '[SC-DEMO:' . $batchId . ']';
        $now = gmdate('Y-m-d H:i:s');
        $actorId = max(1, (int) get_current_user_id());
        $year = (int) gmdate('Y');
        $tables = [];

        $registryAdded = false;
        $this->query($wpdb, 'START TRANSACTION');
        try {
            $tables['safecontracts_meta'] = $this->seedMeta($wpdb, $batchId, $marker, $now);
            $customerIds = $tables['safecontracts_customers'] = $this->seedCustomers($wpdb, $batchId, $marker, $actorId, $now);
            $supplierIds = $tables['safecontracts_suppliers'] = $this->seedSuppliers($wpdb, $batchId, $marker, $actorId, $now);
            $methodIds = $tables['safecontracts_payment_methods'] = $this->seedPaymentMethods($wpdb, $batchId, $now);
            $contractIds = $tables['safecontracts_contracts'] = $this->seedContracts($wpdb, $batchId, $marker, $actorId, $year, $now, $customerIds, $supplierIds);
            $tables['safecontracts_contract_financial_items'] = $this->seedFinancialItems($wpdb, $marker, $actorId, $now, $contractIds);
            $tables['safecontracts_contract_adjustments'] = $this->seedAdjustments($wpdb, $marker, $actorId, $now, $contractIds);
            $tables['safecontracts_contract_attachments'] = $this->seedContractAttachments($wpdb, $marker, $actorId, $now, $contractIds);
            $tables['safecontracts_contract_history'] = $this->seedContractHistory($wpdb, $marker, $actorId, $now, $contractIds);
            $paymentIds = $tables['safecontracts_scheduled_payments'] = $this->seedPayments($wpdb, $batchId, $marker, $actorId, $year, $now, $contractIds);
            $tables['safecontracts_payment_collections'] = $this->seedCollections($wpdb, $batchId, $marker, $actorId, $year, $now, $paymentIds, $methodIds);
            $tables['safecontracts_payment_followups'] = $this->seedFollowUps($wpdb, $marker, $actorId, $now, $paymentIds);
            $tables['safecontracts_audit_log'] = $this->seedAudit($wpdb, $marker, $actorId, $now, $paymentIds);
            $ruleIds = $tables['safecontracts_notification_rules'] = $this->seedNotificationRules($wpdb, $batchId, $actorId, $now);
            $tables['safecontracts_notification_templates'] = $this->seedNotificationTemplates($wpdb, $batchId, $actorId, $now);
            $tokenIds = $tables['safecontracts_device_tokens'] = $this->seedDeviceTokens($wpdb, $marker, $actorId, $now);
            $tables['safecontracts_notification_deliveries'] = $this->seedNotificationDeliveries($wpdb, $batchId, $marker, $actorId, $year, $now, $ruleIds, $paymentIds, $tokenIds);
            $runIds = $tables['safecontracts_import_runs'] = $this->seedImportRuns($wpdb, $batchId, $marker, $actorId, $now);
            $tables['safecontracts_import_errors'] = $this->seedImportErrors($wpdb, $marker, $now, $runIds);
            $tables['safecontracts_notification_schedule'] = $this->seedNotificationSchedule($wpdb, $batchId, $actorId, $year, $now, $ruleIds, $paymentIds);
            $tables['safecontracts_notification_suppressions'] = $this->seedSuppressions($wpdb, $marker, $actorId, $now, $paymentIds);
            $tables['safecontracts_entity_attachments'] = $this->seedEntityAttachments($wpdb, $marker, $actorId, $now, $paymentIds);

            foreach (self::TABLES as $table) {
                if (count($tables[$table] ?? []) !== self::ROWS_PER_TABLE) {
                    throw new RuntimeException('Demo row verification failed for ' . $table . '.');
                }
            }
            $registry = [
                'batch_id' => $batchId,
                'created_at' => $now,
                'created_by' => $actorId,
                'rows_per_table' => self::ROWS_PER_TABLE,
                'total_rows' => self::ROWS_PER_TABLE * count(self::TABLES),
                'tables' => $tables,
            ];
            if (! add_option(self::REGISTRY_OPTION, $registry, '', false)) {
                throw new RuntimeException('Unable to persist the exact SafeContracts demo row registry.');
            }
            $registryAdded = true;
            $this->query($wpdb, 'COMMIT');
            return $registry;
        } catch (Throwable $error) {
            $this->query($wpdb, 'ROLLBACK', false);
            if ($registryAdded) {
                delete_option(self::REGISTRY_OPTION);
            }
            throw $error;
        }
    }

    /** @return array{batch_id:string,deleted_rows:int,table_count:int} */
    public function delete(): array
    {
        $registry = $this->registry();
        if ($registry === null) {
            throw new RuntimeException('No SafeContracts demo batch is registered.');
        }
        $wpdb = $this->database();
        $tables = is_array($registry['tables'] ?? null) ? $registry['tables'] : [];
        $batchId = (string) ($registry['batch_id'] ?? '');
        if (! preg_match('/^\d{14}-[a-f0-9]{8}$/', $batchId)) {
            throw new RuntimeException('SafeContracts demo registry batch ID is invalid.');
        }
        $allowed = array_flip(self::TABLES);
        $deleted = 0;
        $this->query($wpdb, 'START TRANSACTION');
        try {
            foreach (array_reverse(self::TABLES) as $suffix) {
                if (! isset($allowed[$suffix])) {
                    continue;
                }
                $ids = array_values(array_filter(array_map('intval', is_array($tables[$suffix] ?? null) ? $tables[$suffix] : []), static fn (int $id): bool => $id > 0));
                [$markerColumn, $markerPrefix] = $this->deleteMarker($suffix, $batchId);
                $markerLike = $this->escapeLike($wpdb, $markerPrefix) . '%';
                foreach (array_chunk($ids, 200) as $chunk) {
                    $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                    $sql = $wpdb->prepare(
                        'DELETE FROM ' . $this->table($wpdb, $suffix) . " WHERE id IN ({$placeholders}) AND `{$markerColumn}` LIKE %s",
                        ...array_merge($chunk, [$markerLike])
                    );
                    $result = $wpdb->query($sql);
                    if ($result === false) {
                        throw new RuntimeException('Unable to delete registered demo rows from ' . $suffix . '.');
                    }
                    $deleted += (int) $result;
                }
            }
            if (! delete_option(self::REGISTRY_OPTION)) {
                throw new RuntimeException('Unable to clear the SafeContracts demo row registry.');
            }
            $this->query($wpdb, 'COMMIT');
            return [
                'batch_id' => (string) ($registry['batch_id'] ?? ''),
                'deleted_rows' => $deleted,
                'table_count' => count(self::TABLES),
            ];
        } catch (Throwable $error) {
            $this->query($wpdb, 'ROLLBACK', false);
            throw $error;
        }
    }

    /** @return array<string,mixed>|null */
    public function registry(): ?array
    {
        $value = get_option(self::REGISTRY_OPTION, null);
        return is_array($value) && isset($value['batch_id'], $value['tables']) ? $value : null;
    }

    /** @return list<int> */
    private function seedMeta(object $wpdb, string $batchId, string $marker, string $now): array
    {
        $rows = [];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $rows[] = ['demo_' . $batchId . '_' . $i, $marker . ' load row ' . $i, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_meta', ['meta_key', 'meta_value', 'created_at', 'updated_at'], $rows, 'meta_key', 'demo_' . $batchId . '_');
    }

    /** @return list<int> */
    private function seedCustomers(object $wpdb, string $batchId, string $marker, int $actorId, string $now): array
    {
        $rows = [];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $rows[] = ['DEMO-C-' . $batchId . '-' . $i, 'عميل ديمو ' . $i, 'موظف تجريبي ' . $i, 'demo.customer.' . $i . '@example.invalid', '010' . str_pad((string) $i, 8, '0', STR_PAD_LEFT), $marker, 1, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_customers', ['internal_code', 'name', 'contact_name', 'email', 'phone', 'notes', 'is_active', 'created_by', 'created_at', 'updated_at'], $rows, 'internal_code', 'DEMO-C-' . $batchId . '-');
    }

    /** @return list<int> */
    private function seedSuppliers(object $wpdb, string $batchId, string $marker, int $actorId, string $now): array
    {
        $rows = [];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $rows[] = ['DEMO-S-' . $batchId . '-' . $i, 'مورد ديمو ' . $i, 'شركة مورد ديمو ' . $i, 'Demo Supplier ' . $i, 'مسؤول مورد ' . $i, 'demo.supplier.' . $i . '@example.invalid', '011' . str_pad((string) $i, 8, '0', STR_PAD_LEFT), 'عنوان ديمو ' . $i, 'EG', 'REG-' . $batchId . '-' . $i, 'TAX-' . $batchId . '-' . $i, 'EGP', 'Net 30', 'active', $marker, 1, 0, null, null, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_suppliers', ['internal_code', 'name', 'legal_name', 'trading_name', 'contact_name', 'email', 'phone', 'address', 'country_code', 'registration_number', 'tax_number', 'default_currency', 'payment_terms', 'status', 'notes', 'is_active', 'is_archived', 'archived_by', 'archived_at', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'internal_code', 'DEMO-S-' . $batchId . '-');
    }

    /** @return list<int> */
    private function seedPaymentMethods(object $wpdb, string $batchId, string $now): array
    {
        $rows = [];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $rows[] = ['demo-' . $batchId . '-' . $i, 'طريقة دفع ديمو ' . $i, $i, 1, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_payment_methods', ['code', 'name', 'display_order', 'is_active', 'created_at', 'updated_at'], $rows, 'code', 'demo-' . $batchId . '-');
    }

    /** @param list<int> $customerIds @param list<int> $supplierIds @return list<int> */
    private function seedContracts(object $wpdb, string $batchId, string $marker, int $actorId, int $year, string $now, array $customerIds, array $supplierIds): array
    {
        $rows = [];
        $currencies = ['EGP', 'KWD', 'USD'];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $receivable = $i % 2 === 1;
            $counterpartyId = $receivable ? $customerIds[$i - 1] : $supplierIds[$i - 1];
            $rows[] = ['DEMO-CTR-' . $batchId . '-' . $i, $receivable ? $counterpartyId : null, $receivable ? 'customer' : 'supplier', $counterpartyId, $receivable ? 'receivable' : 'payable', $currencies[($i - 1) % 3], $actorId, 'active', $year . '-01-01', $year . '-12-31', (string) (5000 + ($i * 25)), $marker, 0, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_contracts', ['contract_number', 'customer_id', 'counterparty_type', 'counterparty_id', 'financial_direction', 'currency_code', 'accountant_user_id', 'status', 'start_date', 'end_date', 'base_value', 'notes', 'is_archived', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'contract_number', 'DEMO-CTR-' . $batchId . '-');
    }

    /** @param list<int> $contractIds @return list<int> */
    private function seedFinancialItems(object $wpdb, string $marker, int $actorId, string $now, array $contractIds): array
    {
        $rows = [];
        foreach ($contractIds as $index => $contractId) {
            $rows[] = [$contractId, $marker . ' بند مالي ' . ($index + 1), (string) (1000 + $index), 1, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_contract_financial_items', ['contract_id', 'description', 'amount', 'display_order', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'description', $marker);
    }

    /** @param list<int> $contractIds @return list<int> */
    private function seedAdjustments(object $wpdb, string $marker, int $actorId, string $now, array $contractIds): array
    {
        $rows = [];
        foreach ($contractIds as $index => $contractId) {
            $rows[] = [$contractId, $index % 2 === 0 ? 'addition' : 'deduction', $marker . ' تعديل ' . ($index + 1), (string) (10 + $index), 1, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_contract_adjustments', ['contract_id', 'adjustment_type', 'description', 'amount', 'display_order', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'description', $marker);
    }

    /** @param list<int> $contractIds @return list<int> */
    private function seedContractAttachments(object $wpdb, string $marker, int $actorId, string $now, array $contractIds): array
    {
        $rows = [];
        foreach ($contractIds as $index => $contractId) {
            $rows[] = [$contractId, 9000000 + $index, $marker . ' مرفق عقد ' . ($index + 1), $actorId, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_contract_attachments', ['contract_id', 'media_id', 'label', 'created_by', 'created_at'], $rows, 'label', $marker);
    }

    /** @param list<int> $contractIds @return list<int> */
    private function seedContractHistory(object $wpdb, string $marker, int $actorId, string $now, array $contractIds): array
    {
        $rows = [];
        foreach ($contractIds as $contractId) {
            $rows[] = [$contractId, 'demo_created', $actorId, json_encode(['marker' => $marker], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_contract_history', ['contract_id', 'event_type', 'actor_user_id', 'snapshot_json', 'created_at'], $rows, 'snapshot_json', '{"marker":"' . $marker);
    }

    /** @param list<int> $contractIds @return list<int> */
    private function seedPayments(object $wpdb, string $batchId, string $marker, int $actorId, int $year, string $now, array $contractIds): array
    {
        $rows = [];
        $currencies = ['EGP', 'KWD', 'USD'];
        foreach ($contractIds as $index => $contractId) {
            $i = $index + 1;
            $amount = 1000 + ($i * 10);
            $paid = 200 + ($i % 50);
            $month = ($index % 12) + 1;
            $day = ($index % 28) + 1;
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $rows[] = [$contractId, $i % 2 === 1 ? 'receivable' : 'payable', $currencies[$index % 3], 1, 'DEMO-PAY-' . $batchId . '-' . $i, $date, $date, (string) $amount, (string) $paid, (string) ($amount - $paid), 'partially_paid', 0, null, null, $marker, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_scheduled_payments', ['contract_id', 'financial_direction', 'currency_code', 'sequence_no', 'reference', 'due_date', 'expected_payment_date', 'original_amount', 'paid_amount', 'remaining_amount', 'status', 'is_archived', 'archived_by', 'archived_at', 'followup_notes', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'reference', 'DEMO-PAY-' . $batchId . '-');
    }

    /** @param list<int> $paymentIds @param list<int> $methodIds @return list<int> */
    private function seedCollections(object $wpdb, string $batchId, string $marker, int $actorId, int $year, string $now, array $paymentIds, array $methodIds): array
    {
        $rows = [];
        $currencies = ['EGP', 'KWD', 'USD'];
        foreach ($paymentIds as $index => $paymentId) {
            $i = $index + 1;
            $date = sprintf('%04d-%02d-%02d', $year, ($index % 12) + 1, ($index % 28) + 1);
            $rows[] = [$paymentId, $i % 2 === 1 ? 'receivable' : 'payable', $currencies[$index % 3], (string) (200 + ($i % 50)), $date, $methodIds[$index], 'DEMO-SET-' . $batchId . '-' . $i, $marker, null, 0, null, null, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_payment_collections', ['payment_id', 'financial_direction', 'currency_code', 'amount', 'collection_date', 'payment_method_id', 'reference', 'details', 'proof_media_id', 'is_archived', 'archived_by', 'archived_at', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'reference', 'DEMO-SET-' . $batchId . '-');
    }

    /** @param list<int> $paymentIds @return list<int> */
    private function seedFollowUps(object $wpdb, string $marker, int $actorId, string $now, array $paymentIds): array
    {
        $rows = [];
        foreach ($paymentIds as $index => $paymentId) {
            $rows[] = [$paymentId, 'contacted', $marker . ' ملاحظة موظف على الدفعة ' . ($index + 1), null, null, $actorId, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_payment_followups', ['payment_id', 'state', 'note', 'promised_date', 'deferred_until', 'created_by', 'created_at'], $rows, 'note', $marker);
    }

    /** @param list<int> $paymentIds @return list<int> */
    private function seedAudit(object $wpdb, string $marker, int $actorId, string $now, array $paymentIds): array
    {
        $rows = [];
        foreach ($paymentIds as $paymentId) {
            $rows[] = ['payment', $paymentId, 'demo_seeded', $actorId, null, null, json_encode(['marker' => $marker], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_audit_log', ['entity_type', 'entity_id', 'event_type', 'actor_user_id', 'before_json', 'after_json', 'context_json', 'created_at'], $rows, 'context_json', '{"marker":"' . $marker);
    }

    /** @return list<int> */
    private function seedNotificationRules(object $wpdb, string $batchId, int $actorId, string $now): array
    {
        $rows = [];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $code = 'demo-rule-' . $batchId . '-' . $i;
            $rows[] = [$code, 'قاعدة ديمو ' . $i, 'before_due', $i % 30, 0, 7, 3, '["administrator"]', '[' . $actorId . ']', '[]', 1, 0, 0, 'demo-template-' . $batchId . '-' . $i, 1, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_notification_rules', ['code', 'name', 'trigger_type', 'days_before', 'days_after', 'repeat_interval_days', 'max_repeats', 'recipient_roles_json', 'recipient_user_ids_json', 'escalation_roles_json', 'target_assigned_accountant', 'push_enabled', 'email_enabled', 'template_code', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'code', 'demo-rule-' . $batchId . '-');
    }

    /** @return list<int> */
    private function seedNotificationTemplates(object $wpdb, string $batchId, int $actorId, string $now): array
    {
        $rows = [];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $code = 'demo-template-' . $batchId . '-' . $i;
            $rows[] = [$code, 'تنبيه ديمو ' . $i, 'رسالة تنبيه ديمو ' . $i, 'تنبيه ديمو ' . $i, 'رسالة بريد ديمو ' . $i, 'contract_due', 1, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_notification_templates', ['code', 'title_template', 'body_template', 'email_subject_template', 'email_body_template', 'icon_key', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'code', 'demo-template-' . $batchId . '-');
    }

    /** @return list<int> */
    private function seedDeviceTokens(object $wpdb, string $marker, int $actorId, string $now): array
    {
        $rows = [];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $token = $marker . ':inactive-device-' . $i;
            $rows[] = [$actorId, hash('sha256', $token), $token, 'demo', 0, $now, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_device_tokens', ['user_id', 'token_hash', 'token', 'platform', 'is_active', 'last_seen_at', 'created_at', 'updated_at'], $rows, 'token', $marker);
    }

    /** @param list<int> $ruleIds @param list<int> $paymentIds @param list<int> $tokenIds @return list<int> */
    private function seedNotificationDeliveries(object $wpdb, string $batchId, string $marker, int $actorId, int $year, string $now, array $ruleIds, array $paymentIds, array $tokenIds): array
    {
        $rows = [];
        for ($index = 0; $index < self::ROWS_PER_TABLE; $index++) {
            $date = sprintf('%04d-%02d-%02d', $year, ($index % 12) + 1, ($index % 28) + 1);
            $rows[] = [$ruleIds[$index], $paymentIds[$index], $actorId, $tokenIds[$index], 'push', 'demo-template-' . $batchId . '-' . ($index + 1), $date, 0, 'failed', null, $marker . ':' . ($index + 1), $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_notification_deliveries', ['rule_id', 'payment_id', 'user_id', 'device_token_id', 'channel', 'template_code', 'scheduled_for', 'attempt_no', 'status', 'response_code', 'error_code', 'created_at'], $rows, 'error_code', $marker);
    }

    /** @return list<int> */
    private function seedImportRuns(object $wpdb, string $batchId, string $marker, int $actorId, string $now): array
    {
        $rows = [];
        for ($i = 1; $i <= self::ROWS_PER_TABLE; $i++) {
            $key = hash('sha256', $batchId . ':import:' . $i);
            $rows[] = ['demo-' . $batchId . '-' . $i . '.xlsx', $key, hash('sha256', $key . ':file'), 1024 + $i, 'completed', 'Sheet1', $marker, '{}', 'skip', 1, 0, 0, 0, 1, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_import_runs', ['original_filename', 'storage_key', 'file_sha256', 'file_size', 'status', 'selected_sheet', 'discovery_json', 'mapping_json', 'duplicate_strategy', 'total_rows', 'valid_rows', 'imported_rows', 'skipped_rows', 'error_rows', 'created_by', 'created_at', 'updated_at'], $rows, 'original_filename', 'demo-' . $batchId . '-');
    }

    /** @param list<int> $runIds @return list<int> */
    private function seedImportErrors(object $wpdb, string $marker, string $now, array $runIds): array
    {
        $rows = [];
        foreach ($runIds as $index => $runId) {
            $rows[] = [$runId, $index + 1, 'demo_field', 'demo_row', $marker . ' خطأ ديمو ' . ($index + 1), $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_import_errors', ['import_run_id', 'row_number', 'field_name', 'error_code', 'message', 'created_at'], $rows, 'message', $marker);
    }

    /** @param list<int> $ruleIds @param list<int> $paymentIds @return list<int> */
    private function seedNotificationSchedule(object $wpdb, string $batchId, int $actorId, int $year, string $now, array $ruleIds, array $paymentIds): array
    {
        $rows = [];
        for ($index = 0; $index < self::ROWS_PER_TABLE; $index++) {
            $date = sprintf('%04d-%02d-%02d', $year, ($index % 12) + 1, ($index % 28) + 1);
            $template = 'demo-template-' . $batchId . '-' . ($index + 1);
            $rows[] = [$ruleIds[$index], $paymentIds[$index], 0, '[' . $actorId . ']', $template, 'push', $date, $date . ' 09:00:00', 'pending', 1, 0, 0, 0, null, null, null, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_notification_schedule', ['rule_id', 'payment_id', 'attempt_no', 'recipient_ids_json', 'template_code', 'channel', 'scheduled_date', 'scheduled_for', 'status', 'recipient_count', 'sent_count', 'failed_count', 'manual_attempts', 'last_attempt_at', 'sent_at', 'last_error_code', 'created_at', 'updated_at'], $rows, 'template_code', 'demo-template-' . $batchId . '-');
    }

    /** @param list<int> $paymentIds @return list<int> */
    private function seedSuppressions(object $wpdb, string $marker, int $actorId, string $now, array $paymentIds): array
    {
        $rows = [];
        foreach ($paymentIds as $index => $paymentId) {
            $rows[] = ['payment', $paymentId, $marker . ' إيقاف ديمو ' . ($index + 1), 0, $actorId, $actorId, $now, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_notification_suppressions', ['scope_type', 'scope_id', 'reason', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at'], $rows, 'reason', $marker);
    }

    /** @param list<int> $paymentIds @return list<int> */
    private function seedEntityAttachments(object $wpdb, string $marker, int $actorId, string $now, array $paymentIds): array
    {
        $rows = [];
        foreach ($paymentIds as $index => $paymentId) {
            $rows[] = ['payment', $paymentId, 9500000 + $index, $marker . ' مرفق دفعة ' . ($index + 1), 1, $actorId, $now];
        }
        return $this->insertAndCollect($wpdb, 'safecontracts_entity_attachments', ['entity_type', 'entity_id', 'media_id', 'label', 'display_order', 'created_by', 'created_at'], $rows, 'label', $marker);
    }

    /** @param list<string> $columns @param list<list<mixed>> $rows @return list<int> */
    private function insertAndCollect(object $wpdb, string $suffix, array $columns, array $rows, string $markerColumn, string $markerPrefix): array
    {
        $table = $this->table($wpdb, $suffix);
        foreach (array_chunk($rows, 100) as $chunk) {
            $valuesSql = [];
            $args = [];
            foreach ($chunk as $row) {
                $placeholders = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $placeholders[] = 'NULL';
                    } elseif (is_int($value) || is_bool($value)) {
                        $placeholders[] = '%d';
                        $args[] = (int) $value;
                    } else {
                        $placeholders[] = '%s';
                        $args[] = (string) $value;
                    }
                }
                $valuesSql[] = '(' . implode(',', $placeholders) . ')';
            }
            $columnSql = implode(',', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
            $sql = 'INSERT INTO ' . $table . ' (' . $columnSql . ') VALUES ' . implode(',', $valuesSql);
            $this->query($wpdb, $wpdb->prepare($sql, ...$args));
        }
        $like = $this->escapeLike($wpdb, $markerPrefix) . '%';
        $ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . $table . ' WHERE `' . $markerColumn . '` LIKE %s ORDER BY id ASC', $like));
        return array_values(array_map('intval', is_array($ids) ? $ids : []));
    }

    private function database(): object
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'query') || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'get_col') || ! method_exists($wpdb, 'get_var')) {
            throw new RuntimeException('SafeContracts demo data requires WordPress database access.');
        }
        return $wpdb;
    }

    private function assertTablesExist(object $wpdb): void
    {
        foreach (self::TABLES as $suffix) {
            $table = $this->table($wpdb, $suffix);
            if ((string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                throw new RuntimeException('SafeContracts demo table is unavailable: ' . $suffix . '.');
            }
        }
    }

    private function table(object $wpdb, string $suffix): string
    {
        if (! in_array($suffix, self::TABLES, true)) {
            throw new RuntimeException('Unsafe SafeContracts demo table request.');
        }
        return $wpdb->prefix . $suffix;
    }

    private function escapeLike(object $wpdb, string $value): string
    {
        return method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($value) : addcslashes($value, '_%\\');
    }

    /** @return array{0:string,1:string} */
    private function deleteMarker(string $suffix, string $batchId): array
    {
        $tag = '[SC-DEMO:' . $batchId . ']';
        return match ($suffix) {
            'safecontracts_meta' => ['meta_key', 'demo_' . $batchId . '_'],
            'safecontracts_customers' => ['internal_code', 'DEMO-C-' . $batchId . '-'],
            'safecontracts_suppliers' => ['internal_code', 'DEMO-S-' . $batchId . '-'],
            'safecontracts_payment_methods' => ['code', 'demo-' . $batchId . '-'],
            'safecontracts_contracts' => ['contract_number', 'DEMO-CTR-' . $batchId . '-'],
            'safecontracts_contract_financial_items', 'safecontracts_contract_adjustments' => ['description', $tag],
            'safecontracts_contract_attachments', 'safecontracts_entity_attachments' => ['label', $tag],
            'safecontracts_contract_history' => ['snapshot_json', '{"marker":"' . $tag],
            'safecontracts_scheduled_payments' => ['reference', 'DEMO-PAY-' . $batchId . '-'],
            'safecontracts_payment_collections' => ['reference', 'DEMO-SET-' . $batchId . '-'],
            'safecontracts_payment_followups' => ['note', $tag],
            'safecontracts_audit_log' => ['context_json', '{"marker":"' . $tag],
            'safecontracts_notification_rules' => ['code', 'demo-rule-' . $batchId . '-'],
            'safecontracts_notification_templates' => ['code', 'demo-template-' . $batchId . '-'],
            'safecontracts_notification_schedule' => ['template_code', 'demo-template-' . $batchId . '-'],
            'safecontracts_device_tokens' => ['token', $tag],
            'safecontracts_notification_deliveries' => ['error_code', $tag],
            'safecontracts_import_runs' => ['original_filename', 'demo-' . $batchId . '-'],
            'safecontracts_import_errors' => ['message', $tag],
            'safecontracts_notification_suppressions' => ['reason', $tag],
            default => throw new RuntimeException('SafeContracts demo deletion marker is unavailable.'),
        };
    }

    private function query(object $wpdb, string $sql, bool $throw = true): int
    {
        $result = $wpdb->query($sql);
        if ($result === false && $throw) {
            $detail = isset($wpdb->last_error) && is_scalar($wpdb->last_error) ? trim((string) $wpdb->last_error) : '';
            throw new RuntimeException('SafeContracts demo database operation failed.' . ($detail !== '' ? ' ' . $detail : ''));
        }
        return $result === false ? 0 : (int) $result;
    }
}
