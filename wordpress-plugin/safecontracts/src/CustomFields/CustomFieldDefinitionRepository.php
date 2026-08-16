<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomFieldDefinitionRepository
{
    private const SELECT_COLUMNS = 'id, uuid, contract_type_id, field_code, data_type, label, help_text, is_required, status, sort_order, options_json, validation_json, created_by, updated_by, created_at, updated_at';

    public function find(int $definitionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $definitionId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findContractType(int $contractTypeId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_types';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $contractTypeId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function search(int $contractTypeId = 0, string $search = '', string $status = '', int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $where = ['tenant_id = %d'];
        $args = [$tenantId];

        if ($contractTypeId > 0) {
            $where[] = 'contract_type_id = %d';
            $args[] = $contractTypeId;
        }
        if ($status !== '') {
            $where[] = 'status = %s';
            $args[] = $status;
        }
        if ($search !== '') {
            $like = '%' . addcslashes($search, "\\_%") . '%';
            $where[] = '(label LIKE %s OR field_code LIKE %s)';
            $args[] = $like;
            $args[] = $like;
        }
        $args[] = $limit;
        $args[] = $offset;
        $sql = $wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY contract_type_id ASC, sort_order ASC, label ASC, id ASC LIMIT %d OFFSET %d",
            ...$args
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function create(array $data, string $uuid, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        $help = $this->nullableSql($wpdb, $data['help_text']);
        $options = $this->nullableSql($wpdb, $data['options_json']);
        $validation = $this->nullableSql($wpdb, $data['validation_json']);
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, uuid, contract_type_id, field_code, data_type, label, help_text, is_required, status, sort_order, options_json, validation_json, created_by, updated_by, created_at, updated_at)
             SELECT %d, %s, ct.id, %s, %s, %s, {$help}, %d, %s, %d, {$options}, {$validation}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM {$types} ct
             WHERE ct.id = %d AND ct.tenant_id = %d AND ct.status = 'active'",
            $tenantId,
            $uuid,
            $data['field_code'],
            $data['data_type'],
            $data['label'],
            $data['is_required'],
            $data['status'],
            $data['sort_order'],
            $actorId,
            $actorId,
            $data['contract_type_id'],
            $tenantId
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to create Enterprise Custom Field definition.');
        }
        if ($result === 0) {
            throw new RuntimeException('Contract Type changed concurrently and is no longer available for Custom Field authoring.');
        }
        $id = (int) $wpdb->insert_id;
        if ($id <= 0) {
            throw new RuntimeException('Enterprise Custom Field definition insert returned no identifier.');
        }
        return $id;
    }

    public function updateConfiguration(int $definitionId, int $contractTypeId, array $data, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        $help = $this->nullableSql($wpdb, $data['help_text']);
        $options = $this->nullableSql($wpdb, $data['options_json']);
        $validation = $this->nullableSql($wpdb, $data['validation_json']);
        $sql = $wpdb->prepare(
            "UPDATE {$table} d SET label = %s, help_text = {$help}, is_required = %d, sort_order = %d, options_json = {$options}, validation_json = {$validation}, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE d.id = %d AND d.tenant_id = %d AND d.contract_type_id = %d
               AND EXISTS (SELECT 1 FROM {$types} ct WHERE ct.id = d.contract_type_id AND ct.tenant_id = d.tenant_id AND ct.status = 'active')",
            $data['label'],
            $data['is_required'],
            $data['sort_order'],
            $actorId,
            $definitionId,
            $tenantId,
            $contractTypeId
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to update Enterprise Custom Field definition.');
        }
        if ($result === 0) {
            $type = $this->findContractType($contractTypeId);
            if ($type === null || (string) ($type['status'] ?? '') !== 'active') {
                throw new RuntimeException('Contract Type changed concurrently and is no longer available for Custom Field authoring.');
            }
        }
    }

    public function deactivate(int $definitionId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET status = 'inactive', updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND tenant_id = %d AND status <> 'inactive'",
            $actorId,
            $definitionId,
            $tenantId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to deactivate Enterprise Custom Field definition.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Custom Field definition access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, mixed $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
