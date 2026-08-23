<?php

declare(strict_types=1);

namespace SafeContracts\Collections;

use RuntimeException;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Payments\FinancialDirection;
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

    /** @return array{id:int, contract_id:int, financial_direction:string, currency_code:string, original_amount:string, paid_amount:string, remaining_amount:string, status:string, payment_is_archived:bool, accountant_user_id:?int, contract_is_archived:bool, contract_base_value:?string, contract_settled_total:?string}|null */
    public function lockPayment(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.contract_id, p.financial_direction, p.currency_code, p.original_amount, p.paid_amount, p.remaining_amount, p.status, p.is_archived,
                        c.accountant_user_id, c.is_archived AS contract_is_archived, c.base_value AS contract_base_value,
                        (SELECT COALESCE(SUM(pc.amount), 0.0000)
                           FROM {$collections} pc
                           INNER JOIN {$payments} sp ON sp.id = pc.payment_id
                          WHERE sp.contract_id = p.contract_id AND sp.is_archived = 0 AND pc.is_archived = 0) AS contract_settled_total
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
            'financial_direction' => self::directionFromRow($row),
            'currency_code' => self::currencyFromRow($row),
            'original_amount' => (string) ($row['original_amount'] ?? '0.0000'),
            'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
            'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
            'status' => (string) ($row['status'] ?? PaymentStatus::UPCOMING),
            'payment_is_archived' => (bool) ($row['is_archived'] ?? false),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'contract_is_archived' => (bool) ($row['contract_is_archived'] ?? false),
            // Production SQL always selects these values; nullable fallbacks keep
            // older repository mocks compatible with the stricter service guard.
            'contract_base_value' => array_key_exists('contract_base_value', $row) ? (string) $row['contract_base_value'] : null,
            'contract_settled_total' => array_key_exists('contract_settled_total', $row) ? (string) $row['contract_settled_total'] : null,
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
        string $financialDirection,
        string $currencyCode,
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
        $financialDirection = FinancialDirection::normalize($financialDirection);
        $currencyCode = CurrencyCode::normalize($currencyCode);

        $referencePlaceholder = $reference === null ? 'NULL' : '%s';
        $detailsPlaceholder = $details === null ? 'NULL' : '%s';
        $proofPlaceholder = $proofMediaId === null ? 'NULL' : '%d';
        $statement = "INSERT INTO {$table}
            (payment_id, financial_direction, currency_code, amount, collection_date, payment_method_id, reference, details, proof_media_id, created_by, updated_by, created_at, updated_at)
            VALUES (%d, %s, %s, %s, %s, %d, {$referencePlaceholder}, {$detailsPlaceholder}, {$proofPlaceholder}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())";

        $args = [$paymentId, $financialDirection, $currencyCode, $amount, $collectionDate, $paymentMethodId];
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
        $this->execute($wpdb, $sql, 'Unable to record settlement transaction.');

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
                "SELECT id, payment_id, financial_direction, currency_code, amount, collection_date, payment_method_id, reference, details,
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
                'financial_direction' => self::directionFromRow($row),
                'currency_code' => self::currencyFromRow($row),
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

    /**
     * Compatibility is limited to repository mocks that omit the new keys.
     * Actual selected rows always contain P11 columns; null/empty DB values
     * therefore still fail FinancialDirection/CurrencyCode normalization.
     */
    private static function directionFromRow(array $row): string
    {
        if (! array_key_exists('financial_direction', $row)) {
            return FinancialDirection::RECEIVABLE;
        }
        return FinancialDirection::normalize($row['financial_direction']);
    }

    private static function currencyFromRow(array $row): string
    {
        if (! array_key_exists('currency_code', $row)) {
            return CurrencyCode::UNKNOWN;
        }
        return CurrencyCode::normalize($row['currency_code']);
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
