<?php

declare(strict_types=1);

namespace SafeContracts\Milestones;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractMilestoneService
{
    public function __construct(private ?ContractMilestoneRepository $repository = null)
    {
        $this->repository ??= new ContractMilestoneRepository();
    }

    /** @return array<string,mixed>|null */
    public function find(int $milestoneId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($milestoneId, 'Contract Milestone ID');
        $milestone = $this->repository->find($milestoneId);
        if ($milestone === null) {
            return null;
        }
        $contract = $this->requireContract((int) ($milestone['contract_id'] ?? 0));
        $this->assertScope($contract);
        return $milestone;
    }

    /** @return list<array<string,mixed>> */
    public function listForContract(int $contractId, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Contract Milestone list limit must be between 1 and 100.');
        }
        if ($offset < 0 || $offset > 100000) {
            throw new InvalidArgumentException('Contract Milestone list offset must be between 0 and 100000.');
        }
        return $this->repository->listForContract($contractId, $limit, $offset);
    }

    public function create(
        int $contractId,
        string $milestoneCode,
        string $title,
        ?string $description = null,
        ?string $targetDate = null
    ): int {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertContractMutable($contract);

        $code = ContractMilestonePolicy::normalizeCode($milestoneCode);
        $title = ContractMilestonePolicy::normalizeTitle($title);
        $description = ContractMilestonePolicy::normalizeDescription($description);
        $targetDate = ContractMilestonePolicy::normalizeTargetDate($targetDate);
        $actorId = get_current_user_id();

        $milestoneId = $this->repository->create(
            $contractId,
            $this->uuid(),
            $code,
            $title,
            $description,
            $targetDate,
            $actorId
        );
        do_action('safecontracts_enterprise_contract_milestone_created', $contractId, $milestoneId, $actorId);
        return $milestoneId;
    }

    public function update(
        int $milestoneId,
        string $title,
        ?string $description = null,
        ?string $targetDate = null
    ): void {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $milestone = $this->requireMilestone($milestoneId);
        $contract = $this->requireContract((int) ($milestone['contract_id'] ?? 0));
        $this->assertScope($contract);
        $this->assertContractMutable($contract);
        if ((string) ($milestone['status'] ?? '') !== ContractMilestonePolicy::STATUS_PLANNED) {
            throw new DomainException('Terminal Contract Milestones are immutable.');
        }

        $title = ContractMilestonePolicy::normalizeTitle($title);
        $description = ContractMilestonePolicy::normalizeDescription($description);
        $targetDate = ContractMilestonePolicy::normalizeTargetDate($targetDate);
        $actorId = get_current_user_id();
        $this->repository->updateMetadata($milestoneId, $title, $description, $targetDate, $actorId);
        do_action('safecontracts_enterprise_contract_milestone_updated', (int) $milestone['contract_id'], $milestoneId, $actorId);
    }

    /** @return array<string,mixed> */
    public function achieve(int $milestoneId): array
    {
        return $this->transitionTerminal($milestoneId, ContractMilestonePolicy::STATUS_ACHIEVED);
    }

    /** @return array<string,mixed> */
    public function cancel(int $milestoneId): array
    {
        return $this->transitionTerminal($milestoneId, ContractMilestonePolicy::STATUS_CANCELLED);
    }

    /** @return array<string,mixed> */
    private function transitionTerminal(int $milestoneId, string $targetStatus): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $targetStatus = ContractMilestonePolicy::normalizeTerminalStatus($targetStatus);
        $milestone = $this->requireMilestone($milestoneId);
        $contract = $this->requireContract((int) ($milestone['contract_id'] ?? 0));
        $this->assertScope($contract);

        $currentStatus = (string) ($milestone['status'] ?? '');
        if ($currentStatus === $targetStatus) {
            return $milestone;
        }
        if ($currentStatus !== ContractMilestonePolicy::STATUS_PLANNED) {
            throw new DomainException('Contract Milestone is already terminal with a different status.');
        }
        $this->assertContractMutable($contract);

        $actorId = get_current_user_id();
        $result = $this->repository->transitionTerminal($milestoneId, $targetStatus, $actorId);
        do_action(
            'safecontracts_enterprise_contract_milestone_' . $targetStatus,
            (int) $milestone['contract_id'],
            $milestoneId,
            $actorId
        );
        return $result;
    }

    /** @return array<string,mixed> */
    private function requireMilestone(int $milestoneId): array
    {
        $this->requirePositive($milestoneId, 'Contract Milestone ID');
        $milestone = $this->repository->find($milestoneId);
        if ($milestone === null) {
            throw new InvalidArgumentException('Contract Milestone was not found in the current Enterprise tenant.');
        }
        $contractId = (int) ($milestone['contract_id'] ?? 0);
        if ($contractId <= 0) {
            throw new RuntimeException('Contract Milestone has invalid contract identity.');
        }
        return $milestone;
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
            throw new DomainException('Archived contracts cannot mutate Enterprise Contract Milestones.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Milestone access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract Milestone operation.');
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
            throw new RuntimeException('Unable to generate Contract Milestone UUID.', 0, $error);
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
