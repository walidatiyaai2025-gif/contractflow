<?php

declare(strict_types=1);

namespace SafeContracts\Obligations;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractObligationService
{
    public function __construct(private ?ContractObligationRepository $repository = null)
    {
        $this->repository ??= new ContractObligationRepository();
    }

    /** @return array<string,mixed>|null */
    public function find(int $obligationId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($obligationId, 'Contract Obligation ID');
        $obligation = $this->repository->find($obligationId);
        if ($obligation === null) {
            return null;
        }
        $contract = $this->requireContract((int) ($obligation['contract_id'] ?? 0));
        $this->assertScope($contract);
        return $obligation;
    }

    /** @return list<array<string,mixed>> */
    public function listForContract(int $contractId, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Contract Obligation list limit must be between 1 and 100.');
        }
        if ($offset < 0 || $offset > 100000) {
            throw new InvalidArgumentException('Contract Obligation list offset must be between 0 and 100000.');
        }
        return $this->repository->listForContract($contractId, $limit, $offset);
    }

    public function create(
        int $contractId,
        string $obligationCode,
        string $title,
        ?string $description = null,
        ?string $dueDate = null
    ): int {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertContractMutable($contract);

        $code = ContractObligationPolicy::normalizeCode($obligationCode);
        $title = ContractObligationPolicy::normalizeTitle($title);
        $description = ContractObligationPolicy::normalizeDescription($description);
        $dueDate = ContractObligationPolicy::normalizeDueDate($dueDate);
        $actorId = get_current_user_id();

        $obligationId = $this->repository->create(
            $contractId,
            $this->uuid(),
            $code,
            $title,
            $description,
            $dueDate,
            $actorId
        );
        do_action('safecontracts_enterprise_contract_obligation_created', $contractId, $obligationId, $actorId);
        return $obligationId;
    }

    public function update(
        int $obligationId,
        string $title,
        ?string $description = null,
        ?string $dueDate = null
    ): void {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $obligation = $this->requireObligation($obligationId);
        $contract = $this->requireContract((int) ($obligation['contract_id'] ?? 0));
        $this->assertScope($contract);
        $this->assertContractMutable($contract);
        if ((string) ($obligation['status'] ?? '') !== ContractObligationPolicy::STATUS_OPEN) {
            throw new DomainException('Terminal Contract Obligations are immutable.');
        }

        $title = ContractObligationPolicy::normalizeTitle($title);
        $description = ContractObligationPolicy::normalizeDescription($description);
        $dueDate = ContractObligationPolicy::normalizeDueDate($dueDate);
        $actorId = get_current_user_id();
        $this->repository->updateMetadata($obligationId, $title, $description, $dueDate, $actorId);
        do_action('safecontracts_enterprise_contract_obligation_updated', (int) $obligation['contract_id'], $obligationId, $actorId);
    }

    /** @return array<string,mixed> */
    public function complete(int $obligationId): array
    {
        return $this->transitionTerminal($obligationId, ContractObligationPolicy::STATUS_COMPLETED);
    }

    /** @return array<string,mixed> */
    public function cancel(int $obligationId): array
    {
        return $this->transitionTerminal($obligationId, ContractObligationPolicy::STATUS_CANCELLED);
    }

    /** @return array<string,mixed> */
    private function transitionTerminal(int $obligationId, string $targetStatus): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $targetStatus = ContractObligationPolicy::normalizeTerminalStatus($targetStatus);
        $obligation = $this->requireObligation($obligationId);
        $contract = $this->requireContract((int) ($obligation['contract_id'] ?? 0));
        $this->assertScope($contract);

        $currentStatus = (string) ($obligation['status'] ?? '');
        if ($currentStatus === $targetStatus) {
            return $obligation;
        }
        if ($currentStatus !== ContractObligationPolicy::STATUS_OPEN) {
            throw new DomainException('Contract Obligation is already terminal with a different status.');
        }
        $this->assertContractMutable($contract);

        $actorId = get_current_user_id();
        $result = $this->repository->transitionTerminal($obligationId, $targetStatus, $actorId);
        do_action(
            'safecontracts_enterprise_contract_obligation_' . $targetStatus,
            (int) $obligation['contract_id'],
            $obligationId,
            $actorId
        );
        return $result;
    }

    /** @return array<string,mixed> */
    private function requireObligation(int $obligationId): array
    {
        $this->requirePositive($obligationId, 'Contract Obligation ID');
        $obligation = $this->repository->find($obligationId);
        if ($obligation === null) {
            throw new InvalidArgumentException('Contract Obligation was not found in the current Enterprise tenant.');
        }
        $contractId = (int) ($obligation['contract_id'] ?? 0);
        if ($contractId <= 0) {
            throw new RuntimeException('Contract Obligation has invalid contract identity.');
        }
        return $obligation;
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
            throw new DomainException('Archived contracts cannot mutate Enterprise Contract Obligations.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Obligation access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract Obligation operation.');
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
            throw new RuntimeException('Unable to generate Contract Obligation UUID.', 0, $error);
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
