<?php

declare(strict_types=1);

namespace SafeContracts\Suppliers;

use RuntimeException;

final class SupplierRepository
{
    public function find(int $supplierId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, internal_code, legal_name, trading_name, contact_name, phone, email, address,
                    country_code, registration_number, tax_number, default_currency, payment_terms,
                    status, notes, is_archived, created_by, updated_by, created_at, updated_at
             FROM {$table} WHERE id = %d LIMIT 1",
            $supplierId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return $this->map($rows[0]);
    }

    public function isActive(int $supplierId): bool
    {
        $supplier = $this->find($supplierId);
        return $supplier !== null
            && ! $supplier['is_archived']
            && $supplier['status'] === SupplierStatus::ACTIVE;
    }

    /** @return list<array<string,mixed>> */
    public function search(string $query = '', int $limit = 50, bool $includeArchived = false): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $limit = max(1, min(200, $limit));
        $archivedSql = $includeArchived ? '1=1' : 'is_archived = 0';
        if ($query === '') {
            $sql = $wpdb->prepare(
                "SELECT id, internal_code, legal_name, trading_name, contact_name, phone, email, address,
                        country_code, registration_number, tax_number, default_currency, payment_terms,
                        status, notes, is_archived, created_by, updated_by, created_at, updated_at
                 FROM {$table} WHERE {$archivedSql}
                 ORDER BY legal_name ASC, id ASC LIMIT %d",
                $limit
            );
        } else {
            $like = '%' . $wpdb->esc_like($query) . '%';
            $sql = $wpdb->prepare(
                "SELECT id, internal_code, legal_name, trading_name, contact_name, phone, email, address,
                        country_code, registration_number, tax_number, default_currency, payment_terms,
                        status, notes, is_archived, created_by, updated_by, created_at, updated_at
                 FROM {$table}
                 WHERE {$archivedSql}
                   AND (legal_name LIKE %s OR trading_name LIKE %s OR internal_code LIKE %s OR registration_number LIKE %s OR tax_number LIKE %s)
                 ORDER BY legal_name ASC, id ASC LIMIT %d",
                $like, $like, $like, $like, $like, $limit
            );
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }
        return array_map(fn (array $row): array => $this->map($row), $rows);
    }

    public function create(array $data, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (internal_code, legal_name, trading_name, contact_name, phone, email, address, country_code,
                 registration_number, tax_number, default_currency, payment_terms, status, notes, is_archived,
                 created_by, updated_by, created_at, updated_at)
             VALUES (NULLIF(%s, ''), %s, NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''),
                     NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''),
                     NULLIF(%s, ''), %s, NULLIF(%s, ''), 0, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $data['internal_code'],
            $data['legal_name'],
            $data['trading_name'],
            $data['contact_name'],
            $data['phone'],
            $data['email'],
            $data['address'],
            $data['country_code'],
            $data['registration_number'],
            $data['tax_number'],
            $data['default_currency'],
            $data['payment_terms'],
            $data['status'],
            $data['notes'],
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
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET internal_code = NULLIF(%s, ''), legal_name = %s, trading_name = NULLIF(%s, ''),
                 contact_name = NULLIF(%s, ''), phone = NULLIF(%s, ''), email = NULLIF(%s, ''),
                 address = NULLIF(%s, ''), country_code = NULLIF(%s, ''), registration_number = NULLIF(%s, ''),
                 tax_number = NULLIF(%s, ''), default_currency = NULLIF(%s, ''), payment_terms = NULLIF(%s, ''),
                 status = %s, notes = NULLIF(%s, ''), updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            $data['internal_code'],
            $data['legal_name'],
            $data['trading_name'],
            $data['contact_name'],
            $data['phone'],
            $data['email'],
            $data['address'],
            $data['country_code'],
            $data['registration_number'],
            $data['tax_number'],
            $data['default_currency'],
            $data['payment_terms'],
            $data['status'],
            $data['notes'],
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
             SET status = %s, is_archived = 1, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d",
            SupplierStatus::ARCHIVED,
            $actorId,
            $supplierId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to archive supplier.');
        }
    }

    public function duplicateId(array $data, ?int $excludeId = null): ?int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        foreach ([
            'internal_code' => (string) ($data['internal_code'] ?? ''),
            'registration_number' => (string) ($data['registration_number'] ?? ''),
            'tax_number' => (string) ($data['tax_number'] ?? ''),
        ] as $column => $value) {
            if ($value === '') {
                continue;
            }
            $exclude = $excludeId === null ? '' : $wpdb->prepare(' AND id <> %d', $excludeId);
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE {$column} = %s{$exclude} LIMIT 1",
                $value
            ));
            if ($id !== null) {
                return (int) $id;
            }
        }
        return null;
    }

    public function hasContractHistory(int $supplierId): bool
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$contracts}
             WHERE counterparty_type = 'supplier' AND counterparty_id = %d",
            $supplierId
        ));
        return (int) $count > 0;
    }

    private function map(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'internal_code' => (string) ($row['internal_code'] ?? ''),
            'legal_name' => (string) ($row['legal_name'] ?? ''),
            'trading_name' => (string) ($row['trading_name'] ?? ''),
            'contact_name' => (string) ($row['contact_name'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'country_code' => (string) ($row['country_code'] ?? ''),
            'registration_number' => (string) ($row['registration_number'] ?? ''),
            'tax_number' => (string) ($row['tax_number'] ?? ''),
            'default_currency' => (string) ($row['default_currency'] ?? ''),
            'payment_terms' => (string) ($row['payment_terms'] ?? ''),
            'status' => (string) ($row['status'] ?? SupplierStatus::ACTIVE),
            'notes' => (string) ($row['notes'] ?? ''),
            'is_archived' => (bool) ($row['is_archived'] ?? false),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts suppliers require WordPress $wpdb.');
        }
    }
}
