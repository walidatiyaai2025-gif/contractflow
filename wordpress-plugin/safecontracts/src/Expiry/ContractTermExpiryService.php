<?php

declare(strict_types=1);

namespace SafeContracts\Expiry;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractTermExpiryService
{
    public function __construct(private ?ContractTermExpiryRepository $repository = null)
    {
        $this->repository ??= new ContractTermExpiryRepository();
    }

    /**
     * @return array{
     *   contract_id:int,
     *   contract_number:string,
     *   contract_status:string,
     *   is_archived:bool,
     *   start_date:?string,
     *   end_date:?string,
     *   as_of_date:string,
     *   expiry_state:string,
     *   days_until_end:?int,
     *   days_past_end:?int
     * }
     */
    public function evaluate(int $contractId, string $asOfDate): array
    {
        $this->authorize(Capabilities::ACCESS);
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        $asOfDate = ContractTermExpiryPolicy::normalizeDate($asOfDate, 'As-of date');

        $contract = $this->repository->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found in the current Enterprise tenant.');
        }
        $this->assertScope($contract);

        $endDate = isset($contract['end_date']) && $contract['end_date'] !== null && $contract['end_date'] !== ''
            ? (string) $contract['end_date']
            : null;
        $evaluation = ContractTermExpiryPolicy::evaluate($endDate, $asOfDate);

        return [
            'contract_id' => (int) ($contract['id'] ?? 0),
            'contract_number' => (string) ($contract['contract_number'] ?? ''),
            'contract_status' => (string) ($contract['status'] ?? ''),
            'is_archived' => (bool) ($contract['is_archived'] ?? false),
            'start_date' => isset($contract['start_date']) && $contract['start_date'] !== null && $contract['start_date'] !== ''
                ? (string) $contract['start_date']
                : null,
            'end_date' => $endDate,
            'as_of_date' => $asOfDate,
            'expiry_state' => $evaluation['expiry_state'],
            'days_until_end' => $evaluation['days_until_end'],
            'days_past_end' => $evaluation['days_past_end'],
        ];
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
            throw new RuntimeException('Enterprise Contract expiry evaluation requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow Contract expiry evaluation.');
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
