<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ApprovalRouteRepository
{
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
             WHERE t.id = %d AND t.workflow_id = %d AND t.workflow_version_id = %d AND t.tenant_id = %d
             LIMIT 1",
            $transitionId,
            $workflowId,
            $versionId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && count($rows) === 1 && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function getRoute(int $workflowId, int $versionId, int $transitionId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $routes = $wpdb->prefix . 'safecontracts_workflow_transition_approval_routes';
        $stages = $wpdb->prefix . 'safecontracts_workflow_transition_approval_stages';
        $selectors = $wpdb->prefix . 'safecontracts_workflow_transition_approval_selectors';

        $routeRows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, workflow_id, workflow_version_id, transition_id, transition_code_snapshot,
                    source_state_id_snapshot, source_state_code_snapshot,
                    destination_state_id_snapshot, destination_state_code_snapshot
             FROM {$routes}
             WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d AND transition_id = %d
             LIMIT 2",
            $tenantId,
            $workflowId,
            $versionId,
            $transitionId
        ), ARRAY_A);
        if (! is_array($routeRows) || $routeRows === []) {
            return [];
        }
        if (count($routeRows) !== 1 || ! is_array($routeRows[0])) {
            throw new RuntimeException('Approval Route identity is inconsistent.');
        }
        $route = $routeRows[0];
        $routeId = (int) ($route['id'] ?? 0);
        if ($routeId <= 0) {
            throw new RuntimeException('Approval Route returned invalid identity.');
        }

        $stageRows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, position_no, stage_code, name, decision_policy, required_approvals
             FROM {$stages}
             WHERE tenant_id = %d AND route_id = %d
             ORDER BY position_no ASC, id ASC LIMIT %d",
            $tenantId,
            $routeId,
            ApprovalRoutePolicy::MAX_STAGES + 1
        ), ARRAY_A);
        $stageRows = is_array($stageRows) ? array_values(array_filter($stageRows, 'is_array')) : [];
        if ($stageRows === [] || count($stageRows) > ApprovalRoutePolicy::MAX_STAGES) {
            throw new RuntimeException('Stored Approval Route stage structure is invalid or exceeds the supported bound.');
        }

        $result = [];
        $totalSelectors = 0;
        foreach ($stageRows as $stageRow) {
            $stageId = (int) ($stageRow['id'] ?? 0);
            if ($stageId <= 0) {
                throw new RuntimeException('Stored Approval Route stage returned invalid identity.');
            }
            $selectorRows = $wpdb->get_results($wpdb->prepare(
                "SELECT position_no, selector_type, selector_user_id, selector_role_code, selector_key
                 FROM {$selectors}
                 WHERE tenant_id = %d AND route_id = %d AND stage_id = %d
                 ORDER BY position_no ASC, id ASC LIMIT %d",
                $tenantId,
                $routeId,
                $stageId,
                ApprovalRoutePolicy::MAX_SELECTORS_PER_STAGE + 1
            ), ARRAY_A);
            $selectorRows = is_array($selectorRows) ? array_values(array_filter($selectorRows, 'is_array')) : [];
            if ($selectorRows === [] || count($selectorRows) > ApprovalRoutePolicy::MAX_SELECTORS_PER_STAGE) {
                throw new RuntimeException('Stored Approval Route selector structure is invalid or exceeds the supported bound.');
            }
            $totalSelectors += count($selectorRows);
            if ($totalSelectors > ApprovalRoutePolicy::MAX_SELECTORS_PER_ROUTE) {
                throw new RuntimeException('Stored Approval Route selector count exceeds the supported limit.');
            }
            $stageRow['selectors'] = $selectorRows;
            $result[] = $stageRow;
        }
        return $result;
    }

    /**
     * @param list<array<string,mixed>> $stagesInput
     */
    public function replaceDraftRoute(int $workflowId, int $versionId, int $transitionId, array $stagesInput, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $routes = $wpdb->prefix . 'safecontracts_workflow_transition_approval_routes';
        $stages = $wpdb->prefix . 'safecontracts_workflow_transition_approval_stages';
        $selectors = $wpdb->prefix . 'safecontracts_workflow_transition_approval_selectors';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Approval Route replacement transaction.');
        }
        try {
            $transition = $this->lockDraftTransition($wpdb, $tenantId, $workflowId, $versionId, $transitionId);
            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$routes}
                 WHERE tenant_id = %d AND workflow_id = %d AND workflow_version_id = %d AND transition_id = %d
                 LIMIT 2 FOR UPDATE",
                $tenantId,
                $workflowId,
                $versionId,
                $transitionId
            ), ARRAY_A);
            if (is_array($existingRows) && count($existingRows) > 1) {
                throw new RuntimeException('Approval Route identity is inconsistent.');
            }
            $existingRouteId = is_array($existingRows) && $existingRows !== [] && is_array($existingRows[0])
                ? (int) ($existingRows[0]['id'] ?? 0)
                : 0;

            // Lock all referenced tenant memberships in a canonical order before any destructive write.
            // This avoids opposite selector ordering creating cross-route lock inversions/deadlocks.
            $this->lockActiveTenantUsers($wpdb, $tenantId, $this->tenantUserIds($stagesInput));

            if ($existingRouteId > 0) {
                if ($wpdb->query($wpdb->prepare(
                    "DELETE FROM {$selectors} WHERE tenant_id = %d AND route_id = %d",
                    $tenantId,
                    $existingRouteId
                )) === false) {
                    throw new RuntimeException('Unable to replace Approval Route selectors.');
                }
                if ($wpdb->query($wpdb->prepare(
                    "DELETE FROM {$stages} WHERE tenant_id = %d AND route_id = %d",
                    $tenantId,
                    $existingRouteId
                )) === false) {
                    throw new RuntimeException('Unable to replace Approval Route stages.');
                }
                if ($wpdb->query($wpdb->prepare(
                    "DELETE FROM {$routes} WHERE tenant_id = %d AND id = %d",
                    $tenantId,
                    $existingRouteId
                )) !== 1) {
                    throw new RuntimeException('Unable to replace Approval Route identity.');
                }
            }

            if ($stagesInput === []) {
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit empty Approval Route replacement.');
                }
                return;
            }

            $routeSql = $wpdb->prepare(
                "INSERT INTO {$routes} (
                    tenant_id, workflow_id, workflow_version_id, transition_id, transition_code_snapshot,
                    source_state_id_snapshot, source_state_code_snapshot,
                    destination_state_id_snapshot, destination_state_code_snapshot,
                    created_by, updated_by, created_at, updated_at
                 ) VALUES (%d, %d, %d, %d, %s, %d, %s, %d, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                $tenantId,
                $workflowId,
                $versionId,
                $transitionId,
                (string) ($transition['transition_code'] ?? ''),
                (int) ($transition['source_state_id'] ?? 0),
                (string) ($transition['source_state_code'] ?? ''),
                (int) ($transition['destination_state_id'] ?? 0),
                (string) ($transition['destination_state_code'] ?? ''),
                $actorId,
                $actorId
            );
            if ($wpdb->query($routeSql) !== 1) {
                throw new RuntimeException('Unable to persist Approval Route.');
            }
            $routeId = (int) $wpdb->insert_id;
            if ($routeId <= 0) {
                throw new RuntimeException('Approval Route insert returned no identifier.');
            }

            foreach ($stagesInput as $stage) {
                $stageSql = $wpdb->prepare(
                    "INSERT INTO {$stages} (
                        tenant_id, route_id, position_no, stage_code, name, decision_policy, required_approvals,
                        created_by, updated_by, created_at, updated_at
                     ) VALUES (%d, %d, %d, %s, %s, %s, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                    $tenantId,
                    $routeId,
                    (int) ($stage['position_no'] ?? 0),
                    (string) ($stage['stage_code'] ?? ''),
                    (string) ($stage['name'] ?? ''),
                    (string) ($stage['decision_policy'] ?? ''),
                    (int) ($stage['required_approvals'] ?? 0),
                    $actorId,
                    $actorId
                );
                if ($wpdb->query($stageSql) !== 1) {
                    throw new RuntimeException('Unable to persist Approval Route stage.');
                }
                $stageId = (int) $wpdb->insert_id;
                if ($stageId <= 0) {
                    throw new RuntimeException('Approval Route stage insert returned no identifier.');
                }

                foreach ($stage['selectors'] ?? [] as $selector) {
                    $userId = $selector['selector_user_id'] ?? null;
                    $roleCode = $selector['selector_role_code'] ?? null;
                    $selectorSql = $wpdb->prepare(
                        "INSERT INTO {$selectors} (
                            tenant_id, route_id, stage_id, position_no, selector_type,
                            selector_user_id, selector_role_code, selector_key,
                            created_by, updated_by, created_at, updated_at
                         ) VALUES (%d, %d, %d, %d, %s, " . ($userId === null ? 'NULL' : '%d') . ", " . ($roleCode === null ? 'NULL' : '%s') . ", %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                        ...array_values(array_filter([
                            $tenantId,
                            $routeId,
                            $stageId,
                            (int) ($selector['position_no'] ?? 0),
                            (string) ($selector['selector_type'] ?? ''),
                            $userId === null ? null : (int) $userId,
                            $roleCode === null ? null : (string) $roleCode,
                            (string) ($selector['selector_key'] ?? ''),
                            $actorId,
                            $actorId,
                        ], static fn (mixed $value, int $key): bool => ! (($key === 5 && $userId === null) || ($key === 6 && $roleCode === null)), ARRAY_FILTER_USE_BOTH))
                    );
                    if ($wpdb->query($selectorSql) !== 1) {
                        throw new RuntimeException('Unable to persist Approval Route selector.');
                    }
                }
            }

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Approval Route replacement.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function assertVersionPublishable(int $workflowId, int $versionId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $routes = $wpdb->prefix . 'safecontracts_workflow_transition_approval_routes';
        $transitions = $wpdb->prefix . 'safecontracts_workflow_transitions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';
        $stages = $wpdb->prefix . 'safecontracts_workflow_transition_approval_stages';
        $selectors = $wpdb->prefix . 'safecontracts_workflow_transition_approval_selectors';

        $routeRows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.workflow_id, r.workflow_version_id, r.transition_id,
                    r.transition_code_snapshot, r.source_state_id_snapshot, r.source_state_code_snapshot,
                    r.destination_state_id_snapshot, r.destination_state_code_snapshot,
                    t.id AS current_transition_id, t.transition_code AS current_transition_code,
                    t.source_state_id AS current_source_state_id, s.state_code AS current_source_state_code,
                    t.destination_state_id AS current_destination_state_id, d.state_code AS current_destination_state_code
             FROM {$routes} r
             LEFT JOIN {$transitions} t ON t.id = r.transition_id AND t.tenant_id = r.tenant_id
                AND t.workflow_id = r.workflow_id AND t.workflow_version_id = r.workflow_version_id
             LEFT JOIN {$states} s ON s.id = t.source_state_id AND s.tenant_id = t.tenant_id AND s.workflow_id = t.workflow_id AND s.workflow_version_id = t.workflow_version_id
             LEFT JOIN {$states} d ON d.id = t.destination_state_id AND d.tenant_id = t.tenant_id AND d.workflow_id = t.workflow_id AND d.workflow_version_id = t.workflow_version_id
             WHERE r.tenant_id = %d AND r.workflow_id = %d AND r.workflow_version_id = %d
             ORDER BY r.transition_id ASC, r.id ASC LIMIT %d FOR UPDATE",
            $tenantId,
            $workflowId,
            $versionId,
            257
        ), ARRAY_A);
        $routeRows = is_array($routeRows) ? array_values(array_filter($routeRows, 'is_array')) : [];
        if (count($routeRows) > 256) {
            throw new RuntimeException('Stored Workflow approval route count exceeds the bounded Transition limit.');
        }

        /** @var array<int,true> $tenantUserIds */
        $tenantUserIds = [];
        foreach ($routeRows as $route) {
            $this->assertRouteSnapshot($route, $workflowId, $versionId);
            $routeId = (int) ($route['id'] ?? 0);
            $stageRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, position_no, stage_code, name, decision_policy, required_approvals
                 FROM {$stages}
                 WHERE tenant_id = %d AND route_id = %d
                 ORDER BY position_no ASC, id ASC LIMIT %d FOR UPDATE",
                $tenantId,
                $routeId,
                ApprovalRoutePolicy::MAX_STAGES + 1
            ), ARRAY_A);
            $stageRows = is_array($stageRows) ? array_values(array_filter($stageRows, 'is_array')) : [];
            if ($stageRows === [] || count($stageRows) > ApprovalRoutePolicy::MAX_STAGES) {
                throw new RuntimeException('Stored Approval Route stage structure is invalid or exceeds the supported bound.');
            }

            $policyInput = [];
            $totalSelectors = 0;
            foreach ($stageRows as $stageIndex => $stage) {
                if ((int) ($stage['position_no'] ?? 0) !== $stageIndex + 1) {
                    throw new RuntimeException('Stored Approval Route stage positions are not contiguous.');
                }
                $stageId = (int) ($stage['id'] ?? 0);
                $selectorRows = $wpdb->get_results($wpdb->prepare(
                    "SELECT position_no, selector_type, selector_user_id, selector_role_code, selector_key
                     FROM {$selectors}
                     WHERE tenant_id = %d AND route_id = %d AND stage_id = %d
                     ORDER BY position_no ASC, id ASC LIMIT %d FOR UPDATE",
                    $tenantId,
                    $routeId,
                    $stageId,
                    ApprovalRoutePolicy::MAX_SELECTORS_PER_STAGE + 1
                ), ARRAY_A);
                $selectorRows = is_array($selectorRows) ? array_values(array_filter($selectorRows, 'is_array')) : [];
                if ($selectorRows === [] || count($selectorRows) > ApprovalRoutePolicy::MAX_SELECTORS_PER_STAGE) {
                    throw new RuntimeException('Stored Approval Route selector structure is invalid or exceeds the supported bound.');
                }
                $totalSelectors += count($selectorRows);
                if ($totalSelectors > ApprovalRoutePolicy::MAX_SELECTORS_PER_ROUTE) {
                    throw new RuntimeException('Stored Approval Route selector count exceeds the supported route bound.');
                }

                $selectorsInput = [];
                foreach ($selectorRows as $selectorIndex => $selector) {
                    if ((int) ($selector['position_no'] ?? 0) !== $selectorIndex + 1) {
                        throw new RuntimeException('Stored Approval selector positions are not contiguous.');
                    }
                    $type = (string) ($selector['selector_type'] ?? '');
                    if ($type === ApprovalRoutePolicy::SELECTOR_TENANT_USER) {
                        $userId = (int) ($selector['selector_user_id'] ?? 0);
                        if ($userId <= 0
                            || (string) ($selector['selector_key'] ?? '') !== 'user:' . $userId
                            || $selector['selector_role_code'] !== null) {
                            throw new RuntimeException('Stored tenant_user Approval selector shape is invalid.');
                        }
                        $tenantUserIds[$userId] = true;
                        $selectorsInput[] = ['selector_type' => $type, 'user_id' => $userId];
                    } elseif ($type === ApprovalRoutePolicy::SELECTOR_TENANT_ROLE) {
                        $roleCode = (string) ($selector['selector_role_code'] ?? '');
                        if ((string) ($selector['selector_key'] ?? '') !== 'role:' . $roleCode || $selector['selector_user_id'] !== null) {
                            throw new RuntimeException('Stored tenant_role Approval selector shape is invalid.');
                        }
                        $selectorsInput[] = ['selector_type' => $type, 'role_code' => $roleCode];
                    } else {
                        throw new RuntimeException('Stored Approval selector type is unsupported.');
                    }
                }

                $policyInput[] = [
                    'stage_code' => (string) ($stage['stage_code'] ?? ''),
                    'name' => (string) ($stage['name'] ?? ''),
                    'decision_policy' => (string) ($stage['decision_policy'] ?? ''),
                    'required_approvals' => (int) ($stage['required_approvals'] ?? -1),
                    'selectors' => $selectorsInput,
                ];
            }
            try {
                ApprovalRoutePolicy::normalizeRoute($policyInput);
            } catch (\Throwable $error) {
                throw new RuntimeException('Stored Approval Route is malformed and cannot be published.', 0, $error);
            }
        }

        // Memberships are locked only after structural validation, but before publication can update
        // the Workflow Version. Canonical numeric order prevents cross-route lock inversion.
        $this->lockActiveTenantUsers($wpdb, $tenantId, array_keys($tenantUserIds));
    }

    /** @return array<string,mixed> */
    private function lockDraftTransition(object $wpdb, int $tenantId, int $workflowId, int $versionId, int $transitionId): array
    {
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $types = $wpdb->prefix . 'safecontracts_contract_types';
        $transitions = $wpdb->prefix . 'safecontracts_workflow_transitions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.transition_code, t.source_state_id, s.state_code AS source_state_code,
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
            $transitionId,
            $workflowId,
            $versionId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Workflow draft Transition changed concurrently or is no longer Approval Route authorable.');
        }
        return $rows[0];
    }

    /**
     * @param list<array<string,mixed>> $stagesInput
     * @return list<int>
     */
    private function tenantUserIds(array $stagesInput): array
    {
        /** @var array<int,true> $ids */
        $ids = [];
        foreach ($stagesInput as $stage) {
            foreach ($stage['selectors'] ?? [] as $selector) {
                if (($selector['selector_type'] ?? '') !== ApprovalRoutePolicy::SELECTOR_TENANT_USER) {
                    continue;
                }
                $userId = (int) ($selector['selector_user_id'] ?? 0);
                if ($userId <= 0) {
                    throw new RuntimeException('Approval tenant_user selector returned invalid user identity.');
                }
                $ids[$userId] = true;
            }
        }
        $userIds = array_keys($ids);
        sort($userIds, SORT_NUMERIC);
        return $userIds;
    }

    /** @param list<int> $userIds */
    private function lockActiveTenantUsers(object $wpdb, int $tenantId, array $userIds): void
    {
        $normalized = [];
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId <= 0) {
                throw new RuntimeException('Approval tenant_user selector returned invalid user identity.');
            }
            $normalized[$userId] = true;
        }
        $ordered = array_keys($normalized);
        sort($ordered, SORT_NUMERIC);
        foreach ($ordered as $userId) {
            $this->lockActiveTenantUser($wpdb, $tenantId, $userId);
        }
    }

    private function lockActiveTenantUser(object $wpdb, int $tenantId, int $userId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Approval tenant_user selector returned invalid user identity.');
        }
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id
             FROM {$memberships} m
             INNER JOIN {$tenants} t ON t.id = m.tenant_id
             WHERE m.tenant_id = %d AND m.user_id = %d AND m.status = 'active' AND t.status = 'active'
             LIMIT 1 FOR UPDATE",
            $tenantId,
            $userId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1) {
            throw new RuntimeException('Approval tenant_user selector must reference an active membership in the current tenant.');
        }
    }

    /** @param array<string,mixed> $route */
    private function assertRouteSnapshot(array $route, int $workflowId, int $versionId): void
    {
        if ((int) ($route['workflow_id'] ?? 0) !== $workflowId
            || (int) ($route['workflow_version_id'] ?? 0) !== $versionId
            || (int) ($route['current_transition_id'] ?? 0) <= 0
            || (int) ($route['transition_id'] ?? 0) !== (int) ($route['current_transition_id'] ?? 0)
            || (string) ($route['transition_code_snapshot'] ?? '') !== (string) ($route['current_transition_code'] ?? '')
            || (int) ($route['source_state_id_snapshot'] ?? 0) !== (int) ($route['current_source_state_id'] ?? 0)
            || (string) ($route['source_state_code_snapshot'] ?? '') !== (string) ($route['current_source_state_code'] ?? '')
            || (int) ($route['destination_state_id_snapshot'] ?? 0) !== (int) ($route['current_destination_state_id'] ?? 0)
            || (string) ($route['destination_state_code_snapshot'] ?? '') !== (string) ($route['current_destination_state_code'] ?? '')) {
            throw new RuntimeException('Stored Approval Route Transition snapshot is stale or orphaned.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Approval Route access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
