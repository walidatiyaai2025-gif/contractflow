<?php

declare(strict_types=1);

namespace SafeContracts\Renewals;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractRenewalTermsService
{
    public function __construct(private ?ContractRenewalTermsRepository $repository = null)
    {
        $this->repository ??= new ContractRenewalTermsRepository();
    }

    /** @return array<string,mixed>|null */
    public function find(int $termsId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($termsId, 'Contract Renewal Terms ID');
        $terms = $this->repository->find($termsId);
        if ($terms === null) {
            return null;
        }
        $contract = $this->requireContract((int) ($terms['contract_id'] ?? 0));
        $this->assertScope($contract);
        return $terms;
    }

    /** @return array<string,mixed>|null */
    public function findForContract(int $contractId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return $this->repository->findForContract($contractId);
    }

    public function create(
        int $contractId,
        string $mode,
        ?int $intervalValue = null,
        ?string $intervalUnit = null,
        ?int $maxOccurrences = null,
        ?string $notes = null
    ): int {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertContractMutable($contract);

        $normalized = $this->normalizeTerms($mode, $intervalValue, $intervalUnit, $maxOccurrences, $notes);
        $actorId = get_current_user_id();
        $termsId = $this->repository->create(
            $contractId,
            $this->uuid(),
            $normalized['mode'],
            $normalized['interval_value'],
            $normalized['interval_unit'],
            $normalized['max_occurrences'],
            $normalized['notes'],
            $actorId
        );
        do_action('safecontracts_enterprise_contract_renewal_terms_created', $contractId, $termsId, $actorId);
        return $termsId;
    }

    /** @return array<string,mixed> */
    public function update(
        int $termsId,
        int $expectedRevision,
        string $mode,
        ?int $intervalValue = null,
        ?string $intervalUnit = null,
        ?int $maxOccurrences = null,
        ?string $notes = null
    ): array {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $this->requirePositive($termsId, 'Contract Renewal Terms ID');
        $expectedRevision = ContractRenewalTermsPolicy::normalizeExpectedRevision($expectedRevision);
        $terms = $this->repository->find($termsId);
        if ($terms === null) {
            throw new InvalidArgumentException('Contract Renewal Terms were not found in the current Enterprise tenant.');
        }
        $contract = $this->requireContract((int) ($terms['contract_id'] ?? 0));
        $this->assertScope($contract);
        $this->assertContractMutable($contract);
        if ((int) ($terms['revision'] ?? 0) !== $expectedRevision) {
            throw new DomainException('Contract Renewal Terms changed concurrently.');
        }

        $normalized = $this->normalizeTerms($mode, $intervalValue, $intervalUnit, $maxOccurrences, $notes);
        $actorId = get_current_user_id();
        $updated = $this->repository->update(
            $termsId,
            $expectedRevision,
            $normalized['mode'],
            $normalized['interval_value'],
            $normalized['interval_unit'],
            $normalized['max_occurrences'],
            $normalized['notes'],
            $actorId
        );
        do_action(
            'safecontracts_enterprise_contract_renewal_terms_updated',
            (int) $terms['contract_id'],
            $termsId,
            (int) ($updated['revision'] ?? ($expectedRevision + 1)),
            $actorId
        );
        return $updated;
    }

    /** @return array{mode:string,interval_value:?int,interval_unit:?string,max_occurrences:?int,notes:?string} */
    private function normalizeTerms(
        string $mode,
        ?int $intervalValue,
        ?string $intervalUnit,
        ?int $maxOccurrences,
        ?string $notes
    ): array {
        $mode = ContractRenewalTermsPolicy::normalizeMode($mode);
        $interval = ContractRenewalTermsPolicy::normalizeInterval($mode, $intervalValue, $intervalUnit);
        return [
            'mode' => $mode,
            'interval_value' => $interval['interval_value'],
            'interval_unit' => $interval['interval_unit'],
            'max_occurrences' => ContractRenewalTermsPolicy::normalizeMaxOccurrences($mode, $maxOccurrences),
            'notes' => ContractRenewalTermsPolicy::normalizeNotes($notes),
        ];
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
            throw new DomainException('Archived contracts cannot mutate Enterprise Contract Renewal Terms.');
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Renewal Terms access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract Renewal Terms operation.');
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
            throw new RuntimeException('Unable to generate Contract Renewal Terms UUID.', 0, $error);
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
