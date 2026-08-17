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

final class ApprovalDecisionService
{
    public function __construct(
        private ?ApprovalDecisionRepository $decisions = null,
        private ?ContractWorkflowInstanceRepository $instances = null
    ) {
        $this->decisions ??= new ApprovalDecisionRepository();
        $this->instances ??= new ContractWorkflowInstanceRepository();
    }

    /** @return list<array<string,mixed>> */
    public function listDecisions(int $requestId, int $limit = 100, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $request = $this->requireRequest($requestId);
        $contract = $this->requireContract((int) ($request['contract_id'] ?? 0));
        $this->assertScope($contract);
        return $this->decisions->listDecisions($requestId, $limit, $offset);
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
    public function decide(
        int $requestId,
        string $action,
        string $idempotencyKey,
        ?string $comment = null
    ): array {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $request = $this->requireRequest($requestId);
        $contract = $this->requireContract((int) ($request['contract_id'] ?? 0));
        $this->assertScope($contract);
        if ((int) ($contract['is_archived'] ?? 0) === 1) {
            throw new DomainException('Archived contracts cannot accept Enterprise Approval Decisions.');
        }

        $action = ApprovalDecisionPolicy::normalizeAction($action);
        $idempotencyKey = ApprovalDecisionPolicy::normalizeIdempotencyKey($idempotencyKey);
        $decisionKeyHash = ApprovalDecisionPolicy::decisionKeyHash($idempotencyKey);
        $comment = ApprovalDecisionPolicy::normalizeComment($comment);
        $actorId = get_current_user_id();

        $result = $this->decisions->recordDecision(
            $requestId,
            $actorId,
            $action,
            $decisionKeyHash,
            $comment
        );

        if (! $result['idempotent']) {
            do_action(
                'safecontracts_enterprise_workflow_approval_decided',
                (int) ($request['contract_id'] ?? 0),
                $requestId,
                (int) ($result['decision']['request_stage_id'] ?? 0),
                (int) ($result['decision']['id'] ?? 0),
                $action,
                $actorId,
                $result['request_status']
            );
        }

        return $result;
    }

    /** @return array<string,mixed> */
    private function requireRequest(int $requestId): array
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException('Approval Request ID must be positive.');
        }
        $request = $this->decisions->findRequest($requestId);
        if ($request === null) {
            throw new InvalidArgumentException('Approval Request was not found in the current Enterprise tenant.');
        }
        return $request;
    }

    /** @return array<string,mixed> */
    private function requireContract(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Approval Request has invalid contract identity.');
        }
        $contract = $this->instances->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Approval Request contract was not found in the current Enterprise tenant.');
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
        throw new DomainException('Approval Request contract is outside the current user data scope.');
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Approval Decision access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Approval Decision operation.');
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
