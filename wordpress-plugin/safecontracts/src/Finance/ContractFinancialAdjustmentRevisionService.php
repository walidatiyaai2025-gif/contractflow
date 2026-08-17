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

final class ContractFinancialAdjustmentRevisionService
{
    public function __construct(
        private ?ContractFinancialAdjustmentRevisionRepository $repository = null,
        private ?ContractFinancialCurrencyProfileRepository $currencyProfileRepository = null
    ) {
        $this->repository ??= new ContractFinancialAdjustmentRevisionRepository();
        $this->currencyProfileRepository ??= new ContractFinancialCurrencyProfileRepository();
    }

    /** @return list<array<string,mixed>> */
    public function listCurrentForContract(int $contractId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->repository->listCurrentForContract($contractId);
    }

    /** @return array{id:int,line_uuid:string} */
    public function create(int $contractId, mixed $kind, mixed $description, mixed $amount): array
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertDraftMutable($contract);

        $kind = ContractFinancialAdjustmentPolicy::normalizeKind($kind);
        $description = ContractFinancialAdjustmentPolicy::normalizeDescription($description);
        $money = $this->moneyForContract($contractId, $amount);
        $lineUuid = $this->uuid();
        $revisionUuid = $this->uuid();
        $id = $this->repository->createLine(
            $contractId,
            $lineUuid,
            $revisionUuid,
            $kind,
            $description,
            $money,
            get_current_user_id()
        );

        return ['id' => $id, 'line_uuid' => $lineUuid];
    }

    public function revise(int $contractId, mixed $lineUuid, mixed $kind, mixed $description, mixed $amount): int
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertDraftMutable($contract);

        $lineUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($lineUuid, 'line UUID');
        $kind = ContractFinancialAdjustmentPolicy::normalizeKind($kind);
        $description = ContractFinancialAdjustmentPolicy::normalizeDescription($description);
        $money = $this->moneyForContract($contractId, $amount);

        return $this->repository->reviseLine(
            $contractId,
            $lineUuid,
            $this->uuid(),
            $kind,
            $description,
            $money,
            get_current_user_id()
        );
    }

    public function void(int $contractId, mixed $lineUuid): int
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertDraftMutable($contract);
        $lineUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($lineUuid, 'line UUID');

        return $this->repository->voidLine(
            $contractId,
            $lineUuid,
            $this->uuid(),
            get_current_user_id()
        );
    }

    private function moneyForContract(int $contractId, mixed $amount): Money
    {
        $profile = $this->currencyProfileRepository->findForContract($contractId);
        if ($profile === null) {
            throw new DomainException('Enterprise Contract requires a financial currency profile before financial adjustments can be created.');
        }
        $currency = CurrencyCode::from($profile['contract_currency'] ?? null);
        $money = Money::of($amount, $currency);
        if ($money->compare(Money::of('0', $currency)) < 0) {
            throw new InvalidArgumentException('Enterprise financial adjustment amount cannot be negative.');
        }
        return $money;
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
            throw new DomainException('Archived Contracts cannot change Enterprise financial adjustments.');
        }
        if ((string) ($contract['status'] ?? '') !== 'draft') {
            throw new DomainException('Enterprise financial adjustments may only change while the Contract is draft.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise financial adjustment access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Enterprise financial adjustment operation.');
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
            throw new RuntimeException('Unable to generate Enterprise financial adjustment UUID.', 0, $error);
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
