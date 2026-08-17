<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ApprovalReleaseRepository
{
    /** @return array<string,mixed>|null */
    public function findRequest(int $requestId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $requests = $wpdb->prefix . 'safecontracts_workflow_approval_requests';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, instance_id, contract_id, workflow_id, workflow_version_id,
                    transition_id, transition_code_snapshot,
                    from_state_id, from_state_code_snapshot,
                    to_state_id, to_state_code_snapshot,
                    route_id_snapshot, status, requester_user_id, requested_at
             FROM {$requests}
             WHERE tenant_id = %d AND id = %d LIMIT 2",
            $tenantId,
            $requestId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        if (count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Approval Request identity is inconsistent.');
        }
        return $rows[0];
    }

    /**
     * @return array{release:array<string,mixed>,history:array<string,mixed>}|null
     */
    public function findReleaseResult(int $requestId, ?string $expectedReleaseKeyHash = null): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $releases = $wpdb->prefix . 'safecontracts_workflow_approval_releases';
        $history = $wpdb->prefix . 'safecontracts_contract_workflow_transition_history';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id AS release_id, r.request_id, r.instance_id, r.transition_history_id,
                    r.release_key_hash, r.released_by, r.released_at,
                    h.id AS history_id, h.contract_id, h.workflow_id, h.workflow_version_id,
                    h.transition_id, h.transition_code_snapshot,
                    h.from_state_id, h.from_state_code_snapshot,
                    h.to_state_id, h.to_state_code_snapshot,
                    h.actor_user_id, h.occurred_at
             FROM {$releases} r
             LEFT JOIN {$history} h ON h.tenant_id = r.tenant_id AND h.id = r.transition_history_id
             WHERE r.tenant_id = %d AND r.request_id = %d
             LIMIT 2",
            $tenantId,
            $requestId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        if (count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Approval Release identity is inconsistent.');
        }
        $row = $rows[0];
        if ($expectedReleaseKeyHash !== null
            && ! hash_equals((string) ($row['release_key_hash'] ?? ''), $expectedReleaseKeyHash)) {
            throw new RuntimeException('Approval Request was already released with a different idempotency key.');
        }
        $releaseId = (int) ($row['release_id'] ?? 0);
        $historyId = (int) ($row['history_id'] ?? 0);
        if ($releaseId <= 0 || $historyId <= 0
            || $historyId !== (int) ($row['transition_history_id'] ?? 0)
            || (int) ($row['instance_id'] ?? 0) <= 0) {
            throw new RuntimeException('Approval Release is missing its immutable P6 transition history.');
        }
        return [
            'release' => [
                'id' => $releaseId,
                'request_id' => (int) ($row['request_id'] ?? 0),
                'instance_id' => (int) ($row['instance_id'] ?? 0),
                'transition_history_id' => $historyId,
                'released_by' => (int) ($row['released_by'] ?? 0),
                'released_at' => $row['released_at'] ?? null,
            ],
            'history' => [
                'id' => $historyId,
                'instance_id' => (int) ($row['instance_id'] ?? 0),
                'contract_id' => (int) ($row['contract_id'] ?? 0),
                'workflow_id' => (int) ($row['workflow_id'] ?? 0),
                'workflow_version_id' => (int) ($row['workflow_version_id'] ?? 0),
                'transition_id' => (int) ($row['transition_id'] ?? 0),
                'transition_code_snapshot' => (string) ($row['transition_code_snapshot'] ?? ''),
                'from_state_id' => (int) ($row['from_state_id'] ?? 0),
                'from_state_code_snapshot' => (string) ($row['from_state_code_snapshot'] ?? ''),
                'to_state_id' => (int) ($row['to_state_id'] ?? 0),
                'to_state_code_snapshot' => (string) ($row['to_state_code_snapshot'] ?? ''),
                'actor_user_id' => (int) ($row['actor_user_id'] ?? 0),
                'occurred_at' => $row['occurred_at'] ?? null,
            ],
        ];
    }

    /**
     * Called only from inside the authoritative P6 transition transaction.
     * @param array<string,mixed> $instance
     * @return array<string,mixed>
     */
    public function lockApprovedRequestForRelease(
        int $requestId,
        string $releaseKeyHash,
        array $instance
    ): array {
        global $wpdb;
        $tenantId = $this->tenantId();
        $requests = $wpdb->prefix . 'safecontracts_workflow_approval_requests';
        $routes = $wpdb->prefix . 'safecontracts_workflow_transition_approval_routes';
        $releases = $wpdb->prefix . 'safecontracts_workflow_approval_releases';

        $existingRows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, request_id, release_key_hash, transition_history_id
             FROM {$releases}
             WHERE tenant_id = %d AND (request_id = %d OR release_key_hash = %s)
             ORDER BY id ASC LIMIT 3 FOR UPDATE",
            $tenantId,
            $requestId,
            $releaseKeyHash
        ), ARRAY_A);
        $existingRows = is_array($existingRows) ? array_values(array_filter($existingRows, 'is_array')) : [];
        foreach ($existingRows as $existing) {
            if ((int) ($existing['request_id'] ?? 0) === $requestId) {
                if (hash_equals((string) ($existing['release_key_hash'] ?? ''), $releaseKeyHash)) {
                    throw new RuntimeException('Approval Request already has committed Release evidence for this idempotency key.');
                }
                throw new RuntimeException('Approval Request was already released with a different idempotency key.');
            }
            if (hash_equals((string) ($existing['release_key_hash'] ?? ''), $releaseKeyHash)) {
                throw new RuntimeException('Approval Release idempotency key was already used for another Approval Request.');
            }
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.instance_id, r.contract_id, r.workflow_id, r.workflow_version_id,
                    r.transition_id, r.transition_code_snapshot,
                    r.from_state_id, r.from_state_code_snapshot,
                    r.to_state_id, r.to_state_code_snapshot,
                    r.route_id_snapshot, r.status,
                    ar.id AS current_route_id,
                    ar.transition_code_snapshot AS route_transition_code,
                    ar.source_state_id_snapshot AS route_source_state_id,
                    ar.source_state_code_snapshot AS route_source_state_code,
                    ar.destination_state_id_snapshot AS route_destination_state_id,
                    ar.destination_state_code_snapshot AS route_destination_state_code
             FROM {$requests} r
             INNER JOIN {$routes} ar ON ar.tenant_id = r.tenant_id
                AND ar.id = r.route_id_snapshot
                AND ar.workflow_id = r.workflow_id
                AND ar.workflow_version_id = r.workflow_version_id
                AND ar.transition_id = r.transition_id
             WHERE r.tenant_id = %d AND r.id = %d
             LIMIT 2 FOR UPDATE",
            $tenantId,
            $requestId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Approved Approval Request or its immutable Approval Route is unavailable.');
        }
        $request = $rows[0];
        if ((string) ($request['status'] ?? '') !== ApprovalDecisionPolicy::REQUEST_STATUS_APPROVED) {
            throw new RuntimeException('Only an approved Approval Request can release a Workflow transition.');
        }

        $instanceChecks = [
            'instance_id' => 'instance_id',
            'contract_id' => 'contract_id',
            'workflow_id' => 'workflow_id',
            'workflow_version_id' => 'workflow_version_id',
            'from_state_id' => 'current_state_id',
        ];
        foreach ($instanceChecks as $requestKey => $instanceKey) {
            if ((int) ($request[$requestKey] ?? 0) <= 0
                || (int) ($request[$requestKey] ?? 0) !== (int) ($instance[$instanceKey] ?? 0)) {
                throw new RuntimeException('Approved Approval Request no longer matches the locked P6 Workflow instance.');
            }
        }
        if ((string) ($request['from_state_code_snapshot'] ?? '') === ''
            || (string) ($request['from_state_code_snapshot'] ?? '') !== (string) ($instance['current_state_code_snapshot'] ?? '')) {
            throw new RuntimeException('Approved Approval Request source State no longer matches the locked P6 Workflow instance.');
        }

        if ((int) ($request['route_id_snapshot'] ?? 0) !== (int) ($request['current_route_id'] ?? 0)
            || (string) ($request['transition_code_snapshot'] ?? '') !== (string) ($request['route_transition_code'] ?? '')
            || (int) ($request['from_state_id'] ?? 0) !== (int) ($request['route_source_state_id'] ?? 0)
            || (string) ($request['from_state_code_snapshot'] ?? '') !== (string) ($request['route_source_state_code'] ?? '')
            || (int) ($request['to_state_id'] ?? 0) !== (int) ($request['route_destination_state_id'] ?? 0)
            || (string) ($request['to_state_code_snapshot'] ?? '') !== (string) ($request['route_destination_state_code'] ?? '')) {
            throw new RuntimeException('Approved Approval Request snapshots no longer match the immutable Approval Route.');
        }
        return $request;
    }

    /** @param array<string,mixed> $request @param array<string,mixed> $transition */
    public function assertTransitionMatchesRequest(array $request, array $transition): void
    {
        $pairs = [
            ['workflow_id', 'workflow_id'],
            ['workflow_version_id', 'workflow_version_id'],
            ['transition_id', 'transition_id'],
            ['from_state_id', 'source_state_id'],
            ['to_state_id', 'destination_state_id'],
            ['route_id_snapshot', 'approval_route_id'],
        ];
        foreach ($pairs as [$requestKey, $transitionKey]) {
            if ((int) ($request[$requestKey] ?? 0) <= 0
                || (int) ($request[$requestKey] ?? 0) !== (int) ($transition[$transitionKey] ?? 0)) {
                throw new RuntimeException('Approved Approval Request does not match the resolved P6 Transition identity.');
            }
        }
        if ((string) ($request['transition_code_snapshot'] ?? '') !== (string) ($transition['transition_code'] ?? '')
            || (string) ($request['from_state_code_snapshot'] ?? '') !== (string) ($transition['source_state_code'] ?? '')
            || (string) ($request['to_state_code_snapshot'] ?? '') !== (string) ($transition['destination_state_code'] ?? '')) {
            throw new RuntimeException('Approved Approval Request does not match the resolved P6 Transition snapshots.');
        }
    }

    /** @return array<string,mixed> */
    public function insertRelease(
        int $requestId,
        int $instanceId,
        int $transitionHistoryId,
        string $releaseKeyHash,
        int $actorId
    ): array {
        global $wpdb;
        $tenantId = $this->tenantId();
        $releases = $wpdb->prefix . 'safecontracts_workflow_approval_releases';
        if ($requestId <= 0 || $instanceId <= 0 || $transitionHistoryId <= 0 || $actorId <= 0) {
            throw new RuntimeException('Approval Release persistence received invalid identity.');
        }
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$releases}
             (tenant_id, request_id, instance_id, transition_history_id, release_key_hash, released_by, released_at)
             VALUES (%d, %d, %d, %d, %s, %d, UTC_TIMESTAMP())",
            $tenantId,
            $requestId,
            $instanceId,
            $transitionHistoryId,
            $releaseKeyHash,
            $actorId
        ));
        if ($inserted !== 1) {
            throw new RuntimeException('Unable to persist immutable Approval Release evidence.');
        }
        $releaseId = (int) ($wpdb->insert_id ?? 0);
        if ($releaseId <= 0) {
            throw new RuntimeException('Approval Release persistence returned invalid identity.');
        }
        return [
            'id' => $releaseId,
            'request_id' => $requestId,
            'instance_id' => $instanceId,
            'transition_history_id' => $transitionHistoryId,
            'released_by' => $actorId,
            'released_at' => null,
        ];
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Approval Release access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
