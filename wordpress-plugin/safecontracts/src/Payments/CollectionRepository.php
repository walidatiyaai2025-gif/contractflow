<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use RuntimeException;

final class CollectionRepository
{
    /** @return array{id:int, contract_id:int, accountant_user_id:?int, contract_is_archived:bool, remaining_amount:string}|null */
    public function paymentContext(int $paymentId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.id, p.contract_id, p.remaining_amount,
                        c.accountant_user_id, c.is_archived AS contract_is_archived
                 FROM {$payments} p
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 WHERE p.id = %d LIMIT 1",
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
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'contract_is_archived' => (bool) ($row['contract_is_archived'] ?? false),
            'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
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

        $referenceSql = $reference === null ? 'NULL' : '%s';
        $detailsSql = $details === null ? 'NULL' : '%s';
        $proofSql = $proofMediaId === null ? 'NULL' : '%d';
        $query = "INSERT INTO {$table}
            (payment_id, amount, collection_date, payment_method_id, reference, details, proof_media_id, created_by, updated_by, created_at, updated_at)
            VALUES (%d, %s, %s, %d, {$referenceSql}, {$detailsSql}, {$proofSql}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())";

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

        $sql = $wpdb->prepare($query, ...$args);
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to record collection transaction.');
        }

        return (int) $wpdb->insert_id;
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts collections require WordPress $wpdb.');
        }
    }
}
