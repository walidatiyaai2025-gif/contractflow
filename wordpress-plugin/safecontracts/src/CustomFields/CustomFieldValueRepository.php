<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomFieldValueRepository
{
    private const VALUE_COLUMNS = 'id, contract_id, definition_id, is_set, value_json, data_type_snapshot, definition_config_hash, created_by, updated_by, created_at, updated_at';

    public function findContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, accountant_user_id, status, is_archived FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findBinding(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT contract_id, contract_type_id FROM {$table} WHERE contract_id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findDefinition(int $definitionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_type_id, field_code, data_type, label, is_required, status, sort_order, options_json, validation_json, updated_at FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $definitionId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findValue(int $contractId, int $definitionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_values';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::VALUE_COLUMNS . " FROM {$table} WHERE contract_id = %d AND definition_id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $definitionId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listSetValues(int $contractId, int $limit = 200, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_values';
        $limit = max(1, min(500, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::VALUE_COLUMNS . " FROM {$table} WHERE contract_id = %d AND tenant_id = %d AND is_set = 1 ORDER BY definition_id ASC LIMIT %d OFFSET %d",
            $contractId,
            $tenantId,
            $limit,
            $offset
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return list<array<string,mixed>> */
    public function listMissingRequired(int $contractId, int $limit = 500): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $bindings = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $values = $wpdb->prefix . 'safecontracts_custom_field_values';
        $limit = max(1, min(500, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.field_code, d.data_type, d.label, d.sort_order
             FROM {$bindings} b
             INNER JOIN {$definitions} d ON d.tenant_id = b.tenant_id AND d.contract_type_id = b.contract_type_id AND d.status = 'active' AND d.is_required = 1
             LEFT JOIN {$values} v ON v.tenant_id = b.tenant_id AND v.contract_id = b.contract_id AND v.definition_id = d.id AND v.is_set = 1
             WHERE b.contract_id = %d AND b.tenant_id = %d AND v.id IS NULL
             ORDER BY d.sort_order ASC, d.label ASC, d.id ASC LIMIT %d",
            $contractId,
            $tenantId,
            $limit
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param array<string,mixed> $definition */
    public function saveValue(int $contractId, array $definition, string $valueJson, string $configHash, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $values = $wpdb->prefix . 'safecontracts_custom_field_values';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $bindings = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $options = trim((string) ($definition['options_json'] ?? ''));
        $validation = trim((string) ($definition['validation_json'] ?? ''));

        $sql = $wpdb->prepare(
            "INSERT INTO {$values} (tenant_id, contract_id, definition_id, is_set, value_json, data_type_snapshot, definition_config_hash, created_by, updated_by, created_at, updated_at)
             SELECT %d, c.id, d.id, 1, %s, d.data_type, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM {$contracts} c
             INNER JOIN {$bindings} b ON b.tenant_id = c.tenant_id AND b.contract_id = c.id
             INNER JOIN {$definitions} d ON d.id = %d AND d.tenant_id = c.tenant_id AND d.contract_type_id = b.contract_type_id AND d.status = 'active'
             WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'draft' AND c.is_archived = 0
               AND d.field_code = %s AND d.data_type = %s
               AND COALESCE(d.options_json, '') = %s AND COALESCE(d.validation_json, '') = %s
             ON DUPLICATE KEY UPDATE is_set = 1, value_json = VALUES(value_json), data_type_snapshot = VALUES(data_type_snapshot), definition_config_hash = VALUES(definition_config_hash), updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()",
            $tenantId,
            $valueJson,
            $configHash,
            $actorId,
            $actorId,
            (int) ($definition['id'] ?? 0),
            $contractId,
            $tenantId,
            (string) ($definition['field_code'] ?? ''),
            (string) ($definition['data_type'] ?? ''),
            $options,
            $validation
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to save Enterprise Dynamic Field value.');
        }
        if ($result === 0) {
            throw new RuntimeException('Enterprise contract, binding or Dynamic Field definition changed concurrently and the value was not saved.');
        }
    }

    /** @param array<string,mixed> $definition */
    public function clearValue(int $contractId, array $definition, string $configHash, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $values = $wpdb->prefix . 'safecontracts_custom_field_values';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $bindings = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $options = trim((string) ($definition['options_json'] ?? ''));
        $validation = trim((string) ($definition['validation_json'] ?? ''));

        $sql = $wpdb->prepare(
            "UPDATE {$values} v
             SET v.is_set = 0, v.value_json = NULL, v.data_type_snapshot = %s, v.definition_config_hash = %s, v.updated_by = %d, v.updated_at = UTC_TIMESTAMP()
             WHERE v.contract_id = %d AND v.definition_id = %d AND v.tenant_id = %d AND v.is_set = 1
               AND EXISTS (
                   SELECT 1 FROM {$contracts} c
                   INNER JOIN {$bindings} b ON b.tenant_id = c.tenant_id AND b.contract_id = c.id
                   INNER JOIN {$definitions} d ON d.id = v.definition_id AND d.tenant_id = c.tenant_id AND d.contract_type_id = b.contract_type_id AND d.status = 'active'
                   WHERE c.id = v.contract_id AND c.tenant_id = v.tenant_id AND c.status = 'draft' AND c.is_archived = 0
                     AND d.field_code = %s AND d.data_type = %s
                     AND COALESCE(d.options_json, '') = %s AND COALESCE(d.validation_json, '') = %s
               )",
            (string) ($definition['data_type'] ?? ''),
            $configHash,
            $actorId,
            $contractId,
            (int) ($definition['id'] ?? 0),
            $tenantId,
            (string) ($definition['field_code'] ?? ''),
            (string) ($definition['data_type'] ?? ''),
            $options,
            $validation
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to clear Enterprise Dynamic Field value.');
        }
        if ($result === 0) {
            $current = $this->findValue($contractId, (int) ($definition['id'] ?? 0));
            if ($current === null || (int) ($current['is_set'] ?? 0) === 0) {
                return;
            }
            throw new RuntimeException('Enterprise contract, binding or Dynamic Field definition changed concurrently and the value was not cleared.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Dynamic Field value access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
