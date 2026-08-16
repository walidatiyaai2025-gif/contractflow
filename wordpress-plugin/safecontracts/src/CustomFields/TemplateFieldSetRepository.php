<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class TemplateFieldSetRepository
{
    private const SELECT_COLUMNS = 'id, template_id, template_version_id, definition_id, position_no, field_code_snapshot, data_type_snapshot, label_snapshot, help_text_snapshot, definition_required_snapshot, required_override, options_json_snapshot, validation_json_snapshot, definition_config_hash, created_by, updated_by, created_at, updated_at';

    /** @return list<array<string,mixed>> */
    public function list(int $templateId, int $versionId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_template_version_fields';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE tenant_id = %d AND template_id = %d AND template_version_id = %d ORDER BY position_no ASC, id ASC",
            $tenantId,
            $templateId,
            $versionId
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param list<array<string,mixed>> $snapshots */
    public function replaceDraftFieldSet(int $templateId, int $versionId, int $contractTypeId, array $snapshots, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $templates = $wpdb->prefix . 'safecontracts_contract_templates';
        $versions = $wpdb->prefix . 'safecontracts_contract_template_versions';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $fields = $wpdb->prefix . 'safecontracts_contract_template_version_fields';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Template Dynamic Field set transaction.');
        }

        try {
            $locked = $wpdb->get_results($wpdb->prepare(
                "SELECT v.id FROM {$versions} v
                 INNER JOIN {$templates} t ON t.id = v.template_id AND t.tenant_id = v.tenant_id
                 INNER JOIN {$types} ct ON ct.id = t.contract_type_id AND ct.tenant_id = t.tenant_id
                 WHERE v.id = %d AND v.template_id = %d AND v.tenant_id = %d AND v.version_status = 'draft'
                   AND t.contract_type_id = %d AND t.status = 'active' AND ct.status = 'active'
                 LIMIT 1 FOR UPDATE",
                $versionId,
                $templateId,
                $tenantId,
                $contractTypeId
            ), ARRAY_A);
            if (! is_array($locked) || $locked === []) {
                throw new RuntimeException('Contract Template Version changed concurrently or is no longer editable.');
            }

            $delete = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$fields} WHERE tenant_id = %d AND template_id = %d AND template_version_id = %d",
                $tenantId,
                $templateId,
                $versionId
            ));
            if ($delete === false) {
                throw new RuntimeException('Unable to replace Template Dynamic Field set.');
            }

            foreach ($snapshots as $snapshot) {
                $helpSql = $this->nullableSql($wpdb, $snapshot['help_text_snapshot']);
                $overrideSql = $snapshot['required_override'] === null ? 'NULL' : (string) ((int) $snapshot['required_override']);
                $optionsSql = $this->nullableSql($wpdb, $snapshot['options_json_snapshot']);
                $validationSql = $this->nullableSql($wpdb, $snapshot['validation_json_snapshot']);
                $sql = $wpdb->prepare(
                    "INSERT INTO {$fields} (tenant_id, template_id, template_version_id, definition_id, position_no, field_code_snapshot, data_type_snapshot, label_snapshot, help_text_snapshot, definition_required_snapshot, required_override, options_json_snapshot, validation_json_snapshot, definition_config_hash, created_by, updated_by, created_at, updated_at)
                     SELECT %d, %d, %d, d.id, %d, %s, %s, %s, {$helpSql}, %d, {$overrideSql}, {$optionsSql}, {$validationSql}, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                     FROM {$definitions} d
                     WHERE d.id = %d AND d.tenant_id = %d AND d.contract_type_id = %d AND d.status = 'active'
                       AND d.field_code = %s AND d.data_type = %s AND d.label = %s
                       AND COALESCE(d.help_text, '') = %s AND d.is_required = %d
                       AND COALESCE(d.options_json, '') = %s AND COALESCE(d.validation_json, '') = %s",
                    $tenantId,
                    $templateId,
                    $versionId,
                    $snapshot['position_no'],
                    $snapshot['field_code_snapshot'],
                    $snapshot['data_type_snapshot'],
                    $snapshot['label_snapshot'],
                    $snapshot['definition_required_snapshot'],
                    $snapshot['definition_config_hash'],
                    $actorId,
                    $actorId,
                    $snapshot['definition_id'],
                    $tenantId,
                    $contractTypeId,
                    $snapshot['field_code_snapshot'],
                    $snapshot['data_type_snapshot'],
                    $snapshot['label_snapshot'],
                    $snapshot['help_text_snapshot'],
                    $snapshot['definition_required_snapshot'],
                    $snapshot['options_json_snapshot'],
                    $snapshot['validation_json_snapshot']
                );
                $result = $wpdb->query($sql);
                if ($result === false) {
                    throw new RuntimeException('Unable to persist Template Dynamic Field snapshot.');
                }
                if ($result === 0) {
                    throw new RuntimeException('Dynamic Field definition changed concurrently while replacing Template field set.');
                }
            }

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Template Dynamic Field set transaction.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Template Dynamic Field access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, mixed $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
