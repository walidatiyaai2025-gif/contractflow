<?php

declare(strict_types=1);

namespace SafeContracts\Suppliers;

use RuntimeException;

final class SupplierRepository
{
    private const SELECT_FIELDS = 'id, internal_code, name, legal_name, trading_name, contact_name, email, phone, address, country_code, registration_number, tax_number, default_currency, payment_terms, status, notes, is_active, is_archived, archived_by, archived_at, created_by, updated_by, created_at, updated_at';

    /** @return array<string,mixed>|null */
    public function find(int $supplierId, bool $includeArchived = true): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $archive = $includeArchived ? '' : ' AND is_archived = 0';
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT ' . self::SELECT_FIELDS . " FROM {$table} WHERE id = %d{$archive} LIMIT 1",
            $supplierId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return $this->map($rows[0]);
    }

    public function activeExists(int $supplierId): bool
    {
        $supplier = $this->find($supplierId, false);
        return $supplier !== null
            && $supplier['status'] === SupplierStatus::ACTIVE
            && $supplier['is_active']
            && ! $supplier['is_archived'];
    }

    /** @return list<array<string,mixed>> */
    public function active(int $limit = 500): array
    {
        return $this->search('', $limit, false, true);
    }

    /** @return list<array<string,mixed>> */
    public function search(string $query = '', int $limit = 50, bool $includeArchived = false, bool $activeOnly = false): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $limit = max(1, min(500, $limit));
        $where = [$includeArchived ? '1 = 1' : 'is_archived = 0'];
        if ($activeOnly) {
            $where[] = "status = 'active'";
            $where[] = 'is_active = 1';
        }
        $args = [];
        if ($query !== '') {
            $like = '%' . $wpdb->esc_like($query) . '%';
            $where[] = '(legal_name LIKE %s OR trading_name LIKE %s OR name LIKE %s OR internal_code LIKE %s OR registration_number LIKE %s OR tax_number LIKE %s)';
            $args = [$like, $like, $like, $like, $like, $like];
        }
        $sql = 'SELECT ' . self::SELECT_FIELDS . " FROM {$table} WHERE " . implode(' AND ', $where)
            . ' ORDER BY COALESCE(NULLIF(legal_name, \'\'), name) ASC, id ASC LIMIT %d';
        $args[] = $limit;
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }
        return array_values(array_map(fn (array $row): array => $this->map($row), $rows));
    }

    public function create(array $data, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_suppliers';
        $active = $data['status'] === SupplierStatus::ACTIVE ? 1 : 0;
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (internal_code, name, legal_name, trading_name, contact_name, email, phone, address, country_code,
                 registration_number, tax_number, default_currency, payment_terms, status, notes, is_active, is_archived,
                 created_by, updated_by, created_at, updated_at)
             VALUES (NULLIF(%s, ''), %s, %s, NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''),
                     NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''),
                     NULLIF(%s, ''), %s, NULLIF(%s, ''), %d, 0, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $data['internal_code'],
            $data['legal_name'],
            $data['legal_name'],
            $data['trading_name'],
            $data['contact_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['country_code'],
            $data['registration_number'],
            $data['tax_number'],
            $data['default_currency'],
            $data['payment_terms'],
            $data['status'],
            $data['notes'],
            $active,
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
        $active = $data['status'] === SupplierStatus::ACTIVE ? 1 : 0;
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET internal_code = NULLIF(%s, ''), name = %s, legal_name = %s, trading_name = NULLIF(%s, ''),
                 contact_name = NULLIF(%s, ''), email = NULLIF(%s, ''), phone = NULLIF(%s, ''), address = NULLIF(%s, ''),
                 country_code = NULLIF(%s, ''), registration_number = NULLIF(%s, ''), tax_number = NULLIF(%s, ''),
                 default_currency = NULLIF(%s, ''), payment_terms = NULLIF(%s, ''), status = %s, notes = NULLIF(%s, ''),
                 is_active = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            $data['internal_code'],
            $data['legal_name'],
            $data['legal_name'],
            $data['trading_name'],
            $data['contact_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['country_code'],
            $data['registration_number'],
            $data['tax_number'],
            $data['default_currency'],
            $data['payment_terms'],
            $data['status'],
            $data['notes'],
            $active,
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
             SET status = %s, is_archived = 1, is_active = 0, archived_by = %d, archived_at = UTC_TIMESTAMP(),
                 updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            SupplierStatus::ARCHIVED,
            $actorId,
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
            $sql = $wpdb->prepare(
                "SELECT id FROM {$table} WHERE {$column} = %s{$exclude} LIMIT 1",
                $value
            );
            $id = $this->scalar($wpdb, $sql, 'id');
            if ($id !== null && $id !== '') {
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
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) AS total FROM {$contracts} WHERE counterparty_type = 'supplier' AND counterparty_id = %d",
            $supplierId
        );
        return (int) ($this->scalar($wpdb, $sql, 'total') ?? 0) > 0;
    }

    private function scalar(object $wpdb, string $sql, string $field): mixed
    {
        if (method_exists($wpdb, 'get_var')) {
            return $wpdb->get_var($sql);
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows) || $rows === [] || ! is_array($rows[0] ?? null)) {
            return null;
        }
        return $rows[0][$field] ?? null;
    }

    /** @return array<string,mixed> */
    private function map(array $row): array
    {
        $legalName = trim((string) ($row['legal_name'] ?? ''));
        if ($legalName === '') {
            $legalName = (string) ($row['name'] ?? '');
        }
        $status = trim((string) ($row['status'] ?? ''));
        if ($status === '') {
            $status = ! empty($row['is_archived'])
                ? SupplierStatus::ARCHIVED
                : (! empty($row['is_active']) ? SupplierStatus::ACTIVE : SupplierStatus::INACTIVE);
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'internal_code' => (string) ($row['internal_code'] ?? ''),
            'name' => $legalName,
            'legal_name' => $legalName,
            'trading_name' => (string) ($row['trading_name'] ?? ''),
            'contact_name' => (string) ($row['contact_name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'country_code' => (string) ($row['country_code'] ?? ''),
            'registration_number' => (string) ($row['registration_number'] ?? ''),
            'tax_number' => (string) ($row['tax_number'] ?? ''),
            'default_currency' => (string) ($row['default_currency'] ?? ''),
            'payment_terms' => (string) ($row['payment_terms'] ?? ''),
            'status' => $status,
            'notes' => (string) ($row['notes'] ?? ''),
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

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts suppliers require WordPress $wpdb.');
        }
    }
}
