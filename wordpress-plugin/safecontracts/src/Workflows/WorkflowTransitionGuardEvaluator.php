<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use DomainException;
use RuntimeException;
use SafeContracts\CustomFields\CustomFieldValidationService;

final class WorkflowTransitionGuardEvaluator
{
    public function __construct(
        private ?WorkflowTransitionGuardRepository $guards = null,
        private ?CustomFieldValidationService $validation = null
    ) {
        $this->guards ??= new WorkflowTransitionGuardRepository();
        $this->validation ??= new CustomFieldValidationService();
    }

    /** @param array<string,mixed> $transition */
    public function assertAllowed(int $contractId, array $transition): void
    {
        $workflowId = (int) ($transition['workflow_id'] ?? 0);
        $versionId = (int) ($transition['workflow_version_id'] ?? 0);
        $transitionId = (int) ($transition['transition_id'] ?? 0);
        if ($contractId <= 0 || $workflowId <= 0 || $versionId <= 0 || $transitionId <= 0) {
            throw new RuntimeException('Workflow guard evaluation requires valid locked transition identity.');
        }
        $rows = $this->guards->listExecutionGuards($workflowId, $versionId, $transitionId);
        if (count($rows) > WorkflowTransitionGuardPolicy::MAX_GUARDS_PER_TRANSITION) {
            throw new RuntimeException('Stored Workflow transition guards exceed the supported bound.');
        }
        foreach ($rows as $row) {
            $guardType = (string) ($row['guard_type'] ?? '');
            WorkflowTransitionGuardPolicy::normalizeGuardTypes([$guardType]);
            $this->assertSnapshot($row, $transition);
            if ($guardType === WorkflowTransitionGuardPolicy::DYNAMIC_FIELDS_READY) {
                $result = $this->validation->validateContractForWorkflowTransition($contractId);
                if (($result['ready'] ?? false) !== true) {
                    throw new DomainException('Workflow transition is blocked because Dynamic Fields are not ready.');
                }
                continue;
            }
            throw new RuntimeException('Stored Workflow transition guard type is unsupported.');
        }
    }

    private function assertSnapshot(array $guard, array $transition): void
    {
        if ((int) ($guard['workflow_id'] ?? 0) !== (int) ($transition['workflow_id'] ?? 0)
            || (int) ($guard['workflow_version_id'] ?? 0) !== (int) ($transition['workflow_version_id'] ?? 0)
            || (int) ($guard['transition_id'] ?? 0) !== (int) ($transition['transition_id'] ?? 0)
            || (string) ($guard['transition_code_snapshot'] ?? '') !== (string) ($transition['transition_code'] ?? '')
            || (int) ($guard['source_state_id_snapshot'] ?? 0) !== (int) ($transition['source_state_id'] ?? 0)
            || (string) ($guard['source_state_code_snapshot'] ?? '') !== (string) ($transition['source_state_code'] ?? '')
            || (int) ($guard['destination_state_id_snapshot'] ?? 0) !== (int) ($transition['destination_state_id'] ?? 0)
            || (string) ($guard['destination_state_code_snapshot'] ?? '') !== (string) ($transition['destination_state_code'] ?? '')) {
            throw new RuntimeException('Workflow transition guard snapshot is stale or orphaned.');
        }
    }
}
