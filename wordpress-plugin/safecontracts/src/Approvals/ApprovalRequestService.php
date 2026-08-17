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
use SafeContracts\Workflows\ContractWorkflowInstanceRepository;
use SafeContracts\Workflows\WorkflowTransitionGuardEvaluator;

final class ApprovalRequestService
{
    public function __construct(
        private ?ContractWorkflowInstanceRepository $instances = null,
        private ?ApprovalRequestRepository $requests = null,
        private ?WorkflowTransitionGuardEvaluator $guards = null
    ) {
        $this->instances ??= new ContractWorkflowInstanceRepository();
        $this->requests ??= new ApprovalRequestRepository();
        $this->guards ??= new WorkflowTransitionGuardEvaluator();
    }

    /** @return list<array<string,mixed>> */
    public function listRequests(int $contractId, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->requests->listRequests($contractId, $limit, $offset);
    }

    /** @return array{approval_required:bool,request:?array<string,mixed>,idempotent:bool} */
    public function request(int $contractId, string $transitionCode, string $idempotencyKey): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ((int) ($contract['is_archived'] ?? 0) === 1) {
            throw new DomainException('Archived contracts cannot create Enterprise Workflow Approval Requests.');
        }

        $transitionCode = ApprovalRequestPolicy::normalizeTransitionCode($transitionCode);
        $idempotencyKey = ApprovalRequestPolicy::normalizeIdempotencyKey($idempotencyKey);
        $requestKeyHash = ApprovalRequestPolicy::requestKeyHash($idempotencyKey);

        $instance = $this->instances->findInstance($contractId);
        if ($instance === null || (int) ($instance['id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Contract has no current Enterprise Workflow instance.');
        }

        $actorId = get_current_user_id();
        $result = $this->requests->createRequest(
            $contractId,
            (int) $instance['id'],
            $transitionCode,
            $requestKeyHash,
            $actorId,
            function (array $transition) use ($contractId): void {
                $this->guards->assertAllowed($contractId, $transition);
            }
        );

        if ($result['created'] && is_array($result['request'])) {
            do_action(
                'safecontracts_enterprise_workflow_approval_requested',
                $contractId,
                (int) ($instance['id'] ?? 0),
                (int) ($result['request']['id'] ?? 0),
                $transitionCode,
                $actorId
            );
        }

        return [
            'approval_required' => $result['approval_required'],
            'request' => $result['request'],
            'idempotent' => $result['approval_required'] && ! $result['created'],
        ];
    }

    /** @return array<string,mixed> */
    private function requireContract(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        $contract = $this->instances->findContract($contractId);
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
            throw new RuntimeException('Enterprise Approval Request access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Approval Request operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
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
