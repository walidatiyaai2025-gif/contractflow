<?php

declare(strict_types=1);

namespace SafeContracts\ContractTemplates;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractConfigurationBindingRepository
{
    private const BINDING_COLUMNS = 'id, contract_id, contract_type_id, template_id, template_version_id, created_by, updated_by, created_at, updated_at';

    public function findBinding(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::BINDING_COLUMNS . " FROM {$table} WHERE contract_id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

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

    public function findContractType(int $typeId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_types';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $typeId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findTemplate(int $templateId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_templates';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_type_id, status FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $templateId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findTemplateVersion(int $templateId, int $versionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_template_versions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, template_id, version_no, version_status FROM {$table} WHERE id = %d AND template_id = %d AND tenant_id = %d LIMIT 1",
            $versionId,
            $templateId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function saveBinding(int $contractId, int $contractTypeId, ?int $templateId, ?int $templateVersionId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $templateSql = $templateId === null ? 'NULL' : (string) $templateId;
        $versionSql = $templateVersionId === null ? 'NULL' : (string) $templateVersionId;

        // The contract may leave draft after the service-level read. Re-check draft + archive
        // state in the same SQL statement that persists the binding so a concurrent lifecycle
        // transition cannot race the immutable-after-draft rule.
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, contract_id, contract_type_id, template_id, template_version_id, created_by, updated_by, created_at, updated_at)
             SELECT %d, c.id, %d, {$templateSql}, {$versionSql}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM {$contracts} c
             WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'draft' AND c.is_archived = 0
             ON DUPLICATE KEY UPDATE contract_type_id = VALUES(contract_type_id), template_id = VALUES(template_id), template_version_id = VALUES(template_version_id), updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()",
            $tenantId,
            $contractTypeId,
            $actorId,
            $actorId,
            $contractId,
            $tenantId
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to save Enterprise contract configuration binding.');
        }
        if ($result === 0) {
            throw new RuntimeException('Enterprise contract changed concurrently or is no longer an editable draft.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise contract configuration access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
