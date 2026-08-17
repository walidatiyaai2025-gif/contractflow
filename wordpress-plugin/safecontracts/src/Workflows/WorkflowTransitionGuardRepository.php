<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class WorkflowTransitionGuardRepository
{
    private const GUARD_COLUMNS = 'id, workflow_id, workflow_version_id, transition_id, position_no, guard_type, transition_code_snapshot, source_state_id_snapshot, source_state_code_snapshot, destination_state_id_snapshot, destination_state_code_snapshot, created_by, updated_by, created_at, updated_at';

    public function findTransition(int $workflowId, int $versionId, int $transitionId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $transitions = $wpdb->prefix . 'safecontracts_workflow_transitions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.transition_code, t.source_state_id, s.state_code AS source_state_code,
                    t.destination_state_id, d.state_code AS destination_state_code
             FROM {$transitions} t
             INNER JOIN {$states} s ON s.id = t.source_state_id AND s.tenant_id = t.tenant_id AND s.workflow_id = t.workflow_id AND s.workflow_version_id = t.workflow_version_id
             INNER JOIN {$states} d ON d.id = t.destination_state_id AND d.tenant_id = t.tenant_id AND d.workflow_id = t.workflow_id AND d.workflow_version_id = t.workflow_version_id
             WHERE t.id = %d AND t.workflow_id = %d AND t.workflow_version_id = %d AND t.tenant_id = %d LIMIT 1",
            $transitionId, $workflowId, $versionId, $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listGuards(int $workflowId, int $versionId, int $transitionId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $guards = $wpdb->prefix . 'safecontracts_workflow_transition_guards';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::GUARD_COLUMNS . " FROM {$guards}
             WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d AND transition_id = %d
             ORDER BY position_no ASC, id ASC LIMIT %d",
            $tenantId, $workflowId, $versionId, $transitionId, WorkflowTransitionGuardPolicy::MAX_GUARDS_PER_TRANSITION + 1
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param list<string> $guardTypes */
    public function replaceDraftGuards(int $workflowId, int $versionId, int $transitionId, array $guardTypes, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $guards = $wpdb->prefix . 'safecontracts_workflow_transition_guards';
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        $transitions = $wpdb->prefix . 'safecontracts_workflow_transitions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Workflow transition guard replacement transaction.');
        }
        try {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT t.id AS transition_id, t.transition_code, t.source_state_id, s.state_code AS source_state_code,
                        t.destination_state_id, d.state_code AS destination_state_code
                 FROM {$transitions} t
                 INNER JOIN {$versions} v ON v.id = t.workflow_version_id AND v.workflow_id = t.workflow_id AND v.tenant_id = t.tenant_id
                 INNER JOIN {$workflows} w ON w.id = t.workflow_id AND w.tenant_id = t.tenant_id
                 INNER JOIN {$types} ct ON ct.id = w.contract_type_id AND ct.tenant_id = w.tenant_id
                 INNER JOIN {$states} s ON s.id = t.source_state_id AND s.tenant_id = t.tenant_id AND s.workflow_id = t.workflow_id AND s.workflow_version_id = t.workflow_version_id
                 INNER JOIN {$states} d ON d.id = t.destination_state_id AND d.tenant_id = t.tenant_id AND d.workflow_id = t.workflow_id AND d.workflow_version_id = t.workflow_version_id
                 WHERE t.id = %d AND t.workflow_id = %d AND t.workflow_version_id = %d AND t.tenant_id = %d
                   AND v.version_status = 'draft' AND w.status = 'active' AND ct.status = 'active'
                 LIMIT 1 FOR UPDATE",
                $transitionId, $workflowId, $versionId, $tenantId
            ), ARRAY_A);
            if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
                throw new RuntimeException('Workflow draft Transition changed concurrently or is no longer guard-authorable.');
            }
            $transition = $rows[0];
            if ($wpdb->query($wpdb->prepare(
                "DELETE FROM {$guards} WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d AND transition_id = %d",
                $tenantId, $workflowId, $versionId, $transitionId
            )) === false) {
                throw new RuntimeException('Unable to replace Workflow transition guards.');
            }
            $position = 1;
            foreach ($guardTypes as $guardType) {
                $sql = $wpdb->prepare(
                    "INSERT INTO {$guards} (
                        tenant_id, workflow_id, workflow_version_id, transition_id, position_no, guard_type,
                        transition_code_snapshot, source_state_id_snapshot, source_state_code_snapshot,
                        destination_state_id_snapshot, destination_state_code_snapshot,
                        created_by, updated_by, created_at, updated_at
                     ) VALUES (%d, %d, %d, %d, %d, %s, %s, %d, %s, %d, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                    $tenantId, $workflowId, $versionId, $transitionId, $position, $guardType,
                    (string) ($transition['transition_code'] ?? ''), (int) ($transition['source_state_id'] ?? 0),
                    (string) ($transition['source_state_code'] ?? ''), (int) ($transition['destination_state_id'] ?? 0),
                    (string) ($transition['destination_state_code'] ?? ''), $actorId, $actorId
                );
                if ($wpdb->query($sql) !== 1) {
                    throw new RuntimeException('Unable to persist Workflow transition guard.');
                }
                $position++;
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Workflow transition guard replacement.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listExecutionGuards(int $workflowId, int $versionId, int $transitionId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $guards = $wpdb->prefix . 'safecontracts_workflow_transition_guards';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::GUARD_COLUMNS . " FROM {$guards}
             WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d AND transition_id = %d
             ORDER BY position_no ASC, id ASC LIMIT %d FOR UPDATE",
            $tenantId, $workflowId, $versionId, $transitionId, WorkflowTransitionGuardPolicy::MAX_GUARDS_PER_TRANSITION + 1
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Workflow transition guard access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
