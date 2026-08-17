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

final class ContractFinancialCurrencyProfileService
{
    public function __construct(
        private ?ContractFinancialCurrencyProfileRepository $repository = null,
        private ?TenantBaseCurrencyRepository $tenantBaseCurrencyRepository = null
    ) {
        $this->repository ??= new ContractFinancialCurrencyProfileRepository();
        $this->tenantBaseCurrencyRepository ??= new TenantBaseCurrencyRepository();
    }

    /** @return array<string,mixed>|null */
    public function findForContract(int $contractId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->repository->findForContract($contractId);
    }

    public function create(int $contractId, string $contractCurrency): int
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertContractMutable($contract);

        $currency = CurrencyCode::from($contractCurrency);
        $existing = $this->repository->findForContract($contractId);
        if ($existing !== null) {
            $storedCurrency = CurrencyCode::from($existing['contract_currency'] ?? null);
            if (! $storedCurrency->equals($currency)) {
                throw new DomainException('Contract financial currency profile is immutable once created.');
            }
            return (int) $existing['id'];
        }

        $tenantBaseCurrency = $this->tenantBaseCurrencyRepository->resolve(TenantContextStore::context());
        return $this->repository->createOrGet(
            $contractId,
            $this->uuid(),
            $currency,
            $tenantBaseCurrency,
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
    private function assertContractMutable(array $contract): void
    {
        if ((int) ($contract['is_archived'] ?? 0) === 1) {
            throw new DomainException('Archived contracts cannot create Enterprise financial currency profiles.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract financial currency profile access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract financial currency profile operation.');
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
            throw new RuntimeException('Unable to generate Contract financial currency profile UUID.', 0, $error);
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
