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

final class ContractFinancialCollectionReversalRevisionService
{
    public function __construct(private ?ContractFinancialCollectionReversalRevisionRepository $repository = null)
    {
        $this->repository ??= new ContractFinancialCollectionReversalRevisionRepository();
    }

    /** @return list<array<string,mixed>> */
    public function listCurrentForContract(int $contractId): array
    {
        $this->assertContractId($contractId);
        $this->authorize(Capabilities::ACCESS);
        return $this->repository->listCurrentForContract($contractId, function (array $contract): void {
            $this->assertScope($contract);
        });
    }

    /** @return array{id:int,reversal_uuid:string} */
    public function create(int $contractId, mixed $receiptUuid, mixed $externalReference, mixed $reversalDate, mixed $amount): array
    {
        $this->assertContractId($contractId);
        $this->authorize(Capabilities::MANAGE_COLLECTIONS);
        $reversalUuid = $this->uuid();
        $id = $this->repository->createReversal(
            $contractId,
            ContractFinancialCollectionReversalPolicy::normalizeUuid($receiptUuid, 'receipt UUID'),
            $reversalUuid,
            $this->uuid(),
            ContractFinancialCollectionReversalPolicy::normalizeReference($externalReference),
            ContractFinancialCollectionReversalPolicy::normalizeReversalDate($reversalDate),
            $amount,
            get_current_user_id(),
            function (array $contract): void {
                $this->assertScope($contract);
            }
        );
        return ['id' => $id, 'reversal_uuid' => $reversalUuid];
    }

    public function revise(int $contractId, mixed $receiptUuid, mixed $reversalUuid, mixed $externalReference, mixed $reversalDate, mixed $amount): int
    {
        $this->assertContractId($contractId);
        $this->authorize(Capabilities::MANAGE_COLLECTIONS);
        return $this->repository->reviseReversal(
            $contractId,
            ContractFinancialCollectionReversalPolicy::normalizeUuid($receiptUuid, 'receipt UUID'),
            ContractFinancialCollectionReversalPolicy::normalizeUuid($reversalUuid, 'reversal UUID'),
            $this->uuid(),
            ContractFinancialCollectionReversalPolicy::normalizeReference($externalReference),
            ContractFinancialCollectionReversalPolicy::normalizeReversalDate($reversalDate),
            $amount,
            get_current_user_id(),
            function (array $contract): void {
                $this->assertScope($contract);
            }
        );
    }

    public function void(int $contractId, mixed $receiptUuid, mixed $reversalUuid): int
    {
        $this->assertContractId($contractId);
        $this->authorize(Capabilities::MANAGE_COLLECTIONS);
        return $this->repository->voidReversal(
            $contractId,
            ContractFinancialCollectionReversalPolicy::normalizeUuid($receiptUuid, 'receipt UUID'),
            ContractFinancialCollectionReversalPolicy::normalizeUuid($reversalUuid, 'reversal UUID'),
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
        if (current_user_can(Capabilities::VIEW_ASSIGNED) && $accountantUserId !== null && $accountantUserId === get_current_user_id()) {
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
            throw new RuntimeException('Enterprise Contract collection reversal access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Enterprise Contract collection reversal operation.');
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
            throw new RuntimeException('Unable to generate Enterprise Contract collection reversal UUID.', 0, $error);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
