<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class PartyRepository
{
    private const SELECT_COLUMNS = 'id, uuid, party_code, display_name, legal_name, party_kind, country_code, registration_number, tax_number, email, phone, status, metadata_json, created_by, updated_by, created_at, updated_at';

    public function find(int $partyId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_parties';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $partyId,
            $tenantId
        ), ARRAY_A);

        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $search = '', int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_parties';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $search = trim($search);

        if ($search === '') {
            $sql = $wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE tenant_id = %d ORDER BY display_name ASC, id ASC LIMIT %d OFFSET %d",
                $tenantId,
                $limit,
                $offset
            );
        } else {
            $like = '%' . addcslashes($search, "\\_%") . '%';
            $sql = $wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE tenant_id = %d AND (display_name LIKE %s OR legal_name LIKE %s OR party_code LIKE %s OR email LIKE %s) ORDER BY display_name ASC, id ASC LIMIT %d OFFSET %d",
                $tenantId,
                $like,
                $like,
                $like,
                $like,
                $limit,
                $offset
            );
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function create(array $data, string $uuid, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_parties';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, uuid, party_code, display_name, legal_name, party_kind, country_code, registration_number, tax_number, email, phone, status, metadata_json, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $tenantId,
            $uuid,
            $this->nullable($data['party_code']),
            $data['display_name'],
            $this->nullable($data['legal_name']),
            $data['party_kind'],
            $this->nullable($data['country_code']),
            $this->nullable($data['registration_number']),
            $this->nullable($data['tax_number']),
            $this->nullable($data['email']),
            $this->nullable($data['phone']),
            $data['status'],
            $this->nullable($data['metadata_json']),
            $actorId,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create Enterprise party.');
        }
        return (int) $wpdb->insert_id;
    }

    public function update(int $partyId, array $data, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_parties';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET party_code = %s, display_name = %s, legal_name = %s, party_kind = %s, country_code = %s, registration_number = %s, tax_number = %s, email = %s, phone = %s, status = %s, metadata_json = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND tenant_id = %d",
            $this->nullable($data['party_code']),
            $data['display_name'],
            $this->nullable($data['legal_name']),
            $data['party_kind'],
            $this->nullable($data['country_code']),
            $this->nullable($data['registration_number']),
            $this->nullable($data['tax_number']),
            $this->nullable($data['email']),
            $this->nullable($data['phone']),
            $data['status'],
            $this->nullable($data['metadata_json']),
            $actorId,
            $partyId,
            $tenantId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update Enterprise party.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Party access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullable(mixed $value): string
    {
        return trim((string) $value) === '' ? '' : (string) $value;
    }
}
