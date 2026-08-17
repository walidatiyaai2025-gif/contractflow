<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractWorkflowInstanceRepository
{
    private const INSTANCE_COLUMNS = 'id, contract_id, contract_type_id, workflow_id, workflow_version_id, workflow_version_no, workflow_code_snapshot, current_state_id, current_state_code_snapshot, started_by, started_at, updated_by, updated_at';

    public function findInstance(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_workflow_instances';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::INSTANCE_COLUMNS . " FROM {$table} WHERE contract_id = %d AND tenant_id = %d LIMIT 1",
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

    public function findBinding(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_id, contract_type_id FROM {$table} WHERE contract_id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findWorkflow(int $workflowId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_workflows';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_type_id, workflow_code, status FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $workflowId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findVersion(int $workflowId, int $workflowVersionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_workflow_versions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, workflow_id, version_no, version_status FROM {$table} WHERE id = %d AND workflow_id = %d AND tenant_id = %d LIMIT 1",
            $workflowVersionId,
            $workflowId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function findInitialStates(int $workflowId, int $workflowVersionId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_workflow_states';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, state_code, name, is_terminal FROM {$table}
             WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d AND is_initial = 1
             ORDER BY id ASC LIMIT 2",
            $tenantId,
            $workflowId,
            $workflowVersionId
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return array{id:int,created:bool} */
    public function initialize(int $contractId, int $workflowId, int $workflowVersionId, int $actorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $instances = $wpdb->prefix . 'safecontracts_contract_workflow_instances';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $bindings = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Workflow initialization transaction.');
        }

        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT c.id, c.accountant_user_id, c.status, c.is_archived, b.contract_type_id
                 FROM {$contracts} c
                 INNER JOIN {$bindings} b ON b.contract_id = c.id AND b.tenant_id = c.tenant_id
                 WHERE c.id = %d AND c.tenant_id = %d AND c.is_archived = 0
                 LIMIT 1 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise contract or Contract Type binding changed concurrently and is no longer Workflow-initializable.');
            }
            $contractTypeId = (int) ($contractRows[0]['contract_type_id'] ?? 0);
            if ($contractTypeId <= 0) {
                throw new RuntimeException('Enterprise contract has no valid Contract Type binding for Workflow initialization.');
            }

            $definitionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT w.id AS workflow_id, w.contract_type_id, w.workflow_code,
                        v.id AS workflow_version_id, v.version_no, v.version_status,
                        s.id AS current_state_id, s.state_code AS current_state_code
                 FROM {$workflows} w
                 INNER JOIN {$versions} v ON v.workflow_id = w.id AND v.tenant_id = w.tenant_id
                 INNER JOIN {$states} s ON s.workflow_id = w.id AND s.workflow_version_id = v.id AND s.tenant_id = w.tenant_id
                 WHERE w.id = %d AND w.tenant_id = %d AND w.contract_type_id = %d AND w.status = 'active'
                   AND v.id = %d AND v.version_status = 'published' AND s.is_initial = 1
                 ORDER BY s.id ASC LIMIT 2 FOR UPDATE",
                $workflowId,
                $tenantId,
                $contractTypeId,
                $workflowVersionId
            ), ARRAY_A);
            if (! is_array($definitionRows) || count($definitionRows) !== 1 || ! is_array($definitionRows[0])) {
                throw new RuntimeException('Workflow, published version or unique initial state changed concurrently and is no longer bindable.');
            }
            $definition = $definitionRows[0];
            $initialStateId = (int) ($definition['current_state_id'] ?? 0);
            if ($initialStateId <= 0 || (int) ($definition['version_no'] ?? 0) <= 0) {
                throw new RuntimeException('Published Workflow definition returned invalid immutable identity.');
            }

            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::INSTANCE_COLUMNS . " FROM {$instances} WHERE tenant_id = %d AND contract_id = %d LIMIT 1 FOR UPDATE",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (is_array($existingRows) && $existingRows !== []) {
                if (count($existingRows) !== 1 || ! is_array($existingRows[0])) {
                    throw new RuntimeException('Contract Workflow instance identity is inconsistent.');
                }
                $existing = $existingRows[0];
                if (! $this->isExactInstance($existing, $definition, $contractTypeId)) {
                    throw new RuntimeException('Contract already has a different Workflow instance and cannot be silently rebound.');
                }
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit idempotent Contract Workflow initialization.');
                }
                return ['id' => (int) ($existing['id'] ?? 0), 'created' => false];
            }

            $sql = $wpdb->prepare(
                "INSERT INTO {$instances} (
                    tenant_id, contract_id, contract_type_id, workflow_id, workflow_version_id, workflow_version_no,
                    workflow_code_snapshot, current_state_id, current_state_code_snapshot,
                    started_by, started_at, updated_by, updated_at
                 )
                 SELECT %d, c.id, b.contract_type_id, w.id, v.id, v.version_no,
                        w.workflow_code, s.id, s.state_code,
                        %d, UTC_TIMESTAMP(), %d, UTC_TIMESTAMP()
                 FROM {$contracts} c
                 INNER JOIN {$bindings} b ON b.contract_id = c.id AND b.tenant_id = c.tenant_id
                 INNER JOIN {$workflows} w ON w.id = %d AND w.tenant_id = c.tenant_id AND w.contract_type_id = b.contract_type_id AND w.status = 'active'
                 INNER JOIN {$versions} v ON v.id = %d AND v.workflow_id = w.id AND v.tenant_id = w.tenant_id AND v.version_status = 'published'
                 INNER JOIN {$states} s ON s.id = %d AND s.workflow_id = w.id AND s.workflow_version_id = v.id AND s.tenant_id = w.tenant_id AND s.is_initial = 1
                 WHERE c.id = %d AND c.tenant_id = %d AND c.is_archived = 0 AND b.contract_type_id = %d",
                $tenantId,
                $actorId,
                $actorId,
                $workflowId,
                $workflowVersionId,
                $initialStateId,
                $contractId,
                $tenantId,
                $contractTypeId
            );
            $result = $wpdb->query($sql);
            if ($result === false) {
                throw new RuntimeException('Unable to initialize Contract Workflow instance.');
            }
            if ($result !== 1) {
                throw new RuntimeException('Contract Workflow initialization prerequisites changed concurrently.');
            }
            $instanceId = (int) $wpdb->insert_id;
            if ($instanceId <= 0) {
                throw new RuntimeException('Contract Workflow instance insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Workflow initialization transaction.');
            }
            return ['id' => $instanceId, 'created' => true];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param array<string,mixed> $existing @param array<string,mixed> $definition */
    private function isExactInstance(array $existing, array $definition, int $contractTypeId): bool
    {
        return (int) ($existing['contract_type_id'] ?? 0) === $contractTypeId
            && (int) ($existing['workflow_id'] ?? 0) === (int) ($definition['workflow_id'] ?? 0)
            && (int) ($existing['workflow_version_id'] ?? 0) === (int) ($definition['workflow_version_id'] ?? 0)
            && (int) ($existing['workflow_version_no'] ?? 0) === (int) ($definition['version_no'] ?? 0)
            && (string) ($existing['workflow_code_snapshot'] ?? '') === (string) ($definition['workflow_code'] ?? '')
            && (int) ($existing['current_state_id'] ?? 0) === (int) ($definition['current_state_id'] ?? 0)
            && (string) ($existing['current_state_code_snapshot'] ?? '') === (string) ($definition['current_state_code'] ?? '');
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Workflow access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
