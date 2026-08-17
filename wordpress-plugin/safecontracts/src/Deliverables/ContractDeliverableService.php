<?php

declare(strict_types=1);

namespace SafeContracts\Deliverables;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractDeliverableService
{
    public function __construct(private ?ContractDeliverableRepository $repository = null)
    {
        $this->repository ??= new ContractDeliverableRepository();
    }

    /** @return array<string,mixed>|null */
    public function find(int $deliverableId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($deliverableId, 'Contract Deliverable ID');
        $deliverable = $this->repository->find($deliverableId);
        if ($deliverable === null) {
            return null;
        }
        $contract = $this->requireContract((int) ($deliverable['contract_id'] ?? 0));
        $this->assertScope($contract);
        return $deliverable;
    }

    /** @return list<array<string,mixed>> */
    public function listForContract(int $contractId, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Contract Deliverable list limit must be between 1 and 100.');
        }
        if ($offset < 0 || $offset > 100000) {
            throw new InvalidArgumentException('Contract Deliverable list offset must be between 0 and 100000.');
        }
        return $this->repository->listForContract($contractId, $limit, $offset);
    }

    public function create(
        int $contractId,
        string $deliverableCode,
        string $title,
        ?string $description = null,
        ?string $dueDate = null
    ): int {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertContractMutable($contract);

        $code = ContractDeliverablePolicy::normalizeCode($deliverableCode);
        $title = ContractDeliverablePolicy::normalizeTitle($title);
        $description = ContractDeliverablePolicy::normalizeDescription($description);
        $dueDate = ContractDeliverablePolicy::normalizeDueDate($dueDate);
        $actorId = get_current_user_id();

        $deliverableId = $this->repository->create(
            $contractId,
            $this->uuid(),
            $code,
            $title,
            $description,
            $dueDate,
            $actorId
        );
        do_action('safecontracts_enterprise_contract_deliverable_created', $contractId, $deliverableId, $actorId);
        return $deliverableId;
    }

    public function update(
        int $deliverableId,
        string $title,
        ?string $description = null,
        ?string $dueDate = null
    ): void {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $deliverable = $this->requireDeliverable($deliverableId);
        $contract = $this->requireContract((int) ($deliverable['contract_id'] ?? 0));
        $this->assertScope($contract);
        $this->assertContractMutable($contract);
        if ((string) ($deliverable['status'] ?? '') !== ContractDeliverablePolicy::STATUS_PENDING) {
            throw new DomainException('Terminal Contract Deliverables are immutable.');
        }

        $title = ContractDeliverablePolicy::normalizeTitle($title);
        $description = ContractDeliverablePolicy::normalizeDescription($description);
        $dueDate = ContractDeliverablePolicy::normalizeDueDate($dueDate);
        $actorId = get_current_user_id();
        $this->repository->updateMetadata($deliverableId, $title, $description, $dueDate, $actorId);
        do_action('safecontracts_enterprise_contract_deliverable_updated', (int) $deliverable['contract_id'], $deliverableId, $actorId);
    }

    /** @return array<string,mixed> */
    public function deliver(int $deliverableId): array
    {
        return $this->transitionTerminal($deliverableId, ContractDeliverablePolicy::STATUS_DELIVERED);
    }

    /** @return array<string,mixed> */
    public function cancel(int $deliverableId): array
    {
        return $this->transitionTerminal($deliverableId, ContractDeliverablePolicy::STATUS_CANCELLED);
    }

    /** @return array<string,mixed> */
    private function transitionTerminal(int $deliverableId, string $targetStatus): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $targetStatus = ContractDeliverablePolicy::normalizeTerminalStatus($targetStatus);
        $deliverable = $this->requireDeliverable($deliverableId);
        $contract = $this->requireContract((int) ($deliverable['contract_id'] ?? 0));
        $this->assertScope($contract);

        $currentStatus = (string) ($deliverable['status'] ?? '');
        if ($currentStatus === $targetStatus) {
            return $deliverable;
        }
        if ($currentStatus !== ContractDeliverablePolicy::STATUS_PENDING) {
            throw new DomainException('Contract Deliverable is already terminal with a different status.');
        }
        $this->assertContractMutable($contract);

        $actorId = get_current_user_id();
        $result = $this->repository->transitionTerminal($deliverableId, $targetStatus, $actorId);
        do_action(
            'safecontracts_enterprise_contract_deliverable_' . $targetStatus,
            (int) $deliverable['contract_id'],
            $deliverableId,
            $actorId
        );
        return $result;
    }

    /** @return array<string,mixed> */
    private function requireDeliverable(int $deliverableId): array
    {
        $this->requirePositive($deliverableId, 'Contract Deliverable ID');
        $deliverable = $this->repository->find($deliverableId);
        if ($deliverable === null) {
            throw new InvalidArgumentException('Contract Deliverable was not found in the current Enterprise tenant.');
        }
        $contractId = (int) ($deliverable['contract_id'] ?? 0);
        if ($contractId <= 0) {
            throw new RuntimeException('Contract Deliverable has invalid contract identity.');
        }
        return $deliverable;
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

    /** @param array<string,mixed> $contract */
    private function assertContractMutable(array $contract): void
    {
        if ((int) ($contract['is_archived'] ?? 0) === 1) {
            throw new DomainException('Archived contracts cannot mutate Enterprise Contract Deliverables.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Deliverable access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract Deliverable operation.');
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function uuid(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            $uuid = (string) wp_generate_uuid4();
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1) {
                return strtolower($uuid);
            }
        }

        try {
            $bytes = random_bytes(16);
        } catch (\Throwable $error) {
            throw new RuntimeException('Unable to generate Contract Deliverable UUID.', 0, $error);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
