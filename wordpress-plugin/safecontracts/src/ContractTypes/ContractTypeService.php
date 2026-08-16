<?php

declare(strict_types=1);

namespace SafeContracts\ContractTypes;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractTypeService
{
    /** @var list<string> */
    private const CREATE_FIELDS = ['type_code', 'name', 'description', 'category', 'status', 'metadata'];
    /** @var list<string> */
    private const UPDATE_FIELDS = ['name', 'description', 'category', 'metadata'];

    public function __construct(private ?ContractTypeRepository $repository = null)
    {
        $this->repository ??= new ContractTypeRepository();
    }

    public function find(int $typeId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($typeId);
        return $this->repository->find($typeId);
    }

    /** @return list<array<string,mixed>> */
    public function search(string $search = '', string $status = '', int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $search = trim(strip_tags($search));
        if (strlen($search) > 191) {
            throw new InvalidArgumentException('Contract Type search must not exceed 191 characters.');
        }
        $status = trim($status);
        if ($status !== '') {
            $status = ContractTypePolicy::normalizeStatus($status);
        }
        return $this->repository->search($search, $status, $limit, $offset);
    }

    public function create(array $input): int
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->rejectUnsupportedFields($input, self::CREATE_FIELDS);
        $data = $this->normalizeCreate($input);
        $actorId = get_current_user_id();
        $typeId = $this->repository->create($data, $this->uuid(), $actorId);
        do_action('safecontracts_enterprise_contract_type_created', $typeId, $data['type_code'], $actorId);
        return $typeId;
    }

    public function update(int $typeId, array $changes): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requirePositive($typeId);
        $this->rejectUnsupportedFields($changes, self::UPDATE_FIELDS);
        $existing = $this->repository->find($typeId);
        if ($existing === null) {
            throw new InvalidArgumentException('Contract Type was not found in the current tenant.');
        }

        $data = [
            'name' => array_key_exists('name', $changes)
                ? $this->text($changes['name'], 191, true, 'Contract Type name')
                : (string) ($existing['name'] ?? ''),
            'description' => array_key_exists('description', $changes)
                ? $this->text($changes['description'], 5000, false, 'Contract Type description')
                : (string) ($existing['description'] ?? ''),
            'category' => array_key_exists('category', $changes)
                ? $this->text($changes['category'], 100, false, 'Contract Type category')
                : (string) ($existing['category'] ?? ''),
            'metadata_json' => array_key_exists('metadata', $changes)
                ? $this->metadata($changes['metadata'])
                : (string) ($existing['metadata_json'] ?? ''),
        ];

        $actorId = get_current_user_id();
        $this->repository->updateMetadata($typeId, $data, $actorId);
        do_action('safecontracts_enterprise_contract_type_updated', $typeId, $actorId);
    }

    public function deactivate(int $typeId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requirePositive($typeId);
        if ($this->repository->find($typeId) === null) {
            throw new InvalidArgumentException('Contract Type was not found in the current tenant.');
        }
        $actorId = get_current_user_id();
        $this->repository->deactivate($typeId, $actorId);
        do_action('safecontracts_enterprise_contract_type_deactivated', $typeId, $actorId);
    }

    /** @return array{type_code:string,name:string,description:string,category:string,status:string,metadata_json:string} */
    private function normalizeCreate(array $input): array
    {
        return [
            'type_code' => ContractTypePolicy::normalizeCode((string) ($input['type_code'] ?? '')),
            'name' => $this->text($input['name'] ?? '', 191, true, 'Contract Type name'),
            'description' => $this->text($input['description'] ?? '', 5000, false, 'Contract Type description'),
            'category' => $this->text($input['category'] ?? '', 100, false, 'Contract Type category'),
            'status' => ContractTypePolicy::normalizeStatus((string) ($input['status'] ?? ContractTypePolicy::STATUS_ACTIVE)),
            'metadata_json' => $this->metadata($input['metadata'] ?? []),
        ];
    }

    private function metadata(mixed $metadata): string
    {
        if (! is_array($metadata)) {
            throw new InvalidArgumentException('Contract Type metadata must be an object/array.');
        }
        $json = $metadata === [] ? '' : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException('Contract Type metadata could not be encoded.');
        }
        if (strlen($json) > 20000) {
            throw new InvalidArgumentException('Contract Type metadata must not exceed 20000 encoded bytes.');
        }
        return $json;
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
                throw new InvalidArgumentException('Unsupported Contract Type field.');
            }
        }
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Type access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract Type operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }

    private function requirePositive(int $typeId): void
    {
        if ($typeId <= 0) {
            throw new InvalidArgumentException('Contract Type ID must be positive.');
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
            throw new RuntimeException('Unable to generate Contract Type UUID.', 0, $error);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
