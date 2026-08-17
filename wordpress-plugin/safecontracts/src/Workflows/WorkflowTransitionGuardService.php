<?php

declare(strict_types=1);

namespace SafeContracts\Workflows;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class WorkflowTransitionGuardService
{
    public function __construct(
        private ?WorkflowDefinitionRepository $workflows = null,
        private ?WorkflowTransitionGuardRepository $guards = null
    ) {
        $this->workflows ??= new WorkflowDefinitionRepository();
        $this->guards ??= new WorkflowTransitionGuardRepository();
    }

    /** @return list<array<string,mixed>> */
    public function listGuards(int $workflowId, int $versionId, int $transitionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireContext($workflowId, $versionId, $transitionId, false);
        $rows = $this->guards->listGuards($workflowId, $versionId, $transitionId);
        if (count($rows) > WorkflowTransitionGuardPolicy::MAX_GUARDS_PER_TRANSITION) {
            throw new RuntimeException('Stored Workflow transition guards exceed the supported bound.');
        }
        return $rows;
    }

    public function replaceDraftGuards(int $workflowId, int $versionId, int $transitionId, array $guardTypes): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requireContext($workflowId, $versionId, $transitionId, true);
        $guardTypes = WorkflowTransitionGuardPolicy::normalizeGuardTypes($guardTypes);
        $actorId = get_current_user_id();
        $this->guards->replaceDraftGuards($workflowId, $versionId, $transitionId, $guardTypes, $actorId);
        do_action('safecontracts_enterprise_workflow_transition_guards_replaced', $workflowId, $versionId, $transitionId, $guardTypes, $actorId);
    }

    private function requireContext(int $workflowId, int $versionId, int $transitionId, bool $requireDraft): array
    {
        foreach ([[$workflowId, 'Workflow ID'], [$versionId, 'Workflow version ID'], [$transitionId, 'Workflow transition ID']] as [$value, $label]) {
            if ($value <= 0) {
                throw new InvalidArgumentException("{$label} must be positive.");
            }
        }
        $workflow = $this->workflows->findWorkflow($workflowId);
        if ($workflow === null) {
            throw new InvalidArgumentException('Workflow was not found in the current tenant.');
        }
        if ($requireDraft && (string) ($workflow['status'] ?? '') !== WorkflowDefinitionPolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Workflow must be active for transition guard authoring.');
        }
        $version = $this->workflows->findVersion($workflowId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Workflow Version was not found in the current tenant/Workflow.');
        }
        if ($requireDraft && (string) ($version['version_status'] ?? '') !== WorkflowDefinitionPolicy::VERSION_DRAFT) {
            throw new InvalidArgumentException('Published Workflow transition guards are immutable.');
        }
        $transition = $this->guards->findTransition($workflowId, $versionId, $transitionId);
        if ($transition === null) {
            throw new InvalidArgumentException('Workflow Transition was not found in the current tenant/version.');
        }
        return ['workflow' => $workflow, 'version' => $version, 'transition' => $transition];
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Workflow transition guard access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Workflow transition guard operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }
}
