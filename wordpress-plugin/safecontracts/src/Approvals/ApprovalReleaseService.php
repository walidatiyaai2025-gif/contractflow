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
use SafeContracts\Workflows\ContractWorkflowTransitionRepository;
use SafeContracts\Workflows\WorkflowTransitionGuardEvaluator;

final class ApprovalReleaseService
{
    public function __construct(
        private ?ApprovalReleaseRepository $releases = null,
        private ?ContractWorkflowInstanceRepository $instances = null,
        private ?ContractWorkflowTransitionRepository $transitions = null,
        private ?WorkflowTransitionGuardEvaluator $guards = null
    ) {
        $this->releases ??= new ApprovalReleaseRepository();
        $this->instances ??= new ContractWorkflowInstanceRepository();
        $this->transitions ??= new ContractWorkflowTransitionRepository();
        $this->guards ??= new WorkflowTransitionGuardEvaluator();
    }

    /** @return array{release:array<string,mixed>,history:array<string,mixed>}|null */
    public function getRelease(int $requestId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $request = $this->requireRequest($requestId);
        $contract = $this->requireContract((int) ($request['contract_id'] ?? 0));
        $this->assertScope($contract);
        return $this->releases->findReleaseResult($requestId);
    }

    /** @return array{release:array<string,mixed>,history:array<string,mixed>,idempotent:bool} */
    public function release(int $requestId, string $idempotencyKey): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $request = $this->requireRequest($requestId);
        $contractId = (int) ($request['contract_id'] ?? 0);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);

        $idempotencyKey = ApprovalReleasePolicy::normalizeIdempotencyKey($idempotencyKey);
        $releaseKeyHash = ApprovalReleasePolicy::releaseKeyHash($idempotencyKey);
        $transitionRequestKeyHash = ApprovalReleasePolicy::transitionRequestKeyHash($idempotencyKey);

        // Preserve exact retry semantics after later mutable contract/request lifecycle changes.
        // Authorization and contract data scope are still enforced above; this path performs no new mutation.
        $existing = $this->releases->findReleaseResult($requestId, $releaseKeyHash);
        if ($existing !== null) {
            return [
                'release' => $existing['release'],
                'history' => $existing['history'],
                'idempotent' => true,
            ];
        }

        if ((int) ($contract['is_archived'] ?? 0) === 1) {
            throw new DomainException('Archived contracts cannot release Enterprise Approval Requests.');
        }
        if ((string) ($request['status'] ?? '') !== ApprovalDecisionPolicy::REQUEST_STATUS_APPROVED) {
            throw new DomainException('Only an approved Approval Request can release a Workflow transition.');
        }
        $instanceId = (int) ($request['instance_id'] ?? 0);
        $transitionCode = (string) ($request['transition_code_snapshot'] ?? '');
        if ($instanceId <= 0 || $transitionCode === '') {
            throw new RuntimeException('Approved Approval Request has invalid P6 runtime identity.');
        }

        $actorId = get_current_user_id();
        $lockedRequest = null;
        $releaseRow = null;

        $transitionResult = $this->transitions->execute(
            $contractId,
            $instanceId,
            $transitionCode,
            $transitionRequestKeyHash,
            $actorId,
            function (array $transition) use (&$lockedRequest, $contractId): void {
                if (! is_array($lockedRequest)) {
                    throw new RuntimeException('Approval Release request was not locked before Transition validation.');
                }
                $this->releases->assertTransitionMatchesRequest($lockedRequest, $transition);
                $this->guards->assertAllowed($contractId, $transition);
            },
            function (array $instance) use (&$lockedRequest, $requestId, $releaseKeyHash): void {
                $lockedRequest = $this->releases->lockApprovedRequestForRelease(
                    $requestId,
                    $releaseKeyHash,
                    $instance
                );
            },
            function (array $history) use (&$lockedRequest, &$releaseRow, $requestId, $releaseKeyHash, $actorId): void {
                if (! is_array($lockedRequest)) {
                    throw new RuntimeException('Approval Release request lock was lost before evidence persistence.');
                }
                $releaseRow = $this->releases->insertRelease(
                    $requestId,
                    (int) ($lockedRequest['instance_id'] ?? 0),
                    (int) ($history['id'] ?? 0),
                    $releaseKeyHash,
                    $actorId
                );
            },
            true
        );

        if (! $transitionResult['created']) {
            $retry = $this->releases->findReleaseResult($requestId, $releaseKeyHash);
            if ($retry === null) {
                throw new RuntimeException('Idempotent P6 transition history exists without matching Approval Release evidence.');
            }
            return [
                'release' => $retry['release'],
                'history' => $retry['history'],
                'idempotent' => true,
            ];
        }

        if (! is_array($releaseRow) || (int) ($releaseRow['id'] ?? 0) <= 0) {
            throw new RuntimeException('Approval Release evidence was not produced by the committed P6 transition.');
        }
        $history = $transitionResult['history'];
        unset($history['request_key_hash']);

        do_action(
            'safecontracts_enterprise_workflow_approval_released',
            $contractId,
            $requestId,
            (int) ($releaseRow['id'] ?? 0),
            (int) ($history['id'] ?? 0),
            $transitionCode,
            $actorId
        );

        return [
            'release' => $releaseRow,
            'history' => $history,
            'idempotent' => false,
        ];
    }

    /** @return array<string,mixed> */
    private function requireRequest(int $requestId): array
    {
        if ($requestId <= 0) {
            throw new InvalidArgumentException('Approval Request ID must be positive.');
        }
        $request = $this->releases->findRequest($requestId);
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
            throw new RuntimeException('Enterprise Approval Release access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Approval Release operation.');
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
