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

final class ContractWorkflowInstanceService
{
    public function __construct(private ?ContractWorkflowInstanceRepository $repository = null)
    {
        $this->repository ??= new ContractWorkflowInstanceRepository();
    }

    public function findForContract(int $contractId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->repository->findInstance($contractId);
    }

    public function initialize(int $contractId, int $workflowId, int $workflowVersionId): int
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $this->requirePositive($contractId, 'Contract ID');
        $this->requirePositive($workflowId, 'Workflow ID');
        $this->requirePositive($workflowVersionId, 'Workflow version ID');

        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ((int) ($contract['is_archived'] ?? 0) === 1) {
            throw new DomainException('Archived contracts cannot initialize an Enterprise Workflow instance.');
        }

        $binding = $this->repository->findBinding($contractId);
        if ($binding === null || (int) ($binding['contract_type_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Contract requires an existing current-tenant Contract Type binding before Workflow initialization.');
        }
        $contractTypeId = (int) $binding['contract_type_id'];

        $workflow = $this->repository->findWorkflow($workflowId);
        if ($workflow === null || (string) ($workflow['status'] ?? '') !== WorkflowDefinitionPolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Workflow must be an active current-tenant Enterprise Workflow.');
        }
        if ((int) ($workflow['contract_type_id'] ?? 0) !== $contractTypeId) {
            throw new InvalidArgumentException('Workflow Contract Type does not match the contract Contract Type binding.');
        }

        $version = $this->repository->findVersion($workflowId, $workflowVersionId);
        if ($version === null || (string) ($version['version_status'] ?? '') !== WorkflowDefinitionPolicy::VERSION_PUBLISHED) {
            throw new InvalidArgumentException('Workflow Version must be a published current-tenant version of the selected Workflow.');
        }
        if ((int) ($version['version_no'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Workflow Version has an invalid immutable version number.');
        }

        $initialStates = $this->repository->findInitialStates($workflowId, $workflowVersionId);
        if (count($initialStates) !== 1 || ! is_array($initialStates[0])) {
            throw new InvalidArgumentException('Published Workflow Version must expose exactly one initial state.');
        }
        $initialState = $initialStates[0];
        if ((int) ($initialState['id'] ?? 0) <= 0 || (string) ($initialState['state_code'] ?? '') === '') {
            throw new InvalidArgumentException('Published Workflow initial state identity is invalid.');
        }

        $existing = $this->repository->findInstance($contractId);
        if ($existing !== null) {
            if ($this->isExactInstance($existing, $contractTypeId, $workflow, $version, $initialState)) {
                return (int) ($existing['id'] ?? 0);
            }
            throw new DomainException('Contract already has a different Workflow instance and cannot be silently rebound.');
        }

        $actorId = get_current_user_id();
        $result = $this->repository->initialize($contractId, $workflowId, $workflowVersionId, $actorId);
        if ($result['created']) {
            do_action(
                'safecontracts_enterprise_contract_workflow_initialized',
                $contractId,
                $result['id'],
                $workflowId,
                $workflowVersionId,
                $actorId
            );
        }
        return $result['id'];
    }

    /** @return array<string,mixed> */
    private function requireContract(int $contractId): array
    {
        $this->requirePositive($contractId, 'Contract ID');
        $contract = $this->repository->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found in the current Enterprise tenant.');
        }
        return $contract;
    }

    /** @param array<string,mixed> $contract */
    private function assertScope(array $contract): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        $accountantUserId = $this->nullableInt($contract['accountant_user_id'] ?? null);
        if (current_user_can(Capabilities::VIEW_ASSIGNED)
            && $accountantUserId !== null
            && $accountantUserId === get_current_user_id()) {
            return;
        }
        throw new DomainException('Contract is outside the current user data scope.');
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Workflow access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract Workflow operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }

    private function requirePositive(int $value, string $label): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("{$label} must be positive.");
        }
    }

    /**
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $workflow
     * @param array<string,mixed> $version
     * @param array<string,mixed> $initialState
     */
    private function isExactInstance(array $existing, int $contractTypeId, array $workflow, array $version, array $initialState): bool
    {
        return (int) ($existing['id'] ?? 0) > 0
            && (int) ($existing['contract_type_id'] ?? 0) === $contractTypeId
            && (int) ($existing['workflow_id'] ?? 0) === (int) ($workflow['id'] ?? 0)
            && (int) ($existing['workflow_version_id'] ?? 0) === (int) ($version['id'] ?? 0)
            && (int) ($existing['workflow_version_no'] ?? 0) === (int) ($version['version_no'] ?? 0)
            && (string) ($existing['workflow_code_snapshot'] ?? '') === (string) ($workflow['workflow_code'] ?? '')
            && (int) ($existing['current_state_id'] ?? 0) === (int) ($initialState['id'] ?? 0)
            && (string) ($existing['current_state_code_snapshot'] ?? '') === (string) ($initialState['state_code'] ?? '');
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}
