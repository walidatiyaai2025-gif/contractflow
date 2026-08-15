<?php

declare(strict_types=1);

namespace SafeContracts\Collections;

use RuntimeException;

final class CollectionRepository
{
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
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to record collection transaction.');
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @return list<array{
     *   id:int, payment_id:int, amount:string, collection_date:string,
     *   payment_method_id:int, reference:?string, details:?string,
     *   proof_media_id:?int, created_by:?int, updated_by:?int,
     *   created_at:string, updated_at:string
     * }>
     */
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
                 WHERE payment_id = %d
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
}
