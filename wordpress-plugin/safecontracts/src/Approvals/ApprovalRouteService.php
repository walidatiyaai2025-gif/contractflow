<?php

declare(strict_types=1);

namespace SafeContracts\Approvals;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Workflows\WorkflowDefinitionPolicy;
use SafeContracts\Workflows\WorkflowDefinitionRepository;

final class ApprovalRouteService
{
    public function __construct(
        private ?WorkflowDefinitionRepository $workflows = null,
        private ?ApprovalRouteRepository $routes = null
    ) {
        $this->workflows ??= new WorkflowDefinitionRepository();
        $this->routes ??= new ApprovalRouteRepository();
    }

    /** @return list<array<string,mixed>> */
    public function getRoute(int $workflowId, int $versionId, int $transitionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireContext($workflowId, $versionId, $transitionId, false);
        return $this->routes->getRoute($workflowId, $versionId, $transitionId);
    }

    /** @param list<mixed> $stages */
    public function replaceDraftRoute(int $workflowId, int $versionId, int $transitionId, array $stages): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requireContext($workflowId, $versionId, $transitionId, true);
        $normalized = ApprovalRoutePolicy::normalizeRoute($stages);
        $actorId = get_current_user_id();
        $this->routes->replaceDraftRoute($workflowId, $versionId, $transitionId, $normalized, $actorId);
        do_action(
            'safecontracts_enterprise_workflow_transition_approval_route_replaced',
            $workflowId,
            $versionId,
            $transitionId,
            count($normalized),
            $actorId
        );
    }

    /** @return array<string,mixed> */
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
            throw new InvalidArgumentException('Workflow must be active for Approval Route authoring.');
        }
        $version = $this->workflows->findVersion($workflowId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Workflow Version was not found in the current tenant/Workflow.');
        }
        if ($requireDraft && (string) ($version['version_status'] ?? '') !== WorkflowDefinitionPolicy::VERSION_DRAFT) {
            throw new InvalidArgumentException('Published Workflow Approval Routes are immutable.');
        }
        $transition = $this->routes->findTransition($workflowId, $versionId, $transitionId);
        if ($transition === null) {
            throw new InvalidArgumentException('Workflow Transition was not found in the current tenant/version.');
        }
        return ['workflow' => $workflow, 'version' => $version, 'transition' => $transition];
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Approval Route access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Approval Route operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }
}
