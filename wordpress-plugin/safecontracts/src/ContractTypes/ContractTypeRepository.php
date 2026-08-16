<?php

declare(strict_types=1);

namespace SafeContracts\ContractTypes;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractTypeRepository
{
    private const SELECT_COLUMNS = 'id, uuid, type_code, name, description, category, status, metadata_json, created_by, updated_by, created_at, updated_at';

    public function find(int $typeId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_types';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $typeId,
            $tenantId
        ), ARRAY_A);

        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function search(string $search = '', string $status = '', int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_types';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $where = ['tenant_id = %d'];
        $args = [$tenantId];

        if ($status !== '') {
            $where[] = 'status = %s';
            $args[] = $status;
        }
        if ($search !== '') {
            $like = '%' . addcslashes($search, "\\_%") . '%';
            $where[] = '(name LIKE %s OR type_code LIKE %s OR category LIKE %s)';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $args[] = $limit;
        $args[] = $offset;
        $sql = $wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY name ASC, id ASC LIMIT %d OFFSET %d",
            ...$args
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function create(array $data, string $uuid, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_types';
        $description = $this->nullableSql($wpdb, $data['description']);
        $category = $this->nullableSql($wpdb, $data['category']);
        $metadata = $this->nullableSql($wpdb, $data['metadata_json']);
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, uuid, type_code, name, description, category, status, metadata_json, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %s, %s, %s, {$description}, {$category}, %s, {$metadata}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $tenantId,
            $uuid,
            $data['type_code'],
            $data['name'],
            $data['status'],
            $actorId,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create Enterprise Contract Type.');
        }
        $typeId = (int) $wpdb->insert_id;
        if ($typeId <= 0) {
            throw new RuntimeException('Enterprise Contract Type insert returned no identifier.');
        }
        return $typeId;
    }

    public function updateMetadata(int $typeId, array $data, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_types';
        $description = $this->nullableSql($wpdb, $data['description']);
        $category = $this->nullableSql($wpdb, $data['category']);
        $metadata = $this->nullableSql($wpdb, $data['metadata_json']);
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET name = %s, description = {$description}, category = {$category}, metadata_json = {$metadata}, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND tenant_id = %d",
            $data['name'],
            $actorId,
            $typeId,
            $tenantId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update Enterprise Contract Type.');
        }
    }

    public function deactivate(int $typeId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_types';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET status = 'inactive', updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND tenant_id = %d AND status <> 'inactive'",
            $actorId,
            $typeId,
            $tenantId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to deactivate Enterprise Contract Type.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Type access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, mixed $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
