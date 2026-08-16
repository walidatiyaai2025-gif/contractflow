<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomFieldMetadataRepository
{
    private const COLUMNS = 'id, definition_id, data_type_snapshot, show_in_form, show_in_summary, show_in_mobile, show_in_print, filterable, sortable, groupable, exportable, dashboard_visible, report_label, report_data_class, aggregation_policy, created_by, updated_by, created_at, updated_at';

    public function find(int $definitionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_metadata';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::COLUMNS . " FROM {$table} WHERE tenant_id = %d AND definition_id = %d LIMIT 1",
            $tenantId,
            $definitionId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @param array<string,mixed> $metadata */
    public function upsert(int $definitionId, string $dataType, array $metadata, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_metadata';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $labelSql = $this->nullableSql($wpdb, $metadata['report_label'] ?? '');

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, definition_id, data_type_snapshot, show_in_form, show_in_summary, show_in_mobile, show_in_print, filterable, sortable, groupable, exportable, dashboard_visible, report_label, report_data_class, aggregation_policy, created_by, updated_by, created_at, updated_at)
             SELECT %d, d.id, %s, %d, %d, %d, %d, %d, %d, %d, %d, %d, {$labelSql}, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM {$definitions} d
             WHERE d.id = %d AND d.tenant_id = %d AND d.status = 'active' AND d.data_type = %s
             ON DUPLICATE KEY UPDATE
                data_type_snapshot = VALUES(data_type_snapshot),
                show_in_form = VALUES(show_in_form),
                show_in_summary = VALUES(show_in_summary),
                show_in_mobile = VALUES(show_in_mobile),
                show_in_print = VALUES(show_in_print),
                filterable = VALUES(filterable),
                sortable = VALUES(sortable),
                groupable = VALUES(groupable),
                exportable = VALUES(exportable),
                dashboard_visible = VALUES(dashboard_visible),
                report_label = VALUES(report_label),
                report_data_class = VALUES(report_data_class),
                aggregation_policy = VALUES(aggregation_policy),
                updated_by = VALUES(updated_by),
                updated_at = UTC_TIMESTAMP()",
            $tenantId,
            $dataType,
            $metadata['show_in_form'] ? 1 : 0,
            $metadata['show_in_summary'] ? 1 : 0,
            $metadata['show_in_mobile'] ? 1 : 0,
            $metadata['show_in_print'] ? 1 : 0,
            $metadata['filterable'] ? 1 : 0,
            $metadata['sortable'] ? 1 : 0,
            $metadata['groupable'] ? 1 : 0,
            $metadata['exportable'] ? 1 : 0,
            $metadata['dashboard_visible'] ? 1 : 0,
            $metadata['report_data_class'],
            $metadata['aggregation_policy'],
            $actorId,
            $actorId,
            $definitionId,
            $tenantId,
            $dataType
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to persist Dynamic Field presentation/reporting metadata.');
        }
        if ($result === 0) {
            throw new RuntimeException('Dynamic Field definition changed concurrently or is no longer configurable.');
        }
    }

    public function reset(int $definitionId, string $dataType): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_metadata';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $sql = $wpdb->prepare(
            "DELETE m FROM {$table} m
             INNER JOIN {$definitions} d ON d.id = m.definition_id AND d.tenant_id = m.tenant_id
             WHERE m.tenant_id = %d AND m.definition_id = %d AND d.status = 'active' AND d.data_type = %s",
            $tenantId,
            $definitionId,
            $dataType
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to reset Dynamic Field presentation/reporting metadata.');
        }
        if ($result === 0) {
            throw new RuntimeException('Dynamic Field definition changed concurrently or metadata is no longer resettable.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Dynamic Field metadata access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, mixed $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
