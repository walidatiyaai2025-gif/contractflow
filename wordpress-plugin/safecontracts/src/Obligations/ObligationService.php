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

final class ObligationService
{
    public function __construct(private ?ObligationRepository $obligations = null)
    {
        $this->obligations ??= new ObligationRepository();
    }

    /** @return list<array<string,mixed>> */
    public function search(int $contractId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->obligations->search($contractId, ObligationPolicy::normalizeSearch($filters), $limit, $offset);
    }

    /** @return array<string,mixed> */
    public function get(int $contractId, int $obligationId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->requireObligation($contractId, $obligationId);
    }

    /** @return array<string,mixed> */
    public function create(int $contractId, array $input): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertMutableContract($contract);
        $data = ObligationPolicy::normalizeCreate($input);
        $actorId = get_current_user_id();
        $row = $this->obligations->create($contractId, ObligationPolicy::newUuid(), $data, $actorId);
        do_action('safecontracts_enterprise_contract_obligation_created', $contractId, (int) ($row['id'] ?? 0), $actorId);
        return $row;
    }

    /** @return array<string,mixed> */
    public function updateMetadata(int $contractId, int $obligationId, array $input): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $current = $this->requireObligation($contractId, $obligationId);
        if ((string) ($current['status'] ?? '') !== ObligationPolicy::STATUS_OPEN) {
            throw new DomainException('Terminal Contract Obligations are immutable.');
        }
        $this->assertMutableContract($contract);
        $data = ObligationPolicy::normalizeMetadataUpdate($input);
        $actorId = get_current_user_id();
        $row = $this->obligations->updateMetadata($contractId, $obligationId, $data, $actorId);
        do_action('safecontracts_enterprise_contract_obligation_updated', $contractId, $obligationId, $actorId);
        return $row;
    }

    /** @return array{obligation:array<string,mixed>,idempotent:bool} */
    public function complete(int $contractId, int $obligationId): array
    {
        return $this->transition($contractId, $obligationId, ObligationPolicy::STATUS_COMPLETED);
    }

    /** @return array{obligation:array<string,mixed>,idempotent:bool} */
    public function cancel(int $contractId, int $obligationId): array
    {
        return $this->transition($contractId, $obligationId, ObligationPolicy::STATUS_CANCELLED);
    }

    /** @return array{obligation:array<string,mixed>,idempotent:bool} */
    private function transition(int $contractId, int $obligationId, string $target): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $current = $this->requireObligation($contractId, $obligationId);
        $target = ObligationPolicy::normalizeTerminalTarget($target);
        $status = (string) ($current['status'] ?? '');
        if ($status === $target) {
            return ['obligation' => $current, 'idempotent' => true];
        }
        if ($status !== ObligationPolicy::STATUS_OPEN) {
            throw new DomainException('Contract Obligation is already terminal with a conflicting status.');
        }
        $this->assertMutableContract($contract);
        $actorId = get_current_user_id();
        $result = $this->obligations->transition($contractId, $obligationId, $target, $actorId);
        do_action('safecontracts_enterprise_contract_obligation_transitioned', $contractId, $obligationId, $target, $actorId, $result['idempotent']);
        return $result;
    }

    /** @return array<string,mixed> */
    private function requireContract(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        $contract = $this->obligations->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found in the current Enterprise tenant.');
        }
        return $contract;
    }

    /** @return array<string,mixed> */
    private function requireObligation(int $contractId, int $obligationId): array
    {
        if ($obligationId <= 0) {
            throw new InvalidArgumentException('Contract Obligation ID must be positive.');
        }
        $row = $this->obligations->find($contractId, $obligationId);
        if ($row === null) {
            throw new InvalidArgumentException('Contract Obligation was not found for the current-tenant Contract.');
        }
        return $row;
    }

    /** @param array<string,mixed> $contract */
    private function assertScope(array $contract): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        $accountantUserId = $this->nullableInt($contract['accountant_user_id'] ?? null);
        if (current_user_can(Capabilities::VIEW_ASSIGNED) && $accountantUserId !== null && $accountantUserId === get_current_user_id()) {
            return;
        }
        throw new DomainException('Contract Obligation Contract is outside the current user data scope.');
    }

    /** @param array<string,mixed> $contract */
    private function assertMutableContract(array $contract): void
    {
        if ((int) ($contract['is_archived'] ?? 0) === 1) {
            throw new DomainException('Archived Contracts cannot mutate Contract Obligations.');
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}
