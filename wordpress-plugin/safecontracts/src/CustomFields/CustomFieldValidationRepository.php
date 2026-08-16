<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomFieldValidationRepository
{
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

    /** @return list<array<string,mixed>> */
    public function listActiveDefinitions(int $contractTypeId, int $limit = 500): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $limit = max(1, min(500, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_type_id, field_code, data_type, label, is_required, status, sort_order, options_json, validation_json
             FROM {$table}
             WHERE contract_type_id = %d AND tenant_id = %d AND status = 'active'
             ORDER BY sort_order ASC, label ASC, id ASC LIMIT %d",
            $contractTypeId,
            $tenantId,
            $limit
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * Returns set values with current definition metadata when it still exists in the tenant.
     * @return list<array<string,mixed>>
     */
    public function listSetValuesWithDefinitions(int $contractId, int $limit = 500): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $values = $wpdb->prefix . 'safecontracts_custom_field_values';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $limit = max(1, min(500, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT v.id, v.contract_id, v.definition_id, v.value_json, v.data_type_snapshot, v.definition_config_hash,
                    d.id AS current_definition_id, d.contract_type_id AS current_contract_type_id, d.field_code, d.data_type,
                    d.label, d.is_required, d.status AS definition_status, d.sort_order, d.options_json, d.validation_json
             FROM {$values} v
             LEFT JOIN {$definitions} d ON d.id = v.definition_id AND d.tenant_id = v.tenant_id
             WHERE v.contract_id = %d AND v.tenant_id = %d AND v.is_set = 1
             ORDER BY v.definition_id ASC, v.id ASC LIMIT %d",
            $contractId,
            $tenantId,
            $limit
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Dynamic Field validation requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
