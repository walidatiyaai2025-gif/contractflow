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

final class CustomFieldDefinitionService
{
    /** @var list<string> */
    private const CREATE_FIELDS = ['contract_type_id', 'field_code', 'data_type', 'label', 'help_text', 'is_required', 'status', 'sort_order', 'options', 'validation'];
    /** @var list<string> */
    private const UPDATE_FIELDS = ['label', 'help_text', 'is_required', 'sort_order', 'options', 'validation'];

    public function __construct(private ?CustomFieldDefinitionRepository $repository = null)
    {
        $this->repository ??= new CustomFieldDefinitionRepository();
    }

    public function find(int $definitionId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($definitionId, 'Custom Field definition ID');
        return $this->repository->find($definitionId);
    }

    /** @return list<array<string,mixed>> */
    public function search(int $contractTypeId = 0, string $search = '', string $status = '', int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        if ($contractTypeId < 0) {
            throw new InvalidArgumentException('Contract Type ID cannot be negative.');
        }
        if ($contractTypeId > 0 && $this->repository->findContractType($contractTypeId) === null) {
            throw new InvalidArgumentException('Contract Type was not found in the current tenant.');
        }
        $search = trim(strip_tags($search));
        if (strlen($search) > 191) {
            throw new InvalidArgumentException('Custom Field search must not exceed 191 characters.');
        }
        $status = trim($status);
        if ($status !== '') {
            $status = CustomFieldDefinitionPolicy::normalizeStatus($status);
        }
        return $this->repository->search($contractTypeId, $search, $status, $limit, $offset);
    }

    public function create(array $input): int
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->rejectUnsupportedFields($input, self::CREATE_FIELDS);
        if (array_key_exists('tenant_id', $input) || array_key_exists('uuid', $input) || array_key_exists('id', $input)) {
            throw new InvalidArgumentException('Custom Field ownership and identity are server-controlled.');
        }

        $contractTypeId = (int) ($input['contract_type_id'] ?? 0);
        $this->requirePositive($contractTypeId, 'Contract Type ID');
        $this->requireActiveContractType($contractTypeId);
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType((string) ($input['data_type'] ?? ''));
        $data = [
            'contract_type_id' => $contractTypeId,
            'field_code' => CustomFieldDefinitionPolicy::normalizeCode((string) ($input['field_code'] ?? '')),
            'data_type' => $dataType,
            'label' => $this->text($input['label'] ?? '', 191, true, 'Custom Field label'),
            'help_text' => $this->text($input['help_text'] ?? '', 5000, false, 'Custom Field help text'),
            'is_required' => $this->boolean($input['is_required'] ?? false, 'Custom Field required flag'),
            'status' => CustomFieldDefinitionPolicy::normalizeStatus((string) ($input['status'] ?? CustomFieldDefinitionPolicy::STATUS_ACTIVE)),
            'sort_order' => $this->sortOrder($input['sort_order'] ?? 0),
            'options_json' => CustomFieldDefinitionPolicy::encodeOptions($dataType, $input['options'] ?? []),
            'validation_json' => CustomFieldDefinitionPolicy::encodeValidation($dataType, $input['validation'] ?? []),
        ];
        $this->assertMultiSelectCountsFitOptions($dataType, $data['options_json'], $data['validation_json']);

        $actorId = get_current_user_id();
        $definitionId = $this->repository->create($data, $this->uuid(), $actorId);
        do_action('safecontracts_enterprise_custom_field_definition_created', $definitionId, $contractTypeId, $data['field_code'], $actorId);
        return $definitionId;
    }

    public function update(int $definitionId, array $changes): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requirePositive($definitionId, 'Custom Field definition ID');
        $this->rejectUnsupportedFields($changes, self::UPDATE_FIELDS);
        $existing = $this->repository->find($definitionId);
        if ($existing === null) {
            throw new InvalidArgumentException('Custom Field definition was not found in the current tenant.');
        }

        $contractTypeId = (int) ($existing['contract_type_id'] ?? 0);
        $this->requireActiveContractType($contractTypeId);
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType((string) ($existing['data_type'] ?? ''));
        $optionsJson = array_key_exists('options', $changes)
            ? CustomFieldDefinitionPolicy::encodeOptions($dataType, $changes['options'])
            : (string) ($existing['options_json'] ?? '');
        $validationJson = array_key_exists('validation', $changes)
            ? CustomFieldDefinitionPolicy::encodeValidation($dataType, $changes['validation'])
            : (string) ($existing['validation_json'] ?? '');
        $this->assertMultiSelectCountsFitOptions($dataType, $optionsJson, $validationJson);

        $data = [
            'label' => array_key_exists('label', $changes)
                ? $this->text($changes['label'], 191, true, 'Custom Field label')
                : (string) ($existing['label'] ?? ''),
            'help_text' => array_key_exists('help_text', $changes)
                ? $this->text($changes['help_text'], 5000, false, 'Custom Field help text')
                : (string) ($existing['help_text'] ?? ''),
            'is_required' => array_key_exists('is_required', $changes)
                ? $this->boolean($changes['is_required'], 'Custom Field required flag')
                : (int) ($existing['is_required'] ?? 0),
            'sort_order' => array_key_exists('sort_order', $changes)
                ? $this->sortOrder($changes['sort_order'])
                : (int) ($existing['sort_order'] ?? 0),
            'options_json' => $optionsJson,
            'validation_json' => $validationJson,
        ];

        $actorId = get_current_user_id();
        $this->repository->updateConfiguration($definitionId, $contractTypeId, $data, $actorId);
        do_action('safecontracts_enterprise_custom_field_definition_updated', $definitionId, $actorId);
    }

    public function deactivate(int $definitionId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requirePositive($definitionId, 'Custom Field definition ID');
        if ($this->repository->find($definitionId) === null) {
            throw new InvalidArgumentException('Custom Field definition was not found in the current tenant.');
        }
        $actorId = get_current_user_id();
        $this->repository->deactivate($definitionId, $actorId);
        do_action('safecontracts_enterprise_custom_field_definition_deactivated', $definitionId, $actorId);
    }

    private function requireActiveContractType(int $contractTypeId): array
    {
        $type = $this->repository->findContractType($contractTypeId);
        if ($type === null || (string) ($type['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('Custom Field authoring requires an active current-tenant Contract Type.');
        }
        return $type;
    }

    private function assertMultiSelectCountsFitOptions(string $dataType, string $optionsJson, string $validationJson): void
    {
        if ($dataType !== 'multi_select' || $validationJson === '') {
            return;
        }
        $options = json_decode($optionsJson, true);
        $validation = json_decode($validationJson, true);
        if (! is_array($options) || ! is_array($validation)) {
            throw new InvalidArgumentException('Custom Field configuration could not be decoded.');
        }
        $count = count($options);
        foreach (['min_items', 'max_items'] as $key) {
            if (array_key_exists($key, $validation) && (int) $validation[$key] > $count) {
                throw new InvalidArgumentException("Custom Field {$key} cannot exceed the configured option count.");
            }
        }
    }

    private function boolean(mixed $value, string $label): int
    {
        if ($value === true || $value === 1 || $value === '1') {
            return 1;
        }
        if ($value === false || $value === 0 || $value === '0') {
            return 0;
        }
        throw new InvalidArgumentException("{$label} must be boolean.");
    }

    private function sortOrder(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Custom Field sort order must be an integer.');
        }
        $value = (int) $value;
        if ($value < 0 || $value > 100000) {
            throw new InvalidArgumentException('Custom Field sort order must be between 0 and 100000.');
        }
        return $value;
    }

    private function text(mixed $value, int $max, bool $required, string $label): string
    {
        $value = trim(strip_tags((string) $value));
        if ($required && $value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} is too long.");
        }
        return $value;
    }

    /** @param list<string> $allowed */
    private function rejectUnsupportedFields(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Custom Field definition field.');
            }
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Custom Field definition access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Custom Field operation.');
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
            throw new RuntimeException('Unable to generate Custom Field UUID.', 0, $error);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
