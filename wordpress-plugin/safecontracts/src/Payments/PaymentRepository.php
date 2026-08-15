<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use RuntimeException;
use SafeContracts\Contracts\ContractMoney;

final class PaymentRepository
{
    /**
     * @return array{
     *   id:int, contract_id:int, sequence_no:int, reference:?string,
     *   original_amount:string, due_date:string, expected_payment_date:?string,
     *   is_cancelled:bool, contract_accountant_user_id:?int,
     *   contract_status:string, contract_is_archived:bool
     * }|null
     */
    public function find(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.contract_id, p.sequence_no, p.reference, p.original_amount, p.due_date, p.expected_payment_date, p.is_cancelled, c.accountant_user_id AS contract_accountant_user_id, c.status AS contract_status, c.is_archived AS contract_is_archived FROM {$payments} p INNER JOIN {$contracts} c ON c.id = p.contract_id WHERE p.id = %d LIMIT 1",
            $paymentId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contract_id' => (int) ($row['contract_id'] ?? 0),
            'sequence_no' => (int) ($row['sequence_no'] ?? 0),
            'reference' => isset($row['reference']) && $row['reference'] !== null ? (string) $row['reference'] : null,
            'original_amount' => ContractMoney::normalizeNonNegative((string) ($row['original_amount'] ?? '0')),
            'due_date' => (string) ($row['due_date'] ?? ''),
            'expected_payment_date' => isset($row['expected_payment_date']) && $row['expected_payment_date'] !== null ? (string) $row['expected_payment_date'] : null,
            'is_cancelled' => (bool) ($row['is_cancelled'] ?? false),
            'contract_accountant_user_id' => isset($row['contract_accountant_user_id']) && $row['contract_accountant_user_id'] !== null ? (int) $row['contract_accountant_user_id'] : null,
            'contract_status' => (string) ($row['contract_status'] ?? ''),
            'contract_is_archived' => (bool) ($row['contract_is_archived'] ?? false),
        ];
    }

    public function create(
        int $contractId,
        int $sequenceNo,
        ?string $reference,
        string $originalAmount,
        string $dueDate,
        ?string $expectedPaymentDate,
        int $actorId
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $referencePlaceholder = $reference === null ? 'NULL' : '%s';
        $expectedPlaceholder = $expectedPaymentDate === null ? 'NULL' : '%s';
        $statement = "INSERT INTO {$table} (contract_id, sequence_no, reference, original_amount, due_date, expected_payment_date, created_by, updated_by, created_at, updated_at) VALUES (%d, %d, {$referencePlaceholder}, %s, %s, {$expectedPlaceholder}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())";
        $args = [$contractId, $sequenceNo];
        if ($reference !== null) {
            $args[] = $reference;
        }
        $args[] = $originalAmount;
        $args[] = $dueDate;
        if ($expectedPaymentDate !== null) {
            $args[] = $expectedPaymentDate;
        }
        $args[] = $actorId;
        $args[] = $actorId;

        $sql = $wpdb->prepare($statement, ...$args);
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create scheduled payment.');
        }

        return (int) $wpdb->insert_id;
    }

    public function updateDates(int $paymentId, string $dueDate, ?string $expectedPaymentDate, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $expectedPlaceholder = $expectedPaymentDate === null ? 'NULL' : '%s';
        $statement = "UPDATE {$table} SET due_date = %s, expected_payment_date = {$expectedPlaceholder}, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d";
        $args = [$dueDate];
        if ($expectedPaymentDate !== null) {
            $args[] = $expectedPaymentDate;
        }
        $args[] = $actorId;
        $args[] = $paymentId;
        $sql = $wpdb->prepare($statement, ...$args);
        $this->executeMutation($wpdb, $sql, 'Unable to update payment dates.');
    }

    public function cancel(int $paymentId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET is_cancelled = 1, cancelled_at = UTC_TIMESTAMP(), cancelled_by = %d, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND is_cancelled = 0",
            $actorId,
            $actorId,
            $paymentId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to cancel scheduled payment.');
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts payment repository requires WordPress $wpdb.');
        }
    }

    private function executeMutation(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }
}
