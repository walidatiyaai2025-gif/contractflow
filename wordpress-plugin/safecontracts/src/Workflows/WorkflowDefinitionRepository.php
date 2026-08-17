<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use RuntimeException;
use SafeContracts\Approvals\ApprovalRouteRepository;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class WorkflowDefinitionRepository
{
    private const WORKFLOW_COLUMNS = 'id, uuid, contract_type_id, workflow_code, name, description, status, created_by, updated_by, created_at, updated_at';
    private const VERSION_COLUMNS = 'id, workflow_id, version_no, version_status, created_by, updated_by, published_by, published_at, created_at, updated_at';

    public function findWorkflow(int $workflowId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_workflows';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::WORKFLOW_COLUMNS . " FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $workflowId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function searchWorkflows(string $search = '', string $status = '', int $contractTypeId = 0, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_workflows';
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
            $where[] = '(name LIKE %s OR workflow_code LIKE %s)';
            $args[] = $like;
            $args[] = $like;
        }
        $args[] = $limit;
        $args[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::WORKFLOW_COLUMNS . " FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY name ASC, id ASC LIMIT %d OFFSET %d",
            ...$args
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param array<string,mixed> $data */
    public function createWorkflow(array $data, string $uuid, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        $description = $this->nullableSql($wpdb, $data['description'] ?? '');
        $sql = $wpdb->prepare(
            "INSERT INTO {$workflows} (tenant_id, uuid, contract_type_id, workflow_code, name, description, status, created_by, updated_by, created_at, updated_at)
             SELECT %d, %s, ct.id, %s, %s, {$description}, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM {$types} ct
             WHERE ct.id = %d AND ct.tenant_id = %d AND ct.status = 'active'",
            $tenantId,
            $uuid,
            (string) ($data['workflow_code'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['status'] ?? ''),
            $actorId,
            $actorId,
            (int) ($data['contract_type_id'] ?? 0),
            $tenantId
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to create Enterprise Workflow.');
        }
        if ($result === 0) {
            throw new RuntimeException('Contract Type changed concurrently and is no longer available for Workflow authoring.');
        }
        $workflowId = (int) $wpdb->insert_id;
        if ($workflowId <= 0) {
            throw new RuntimeException('Enterprise Workflow insert returned no identifier.');
        }
        return $workflowId;
    }

    public function updateWorkflowMetadata(int $workflowId, string $name, string $description, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $descriptionSql = $this->nullableSql($wpdb, $description);
        $sql = $wpdb->prepare(
            "UPDATE {$workflows} SET name = %s, description = {$descriptionSql}, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND tenant_id = %d",
            $name,
            $actorId,
            $workflowId,
            $tenantId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update Enterprise Workflow metadata.');
        }
    }

    public function deactivateWorkflow(int $workflowId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $sql = $wpdb->prepare(
            "UPDATE {$workflows} SET status = 'inactive', updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND tenant_id = %d AND status <> 'inactive'",
            $actorId,
            $workflowId,
            $tenantId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to deactivate Enterprise Workflow.');
        }
    }

    public function findVersion(int $workflowId, int $versionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::VERSION_COLUMNS . " FROM {$versions} WHERE id = %d AND workflow_id = %d AND tenant_id = %d LIMIT 1",
            $versionId,
            $workflowId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listVersions(int $workflowId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::VERSION_COLUMNS . " FROM {$versions} WHERE tenant_id = %d AND workflow_id = %d
             ORDER BY version_no DESC, id DESC LIMIT %d OFFSET %d",
            $tenantId,
            $workflowId,
            $limit,
            $offset
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function createDraftVersion(int $workflowId, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Workflow draft creation transaction.');
        }
        try {
            $locked = $wpdb->get_results($wpdb->prepare(
                "SELECT w.id
                 FROM {$workflows} w
                 INNER JOIN {$types} ct ON ct.id = w.contract_type_id AND ct.tenant_id = w.tenant_id
                 WHERE w.id = %d AND w.tenant_id = %d AND w.status = 'active' AND ct.status = 'active'
                 LIMIT 1 FOR UPDATE",
                $workflowId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($locked) || $locked === []) {
                throw new RuntimeException('Workflow or Contract Type changed concurrently and is no longer available for version authoring.');
            }
            $nextRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COALESCE(MAX(version_no), 0) AS max_version FROM {$versions} WHERE tenant_id = %d AND workflow_id = %d",
                $tenantId,
                $workflowId
            ), ARRAY_A);
            $maxVersion = is_array($nextRows) && $nextRows !== [] && is_array($nextRows[0])
                ? (int) ($nextRows[0]['max_version'] ?? 0)
                : 0;
            if ($maxVersion < 0 || $maxVersion === PHP_INT_MAX) {
                throw new RuntimeException('Workflow version number is outside the supported range.');
            }
            $sql = $wpdb->prepare(
                "INSERT INTO {$versions} (tenant_id, workflow_id, version_no, version_status, created_by, updated_by, created_at, updated_at)
                 VALUES (%d, %d, %d, 'draft', %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                $tenantId,
                $workflowId,
                $maxVersion + 1,
                $actorId,
                $actorId
            );
            if ($wpdb->query($sql) === false) {
                throw new RuntimeException('Unable to create Workflow draft version.');
            }
            $versionId = (int) $wpdb->insert_id;
            if ($versionId <= 0) {
                throw new RuntimeException('Workflow draft version insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Workflow draft creation transaction.');
            }
            return $versionId;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array{states:list<array<string,mixed>>,transitions:list<array<string,mixed>>} */
    public function getGraph(int $workflowId, int $versionId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $statesTable = $wpdb->prefix . 'safecontracts_workflow_states';
        $transitionsTable = $wpdb->prefix . 'safecontracts_workflow_transitions';
        $statesRows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, state_code, name, description, sort_order, is_initial, is_terminal
             FROM {$statesTable}
             WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d
             ORDER BY sort_order ASC, id ASC LIMIT %d",
            $tenantId,
            $workflowId,
            $versionId,
            WorkflowDefinitionPolicy::MAX_STATES + 1
        ), ARRAY_A);
        $transitionRows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.transition_code, t.name, t.description, t.sort_order,
                    s.state_code AS source_state_code, d.state_code AS destination_state_code
             FROM {$transitionsTable} t
             INNER JOIN {$statesTable} s ON s.id = t.source_state_id AND s.tenant_id = t.tenant_id AND s.workflow_id = t.workflow_id AND s.workflow_version_id = t.workflow_version_id
             INNER JOIN {$statesTable} d ON d.id = t.destination_state_id AND d.tenant_id = t.tenant_id AND d.workflow_id = t.workflow_id AND d.workflow_version_id = t.workflow_version_id
             WHERE t.tenant_id = %d AND t.workflow_id = %d AND t.workflow_version_id = %d
             ORDER BY t.sort_order ASC, t.id ASC LIMIT %d",
            $tenantId,
            $workflowId,
            $versionId,
            WorkflowDefinitionPolicy::MAX_TRANSITIONS + 1
        ), ARRAY_A);
        $states = [];
        if (is_array($statesRows)) {
            foreach ($statesRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $states[] = [
                    'state_code' => (string) ($row['state_code'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_initial' => (int) ($row['is_initial'] ?? 0) === 1,
                    'is_terminal' => (int) ($row['is_terminal'] ?? 0) === 1,
                ];
            }
        }
        $transitions = [];
        if (is_array($transitionRows)) {
            foreach ($transitionRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $transitions[] = [
                    'transition_code' => (string) ($row['transition_code'] ?? ''),
                    'source_state_code' => (string) ($row['source_state_code'] ?? ''),
                    'destination_state_code' => (string) ($row['destination_state_code'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];
            }
        }
        return ['states' => $states, 'transitions' => $transitions];
    }

    /** @param list<array<string,mixed>> $states @param list<array<string,mixed>> $transitions */
    public function replaceDraftGraph(int $workflowId, int $versionId, array $states, array $transitions, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $statesTable = $wpdb->prefix . 'safecontracts_workflow_states';
        $transitionsTable = $wpdb->prefix . 'safecontracts_workflow_transitions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Workflow graph replacement transaction.');
        }
        try {
            $this->lockDraftVersion($wpdb, $tenantId, $workflowId, $versionId);
            if ($wpdb->query($wpdb->prepare(
                "DELETE FROM {$transitionsTable} WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d",
                $tenantId,
                $workflowId,
                $versionId
            )) === false) {
                throw new RuntimeException('Unable to replace Workflow transitions.');
            }
            if ($wpdb->query($wpdb->prepare(
                "DELETE FROM {$statesTable} WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d",
                $tenantId,
                $workflowId,
                $versionId
            )) === false) {
                throw new RuntimeException('Unable to replace Workflow states.');
            }

            $stateIds = [];
            foreach ($states as $state) {
                $description = $this->nullableSql($wpdb, $state['description'] ?? '');
                $sql = $wpdb->prepare(
                    "INSERT INTO {$statesTable} (tenant_id, workflow_id, workflow_version_id, state_code, name, description, sort_order, is_initial, is_terminal, created_by, updated_by, created_at, updated_at)
                     VALUES (%d, %d, %d, %s, %s, {$description}, %d, %d, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                    $tenantId,
                    $workflowId,
                    $versionId,
                    (string) ($state['state_code'] ?? ''),
                    (string) ($state['name'] ?? ''),
                    (int) ($state['sort_order'] ?? 0),
                    ! empty($state['is_initial']) ? 1 : 0,
                    ! empty($state['is_terminal']) ? 1 : 0,
                    $actorId,
                    $actorId
                );
                if ($wpdb->query($sql) === false) {
                    throw new RuntimeException('Unable to persist Workflow state.');
                }
                $stateId = (int) $wpdb->insert_id;
                if ($stateId <= 0) {
                    throw new RuntimeException('Workflow state insert returned no identifier.');
                }
                $stateIds[(string) $state['state_code']] = $stateId;
            }

            foreach ($transitions as $transition) {
                $sourceCode = (string) ($transition['source_state_code'] ?? '');
                $destinationCode = (string) ($transition['destination_state_code'] ?? '');
                $sourceId = (int) ($stateIds[$sourceCode] ?? 0);
                $destinationId = (int) ($stateIds[$destinationCode] ?? 0);
                if ($sourceId <= 0 || $destinationId <= 0) {
                    throw new RuntimeException('Workflow transition endpoint identity was not persisted in this graph replacement.');
                }
                $description = $this->nullableSql($wpdb, $transition['description'] ?? '');
                $sql = $wpdb->prepare(
                    "INSERT INTO {$transitionsTable} (tenant_id, workflow_id, workflow_version_id, transition_code, source_state_id, destination_state_id, name, description, sort_order, created_by, updated_by, created_at, updated_at)
                     VALUES (%d, %d, %d, %s, %d, %d, %s, {$description}, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                    $tenantId,
                    $workflowId,
                    $versionId,
                    (string) ($transition['transition_code'] ?? ''),
                    $sourceId,
                    $destinationId,
                    (string) ($transition['name'] ?? ''),
                    (int) ($transition['sort_order'] ?? 0),
                    $actorId,
                    $actorId
                );
                if ($wpdb->query($sql) === false) {
                    throw new RuntimeException('Unable to persist Workflow transition.');
                }
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Workflow graph replacement transaction.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function publishDraftVersion(int $workflowId, int $versionId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Workflow publication transaction.');
        }
        try {
            $this->lockDraftVersion($wpdb, $tenantId, $workflowId, $versionId);
            $graph = $this->getGraph($workflowId, $versionId);
            if (count($graph['states']) > WorkflowDefinitionPolicy::MAX_STATES || count($graph['transitions']) > WorkflowDefinitionPolicy::MAX_TRANSITIONS) {
                throw new RuntimeException('Stored Workflow graph exceeds bounded publication limits.');
            }
            WorkflowDefinitionPolicy::normalizeGraph($graph['states'], $graph['transitions']);
            $this->assertTransitionGuardsPublishable($wpdb, $tenantId, $workflowId, $versionId);
            (new ApprovalRouteRepository())->assertVersionPublishable($workflowId, $versionId);
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$versions}
                 SET version_status = 'published', published_by = %d, published_at = UTC_TIMESTAMP(), updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND workflow_id = %d AND tenant_id = %d AND version_status = 'draft'",
                $actorId,
                $actorId,
                $versionId,
                $workflowId,
                $tenantId
            ));
            if ($result === false) {
                throw new RuntimeException('Unable to publish Workflow draft version.');
            }
            if ($result === 0) {
                throw new RuntimeException('Workflow draft version changed concurrently or is no longer publishable.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Workflow publication transaction.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    private function assertTransitionGuardsPublishable(object $wpdb, int $tenantId, int $workflowId, int $versionId): void
    {
        $guards = $wpdb->prefix . 'safecontracts_workflow_transition_guards';
        $transitions = $wpdb->prefix . 'safecontracts_workflow_transitions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT g.id
             FROM {$guards} g
             LEFT JOIN {$transitions} t ON t.id = g.transition_id AND t.tenant_id = g.tenant_id
                AND t.workflow_id = g.workflow_id AND t.workflow_version_id = g.workflow_version_id
             LEFT JOIN {$states} s ON s.id = t.source_state_id AND s.tenant_id = t.tenant_id
                AND s.workflow_id = t.workflow_id AND s.workflow_version_id = t.workflow_version_id
             LEFT JOIN {$states} d ON d.id = t.destination_state_id AND d.tenant_id = t.tenant_id
                AND d.workflow_id = t.workflow_id AND d.workflow_version_id = t.workflow_version_id
             WHERE g.tenant_id = %d AND g.workflow_id = %d AND g.workflow_version_id = %d
               AND (
                    t.id IS NULL
                    OR g.guard_type <> %s
                    OR t.transition_code <> g.transition_code_snapshot
                    OR t.source_state_id <> g.source_state_id_snapshot
                    OR COALESCE(s.state_code, '') <> g.source_state_code_snapshot
                    OR t.destination_state_id <> g.destination_state_id_snapshot
                    OR COALESCE(d.state_code, '') <> g.destination_state_code_snapshot
               )
             LIMIT 1 FOR UPDATE",
            $tenantId,
            $workflowId,
            $versionId,
            WorkflowTransitionGuardPolicy::DYNAMIC_FIELDS_READY
        ), ARRAY_A);
        if (is_array($rows) && $rows !== []) {
            throw new RuntimeException('Workflow transition guard configuration is stale, orphaned or unsupported.');
        }
    }

    private function lockDraftVersion(object $wpdb, int $tenantId, int $workflowId, int $versionId): void
    {
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT v.id
             FROM {$versions} v
             INNER JOIN {$workflows} w ON w.id = v.workflow_id AND w.tenant_id = v.tenant_id
             INNER JOIN {$types} ct ON ct.id = w.contract_type_id AND ct.tenant_id = w.tenant_id
             WHERE v.id = %d AND v.workflow_id = %d AND v.tenant_id = %d AND v.version_status = 'draft'
               AND w.status = 'active' AND ct.status = 'active'
             LIMIT 1 FOR UPDATE",
            $versionId,
            $workflowId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Workflow, Contract Type or draft version changed concurrently or is no longer authorable.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Workflow access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, mixed $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
