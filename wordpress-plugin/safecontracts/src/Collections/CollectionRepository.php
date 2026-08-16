<?php

declare(strict_types=1);

namespace SafeContracts\Collections;

use RuntimeException;
use SafeContracts\Payments\PaymentStatus;

final class CollectionRepository
{
    public function beginTransaction(): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $this->execute($wpdb, 'START TRANSACTION', 'Unable to start collection transaction.');
    }

    public function commitTransaction(): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $this->execute($wpdb, 'COMMIT', 'Unable to commit collection transaction.');
    }

    public function rollbackTransaction(): void
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'query')) {
            return;
        }
        $wpdb->query('ROLLBACK');
    }

    /** @return array{id:int, contract_id:int, original_amount:string, paid_amount:string, remaining_amount:string, status:string, payment_is_archived:bool, accountant_user_id:?int, contract_is_archived:bool}|null */
    public function lockPayment(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.contract_id, p.original_amount, p.paid_amount, p.remaining_amount, p.status, p.is_archived,
                        c.accountant_user_id, c.is_archived AS contract_is_archived
                 FROM {$payments} p
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 WHERE p.id = %d
                 LIMIT 1
                 FOR UPDATE",
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
            'original_amount' => (string) ($row['original_amount'] ?? '0.0000'),
            'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
            'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
            'status' => (string) ($row['status'] ?? PaymentStatus::UPCOMING),
            'payment_is_archived' => (bool) ($row['is_archived'] ?? false),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'contract_is_archived' => (bool) ($row['contract_is_archived'] ?? false),
        ];
    }

    public function paymentMethodIsActive(int $paymentMethodId): bool
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_methods';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND is_active = 1 LIMIT 1",
                $paymentMethodId
            ),
            ARRAY_A
        );

        return is_array($rows) && $rows !== [];
    }

    public function collectedTotal(int $paymentId): string
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_collections';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0.0000) AS total FROM {$table} WHERE payment_id = %d AND is_archived = 0",
                $paymentId
            ),
            ARRAY_A
        );

        if (! is_array($rows) || $rows === []) {
            return '0.0000';
        }

        return (string) ($rows[0]['total'] ?? '0.0000');
    }

    public function create(
        int $paymentId,
        string $amount,
        string $collectionDate,
        int $paymentMethodId,
        ?string $reference,
        ?string $details,
        ?int $proofMediaId,
        int $actorId
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_collections';

        $referencePlaceholder = $reference === null ? 'NULL' : '%s';
        $detailsPlaceholder = $details === null ? 'NULL' : '%s';
        $proofPlaceholder = $proofMediaId === null ? 'NULL' : '%d';
        $statement = "INSERT INTO {$table}
            (payment_id, amount, collection_date, payment_method_id, reference, details, proof_media_id, created_by, updated_by, created_at, updated_at)
            VALUES (%d, %s, %s, %d, {$referencePlaceholder}, {$detailsPlaceholder}, {$proofPlaceholder}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())";

        $args = [$paymentId, $amount, $collectionDate, $paymentMethodId];
        if ($reference !== null) {
            $args[] = $reference;
        }
        if ($details !== null) {
            $args[] = $details;
        }
        if ($proofMediaId !== null) {
            $args[] = $proofMediaId;
        }
        $args[] = $actorId;
        $args[] = $actorId;

        $sql = $wpdb->prepare($statement, ...$args);
        $this->execute($wpdb, $sql, 'Unable to record collection transaction.');

        return (int) $wpdb->insert_id;
    }

    public function updatePaymentSettlement(
        int $paymentId,
        string $paidAmount,
        string $remainingAmount,
        string $status,
        int $actorId
    ): void {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET paid_amount = %s,
                 remaining_amount = %s,
                 status = %s,
                 updated_by = %d,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            $paidAmount,
            $remainingAmount,
            $status,
            $actorId,
            $paymentId
        );
        $this->execute($wpdb, $sql, 'Unable to update payment settlement balances.');
    }

    /** @return list<array<string,mixed>> */
    public function forPayment(int $paymentId): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_collections';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, payment_id, amount, collection_date, payment_method_id, reference, details,
                        proof_media_id, created_by, updated_by, created_at, updated_at
                 FROM {$table}
                 WHERE payment_id = %d AND is_archived = 0
                 ORDER BY collection_date ASC, id ASC",
                $paymentId
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(
            static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'payment_id' => (int) ($row['payment_id'] ?? 0),
                'amount' => (string) ($row['amount'] ?? '0.0000'),
                'collection_date' => (string) ($row['collection_date'] ?? ''),
                'payment_method_id' => (int) ($row['payment_method_id'] ?? 0),
                'reference' => isset($row['reference']) && $row['reference'] !== null ? (string) $row['reference'] : null,
                'details' => isset($row['details']) && $row['details'] !== null ? (string) $row['details'] : null,
                'proof_media_id' => isset($row['proof_media_id']) && $row['proof_media_id'] !== null ? (int) $row['proof_media_id'] : null,
                'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
                'updated_by' => isset($row['updated_by']) && $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ],
            $rows
        );
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (
            ! is_object($wpdb)
            || ! method_exists($wpdb, 'prepare')
            || ! method_exists($wpdb, 'query')
            || ! method_exists($wpdb, 'get_results')
        ) {
            throw new RuntimeException('SafeContracts collection repository requires WordPress $wpdb.');
        }
    }

    private function execute(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }
}
