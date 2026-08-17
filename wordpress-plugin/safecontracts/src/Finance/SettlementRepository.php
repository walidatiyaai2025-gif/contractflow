<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use RuntimeException;
use SafeContracts\Payments\PaymentStatus;

final class SettlementRepository
{
    public function beginTransaction(): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $this->execute($wpdb, 'START TRANSACTION', 'Unable to start finance transaction.');
    }

    public function commitTransaction(): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $this->execute($wpdb, 'COMMIT', 'Unable to commit finance transaction.');
    }

    public function rollbackTransaction(): void
    {
        global $wpdb;
        if (is_object($wpdb) && method_exists($wpdb, 'query')) {
            $wpdb->query('ROLLBACK');
        }
    }

    /** @return array<string,mixed>|null */
    public function lockObligation(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.contract_id, p.financial_direction, p.currency_code, p.original_amount,
                    p.paid_amount, p.remaining_amount, p.status, p.is_archived AS payment_is_archived,
                    c.accountant_user_id, c.is_archived AS contract_is_archived,
                    c.counterparty_type, c.counterparty_id
             FROM {$payments} p
             INNER JOIN {$contracts} c ON c.id = p.contract_id
             WHERE p.id = %d LIMIT 1 FOR UPDATE",
            $paymentId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contract_id' => (int) ($row['contract_id'] ?? 0),
            'financial_direction' => (string) ($row['financial_direction'] ?? ''),
            'currency_code' => strtoupper(trim((string) ($row['currency_code'] ?? ''))),
            'original_amount' => (string) ($row['original_amount'] ?? '0.0000'),
            'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
            'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
            'status' => (string) ($row['status'] ?? PaymentStatus::UPCOMING),
            'payment_is_archived' => (bool) ($row['payment_is_archived'] ?? false),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'contract_is_archived' => (bool) ($row['contract_is_archived'] ?? false),
            'counterparty_type' => (string) ($row['counterparty_type'] ?? ''),
            'counterparty_id' => isset($row['counterparty_id']) && $row['counterparty_id'] !== null
                ? (int) $row['counterparty_id']
                : null,
        ];
    }

    /** @return array<string,mixed>|null */
    public function findByIdempotencyKey(string $key): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_financial_transactions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, payment_id, contract_id, financial_direction, transaction_kind, amount, currency_code,
                    transaction_date, payment_method_id, reference, details, proof_media_id, idempotency_key,
                    reversal_of_transaction_id, created_by, created_at
             FROM {$table} WHERE idempotency_key = %s LIMIT 1",
            $key
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return $this->mapTransaction($rows[0]);
    }

    public function paymentMethodIsActive(int $paymentMethodId): bool
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_methods';
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE id = %d AND is_active = 1 LIMIT 1",
            $paymentMethodId
        ));
        return $id !== null;
    }

    public function createTransaction(
        int $paymentId,
        int $contractId,
        string $direction,
        string $amount,
        string $currencyCode,
        string $transactionDate,
        ?int $paymentMethodId,
        ?string $reference,
        ?string $details,
        ?int $proofMediaId,
        string $idempotencyKey,
        int $actorId
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_financial_transactions';
        $methodSql = $paymentMethodId === null ? 'NULL' : '%d';
        $referenceSql = $reference === null ? 'NULL' : '%s';
        $detailsSql = $details === null ? 'NULL' : '%s';
        $proofSql = $proofMediaId === null ? 'NULL' : '%d';
        $query = "INSERT INTO {$table}
            (payment_id, contract_id, financial_direction, transaction_kind, amount, currency_code,
             transaction_date, payment_method_id, reference, details, proof_media_id, idempotency_key,
             reversal_of_transaction_id, created_by, created_at)
            VALUES (%d, %d, %s, %s, %s, %s, %s, {$methodSql}, {$referenceSql}, {$detailsSql}, {$proofSql}, %s, NULL, %d, UTC_TIMESTAMP())";
        $args = [
            $paymentId,
            $contractId,
            $direction,
            FinancialDirection::transactionKind($direction),
            $amount,
            $currencyCode,
            $transactionDate,
        ];
        if ($paymentMethodId !== null) {
            $args[] = $paymentMethodId;
        }
        if ($reference !== null) {
            $args[] = $reference;
        }
        if ($details !== null) {
            $args[] = $details;
        }
        if ($proofMediaId !== null) {
            $args[] = $proofMediaId;
        }
        $args[] = $idempotencyKey;
        $args[] = $actorId;

        $sql = $wpdb->prepare($query, ...$args);
        $this->execute($wpdb, $sql, 'Unable to record financial settlement transaction.');
        return (int) $wpdb->insert_id;
    }

    public function updateObligationSettlement(
        int $paymentId,
        string $settledAmount,
        string $remainingAmount,
        string $status,
        int $actorId
    ): void {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET paid_amount = %s, remaining_amount = %s, status = %s,
                 updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            $settledAmount,
            $remainingAmount,
            $status,
            $actorId,
            $paymentId
        );
        $this->execute($wpdb, $sql, 'Unable to update financial obligation settlement.');
    }

    /** @return list<array<string,mixed>> */
    public function forPayment(int $paymentId): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_financial_transactions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, payment_id, contract_id, financial_direction, transaction_kind, amount, currency_code,
                    transaction_date, payment_method_id, reference, details, proof_media_id, idempotency_key,
                    reversal_of_transaction_id, created_by, created_at
             FROM {$table} WHERE payment_id = %d ORDER BY transaction_date ASC, id ASC",
            $paymentId
        ), ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }
        return array_map(fn (array $row): array => $this->mapTransaction($row), $rows);
    }

    private function mapTransaction(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'payment_id' => (int) ($row['payment_id'] ?? 0),
            'contract_id' => (int) ($row['contract_id'] ?? 0),
            'financial_direction' => (string) ($row['financial_direction'] ?? ''),
            'transaction_kind' => (string) ($row['transaction_kind'] ?? ''),
            'amount' => (string) ($row['amount'] ?? '0.0000'),
            'currency_code' => (string) ($row['currency_code'] ?? ''),
            'transaction_date' => (string) ($row['transaction_date'] ?? ''),
            'payment_method_id' => isset($row['payment_method_id']) && $row['payment_method_id'] !== null ? (int) $row['payment_method_id'] : null,
            'reference' => isset($row['reference']) && $row['reference'] !== null ? (string) $row['reference'] : null,
            'details' => isset($row['details']) && $row['details'] !== null ? (string) $row['details'] : null,
            'proof_media_id' => isset($row['proof_media_id']) && $row['proof_media_id'] !== null ? (int) $row['proof_media_id'] : null,
            'idempotency_key' => (string) ($row['idempotency_key'] ?? ''),
            'reversal_of_transaction_id' => isset($row['reversal_of_transaction_id']) && $row['reversal_of_transaction_id'] !== null ? (int) $row['reversal_of_transaction_id'] : null,
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)
            || ! method_exists($wpdb, 'prepare')
            || ! method_exists($wpdb, 'query')
            || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts settlement repository requires WordPress $wpdb.');
        }
    }

    private function execute(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }
}
