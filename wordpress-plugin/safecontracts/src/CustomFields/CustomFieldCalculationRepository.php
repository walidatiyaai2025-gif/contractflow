<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomFieldCalculationRepository
{
    private const RULE_COLUMNS = 'id, target_definition_id, contract_type_id, target_field_code_snapshot, target_data_type_snapshot, target_config_hash, expression_json, created_by, updated_by, created_at, updated_at';
    private const MAX_GRAPH_SCAN = 6401;

    public function findRule(int $targetDefinitionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_custom_field_calculation_rules';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::RULE_COLUMNS . " FROM {$table} WHERE tenant_id = %d AND target_definition_id = %d LIMIT 1",
            $tenantId,
            $targetDefinitionId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listDependencies(int $ruleId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $dependencies = $wpdb->prefix . 'safecontracts_custom_field_calculation_dependencies';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.rule_id, c.target_definition_id, c.position_no, c.source_definition_id,
                    c.source_field_code_snapshot, c.source_data_type_snapshot, c.source_config_hash,
                    d.id AS current_source_id, d.contract_type_id AS current_contract_type_id, d.field_code AS current_field_code,
                    d.data_type AS current_data_type, d.status AS current_status, d.options_json AS current_options_json,
                    d.validation_json AS current_validation_json
             FROM {$dependencies} c
             LEFT JOIN {$definitions} d ON d.id = c.source_definition_id AND d.tenant_id = c.tenant_id
             WHERE c.tenant_id = %d AND c.rule_id = %d
             ORDER BY c.position_no ASC, c.id ASC LIMIT %d",
            $tenantId,
            $ruleId,
            CustomFieldCalculationPolicy::MAX_DEPENDENCIES + 1
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return list<array{target_definition_id:string,source_definition_id:string}> */
    public function listDependencyEdges(int $contractTypeId, int $limit = self::MAX_GRAPH_SCAN): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $rules = $wpdb->prefix . 'safecontracts_custom_field_calculation_rules';
        $dependencies = $wpdb->prefix . 'safecontracts_custom_field_calculation_dependencies';
        $limit = max(1, min(self::MAX_GRAPH_SCAN, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.target_definition_id, c.source_definition_id
             FROM {$rules} r
             INNER JOIN {$dependencies} c ON c.rule_id = r.id AND c.tenant_id = r.tenant_id
             WHERE r.tenant_id = %d AND r.contract_type_id = %d
             ORDER BY r.target_definition_id ASC, c.position_no ASC, c.id ASC LIMIT %d",
            $tenantId,
            $contractTypeId,
            $limit
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @param array<string,mixed> $target
     * @param list<array<string,mixed>> $sources
     */
    public function replaceRule(array $target, string $expressionJson, array $sources, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $rules = $wpdb->prefix . 'safecontracts_custom_field_calculation_rules';
        $dependencies = $wpdb->prefix . 'safecontracts_custom_field_calculation_dependencies';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $targetId = (int) ($target['id'] ?? 0);
        $contractTypeId = (int) ($target['contract_type_id'] ?? 0);
        $targetOptions = trim((string) ($target['options_json'] ?? ''));
        $targetValidation = trim((string) ($target['validation_json'] ?? ''));
        $targetHash = CustomFieldValuePolicy::configurationHash($target);

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Dynamic Field calculation rule transaction.');
        }

        try {
            $definitionIds = [$targetId];
            foreach ($sources as $source) {
                $definitionIds[] = (int) ($source['id'] ?? 0);
            }
            $definitionIds = array_values(array_unique($definitionIds));
            sort($definitionIds, SORT_NUMERIC);
            if ($definitionIds === [] || in_array(0, $definitionIds, true)) {
                throw new RuntimeException('Dynamic Field calculation rule contains an invalid definition identity.');
            }

            $placeholders = implode(', ', array_fill(0, count($definitionIds), '%d'));
            $lockArgs = [$tenantId, $contractTypeId, ...$definitionIds];
            $lockedRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$definitions}
                 WHERE tenant_id = %d AND contract_type_id = %d AND status = 'active' AND data_type IN ('integer','decimal') AND id IN ({$placeholders})
                 ORDER BY id ASC FOR UPDATE",
                ...$lockArgs
            ), ARRAY_A);
            $lockedIds = [];
            if (is_array($lockedRows)) {
                foreach ($lockedRows as $row) {
                    if (is_array($row)) {
                        $lockedIds[] = (int) ($row['id'] ?? 0);
                    }
                }
            }
            sort($lockedIds, SORT_NUMERIC);
            if ($lockedIds !== $definitionIds) {
                throw new RuntimeException('Dynamic Field calculation definitions changed concurrently or are no longer active numeric fields in the Contract Type.');
            }

            $ruleSql = $wpdb->prepare(
                "INSERT INTO {$rules} (tenant_id, target_definition_id, contract_type_id, target_field_code_snapshot, target_data_type_snapshot, target_config_hash, expression_json, created_by, updated_by, created_at, updated_at)
                 SELECT %d, d.id, d.contract_type_id, %s, %s, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                 FROM {$definitions} d
                 WHERE d.id = %d AND d.tenant_id = %d AND d.contract_type_id = %d AND d.status = 'active' AND d.data_type IN ('integer','decimal')
                   AND d.field_code = %s AND d.data_type = %s
                   AND COALESCE(d.options_json, '') = %s AND COALESCE(d.validation_json, '') = %s
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), contract_type_id = VALUES(contract_type_id),
                    target_field_code_snapshot = VALUES(target_field_code_snapshot), target_data_type_snapshot = VALUES(target_data_type_snapshot),
                    target_config_hash = VALUES(target_config_hash), expression_json = VALUES(expression_json), updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()",
                $tenantId,
                (string) ($target['field_code'] ?? ''),
                (string) ($target['data_type'] ?? ''),
                $targetHash,
                $expressionJson,
                $actorId,
                $actorId,
                $targetId,
                $tenantId,
                $contractTypeId,
                (string) ($target['field_code'] ?? ''),
                (string) ($target['data_type'] ?? ''),
                $targetOptions,
                $targetValidation
            );
            $ruleResult = $wpdb->query($ruleSql);
            if ($ruleResult === false) {
                throw new RuntimeException('Unable to persist Dynamic Field calculation rule.');
            }
            $ruleId = (int) $wpdb->insert_id;
            if ($ruleId <= 0) {
                $rule = $this->findRule($targetId);
                $ruleId = (int) ($rule['id'] ?? 0);
            }
            if ($ruleId <= 0) {
                throw new RuntimeException('Dynamic Field calculation rule persistence returned no identifier.');
            }

            $deleteResult = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$dependencies} WHERE tenant_id = %d AND rule_id = %d AND target_definition_id = %d",
                $tenantId,
                $ruleId,
                $targetId
            ));
            if ($deleteResult === false) {
                throw new RuntimeException('Unable to replace Dynamic Field calculation dependencies.');
            }

            $position = 0;
            foreach ($sources as $source) {
                $position++;
                $sourceOptions = trim((string) ($source['options_json'] ?? ''));
                $sourceValidation = trim((string) ($source['validation_json'] ?? ''));
                $sourceHash = CustomFieldValuePolicy::configurationHash($source);
                $insertSql = $wpdb->prepare(
                    "INSERT INTO {$dependencies} (tenant_id, rule_id, target_definition_id, position_no, source_definition_id, source_field_code_snapshot, source_data_type_snapshot, source_config_hash, created_by, updated_by, created_at, updated_at)
                     SELECT %d, %d, %d, %d, d.id, %s, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                     FROM {$definitions} d
                     WHERE d.id = %d AND d.tenant_id = %d AND d.contract_type_id = %d AND d.status = 'active' AND d.data_type IN ('integer','decimal')
                       AND d.field_code = %s AND d.data_type = %s
                       AND COALESCE(d.options_json, '') = %s AND COALESCE(d.validation_json, '') = %s",
                    $tenantId,
                    $ruleId,
                    $targetId,
                    $position,
                    (string) ($source['field_code'] ?? ''),
                    (string) ($source['data_type'] ?? ''),
                    $sourceHash,
                    $actorId,
                    $actorId,
                    (int) ($source['id'] ?? 0),
                    $tenantId,
                    $contractTypeId,
                    (string) ($source['field_code'] ?? ''),
                    (string) ($source['data_type'] ?? ''),
                    $sourceOptions,
                    $sourceValidation
                );
                $insertResult = $wpdb->query($insertSql);
                if ($insertResult === false) {
                    throw new RuntimeException('Unable to persist Dynamic Field calculation dependency.');
                }
                if ($insertResult === 0) {
                    throw new RuntimeException('Dynamic Field calculation source definition changed concurrently.');
                }
            }

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Dynamic Field calculation rule transaction.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param array<string,mixed> $target */
    public function resetRule(array $target): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $rules = $wpdb->prefix . 'safecontracts_custom_field_calculation_rules';
        $dependencies = $wpdb->prefix . 'safecontracts_custom_field_calculation_dependencies';
        $definitions = $wpdb->prefix . 'safecontracts_custom_field_definitions';
        $targetId = (int) ($target['id'] ?? 0);
        $contractTypeId = (int) ($target['contract_type_id'] ?? 0);

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Dynamic Field calculation reset transaction.');
        }
        try {
            $locked = $wpdb->get_results($wpdb->prepare(
                "SELECT d.id FROM {$definitions} d
                 WHERE d.id = %d AND d.tenant_id = %d AND d.contract_type_id = %d AND d.status = 'active' AND d.data_type IN ('integer','decimal') AND d.data_type = %s
                 LIMIT 1 FOR UPDATE",
                $targetId,
                $tenantId,
                $contractTypeId,
                (string) ($target['data_type'] ?? '')
            ), ARRAY_A);
            if (! is_array($locked) || $locked === []) {
                throw new RuntimeException('Dynamic Field calculation target changed concurrently or is no longer resettable.');
            }
            $ruleRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$rules} WHERE tenant_id = %d AND target_definition_id = %d LIMIT 1 FOR UPDATE",
                $tenantId,
                $targetId
            ), ARRAY_A);
            $ruleId = is_array($ruleRows) && $ruleRows !== [] && is_array($ruleRows[0]) ? (int) ($ruleRows[0]['id'] ?? 0) : 0;
            if ($ruleId > 0) {
                if ($wpdb->query($wpdb->prepare("DELETE FROM {$dependencies} WHERE tenant_id = %d AND rule_id = %d", $tenantId, $ruleId)) === false) {
                    throw new RuntimeException('Unable to delete Dynamic Field calculation dependencies.');
                }
                if ($wpdb->query($wpdb->prepare("DELETE FROM {$rules} WHERE tenant_id = %d AND id = %d AND target_definition_id = %d", $tenantId, $ruleId, $targetId)) === false) {
                    throw new RuntimeException('Unable to reset Dynamic Field calculation rule.');
                }
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Dynamic Field calculation reset transaction.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
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

    /** @param list<int> $definitionIds @return array<int,array<string,mixed>> */
    public function listValues(int $contractId, array $definitionIds): array
    {
        global $wpdb;
        if ($definitionIds === []) {
            return [];
        }
        $tenantId = $this->tenantId();
        $definitionIds = array_values(array_unique(array_map('intval', $definitionIds)));
        sort($definitionIds, SORT_NUMERIC);
        if (count($definitionIds) > CustomFieldCalculationPolicy::MAX_DEPENDENCIES || in_array(0, $definitionIds, true)) {
            throw new RuntimeException('Dynamic Field calculation value request exceeds the bounded dependency limit.');
        }
        $table = $wpdb->prefix . 'safecontracts_custom_field_values';
        $placeholders = implode(', ', array_fill(0, count($definitionIds), '%d'));
        $args = [$contractId, $tenantId, ...$definitionIds];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_id, definition_id, is_set, value_json, data_type_snapshot, definition_config_hash
             FROM {$table}
             WHERE contract_id = %d AND tenant_id = %d AND definition_id IN ({$placeholders})
             ORDER BY definition_id ASC",
            ...$args
        ), ARRAY_A);
        $indexed = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $indexed[(int) ($row['definition_id'] ?? 0)] = $row;
                }
            }
        }
        return $indexed;
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Dynamic Field calculation access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
