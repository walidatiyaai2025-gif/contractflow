<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractWorkflowTransitionRepository
{
    private const HISTORY_COLUMNS = 'id, instance_id, contract_id, workflow_id, workflow_version_id, transition_id, transition_code_snapshot, from_state_id, from_state_code_snapshot, to_state_id, to_state_code_snapshot, request_key_hash, actor_user_id, occurred_at';

    /** @return list<array<string,mixed>> */
    public function listHistory(int $contractId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_workflow_transition_history';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::HISTORY_COLUMNS . " FROM {$table}
             WHERE tenant_id = %d AND contract_id = %d
             ORDER BY occurred_at DESC, id DESC LIMIT %d OFFSET %d",
            $tenantId,
            $contractId,
            $limit,
            $offset
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @param null|callable(array<string,mixed>):void $beforeMutation
     * @return array{history:array<string,mixed>,created:bool}
     */
    public function execute(int $contractId, int $instanceId, string $transitionCode, string $requestKeyHash, int $actorId, ?callable $beforeMutation = null): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $instances = $wpdb->prefix . 'safecontracts_contract_workflow_instances';
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';
        $transitions = $wpdb->prefix . 'safecontracts_workflow_transitions';
        $history = $wpdb->prefix . 'safecontracts_contract_workflow_transition_history';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Workflow transition transaction.');
        }

        try {
            $instanceRows = $wpdb->get_results($wpdb->prepare(
                "SELECT c.id AS contract_id, c.accountant_user_id, c.is_archived,
                        i.id AS instance_id, i.workflow_id, i.workflow_version_id,
                        i.current_state_id, i.current_state_code_snapshot
                 FROM {$contracts} c
                 INNER JOIN {$instances} i ON i.contract_id = c.id AND i.tenant_id = c.tenant_id
                 WHERE c.id = %d AND c.tenant_id = %d AND c.is_archived = 0 AND i.id = %d
                 LIMIT 1 FOR UPDATE",
                $contractId,
                $tenantId,
                $instanceId
            ), ARRAY_A);
            if (! is_array($instanceRows) || count($instanceRows) !== 1 || ! is_array($instanceRows[0])) {
                throw new RuntimeException('Contract Workflow instance changed concurrently or is no longer executable.');
            }
            $instance = $instanceRows[0];
            $workflowId = (int) ($instance['workflow_id'] ?? 0);
            $workflowVersionId = (int) ($instance['workflow_version_id'] ?? 0);
            $fromStateId = (int) ($instance['current_state_id'] ?? 0);
            $fromStateCode = (string) ($instance['current_state_code_snapshot'] ?? '');
            if ($workflowId <= 0 || $workflowVersionId <= 0 || $fromStateId <= 0 || $fromStateCode === '') {
                throw new RuntimeException('Contract Workflow instance has invalid immutable/current-state identity.');
            }

            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::HISTORY_COLUMNS . " FROM {$history}
                 WHERE tenant_id = %d AND instance_id = %d AND request_key_hash = %s
                 LIMIT 1 FOR UPDATE",
                $tenantId,
                $instanceId,
                $requestKeyHash
            ), ARRAY_A);
            if (is_array($existingRows) && $existingRows !== []) {
                if (count($existingRows) !== 1 || ! is_array($existingRows[0])) {
                    throw new RuntimeException('Workflow transition idempotency identity is inconsistent.');
                }
                $existing = $existingRows[0];
                if ((string) ($existing['transition_code_snapshot'] ?? '') !== $transitionCode
                    || (int) ($existing['contract_id'] ?? 0) !== $contractId
                    || (int) ($existing['instance_id'] ?? 0) !== $instanceId) {
                    throw new RuntimeException('Workflow transition idempotency key was already used for a different operation.');
                }
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit idempotent Workflow transition retry.');
                }
                return ['history' => $existing, 'created' => false];
            }

            $transitionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT t.id AS transition_id, t.workflow_id, t.workflow_version_id, t.transition_code,
                        t.source_state_id, sf.state_code AS source_state_code,
                        t.destination_state_id, st.state_code AS destination_state_code
                 FROM {$transitions} t
                 INNER JOIN {$states} sf ON sf.id = t.source_state_id AND sf.tenant_id = t.tenant_id
                    AND sf.workflow_id = t.workflow_id AND sf.workflow_version_id = t.workflow_version_id
                 INNER JOIN {$states} st ON st.id = t.destination_state_id AND st.tenant_id = t.tenant_id
                    AND st.workflow_id = t.workflow_id AND st.workflow_version_id = t.workflow_version_id
                 INNER JOIN {$versions} v ON v.id = t.workflow_version_id AND v.workflow_id = t.workflow_id AND v.tenant_id = t.tenant_id
                 INNER JOIN {$workflows} w ON w.id = t.workflow_id AND w.tenant_id = t.tenant_id
                 WHERE t.tenant_id = %d AND t.workflow_id = %d AND t.workflow_version_id = %d
                   AND t.transition_code = %s AND t.source_state_id = %d
                   AND v.version_status = 'published'
                 ORDER BY t.id ASC LIMIT 2 FOR UPDATE",
                $tenantId,
                $workflowId,
                $workflowVersionId,
                $transitionCode,
                $fromStateId
            ), ARRAY_A);
            if (! is_array($transitionRows) || count($transitionRows) !== 1 || ! is_array($transitionRows[0])) {
                throw new RuntimeException('No executable transition exists from the locked current Workflow state.');
            }
            $transition = $transitionRows[0];
            if ((int) ($transition['source_state_id'] ?? 0) !== $fromStateId
                || (string) ($transition['source_state_code'] ?? '') !== $fromStateCode) {
                throw new RuntimeException('Workflow transition source snapshot does not match the locked current state.');
            }
            $transitionId = (int) ($transition['transition_id'] ?? 0);
            $toStateId = (int) ($transition['destination_state_id'] ?? 0);
            $toStateCode = (string) ($transition['destination_state_code'] ?? '');
            if ($transitionId <= 0 || $toStateId <= 0 || $toStateCode === '') {
                throw new RuntimeException('Workflow transition returned invalid destination identity.');
            }

            if ($beforeMutation !== null) {
                $beforeMutation($transition);
            }

            $historySql = $wpdb->prepare(
                "INSERT INTO {$history} (
                    tenant_id, instance_id, contract_id, workflow_id, workflow_version_id,
                    transition_id, transition_code_snapshot,
                    from_state_id, from_state_code_snapshot, to_state_id, to_state_code_snapshot,
                    request_key_hash, actor_user_id, occurred_at
                 ) VALUES (%d, %d, %d, %d, %d, %d, %s, %d, %s, %d, %s, %s, %d, UTC_TIMESTAMP())",
                $tenantId,
                $instanceId,
                $contractId,
                $workflowId,
                $workflowVersionId,
                $transitionId,
                $transitionCode,
                $fromStateId,
                $fromStateCode,
                $toStateId,
                $toStateCode,
                $requestKeyHash,
                $actorId
            );
            if ($wpdb->query($historySql) !== 1) {
                throw new RuntimeException('Unable to persist immutable Workflow transition history.');
            }
            $historyId = (int) $wpdb->insert_id;
            if ($historyId <= 0) {
                throw new RuntimeException('Workflow transition history insert returned no identifier.');
            }

            $updateSql = $wpdb->prepare(
                "UPDATE {$instances}
                 SET current_state_id = %d, current_state_code_snapshot = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND tenant_id = %d AND contract_id = %d
                   AND workflow_id = %d AND workflow_version_id = %d AND current_state_id = %d",
                $toStateId,
                $toStateCode,
                $actorId,
                $instanceId,
                $tenantId,
                $contractId,
                $workflowId,
                $workflowVersionId,
                $fromStateId
            );
            if ($wpdb->query($updateSql) !== 1) {
                throw new RuntimeException('Contract Workflow state changed concurrently before compare-and-set update.');
            }

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Workflow transition transaction.');
            }

            return [
                'history' => [
                    'id' => $historyId,
                    'instance_id' => $instanceId,
                    'contract_id' => $contractId,
                    'workflow_id' => $workflowId,
                    'workflow_version_id' => $workflowVersionId,
                    'transition_id' => $transitionId,
                    'transition_code_snapshot' => $transitionCode,
                    'from_state_id' => $fromStateId,
                    'from_state_code_snapshot' => $fromStateCode,
                    'to_state_id' => $toStateId,
                    'to_state_code_snapshot' => $toStateCode,
                    'request_key_hash' => $requestKeyHash,
                    'actor_user_id' => $actorId,
                    'occurred_at' => null,
                ],
                'created' => true,
            ];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Workflow transition access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
