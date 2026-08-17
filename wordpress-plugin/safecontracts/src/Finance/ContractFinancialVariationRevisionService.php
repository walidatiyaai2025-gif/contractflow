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

final class ContractFinancialVariationRevisionService
{
    public function __construct(private ?ContractFinancialVariationRevisionRepository $repository = null)
    {
        $this->repository ??= new ContractFinancialVariationRevisionRepository();
    }

    /** @return list<array<string,mixed>> */
    public function listCurrentForContract(int $contractId): array
    {
        $this->assertContractId($contractId);
        $this->authorize(Capabilities::ACCESS);
        return $this->repository->listCurrentForContract(
            $contractId,
            function (array $contract): void {
                $this->assertScope($contract);
            }
        );
    }

    /** @return array{id:int,variation_uuid:string} */
    public function create(int $contractId, mixed $direction, mixed $description, mixed $amount): array
    {
        $this->assertContractId($contractId);
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $variationUuid = $this->uuid();
        $id = $this->repository->createVariation(
            $contractId,
            $variationUuid,
            $this->uuid(),
            ContractFinancialVariationPolicy::normalizeDirection($direction),
            ContractFinancialVariationPolicy::normalizeDescription($description),
            $amount,
            get_current_user_id(),
            function (array $contract): void {
                $this->assertScope($contract);
            }
        );
        return ['id' => $id, 'variation_uuid' => $variationUuid];
    }

    public function revise(int $contractId, mixed $variationUuid, mixed $direction, mixed $description, mixed $amount): int
    {
        $this->assertContractId($contractId);
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        return $this->repository->reviseVariation(
            $contractId,
            ContractFinancialVariationPolicy::normalizeUuid($variationUuid, 'variation UUID'),
            $this->uuid(),
            ContractFinancialVariationPolicy::normalizeDirection($direction),
            ContractFinancialVariationPolicy::normalizeDescription($description),
            $amount,
            get_current_user_id(),
            function (array $contract): void {
                $this->assertScope($contract);
            }
        );
    }

    public function void(int $contractId, mixed $variationUuid): int
    {
        $this->assertContractId($contractId);
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        return $this->repository->voidVariation(
            $contractId,
            ContractFinancialVariationPolicy::normalizeUuid($variationUuid, 'variation UUID'),
            $this->uuid(),
            get_current_user_id(),
            function (array $contract): void {
                $this->assertScope($contract);
            }
        );
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

    private function assertContractId(int $contractId): void
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise financial variation access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Enterprise financial variation operation.');
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
            throw new RuntimeException('Unable to generate Enterprise financial variation UUID.', 0, $error);
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
