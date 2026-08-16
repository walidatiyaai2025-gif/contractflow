<?php

declare(strict_types=1);

namespace SafeContracts\ContractTemplates;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractTemplateRepository
{
    private const TEMPLATE_COLUMNS = 'id, uuid, contract_type_id, template_code, name, description, status, created_by, updated_by, created_at, updated_at';
    private const VERSION_COLUMNS = 'id, template_id, version_no, version_status, definition_json, notes, created_by, updated_by, published_by, published_at, created_at, updated_at';

    public function findTemplate(int $templateId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_templates';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::TEMPLATE_COLUMNS . " FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $templateId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function searchTemplates(string $search = '', string $status = '', int $contractTypeId = 0, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_templates';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $where = ['tenant_id = %d'];
        $args = [$tenantId];

        if ($status !== '') {
            $where[] = 'status = %s';
            $args[] = $status;
        }
        if ($contractTypeId > 0) {
            $where[] = 'contract_type_id = %d';
            $args[] = $contractTypeId;
        }
        if ($search !== '') {
            $like = '%' . addcslashes($search, "\\_%") . '%';
            $where[] = '(name LIKE %s OR template_code LIKE %s)';
            $args[] = $like;
            $args[] = $like;
        }

        $args[] = $limit;
        $args[] = $offset;
        $sql = $wpdb->prepare(
            "SELECT " . self::TEMPLATE_COLUMNS . " FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY name ASC, id ASC LIMIT %d OFFSET %d",
            ...$args
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function createTemplate(array $data, string $uuid, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_templates';
        $description = $this->nullableSql($wpdb, $data['description']);
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, uuid, contract_type_id, template_code, name, description, status, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %s, %d, %s, %s, {$description}, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $tenantId,
            $uuid,
            $data['contract_type_id'],
            $data['template_code'],
            $data['name'],
            $data['status'],
            $actorId,
            $actorId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create Enterprise Contract Template.');
        }
        $templateId = (int) $wpdb->insert_id;
        if ($templateId <= 0) {
            throw new RuntimeException('Enterprise Contract Template insert returned no identifier.');
        }
        return $templateId;
    }

    public function updateTemplateMetadata(int $templateId, string $name, string $description, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_templates';
        $descriptionSql = $this->nullableSql($wpdb, $description);
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET name = %s, description = {$descriptionSql}, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND tenant_id = %d",
            $name,
            $actorId,
            $templateId,
            $tenantId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update Enterprise Contract Template.');
        }
    }

    public function deactivateTemplate(int $templateId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_templates';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET status = 'inactive', updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND tenant_id = %d AND status <> 'inactive'",
            $actorId,
            $templateId,
            $tenantId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to deactivate Enterprise Contract Template.');
        }
    }

    public function findVersion(int $templateId, int $versionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_template_versions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::VERSION_COLUMNS . " FROM {$table} WHERE id = %d AND template_id = %d AND tenant_id = %d LIMIT 1",
            $versionId,
            $templateId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listVersions(int $templateId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_template_versions';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::VERSION_COLUMNS . " FROM {$table} WHERE tenant_id = %d AND template_id = %d ORDER BY version_no DESC, id DESC LIMIT %d OFFSET %d",
            $tenantId,
            $templateId,
            $limit,
            $offset
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function createDraftVersion(int $templateId, string $definitionJson, string $notes, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_template_versions';
        $nextRows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(MAX(version_no), 0) AS max_version FROM {$table} WHERE tenant_id = %d AND template_id = %d",
            $tenantId,
            $templateId
        ), ARRAY_A);
        $maxVersion = is_array($nextRows) && $nextRows !== [] && is_array($nextRows[0])
            ? (int) ($nextRows[0]['max_version'] ?? 0)
            : 0;
        $versionNo = $maxVersion + 1;
        $notesSql = $this->nullableSql($wpdb, $notes);
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, template_id, version_no, version_status, definition_json, notes, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %d, %d, 'draft', %s, {$notesSql}, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $tenantId,
            $templateId,
            $versionNo,
            $definitionJson,
            $actorId,
            $actorId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create Contract Template draft version; retry if another version was created concurrently.');
        }
        $versionId = (int) $wpdb->insert_id;
        if ($versionId <= 0) {
            throw new RuntimeException('Contract Template draft insert returned no identifier.');
        }
        return $versionId;
    }

    public function updateDraftVersion(int $templateId, int $versionId, string $definitionJson, string $notes, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_template_versions';
        $notesSql = $this->nullableSql($wpdb, $notes);
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET definition_json = %s, notes = {$notesSql}, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND template_id = %d AND tenant_id = %d AND version_status = 'draft'",
            $definitionJson,
            $actorId,
            $versionId,
            $templateId,
            $tenantId
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to update Contract Template draft version.');
        }
        if ($result === 0) {
            throw new RuntimeException('Contract Template draft version changed concurrently or is no longer editable.');
        }
    }

    public function publishDraftVersion(int $templateId, int $versionId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $versions = $wpdb->prefix . 'safecontracts_contract_template_versions';
        $templates = $wpdb->prefix . 'safecontracts_contract_templates';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        $fields = $wpdb->prefix . 'safecontracts_contract_template_version_fields';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $sql = $wpdb->prepare(
            "UPDATE {$versions} v
             INNER JOIN {$templates} t ON t.id = v.template_id AND t.tenant_id = v.tenant_id
             INNER JOIN {$types} ct ON ct.id = t.contract_type_id AND ct.tenant_id = t.tenant_id
             SET v.version_status = 'published', v.published_by = %d, v.published_at = UTC_TIMESTAMP(), v.updated_by = %d, v.updated_at = UTC_TIMESTAMP()
             WHERE v.id = %d AND v.template_id = %d AND v.tenant_id = %d AND v.version_status = 'draft'
               AND t.status = 'active' AND ct.status = 'active'
               AND NOT EXISTS (
                    SELECT 1
                    FROM {$fields} f
                    LEFT JOIN {$definitions} d ON d.id = f.definition_id AND d.tenant_id = f.tenant_id
                    WHERE f.tenant_id = v.tenant_id AND f.template_id = v.template_id AND f.template_version_id = v.id
                      AND (
                          d.id IS NULL OR d.status <> 'active' OR d.contract_type_id <> t.contract_type_id
                          OR d.field_code <> f.field_code_snapshot OR d.data_type <> f.data_type_snapshot
                          OR d.label <> f.label_snapshot OR COALESCE(d.help_text, '') <> COALESCE(f.help_text_snapshot, '')
                          OR d.is_required <> f.definition_required_snapshot
                          OR COALESCE(d.options_json, '') <> COALESCE(f.options_json_snapshot, '')
                          OR COALESCE(d.validation_json, '') <> COALESCE(f.validation_json_snapshot, '')
                      )
               )",
            $actorId,
            $actorId,
            $versionId,
            $templateId,
            $tenantId
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to publish Contract Template draft version.');
        }
        if ($result === 0) {
            throw new RuntimeException('Contract Template version or Dynamic Field snapshot changed concurrently or is no longer publishable.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Template access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, mixed $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
