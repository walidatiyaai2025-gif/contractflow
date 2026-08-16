<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomFieldValueService
{
    public function __construct(private ?CustomFieldValueRepository $repository = null)
    {
        $this->repository ??= new CustomFieldValueRepository();
    }

    public function get(int $contractId, int $definitionId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->requirePositive($definitionId, 'Custom Field definition ID');
        $row = $this->repository->findValue($contractId, $definitionId);
        if ($row === null || (int) ($row['is_set'] ?? 0) !== 1) {
            return null;
        }
        return $this->hydrate($row);
    }

    /** @return list<array<string,mixed>> */
    public function list(int $contractId, int $limit = 200, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        return array_map(fn (array $row): array => $this->hydrate($row), $this->repository->listSetValues($contractId, $limit, $offset));
    }

    public function set(int $contractId, int $definitionId, mixed $value): void
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertEditable($contract);
        $binding = $this->requireBinding($contractId);
        $definition = $this->requireDefinition($definitionId, (int) ($binding['contract_type_id'] ?? 0));
        $canonical = CustomFieldValuePolicy::canonicalize($definition, $value);
        $existing = $this->repository->findValue($contractId, $definitionId);
        if ($existing !== null
            && (int) ($existing['is_set'] ?? 0) === 1
            && (string) ($existing['value_json'] ?? '') === $canonical['value_json']
            && (string) ($existing['definition_config_hash'] ?? '') === $canonical['config_hash']) {
            return;
        }

        $actorId = get_current_user_id();
        $this->repository->saveValue($contractId, $definition, $canonical['value_json'], $canonical['config_hash'], $actorId);
        do_action('safecontracts_enterprise_custom_field_value_set', $contractId, $definitionId, $actorId);
    }

    public function clear(int $contractId, int $definitionId): void
    {
        $this->authorize(Capabilities::EDIT_CONTRACTS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->assertEditable($contract);
        $binding = $this->requireBinding($contractId);
        $definition = $this->requireDefinition($definitionId, (int) ($binding['contract_type_id'] ?? 0));
        $existing = $this->repository->findValue($contractId, $definitionId);
        if ($existing === null || (int) ($existing['is_set'] ?? 0) !== 1) {
            return;
        }

        $actorId = get_current_user_id();
        $hash = CustomFieldValuePolicy::configurationHash($definition);
        $this->repository->clearValue($contractId, $definition, $hash, $actorId);
        do_action('safecontracts_enterprise_custom_field_value_cleared', $contractId, $definitionId, $actorId);
    }

    /** @return list<array<string,mixed>> */
    public function missingRequired(int $contractId, int $limit = 500): array
    {
        $this->authorize(Capabilities::ACCESS);
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $this->requireBinding($contractId);
        return $this->repository->listMissingRequired($contractId, $limit);
    }

    private function requireContract(int $contractId): array
    {
        $this->requirePositive($contractId, 'Contract ID');
        $contract = $this->repository->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found in the current Enterprise tenant.');
        }
        return $contract;
    }

    private function requireBinding(int $contractId): array
    {
        $binding = $this->repository->findBinding($contractId);
        if ($binding === null || (int) ($binding['contract_type_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Dynamic Field values require an Enterprise Contract Type binding.');
        }
        return $binding;
    }

    private function requireDefinition(int $definitionId, int $contractTypeId): array
    {
        $this->requirePositive($definitionId, 'Custom Field definition ID');
        $definition = $this->repository->findDefinition($definitionId);
        if ($definition === null
            || (int) ($definition['contract_type_id'] ?? 0) !== $contractTypeId
            || (string) ($definition['status'] ?? '') !== CustomFieldDefinitionPolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Custom Field definition must be active and belong to the contract\'s bound Contract Type.');
        }
        return $definition;
    }

    private function assertEditable(array $contract): void
    {
        if ((bool) ($contract['is_archived'] ?? false)) {
            throw new DomainException('Archived contracts cannot change Dynamic Field values.');
        }
        if ((string) ($contract['status'] ?? '') !== ContractStatus::DRAFT) {
            throw new DomainException('Dynamic Field values are immutable after the contract leaves draft in this foundation.');
        }
    }

    private function assertScope(array $contract): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        $assigned = $this->nullableInt($contract['accountant_user_id'] ?? null);
        if (current_user_can(Capabilities::VIEW_ASSIGNED) && $assigned !== null && $assigned === get_current_user_id()) {
            return;
        }
        throw new DomainException('Contract is outside the current user data scope.');
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Dynamic Field value access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Dynamic Field value operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }

    private function requirePositive(int $value, string $label): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException($label . ' must be positive.');
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private function hydrate(array $row): array
    {
        $json = (string) ($row['value_json'] ?? '');
        $row['value'] = CustomFieldValuePolicy::decodeStored($json);
        return $row;
    }
}
