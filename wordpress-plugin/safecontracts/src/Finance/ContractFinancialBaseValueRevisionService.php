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

final class ContractFinancialBaseValueRevisionService
{
    public function __construct(
        private ?ContractFinancialBaseValueRevisionRepository $repository = null,
        private ?ContractFinancialCurrencyProfileRepository $currencyProfileRepository = null
    ) {
        $this->repository ??= new ContractFinancialBaseValueRevisionRepository();
        $this->currencyProfileRepository ??= new ContractFinancialCurrencyProfileRepository();
    }

    /** @return array<string,mixed>|null */
    public function findLatestForContract(int $contractId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->repository->findLatestForContract($contractId);
    }

    public function append(int $contractId, mixed $amount): int
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertDraftMutable($contract);

        $profile = $this->currencyProfileRepository->findForContract($contractId);
        if ($profile === null) {
            throw new DomainException('Enterprise Contract requires a financial currency profile before setting base value.');
        }

        $currency = CurrencyCode::from($profile['contract_currency'] ?? null);
        $money = Money::of($amount, $currency);
        if ($money->compare(Money::of('0', $currency)) < 0) {
            throw new InvalidArgumentException('Enterprise Contract base value cannot be negative.');
        }

        return $this->repository->appendOrGetLatest(
            $contractId,
            $this->uuid(),
            $money,
            get_current_user_id()
        );
    }

    /** @return array<string,mixed> */
    private function requireContract(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
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
    private function assertDraftMutable(array $contract): void
    {
        if ((int) ($contract['is_archived'] ?? 0) !== 0) {
            throw new DomainException('Archived contracts cannot revise Enterprise base value.');
        }
        if ((string) ($contract['status'] ?? '') !== 'draft') {
            throw new DomainException('Enterprise Contract base value may only be revised while the Contract is draft.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract base-value access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract base-value operation.');
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
            throw new RuntimeException('Unable to generate Contract base-value revision UUID.', 0, $error);
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
