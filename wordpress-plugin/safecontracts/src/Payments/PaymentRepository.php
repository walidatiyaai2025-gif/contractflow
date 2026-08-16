<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantScope;

final class PaymentRepository
{
    /** @return array{id:int, accountant_user_id:?int, is_archived:bool}|null */
    public function contractContext(int $contractId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenant = $this->tenantCondition();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, accountant_user_id, is_archived FROM {$table} WHERE id = %d{$tenant} LIMIT 1",
                $contractId
            ),
            ARRAY_A
        );

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'is_archived' => (bool) ($row['is_archived'] ?? false),
        ];
    }

    /** @return array{id:int, contract_id:int, sequence_no:int, reference:?string, due_date:string, expected_payment_date:?string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, is_archived:bool, accountant_user_id:?int, contract_is_archived:bool}|null */
    public function find(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $tenantId = CoreTenantScope::tenantId();
        $tenant = $tenantId === null
            ? ''
            : ' AND p.tenant_id = ' . $tenantId . ' AND c.tenant_id = ' . $tenantId;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.contract_id, p.sequence_no, p.reference, p.due_date, p.expected_payment_date,
                        p.original_amount, p.paid_amount, p.remaining_amount, p.status, p.is_archived,
                        c.accountant_user_id, c.is_archived AS contract_is_archived
                 FROM {$payments} p
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 WHERE p.id = %d AND p.is_archived = 0{$tenant} LIMIT 1",
                $paymentId
            ),
            ARRAY_A
        );

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contract_id' => (int) ($row['contract_id'] ?? 0),
            'sequence_no' => (int) ($row['sequence_no'] ?? 0),
            'reference' => isset($row['reference']) && $row['reference'] !== null ? (string) $row['reference'] : null,
            'due_date' => (string) ($row['due_date'] ?? ''),
            'expected_payment_date' => isset($row['expected_payment_date']) && $row['expected_payment_date'] !== null
                ? (string) $row['expected_payment_date']
                : null,
            'original_amount' => (string) ($row['original_amount'] ?? '0.0000'),
            'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
            'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
            'status' => (string) ($row['status'] ?? PaymentStatus::UPCOMING),
            'is_archived' => (bool) ($row['is_archived'] ?? false),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'contract_is_archived' => (bool) ($row['contract_is_archived'] ?? false),
        ];
    }

    public function create(
        int $contractId,
        int $sequenceNo,
        ?string $reference,
        string $dueDate,
        ?string $expectedPaymentDate,
        string $amount,
        int $actorId
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $tenantId = CoreTenantScope::tenantId();
        if ($tenantId !== null && $this->contractContext($contractId) === null) {
            throw new RuntimeException('Payment contract is outside the current Enterprise tenant.');
        }

        $referenceSql = $reference === null ? 'NULL' : '%s';
        $expectedSql = $expectedPaymentDate === null ? 'NULL' : '%s';
        $tenantColumn = $tenantId === null ? '' : 'tenant_id, ';
        $tenantPlaceholder = $tenantId === null ? '' : '%d, ';
        $query = "INSERT INTO {$table}
            ({$tenantColumn}contract_id, sequence_no, reference, due_date, expected_payment_date, original_amount, paid_amount, remaining_amount, status, created_by, updated_by, created_at, updated_at)
            VALUES ({$tenantPlaceholder}%d, %d, {$referenceSql}, %s, {$expectedSql}, %s, '0.0000', %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())";

        $args = [];
        if ($tenantId !== null) {
            $args[] = $tenantId;
        }
        $args[] = $contractId;
        $args[] = $sequenceNo;
        if ($reference !== null) {
            $args[] = $reference;
        }
        $args[] = $dueDate;
        if ($expectedPaymentDate !== null) {
            $args[] = $expectedPaymentDate;
        }
        $args[] = $amount;
        $args[] = $amount;
        $args[] = PaymentStatus::UPCOMING;
        $args[] = $actorId;
        $args[] = $actorId;

        $sql = $wpdb->prepare($query, ...$args);
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create scheduled payment.');
        }

        return (int) $wpdb->insert_id;
    }

    public function updateStatus(int $paymentId, string $status, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $tenant = $this->tenantCondition();
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET status = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0{$tenant}",
            $status,
            $actorId,
            $paymentId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to update payment status.');
    }

    public function updateDates(int $paymentId, string $dueDate, ?string $expectedPaymentDate, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $tenant = $this->tenantCondition();

        if ($expectedPaymentDate === null) {
            $sql = $wpdb->prepare(
                "UPDATE {$table}
                 SET due_date = %s, expected_payment_date = NULL, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND is_archived = 0{$tenant}",
                $dueDate,
                $actorId,
                $paymentId
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$table}
                 SET due_date = %s, expected_payment_date = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND is_archived = 0{$tenant}",
                $dueDate,
                $expectedPaymentDate,
                $actorId,
                $paymentId
            );
        }

        $this->executeMutation($wpdb, $sql, 'Unable to update payment dates.');
    }

    private function tenantCondition(string $column = 'tenant_id'): string
    {
        $tenantId = CoreTenantScope::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts payments require WordPress $wpdb.');
        }
    }

    private function executeMutation(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }
}
