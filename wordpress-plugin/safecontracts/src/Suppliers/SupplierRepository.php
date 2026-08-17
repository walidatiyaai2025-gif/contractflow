<?php

declare(strict_types=1);

namespace SafeContracts\Suppliers;

use RuntimeException;

final class SupplierRepository
{
    /** @return array<string,mixed>|null */
    public function find(int $supplierId, bool $includeArchived = false): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $archive = $includeArchived ? '' : ' AND is_archived = 0';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, internal_code, name, contact_name, email, phone, notes, is_active, is_archived, archived_by, archived_at, created_by, updated_by, created_at, updated_at
             FROM {$table} WHERE id = %d{$archive} LIMIT 1",
            $supplierId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return $this->normalize($rows[0]);
    }

    public function activeExists(int $supplierId): bool
    {
        $supplier = $this->find($supplierId);
        return $supplier !== null && $supplier['is_active'] && ! $supplier['is_archived'];
    }

    /** @return list<array<string,mixed>> */
    public function active(int $limit = 500): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $limit = max(1, min(500, $limit));
        $rows = $wpdb->get_results(
            "SELECT id, internal_code, name, contact_name, email, phone, notes, is_active, is_archived, archived_by, archived_at, created_by, updated_by, created_at, updated_at
             FROM {$table}
             WHERE is_archived = 0 AND is_active = 1
             ORDER BY name ASC, id ASC LIMIT {$limit}",
            ARRAY_A
        );
        if (! is_array($rows)) {
            return [];
        }
        return array_values(array_map(fn (array $row): array => $this->normalize($row), $rows));
    }

    public function create(array $data, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $codeSql = $this->nullableStringSql((string) $data['internal_code']);
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (internal_code, name, contact_name, email, phone, notes, is_active, is_archived, created_by, updated_by, created_at, updated_at)
             VALUES ({$codeSql}, %s, %s, %s, %s, %s, %d, 0, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $data['name'],
            $data['contact_name'],
            $data['email'],
            $data['phone'],
            $data['notes'],
            $data['is_active'] ? 1 : 0,
            $actorId,
            $actorId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create supplier.');
        }
        return (int) $wpdb->insert_id;
    }

    public function update(int $supplierId, array $data, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $codeSql = $this->nullableStringSql((string) $data['internal_code']);
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET internal_code = {$codeSql}, name = %s, contact_name = %s, email = %s, phone = %s, notes = %s,
                 is_active = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            $data['name'],
            $data['contact_name'],
            $data['email'],
            $data['phone'],
            $data['notes'],
            $data['is_active'] ? 1 : 0,
            $actorId,
            $supplierId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update supplier.');
        }
    }

    public function archive(int $supplierId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET is_archived = 1, is_active = 0, archived_by = %d, archived_at = UTC_TIMESTAMP(), updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            $actorId,
            $actorId,
            $supplierId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to archive supplier.');
        }
    }

    /** @return array<string,mixed> */
    private function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'internal_code' => isset($row['internal_code']) && $row['internal_code'] !== null ? (string) $row['internal_code'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'contact_name' => isset($row['contact_name']) && $row['contact_name'] !== null ? (string) $row['contact_name'] : '',
            'email' => isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : '',
            'phone' => isset($row['phone']) && $row['phone'] !== null ? (string) $row['phone'] : '',
            'notes' => isset($row['notes']) && $row['notes'] !== null ? (string) $row['notes'] : '',
            'is_active' => (bool) ($row['is_active'] ?? false),
            'is_archived' => (bool) ($row['is_archived'] ?? false),
            'archived_by' => isset($row['archived_by']) && $row['archived_by'] !== null ? (int) $row['archived_by'] : null,
            'archived_at' => isset($row['archived_at']) && $row['archived_at'] !== null ? (string) $row['archived_at'] : null,
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) && $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function nullableStringSql(string $value): string
    {
        return $value === '' ? 'NULL' : "'" . addslashes($value) . "'";
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts suppliers require WordPress $wpdb.');
        }
    }
}
