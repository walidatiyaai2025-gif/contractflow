<?php

declare(strict_types=1);

namespace SafeContracts\Organizations;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class OrgUnitRepository
{
    private const SELECT_COLUMNS = 'id, uuid, unit_code, name, unit_type, parent_unit_id, status, metadata_json, created_by, updated_by, created_at, updated_at';

    public function find(int $unitId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_org_units';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $unitId,
            $tenantId
        ), ARRAY_A);

        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function search(string $search = '', int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_org_units';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $search = trim($search);

        if ($search === '') {
            $sql = $wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE tenant_id = %d ORDER BY name ASC, id ASC LIMIT %d OFFSET %d",
                $tenantId,
                $limit,
                $offset
            );
        } else {
            $like = '%' . addcslashes($search, "\\_%") . '%';
            $sql = $wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE tenant_id = %d AND (name LIKE %s OR unit_code LIKE %s) ORDER BY name ASC, id ASC LIMIT %d OFFSET %d",
                $tenantId,
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
        $table = $wpdb->prefix . 'safecontracts_org_units';
        $unitCode = $this->nullableSql($wpdb, $data['unit_code']);
        $parentId = $this->nullableIntSql($data['parent_unit_id']);
        $metadata = $this->nullableSql($wpdb, $data['metadata_json']);

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, uuid, unit_code, name, unit_type, parent_unit_id, status, metadata_json, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %s, {$unitCode}, %s, %s, {$parentId}, %s, {$metadata}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $tenantId,
            $uuid,
            $data['name'],
            $data['unit_type'],
            $data['status'],
            $actorId,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create Enterprise organization unit.');
        }
        $unitId = (int) $wpdb->insert_id;
        if ($unitId <= 0) {
            throw new RuntimeException('Enterprise organization unit insert returned no identifier.');
        }
        return $unitId;
    }

    public function update(int $unitId, array $data, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_org_units';
        $unitCode = $this->nullableSql($wpdb, $data['unit_code']);
        $parentId = $this->nullableIntSql($data['parent_unit_id']);
        $metadata = $this->nullableSql($wpdb, $data['metadata_json']);

        $sql = $wpdb->prepare(
            "UPDATE {$table} SET unit_code = {$unitCode}, name = %s, unit_type = %s, parent_unit_id = {$parentId}, status = %s, metadata_json = {$metadata}, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND tenant_id = %d",
            $data['name'],
            $data['unit_type'],
            $data['status'],
            $actorId,
            $unitId,
            $tenantId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update Enterprise organization unit.');
        }
    }

    public function deactivate(int $unitId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_org_units';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET status = 'inactive', updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND tenant_id = %d AND status <> 'inactive'",
            $actorId,
            $unitId,
            $tenantId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to deactivate Enterprise organization unit.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise organization unit access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, mixed $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'NULL' : $wpdb->prepare('%s', $value);
    }

    private function nullableIntSql(mixed $value): string
    {
        $value = (int) $value;
        return $value > 0 ? (string) $value : 'NULL';
    }
}
