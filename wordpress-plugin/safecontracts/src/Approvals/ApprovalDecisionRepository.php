<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ApprovalDecisionRepository
{
    private const PUBLIC_DECISION_COLUMNS = 'id, request_id, request_stage_id, user_id, action, comment, decided_at';
    private const INTERNAL_DECISION_COLUMNS = 'id, request_id, request_stage_id, user_id, action, decision_key_hash, comment, decided_at';

    /** @return array<string,mixed>|null */
    public function findRequest(int $requestId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $requests = $wpdb->prefix . 'safecontracts_workflow_approval_requests';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, instance_id, contract_id, workflow_id, workflow_version_id, transition_id,
                    transition_code_snapshot, from_state_id, from_state_code_snapshot,
                    to_state_id, to_state_code_snapshot, route_id_snapshot, status,
                    requester_user_id, requested_at
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

    /** @return list<array<string,mixed>> */
    public function listDecisions(int $requestId, int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $decisions = $wpdb->prefix . 'safecontracts_workflow_approval_decisions';
        $limit = max(1, min(200, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::PUBLIC_DECISION_COLUMNS . " FROM {$decisions}
             WHERE tenant_id = %d AND request_id = %d
             ORDER BY decided_at ASC, id ASC LIMIT %d OFFSET %d",
            $tenantId,
            $requestId,
            $limit,
            $offset
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array{
     *   decision:array<string,mixed>,
     *   request_status:string,
     *   stage_position:int,
     *   stage_code:string,
     *   stage_completed:bool,
     *   request_completed:bool,
     *   idempotent:bool
     * }
     */
    public function recordDecision(
        int $requestId,
        int $actorId,
        string $action,
        string $decisionKeyHash,
        ?string $comment
    ): array {
        global $wpdb;
        $tenantId = $this->tenantId();
        $requests = $wpdb->prefix . 'safecontracts_workflow_approval_requests';
        $stages = $wpdb->prefix . 'safecontracts_workflow_approval_request_stages';
        $candidates = $wpdb->prefix . 'safecontracts_workflow_approval_request_candidates';
        $decisions = $wpdb->prefix . 'safecontracts_workflow_approval_decisions';

        if ($requestId <= 0 || $actorId <= 0) {
            throw new RuntimeException('Approval Decision request and actor identities must be positive.');
        }
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Approval Decision transaction.');
        }

        try {
            $requestRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, contract_id, instance_id, status, transition_id, from_state_id
                 FROM {$requests}
                 WHERE tenant_id = %d AND id = %d
                 LIMIT 2 FOR UPDATE",
                $tenantId,
                $requestId
            ), ARRAY_A);
            if (! is_array($requestRows) || count($requestRows) !== 1 || ! is_array($requestRows[0])) {
                throw new RuntimeException('Approval Request was not found in the current tenant.');
            }
            $request = $requestRows[0];
            $requestStatus = (string) ($request['status'] ?? '');
            if (! in_array($requestStatus, [
                ApprovalDecisionPolicy::REQUEST_STATUS_PENDING,
                ApprovalDecisionPolicy::REQUEST_STATUS_APPROVED,
                ApprovalDecisionPolicy::REQUEST_STATUS_REJECTED,
            ], true)) {
                throw new RuntimeException('Approval Request has an unsupported runtime status.');
            }

            $retryRows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::INTERNAL_DECISION_COLUMNS . " FROM {$decisions}
                 WHERE tenant_id = %d AND decision_key_hash = %s
                 LIMIT 2 FOR UPDATE",
                $tenantId,
                $decisionKeyHash
            ), ARRAY_A);
            if (is_array($retryRows) && $retryRows !== []) {
                if (count($retryRows) !== 1 || ! is_array($retryRows[0])) {
                    throw new RuntimeException('Approval Decision idempotency identity is inconsistent.');
                }
                $existing = $retryRows[0];
                if ((int) ($existing['request_id'] ?? 0) !== $requestId
                    || (int) ($existing['user_id'] ?? 0) !== $actorId
                    || (string) ($existing['action'] ?? '') !== $action) {
                    throw new RuntimeException('Approval Decision idempotency key was already used for a different operation.');
                }
                $stage = $this->stageByIdLocked($wpdb, $tenantId, $requestId, (int) ($existing['request_stage_id'] ?? 0));
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit idempotent Approval Decision retry.');
                }
                return [
                    'decision' => $this->publicDecision($existing),
                    'request_status' => $requestStatus,
                    'stage_position' => (int) ($stage['position_no'] ?? 0),
                    'stage_code' => (string) ($stage['stage_code_snapshot'] ?? ''),
                    'stage_completed' => $this->stageIsCompleteLocked($wpdb, $tenantId, $requestId, $stage),
                    'request_completed' => $requestStatus !== ApprovalDecisionPolicy::REQUEST_STATUS_PENDING,
                    'idempotent' => true,
                ];
            }

            if ($requestStatus !== ApprovalDecisionPolicy::REQUEST_STATUS_PENDING) {
                throw new RuntimeException('Approval Request is already terminal and cannot accept another decision.');
            }

            $stageRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, position_no, stage_code_snapshot, decision_policy_snapshot, required_approvals_snapshot
                 FROM {$stages}
                 WHERE tenant_id = %d AND request_id = %d
                 ORDER BY position_no ASC, id ASC LIMIT %d FOR UPDATE",
                $tenantId,
                $requestId,
                ApprovalRoutePolicy::MAX_STAGES + 1
            ), ARRAY_A);
            $stageRows = is_array($stageRows) ? array_values(array_filter($stageRows, 'is_array')) : [];
            if ($stageRows === [] || count($stageRows) > ApprovalRoutePolicy::MAX_STAGES) {
                throw new RuntimeException('Approval Request stage snapshot is empty or exceeds the supported bound.');
            }
            foreach ($stageRows as $index => $stage) {
                if ((int) ($stage['position_no'] ?? 0) !== $index + 1 || (int) ($stage['id'] ?? 0) <= 0) {
                    throw new RuntimeException('Approval Request stage snapshot ordering is invalid.');
                }
            }

            $candidateRows = $wpdb->get_results($wpdb->prepare(
                "SELECT request_stage_id, user_id
                 FROM {$candidates}
                 WHERE tenant_id = %d AND request_id = %d
                 ORDER BY request_stage_id ASC, user_id ASC LIMIT %d FOR UPDATE",
                $tenantId,
                $requestId,
                ApprovalRequestPolicy::MAX_CANDIDATES_PER_REQUEST + 1
            ), ARRAY_A);
            $candidateRows = is_array($candidateRows) ? array_values(array_filter($candidateRows, 'is_array')) : [];
            if ($candidateRows === [] || count($candidateRows) > ApprovalRequestPolicy::MAX_CANDIDATES_PER_REQUEST) {
                throw new RuntimeException('Approval Request candidate snapshot is empty or exceeds the supported bound.');
            }

            $decisionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::INTERNAL_DECISION_COLUMNS . " FROM {$decisions}
                 WHERE tenant_id = %d AND request_id = %d
                 ORDER BY request_stage_id ASC, user_id ASC, id ASC LIMIT %d FOR UPDATE",
                $tenantId,
                $requestId,
                ApprovalRequestPolicy::MAX_CANDIDATES_PER_REQUEST + 1
            ), ARRAY_A);
            $decisionRows = is_array($decisionRows) ? array_values(array_filter($decisionRows, 'is_array')) : [];
            if (count($decisionRows) > ApprovalRequestPolicy::MAX_CANDIDATES_PER_REQUEST) {
                throw new RuntimeException('Approval Decision history exceeds the supported request bound.');
            }

            $candidatesByStage = [];
            foreach ($candidateRows as $candidate) {
                $stageId = (int) ($candidate['request_stage_id'] ?? 0);
                $userId = (int) ($candidate['user_id'] ?? 0);
                if ($stageId <= 0 || $userId <= 0) {
                    throw new RuntimeException('Approval Request candidate snapshot contains invalid identity.');
                }
                $candidatesByStage[$stageId][$userId] = true;
            }

            $decisionsByStage = [];
            foreach ($decisionRows as $decision) {
                $stageId = (int) ($decision['request_stage_id'] ?? 0);
                $userId = (int) ($decision['user_id'] ?? 0);
                $decisionAction = (string) ($decision['action'] ?? '');
                if ($stageId <= 0 || $userId <= 0
                    || ! isset($candidatesByStage[$stageId][$userId])
                    || ! in_array($decisionAction, [ApprovalDecisionPolicy::ACTION_APPROVE, ApprovalDecisionPolicy::ACTION_REJECT], true)) {
                    throw new RuntimeException('Approval Decision history is inconsistent with the immutable candidate snapshot.');
                }
                if (isset($decisionsByStage[$stageId][$userId])) {
                    throw new RuntimeException('Approval Decision history contains duplicate user decisions for a stage.');
                }
                if ($decisionAction === ApprovalDecisionPolicy::ACTION_REJECT) {
                    throw new RuntimeException('Pending Approval Request contains a terminal rejection decision.');
                }
                $decisionsByStage[$stageId][$userId] = $decisionAction;
            }

            $activeStage = null;
            foreach ($stageRows as $stage) {
                $stageId = (int) $stage['id'];
                $stageCandidates = array_keys($candidatesByStage[$stageId] ?? []);
                if ($stageCandidates === [] || count($stageCandidates) > ApprovalRequestPolicy::MAX_CANDIDATES_PER_STAGE) {
                    throw new RuntimeException('Approval Request stage candidate snapshot is invalid or exceeds the supported bound.');
                }
                $stageDecisions = $decisionsByStage[$stageId] ?? [];
                $this->assertStagePolicy($stage, count($stageCandidates));
                if (! $this->stageCompleteFromMaps($stage, count($stageCandidates), $stageDecisions)) {
                    $activeStage = $stage;
                    break;
                }
            }
            if ($activeStage === null) {
                throw new RuntimeException('Pending Approval Request has no incomplete active stage.');
            }

            $activeStageId = (int) $activeStage['id'];
            foreach ($stageRows as $stage) {
                if ((int) ($stage['position_no'] ?? 0) > (int) ($activeStage['position_no'] ?? 0)
                    && isset($decisionsByStage[(int) $stage['id']])) {
                    throw new RuntimeException('Future Approval Request stage contains decisions before activation.');
                }
            }
            if (! isset($candidatesByStage[$activeStageId][$actorId])) {
                throw new RuntimeException('Current user is not an immutable candidate of the active Approval Request stage.');
            }
            if (isset($decisionsByStage[$activeStageId][$actorId])) {
                throw new RuntimeException('Current user already recorded a Decision for this Approval Request stage with a different idempotency key.');
            }

            $decidedAt = gmdate('Y-m-d H:i:s');
            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$decisions}
                 (tenant_id, request_id, request_stage_id, user_id, action, decision_key_hash, comment, decided_at)
                 VALUES (%d, %d, %d, %d, %s, %s, %s, %s)",
                $tenantId,
                $requestId,
                $activeStageId,
                $actorId,
                $action,
                $decisionKeyHash,
                $comment,
                $decidedAt
            ));
            if ($inserted !== 1) {
                throw new RuntimeException('Unable to persist immutable Approval Decision.');
            }
            $decisionId = (int) ($wpdb->insert_id ?? 0);
            if ($decisionId <= 0) {
                throw new RuntimeException('Approval Decision persistence returned invalid identity.');
            }

            $stageDecisions = $decisionsByStage[$activeStageId] ?? [];
            $stageDecisions[$actorId] = $action;
            $stageCompleted = false;
            $newRequestStatus = ApprovalDecisionPolicy::REQUEST_STATUS_PENDING;
            if ($action === ApprovalDecisionPolicy::ACTION_REJECT) {
                $newRequestStatus = ApprovalDecisionPolicy::REQUEST_STATUS_REJECTED;
            } else {
                $stageCompleted = $this->stageCompleteFromMaps(
                    $activeStage,
                    count($candidatesByStage[$activeStageId] ?? []),
                    $stageDecisions
                );
                if ($stageCompleted && (int) ($activeStage['position_no'] ?? 0) === count($stageRows)) {
                    $newRequestStatus = ApprovalDecisionPolicy::REQUEST_STATUS_APPROVED;
                }
            }

            if ($newRequestStatus !== ApprovalDecisionPolicy::REQUEST_STATUS_PENDING) {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$requests} SET status = %s
                     WHERE tenant_id = %d AND id = %d AND status = %s",
                    $newRequestStatus,
                    $tenantId,
                    $requestId,
                    ApprovalDecisionPolicy::REQUEST_STATUS_PENDING
                ));
                if ($updated !== 1) {
                    throw new RuntimeException('Approval Request terminal status changed concurrently.');
                }
            }

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Approval Decision transaction.');
            }

            return [
                'decision' => [
                    'id' => $decisionId,
                    'request_id' => $requestId,
                    'request_stage_id' => $activeStageId,
                    'user_id' => $actorId,
                    'action' => $action,
                    'comment' => $comment,
                    'decided_at' => $decidedAt,
                ],
                'request_status' => $newRequestStatus,
                'stage_position' => (int) ($activeStage['position_no'] ?? 0),
                'stage_code' => (string) ($activeStage['stage_code_snapshot'] ?? ''),
                'stage_completed' => $stageCompleted,
                'request_completed' => $newRequestStatus !== ApprovalDecisionPolicy::REQUEST_STATUS_PENDING,
                'idempotent' => false,
            ];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    private function stageByIdLocked(object $wpdb, int $tenantId, int $requestId, int $stageId): array
    {
        $stages = $wpdb->prefix . 'safecontracts_workflow_approval_request_stages';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, position_no, stage_code_snapshot, decision_policy_snapshot, required_approvals_snapshot
             FROM {$stages}
             WHERE tenant_id = %d AND request_id = %d AND id = %d LIMIT 2 FOR UPDATE",
            $tenantId,
            $requestId,
            $stageId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Approval Decision references a missing request stage snapshot.');
        }
        return $rows[0];
    }

    /** @param array<string,mixed> $stage */
    private function stageIsCompleteLocked(object $wpdb, int $tenantId, int $requestId, array $stage): bool
    {
        $candidates = $wpdb->prefix . 'safecontracts_workflow_approval_request_candidates';
        $decisions = $wpdb->prefix . 'safecontracts_workflow_approval_decisions';
        $stageId = (int) ($stage['id'] ?? 0);
        $candidateRows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$candidates}
             WHERE tenant_id = %d AND request_id = %d AND request_stage_id = %d
             ORDER BY user_id ASC LIMIT %d FOR UPDATE",
            $tenantId,
            $requestId,
            $stageId,
            ApprovalRequestPolicy::MAX_CANDIDATES_PER_STAGE + 1
        ), ARRAY_A);
        $candidateRows = is_array($candidateRows) ? array_values(array_filter($candidateRows, 'is_array')) : [];
        if ($candidateRows === [] || count($candidateRows) > ApprovalRequestPolicy::MAX_CANDIDATES_PER_STAGE) {
            throw new RuntimeException('Approval Decision stage candidate snapshot is invalid.');
        }
        $candidateSet = [];
        foreach ($candidateRows as $candidate) {
            $candidateSet[(int) ($candidate['user_id'] ?? 0)] = true;
        }
        $decisionRows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, action FROM {$decisions}
             WHERE tenant_id = %d AND request_id = %d AND request_stage_id = %d
             ORDER BY user_id ASC LIMIT %d FOR UPDATE",
            $tenantId,
            $requestId,
            $stageId,
            ApprovalRequestPolicy::MAX_CANDIDATES_PER_STAGE + 1
        ), ARRAY_A);
        $decisionRows = is_array($decisionRows) ? array_values(array_filter($decisionRows, 'is_array')) : [];
        if (count($decisionRows) > ApprovalRequestPolicy::MAX_CANDIDATES_PER_STAGE) {
            throw new RuntimeException('Approval Decision stage history exceeds the supported bound.');
        }
        $stageDecisions = [];
        foreach ($decisionRows as $decision) {
            $userId = (int) ($decision['user_id'] ?? 0);
            $decisionAction = (string) ($decision['action'] ?? '');
            if (! isset($candidateSet[$userId]) || isset($stageDecisions[$userId])) {
                throw new RuntimeException('Approval Decision stage history is inconsistent.');
            }
            if ($decisionAction === ApprovalDecisionPolicy::ACTION_REJECT) {
                return false;
            }
            if ($decisionAction !== ApprovalDecisionPolicy::ACTION_APPROVE) {
                throw new RuntimeException('Approval Decision stage history contains unsupported action.');
            }
            $stageDecisions[$userId] = $decisionAction;
        }
        $this->assertStagePolicy($stage, count($candidateSet));
        return $this->stageCompleteFromMaps($stage, count($candidateSet), $stageDecisions);
    }

    /** @param array<string,mixed> $stage @param array<int,string> $stageDecisions */
    private function stageCompleteFromMaps(array $stage, int $candidateCount, array $stageDecisions): bool
    {
        $approvals = 0;
        foreach ($stageDecisions as $decisionAction) {
            if ($decisionAction === ApprovalDecisionPolicy::ACTION_REJECT) {
                return false;
            }
            if ($decisionAction === ApprovalDecisionPolicy::ACTION_APPROVE) {
                $approvals++;
            }
        }
        $policy = (string) ($stage['decision_policy_snapshot'] ?? '');
        if ($policy === ApprovalRoutePolicy::POLICY_ALL) {
            return $candidateCount > 0 && $approvals === $candidateCount;
        }
        return $approvals >= (int) ($stage['required_approvals_snapshot'] ?? 0);
    }

    /** @param array<string,mixed> $stage */
    private function assertStagePolicy(array $stage, int $candidateCount): void
    {
        $policy = (string) ($stage['decision_policy_snapshot'] ?? '');
        $required = (int) ($stage['required_approvals_snapshot'] ?? -1);
        if ($candidateCount < 1 || $candidateCount > ApprovalRequestPolicy::MAX_CANDIDATES_PER_STAGE) {
            throw new RuntimeException('Approval Request stage candidate count is outside supported bounds.');
        }
        if ($policy === ApprovalRoutePolicy::POLICY_ALL) {
            if ($required !== 0) {
                throw new RuntimeException('Approval Request all-policy stage has invalid threshold snapshot.');
            }
            return;
        }
        if ($policy === ApprovalRoutePolicy::POLICY_QUORUM && $required >= 1 && $required <= $candidateCount) {
            return;
        }
        throw new RuntimeException('Approval Request stage decision policy snapshot is invalid.');
    }

    /** @param array<string,mixed> $decision @return array<string,mixed> */
    private function publicDecision(array $decision): array
    {
        unset($decision['decision_key_hash']);
        return $decision;
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Approval Decision access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
