<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractFinancialScheduleSettlementService
{
    public function __construct(private ?ContractFinancialScheduleSettlementRepository $repository = null)
    {
        $this->repository ??= new ContractFinancialScheduleSettlementRepository();
    }

    /** @return array{entries:list<array<string,mixed>>,summary:array<string,string>} */
    public function reconcileContract(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        $this->authorize();
        return $this->repository->reconcileContract($contractId, function (array $contract): void {
            $this->assertScope($contract);
        });
    }

    private function authorize(): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract schedule settlement access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can(Capabilities::ACCESS) || ! TenantAuthorization::allowsCapability(Capabilities::ACCESS)) {
            throw new DomainException('The current tenant role does not allow Enterprise Contract schedule settlement access.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
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
        throw new DomainException('Contract is outside the current user data scope.');
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
