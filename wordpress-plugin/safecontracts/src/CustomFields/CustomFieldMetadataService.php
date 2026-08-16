<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomFieldMetadataService
{
    public function __construct(
        private ?CustomFieldDefinitionRepository $definitions = null,
        private ?CustomFieldMetadataRepository $repository = null
    ) {
        $this->definitions ??= new CustomFieldDefinitionRepository();
        $this->repository ??= new CustomFieldMetadataRepository();
    }

    /** @return array<string,mixed> */
    public function get(int $definitionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $definition = $this->requireDefinition($definitionId, false);
        $dataType = (string) ($definition['data_type'] ?? '');
        $row = $this->repository->find($definitionId);
        if ($row === null) {
            return $this->decorate($definitionId, $dataType, CustomFieldMetadataPolicy::defaults($dataType), true);
        }
        return $this->hydrate($definitionId, $dataType, $row);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function upsert(int $definitionId, array $input): array
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $definition = $this->requireDefinition($definitionId, true);
        $dataType = (string) ($definition['data_type'] ?? '');
        $normalized = CustomFieldMetadataPolicy::normalize($dataType, $input);
        $existing = $this->repository->find($definitionId);
        if ($existing !== null) {
            $hydrated = $this->hydrate($definitionId, $dataType, $existing);
            if ($this->sameMetadata($hydrated, $normalized)) {
                return $hydrated;
            }
        } elseif ($this->sameMetadata($this->decorate($definitionId, $dataType, CustomFieldMetadataPolicy::defaults($dataType), true), $normalized)) {
            return $this->decorate($definitionId, $dataType, $normalized, true);
        }

        $actorId = get_current_user_id();
        $this->repository->upsert($definitionId, $dataType, $normalized, $actorId);
        do_action('safecontracts_enterprise_custom_field_metadata_updated', $definitionId, $actorId);
        return $this->decorate($definitionId, $dataType, $normalized, false);
    }

    /** @return array<string,mixed> */
    public function reset(int $definitionId): array
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $definition = $this->requireDefinition($definitionId, true);
        $dataType = (string) ($definition['data_type'] ?? '');
        $existing = $this->repository->find($definitionId);
        $defaults = CustomFieldMetadataPolicy::defaults($dataType);
        if ($existing === null) {
            return $this->decorate($definitionId, $dataType, $defaults, true);
        }
        $this->repository->reset($definitionId, $dataType);
        $actorId = get_current_user_id();
        do_action('safecontracts_enterprise_custom_field_metadata_reset', $definitionId, $actorId);
        return $this->decorate($definitionId, $dataType, $defaults, true);
    }

    /** @return array<string,mixed> */
    private function requireDefinition(int $definitionId, bool $requireActive): array
    {
        if ($definitionId <= 0) {
            throw new InvalidArgumentException('Dynamic Field definition ID must be positive.');
        }
        $definition = $this->definitions->find($definitionId);
        if ($definition === null) {
            throw new InvalidArgumentException('Dynamic Field definition was not found in the current tenant.');
        }
        if ($requireActive && (string) ($definition['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('Dynamic Field definition must be active for metadata mutation.');
        }
        return $definition;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrate(int $definitionId, string $dataType, array $row): array
    {
        if ((string) ($row['data_type_snapshot'] ?? '') !== $dataType) {
            throw new RuntimeException('Stored Dynamic Field metadata data type snapshot does not match the definition.');
        }
        $metadata = [
            'show_in_form' => (int) ($row['show_in_form'] ?? 0) === 1,
            'show_in_summary' => (int) ($row['show_in_summary'] ?? 0) === 1,
            'show_in_mobile' => (int) ($row['show_in_mobile'] ?? 0) === 1,
            'show_in_print' => (int) ($row['show_in_print'] ?? 0) === 1,
            'filterable' => (int) ($row['filterable'] ?? 0) === 1,
            'sortable' => (int) ($row['sortable'] ?? 0) === 1,
            'groupable' => (int) ($row['groupable'] ?? 0) === 1,
            'exportable' => (int) ($row['exportable'] ?? 0) === 1,
            'dashboard_visible' => (int) ($row['dashboard_visible'] ?? 0) === 1,
            'report_label' => trim((string) ($row['report_label'] ?? '')),
            'report_data_class' => (string) ($row['report_data_class'] ?? ''),
            'aggregation_policy' => (string) ($row['aggregation_policy'] ?? ''),
        ];
        CustomFieldMetadataPolicy::assertCompatible($dataType, $metadata);
        return $this->decorate($definitionId, $dataType, $metadata, false);
    }

    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    private function decorate(int $definitionId, string $dataType, array $metadata, bool $isDefault): array
    {
        return array_merge([
            'definition_id' => $definitionId,
            'data_type' => $dataType,
            'is_default' => $isDefault,
        ], $metadata);
    }

    /** @param array<string,mixed> $actual @param array<string,mixed> $expected */
    private function sameMetadata(array $actual, array $expected): bool
    {
        foreach (array_keys(CustomFieldMetadataPolicy::defaults((string) ($actual['data_type'] ?? 'text'))) as $key) {
            if (($actual[$key] ?? null) !== ($expected[$key] ?? null)) {
                return false;
            }
        }
        return true;
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Dynamic Field metadata access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Dynamic Field metadata operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }
}
