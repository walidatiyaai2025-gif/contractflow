<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ApprovalRequestRepository
{
    private const PUBLIC_REQUEST_COLUMNS = 'id, instance_id, contract_id, workflow_id, workflow_version_id, transition_id, transition_code_snapshot, from_state_id, from_state_code_snapshot, to_state_id, to_state_code_snapshot, route_id_snapshot, status, requester_user_id, requested_at';
    private const INTERNAL_REQUEST_COLUMNS = 'id, instance_id, contract_id, workflow_id, workflow_version_id, transition_id, transition_code_snapshot, from_state_id, from_state_code_snapshot, to_state_id, to_state_code_snapshot, route_id_snapshot, request_key_hash, status, requester_user_id, requested_at';

    /** @return list<array<string,mixed>> */
    public function listRequests(int $contractId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $requests = $wpdb->prefix . 'safecontracts_workflow_approval_requests';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::PUBLIC_REQUEST_COLUMNS . " FROM {$requests}
             WHERE tenant_id = %d AND contract_id = %d
             ORDER BY requested_at DESC, id DESC LIMIT %d OFFSET %d",
            $tenantId,
            $contractId,
            $limit,
            $offset
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @param callable(array<string,mixed>):void|null $beforePersist
     * @return array{approval_required:bool,request:?array<string,mixed>,created:bool}
     */
    public function createRequest(
        int $contractId,
        int $instanceId,
        string $transitionCode,
        string $requestKeyHash,
        int $actorId,
        ?callable $beforePersist = null
    ): array {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $instances = $wpdb->prefix . 'safecontracts_contract_workflow_instances';
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $transitions = $wpdb->prefix . 'safecontracts_workflow_transitions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';
        $routes = $wpdb->prefix . 'safecontracts_workflow_transition_approval_routes';
        $routeStages = $wpdb->prefix . 'safecontracts_workflow_transition_approval_stages';
        $routeSelectors = $wpdb->prefix . 'safecontracts_workflow_transition_approval_selectors';
        $requests = $wpdb->prefix . 'safecontracts_workflow_approval_requests';
        $requestStages = $wpdb->prefix . 'safecontracts_workflow_approval_request_stages';
        $requestSelectors = $wpdb->prefix . 'safecontracts_workflow_approval_request_selectors';
        $requestCandidates = $wpdb->prefix . 'safecontracts_workflow_approval_request_candidates';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Workflow Approval Request transaction.');
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
                throw new RuntimeException('Contract Workflow instance changed concurrently or is no longer approval-requestable.');
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
                "SELECT " . self::INTERNAL_REQUEST_COLUMNS . " FROM {$requests}
                 WHERE tenant_id = %d AND instance_id = %d AND request_key_hash = %s
                 LIMIT 1 FOR UPDATE",
                $tenantId,
                $instanceId,
                $requestKeyHash
            ), ARRAY_A);
            if (is_array($existingRows) && $existingRows !== []) {
                if (count($existingRows) !== 1 || ! is_array($existingRows[0])) {
                    throw new RuntimeException('Approval Request idempotency identity is inconsistent.');
                }
                $existing = $existingRows[0];
                if ((int) ($existing['contract_id'] ?? 0) !== $contractId
                    || (int) ($existing['instance_id'] ?? 0) !== $instanceId
                    || (string) ($existing['transition_code_snapshot'] ?? '') !== $transitionCode) {
                    throw new RuntimeException('Approval Request idempotency key was already used for a different operation.');
                }
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit idempotent Approval Request retry.');
                }
                return ['approval_required' => true, 'request' => $this->publicRequest($existing), 'created' => false];
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
                 WHERE t.tenant_id = %d AND t.workflow_id = %d AND t.workflow_version_id = %d
                   AND t.transition_code = %s AND t.source_state_id = %d AND v.version_status = 'published'
                 ORDER BY t.id ASC LIMIT 2 FOR UPDATE",
                $tenantId,
                $workflowId,
                $workflowVersionId,
                $transitionCode,
                $fromStateId
            ), ARRAY_A);
            if (! is_array($transitionRows) || count($transitionRows) !== 1 || ! is_array($transitionRows[0])) {
                throw new RuntimeException('No approval-requestable Transition exists from the locked current Workflow state.');
            }
            $transition = $transitionRows[0];
            if ((int) ($transition['source_state_id'] ?? 0) !== $fromStateId
                || (string) ($transition['source_state_code'] ?? '') !== $fromStateCode) {
                throw new RuntimeException('Approval Request Transition source does not match the locked current Workflow state.');
            }
            $transitionId = (int) ($transition['transition_id'] ?? 0);
            $toStateId = (int) ($transition['destination_state_id'] ?? 0);
            $toStateCode = (string) ($transition['destination_state_code'] ?? '');
            if ($transitionId <= 0 || $toStateId <= 0 || $toStateCode === '') {
                throw new RuntimeException('Approval Request Transition returned invalid destination identity.');
            }

            $pendingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, request_key_hash FROM {$requests}
                 WHERE tenant_id = %d AND instance_id = %d AND status = %s
                   AND transition_id = %d AND from_state_id = %d
                 ORDER BY id ASC LIMIT 2 FOR UPDATE",
                $tenantId,
                $instanceId,
                ApprovalRequestPolicy::STATUS_PENDING,
                $transitionId,
                $fromStateId
            ), ARRAY_A);
            if (is_array($pendingRows) && $pendingRows !== []) {
                throw new RuntimeException('A different pending Approval Request already exists for this Workflow Transition and source state.');
            }

            $routeRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, workflow_id, workflow_version_id, transition_id, transition_code_snapshot,
                        source_state_id_snapshot, source_state_code_snapshot,
                        destination_state_id_snapshot, destination_state_code_snapshot
                 FROM {$routes}
                 WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d AND transition_id = %d
                 LIMIT 2 FOR UPDATE",
                $tenantId,
                $workflowId,
                $workflowVersionId,
                $transitionId
            ), ARRAY_A);
            if (! is_array($routeRows) || $routeRows === []) {
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit Approval-not-required result.');
                }
                return ['approval_required' => false, 'request' => null, 'created' => false];
            }
            if (count($routeRows) !== 1 || ! is_array($routeRows[0])) {
                throw new RuntimeException('Published Approval Route identity is inconsistent.');
            }
            $route = $routeRows[0];
            $routeId = (int) ($route['id'] ?? 0);
            $this->assertRouteSnapshot($route, $transition, $workflowId, $workflowVersionId);

            $stageRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, position_no, stage_code, name, decision_policy, required_approvals
                 FROM {$routeStages}
                 WHERE tenant_id = %d AND route_id = %d
                 ORDER BY position_no ASC, id ASC LIMIT %d FOR UPDATE",
                $tenantId,
                $routeId,
                ApprovalRoutePolicy::MAX_STAGES + 1
            ), ARRAY_A);
            $stageRows = is_array($stageRows) ? array_values(array_filter($stageRows, 'is_array')) : [];
            if ($stageRows === [] || count($stageRows) > ApprovalRoutePolicy::MAX_STAGES) {
                throw new RuntimeException('Published Approval Route stage structure is invalid or exceeds the supported bound.');
            }

            $policyInput = [];
            $stageSelectors = [];
            $explicitUserIds = [];
            $roleCodes = [];
            $totalSelectors = 0;
            foreach ($stageRows as $stageIndex => $stage) {
                if ((int) ($stage['position_no'] ?? 0) !== $stageIndex + 1) {
                    throw new RuntimeException('Published Approval Route stage positions are not contiguous.');
                }
                $stageId = (int) ($stage['id'] ?? 0);
                if ($stageId <= 0) {
                    throw new RuntimeException('Published Approval Route stage returned invalid identity.');
                }
                $selectorRows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, position_no, selector_type, selector_user_id, selector_role_code, selector_key
                     FROM {$routeSelectors}
                     WHERE tenant_id = %d AND route_id = %d AND stage_id = %d
                     ORDER BY position_no ASC, id ASC LIMIT %d FOR UPDATE",
                    $tenantId,
                    $routeId,
                    $stageId,
                    ApprovalRoutePolicy::MAX_SELECTORS_PER_STAGE + 1
                ), ARRAY_A);
                $selectorRows = is_array($selectorRows) ? array_values(array_filter($selectorRows, 'is_array')) : [];
                if ($selectorRows === [] || count($selectorRows) > ApprovalRoutePolicy::MAX_SELECTORS_PER_STAGE) {
                    throw new RuntimeException('Published Approval Route selector structure is invalid or exceeds the supported bound.');
                }
                $totalSelectors += count($selectorRows);
                if ($totalSelectors > ApprovalRoutePolicy::MAX_SELECTORS_PER_ROUTE) {
                    throw new RuntimeException('Published Approval Route selector count exceeds the supported route bound.');
                }

                $selectorsInput = [];
                foreach ($selectorRows as $selectorIndex => $selector) {
                    if ((int) ($selector['position_no'] ?? 0) !== $selectorIndex + 1) {
                        throw new RuntimeException('Published Approval selector positions are not contiguous.');
                    }
                    $type = (string) ($selector['selector_type'] ?? '');
                    if ($type === ApprovalRoutePolicy::SELECTOR_TENANT_USER) {
                        $userId = (int) ($selector['selector_user_id'] ?? 0);
                        if ($userId <= 0
                            || ($selector['selector_role_code'] ?? null) !== null
                            || (string) ($selector['selector_key'] ?? '') !== 'user:' . $userId) {
                            throw new RuntimeException('Published tenant_user Approval selector shape is invalid.');
                        }
                        $explicitUserIds[$userId] = true;
                        $selectorsInput[] = ['selector_type' => $type, 'user_id' => $userId];
                    } elseif ($type === ApprovalRoutePolicy::SELECTOR_TENANT_ROLE) {
                        $roleCode = (string) ($selector['selector_role_code'] ?? '');
                        if ($roleCode === ''
                            || ($selector['selector_user_id'] ?? null) !== null
                            || (string) ($selector['selector_key'] ?? '') !== 'role:' . $roleCode) {
                            throw new RuntimeException('Published tenant_role Approval selector shape is invalid.');
                        }
                        $roleCodes[$roleCode] = true;
                        $selectorsInput[] = ['selector_type' => $type, 'role_code' => $roleCode];
                    } else {
                        throw new RuntimeException('Published Approval selector type is unsupported.');
                    }
                }
                $policyInput[] = [
                    'stage_code' => (string) ($stage['stage_code'] ?? ''),
                    'name' => (string) ($stage['name'] ?? ''),
                    'decision_policy' => (string) ($stage['decision_policy'] ?? ''),
                    'required_approvals' => (int) ($stage['required_approvals'] ?? -1),
                    'selectors' => $selectorsInput,
                ];
                $stageSelectors[$stageId] = $selectorRows;
            }

            try {
                ApprovalRoutePolicy::normalizeRoute($policyInput);
            } catch (\Throwable $error) {
                throw new RuntimeException('Published Approval Route is malformed and cannot create a runtime request.', 0, $error);
            }

            $membershipRows = $this->lockCandidateMemberships(
                $wpdb,
                $tenantId,
                array_map('intval', array_keys($explicitUserIds)),
                array_map('strval', array_keys($roleCodes))
            );
            $membersByUser = [];
            $usersByRole = [];
            foreach ($membershipRows as $membership) {
                $userId = (int) ($membership['user_id'] ?? 0);
                $roleCode = (string) ($membership['role_code'] ?? '');
                if ($userId <= 0 || $roleCode === '') {
                    throw new RuntimeException('Approval candidate membership returned invalid identity.');
                }
                $membersByUser[$userId] = true;
                $usersByRole[$roleCode][$userId] = true;
            }
            foreach (array_keys($explicitUserIds) as $userId) {
                if (! isset($membersByUser[(int) $userId])) {
                    throw new RuntimeException('Approval tenant_user selector is no longer an active membership in the current tenant.');
                }
            }

            $stageCandidates = [];
            $totalCandidates = 0;
            foreach ($stageRows as $stage) {
                $stageId = (int) ($stage['id'] ?? 0);
                $candidateSet = [];
                foreach ($stageSelectors[$stageId] ?? [] as $selector) {
                    $type = (string) ($selector['selector_type'] ?? '');
                    if ($type === ApprovalRoutePolicy::SELECTOR_TENANT_USER) {
                        $candidateSet[(int) ($selector['selector_user_id'] ?? 0)] = true;
                    } else {
                        $roleCode = (string) ($selector['selector_role_code'] ?? '');
                        foreach (array_keys($usersByRole[$roleCode] ?? []) as $userId) {
                            $candidateSet[(int) $userId] = true;
                        }
                    }
                }
                unset($candidateSet[0]);
                $candidateIds = array_map('intval', array_keys($candidateSet));
                sort($candidateIds, SORT_NUMERIC);
                if ($candidateIds === []) {
                    throw new RuntimeException('Approval Route stage resolves to no active approver candidates.');
                }
                if (count($candidateIds) > ApprovalRequestPolicy::MAX_CANDIDATES_PER_STAGE) {
                    throw new RuntimeException('Approval Route stage candidate count exceeds the supported bound.');
                }
                if ((string) ($stage['decision_policy'] ?? '') === ApprovalRoutePolicy::POLICY_QUORUM
                    && (int) ($stage['required_approvals'] ?? 0) > count($candidateIds)) {
                    throw new RuntimeException('Approval Route quorum exceeds the distinct resolved candidate count.');
                }
                $totalCandidates += count($candidateIds);
                if ($totalCandidates > ApprovalRequestPolicy::MAX_CANDIDATES_PER_REQUEST) {
                    throw new RuntimeException('Approval Request candidate count exceeds the supported request bound.');
                }
                $stageCandidates[$stageId] = $candidateIds;
            }

            if ($beforePersist !== null) {
                $beforePersist($transition);
            }

            $requestSql = $wpdb->prepare(
                "INSERT INTO {$requests} (
                    tenant_id, instance_id, contract_id, workflow_id, workflow_version_id,
                    transition_id, transition_code_snapshot,
                    from_state_id, from_state_code_snapshot, to_state_id, to_state_code_snapshot,
                    route_id_snapshot, request_key_hash, status, requester_user_id, requested_at
                 ) VALUES (%d, %d, %d, %d, %d, %d, %s, %d, %s, %d, %s, %d, %s, %s, %d, UTC_TIMESTAMP())",
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
                $routeId,
                $requestKeyHash,
                ApprovalRequestPolicy::STATUS_PENDING,
                $actorId
            );
            if ($wpdb->query($requestSql) !== 1) {
                throw new RuntimeException('Unable to persist immutable Approval Request.');
            }
            $requestId = (int) $wpdb->insert_id;
            if ($requestId <= 0) {
                throw new RuntimeException('Approval Request insert returned no identifier.');
            }

            foreach ($stageRows as $stage) {
                $routeStageId = (int) ($stage['id'] ?? 0);
                $stageSql = $wpdb->prepare(
                    "INSERT INTO {$requestStages} (
                        tenant_id, request_id, route_stage_id_snapshot, position_no,
                        stage_code_snapshot, name_snapshot, decision_policy_snapshot, required_approvals_snapshot
                     ) VALUES (%d, %d, %d, %d, %s, %s, %s, %d)",
                    $tenantId,
                    $requestId,
                    $routeStageId,
                    (int) ($stage['position_no'] ?? 0),
                    (string) ($stage['stage_code'] ?? ''),
                    (string) ($stage['name'] ?? ''),
                    (string) ($stage['decision_policy'] ?? ''),
                    (int) ($stage['required_approvals'] ?? 0)
                );
                if ($wpdb->query($stageSql) !== 1) {
                    throw new RuntimeException('Unable to persist Approval Request stage snapshot.');
                }
                $requestStageId = (int) $wpdb->insert_id;
                if ($requestStageId <= 0) {
                    throw new RuntimeException('Approval Request stage insert returned no identifier.');
                }

                foreach ($stageSelectors[$routeStageId] ?? [] as $selector) {
                    $userId = $selector['selector_user_id'] ?? null;
                    $roleCode = $selector['selector_role_code'] ?? null;
                    $values = [$tenantId, $requestId, $requestStageId, (int) ($selector['id'] ?? 0), (int) ($selector['position_no'] ?? 0), (string) ($selector['selector_type'] ?? '')];
                    $userSql = 'NULL';
                    if ($userId !== null) {
                        $userSql = '%d';
                        $values[] = (int) $userId;
                    }
                    $roleSql = 'NULL';
                    if ($roleCode !== null) {
                        $roleSql = '%s';
                        $values[] = (string) $roleCode;
                    }
                    $values[] = (string) ($selector['selector_key'] ?? '');
                    $selectorSql = $wpdb->prepare(
                        "INSERT INTO {$requestSelectors} (
                            tenant_id, request_id, request_stage_id, route_selector_id_snapshot,
                            position_no, selector_type_snapshot, selector_user_id_snapshot,
                            selector_role_code_snapshot, selector_key_snapshot
                         ) VALUES (%d, %d, %d, %d, %d, %s, {$userSql}, {$roleSql}, %s)",
                        ...$values
                    );
                    if ($wpdb->query($selectorSql) !== 1) {
                        throw new RuntimeException('Unable to persist Approval Request selector snapshot.');
                    }
                }

                foreach ($stageCandidates[$routeStageId] ?? [] as $candidateUserId) {
                    $candidateSql = $wpdb->prepare(
                        "INSERT INTO {$requestCandidates} (tenant_id, request_id, request_stage_id, user_id)
                         VALUES (%d, %d, %d, %d)",
                        $tenantId,
                        $requestId,
                        $requestStageId,
                        $candidateUserId
                    );
                    if ($wpdb->query($candidateSql) !== 1) {
                        throw new RuntimeException('Unable to persist Approval Request candidate snapshot.');
                    }
                }
            }

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Workflow Approval Request transaction.');
            }

            return [
                'approval_required' => true,
                'created' => true,
                'request' => [
                    'id' => $requestId,
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
                    'route_id_snapshot' => $routeId,
                    'status' => ApprovalRequestPolicy::STATUS_PENDING,
                    'requester_user_id' => $actorId,
                    'requested_at' => null,
                ],
            ];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    private function publicRequest(array $request): array
    {
        unset($request['request_key_hash']);
        return $request;
    }

    /** @return list<array<string,mixed>> */
    private function lockCandidateMemberships(object $wpdb, int $tenantId, array $explicitUserIds, array $roleCodes): array
    {
        $explicitUserIds = array_values(array_unique(array_filter(array_map('intval', $explicitUserIds), static fn (int $id): bool => $id > 0)));
        sort($explicitUserIds, SORT_NUMERIC);
        $roleCodes = array_values(array_unique(array_filter(array_map(static fn (mixed $role): string => trim((string) $role), $roleCodes), static fn (string $role): bool => $role !== '')));
        sort($roleCodes, SORT_STRING);
        if ($explicitUserIds === [] && $roleCodes === []) {
            throw new RuntimeException('Approval Route has no resolvable selector identities.');
        }

        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $conditions = [];
        $args = [$tenantId];
        if ($explicitUserIds !== []) {
            $conditions[] = 'm.user_id IN (' . implode(', ', array_fill(0, count($explicitUserIds), '%d')) . ')';
            array_push($args, ...$explicitUserIds);
        }
        if ($roleCodes !== []) {
            $conditions[] = 'm.role_code IN (' . implode(', ', array_fill(0, count($roleCodes), '%s')) . ')';
            array_push($args, ...$roleCodes);
        }
        $args[] = ApprovalRequestPolicy::MAX_CANDIDATES_PER_REQUEST + 1;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.user_id, m.role_code
             FROM {$memberships} m
             INNER JOIN {$tenants} t ON t.id = m.tenant_id
             WHERE m.tenant_id = %d AND m.status = 'active' AND t.status = 'active'
               AND (" . implode(' OR ', $conditions) . ")
             ORDER BY m.user_id ASC LIMIT %d FOR UPDATE",
            ...$args
        ), ARRAY_A);
        $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        if (count($rows) > ApprovalRequestPolicy::MAX_CANDIDATES_PER_REQUEST) {
            throw new RuntimeException('Approval candidate membership resolution exceeds the supported request bound.');
        }
        return $rows;
    }

    /** @param array<string,mixed> $route @param array<string,mixed> $transition */
    private function assertRouteSnapshot(array $route, array $transition, int $workflowId, int $workflowVersionId): void
    {
        if ((int) ($route['id'] ?? 0) <= 0
            || (int) ($route['workflow_id'] ?? 0) !== $workflowId
            || (int) ($route['workflow_version_id'] ?? 0) !== $workflowVersionId
            || (int) ($route['transition_id'] ?? 0) !== (int) ($transition['transition_id'] ?? 0)
            || (string) ($route['transition_code_snapshot'] ?? '') !== (string) ($transition['transition_code'] ?? '')
            || (int) ($route['source_state_id_snapshot'] ?? 0) !== (int) ($transition['source_state_id'] ?? 0)
            || (string) ($route['source_state_code_snapshot'] ?? '') !== (string) ($transition['source_state_code'] ?? '')
            || (int) ($route['destination_state_id_snapshot'] ?? 0) !== (int) ($transition['destination_state_id'] ?? 0)
            || (string) ($route['destination_state_code_snapshot'] ?? '') !== (string) ($transition['destination_state_code'] ?? '')) {
            throw new RuntimeException('Published Approval Route Transition snapshot is stale or orphaned.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Approval Request access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
