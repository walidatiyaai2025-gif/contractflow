<?php

declare(strict_types=1);

namespace SafeContracts\Organizations;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;

final class OrgUnitService
{
    /** @var list<string> */
    private const MUTATION_FIELDS = [
        'id',
        'unit_code',
        'name',
        'unit_type',
        'parent_unit_id',
        'status',
        'metadata',
    ];

    public function __construct(private ?OrgUnitRepository $repository = null)
    {
        $this->repository ??= new OrgUnitRepository();
    }

    public function find(int $unitId): ?array
    {
        $this->requireReadPermission();
        if ($unitId <= 0) {
            throw new InvalidArgumentException('Organization unit ID must be positive.');
        }
        return $this->repository->find($unitId);
    }

    /** @return list<array<string,mixed>> */
    public function search(string $search = '', int $limit = 50, int $offset = 0): array
    {
        $this->requireReadPermission();
        $search = trim(strip_tags($search));
        if (strlen($search) > 191) {
            throw new InvalidArgumentException('Organization unit search must not exceed 191 characters.');
        }
        return $this->repository->search($search, $limit, $offset);
    }

    public function save(array $input): int
    {
        $this->requireWritePermission();
        $this->rejectUnsupportedFields($input);

        $unitId = 0;
        if (array_key_exists('id', $input)) {
            $unitId = (int) $input['id'];
            if ($unitId <= 0) {
                throw new InvalidArgumentException('Organization unit ID must be positive when supplied.');
            }
        }

        $data = $this->normalize($input);
        $actorId = get_current_user_id();

        if ($unitId > 0) {
            if ($this->repository->find($unitId) === null) {
                throw new InvalidArgumentException('Organization unit was not found in the current tenant.');
            }
            $this->assertParentIsSafe((int) $data['parent_unit_id'], $unitId);
            $this->repository->update($unitId, $data, $actorId);
            do_action('safecontracts_enterprise_org_unit_updated', $unitId, $actorId);
            return $unitId;
        }

        $this->assertParentIsSafe((int) $data['parent_unit_id'], 0);
        $unitId = $this->repository->create($data, $this->uuid(), $actorId);
        do_action('safecontracts_enterprise_org_unit_created', $unitId, $actorId);
        return $unitId;
    }

    public function deactivate(int $unitId): void
    {
        $this->requireWritePermission();
        if ($unitId <= 0) {
            throw new InvalidArgumentException('Organization unit ID must be positive.');
        }
        if ($this->repository->find($unitId) === null) {
            throw new InvalidArgumentException('Organization unit was not found in the current tenant.');
        }
        $actorId = get_current_user_id();
        $this->repository->deactivate($unitId, $actorId);
        do_action('safecontracts_enterprise_org_unit_deactivated', $unitId, $actorId);
    }

    private function assertParentIsSafe(int $parentUnitId, int $unitId): void
    {
        if ($parentUnitId <= 0) {
            return;
        }
        if ($unitId > 0 && $parentUnitId === $unitId) {
            throw new InvalidArgumentException('Organization unit cannot be its own parent.');
        }

        $seen = [];
        $currentId = $parentUnitId;
        for ($depth = 0; $depth < OrgUnitPolicy::MAX_HIERARCHY_DEPTH; $depth++) {
            if ($unitId > 0 && $currentId === $unitId) {
                throw new InvalidArgumentException('Organization unit parent change would create a hierarchy cycle.');
            }
            if (isset($seen[$currentId])) {
                throw new InvalidArgumentException('Existing organization hierarchy contains a cycle.');
            }
            $seen[$currentId] = true;

            $parent = $this->repository->find($currentId);
            if ($parent === null) {
                throw new InvalidArgumentException('Parent organization unit was not found in the current tenant.');
            }
            $nextId = (int) ($parent['parent_unit_id'] ?? 0);
            if ($nextId <= 0) {
                return;
            }
            $currentId = $nextId;
        }

        throw new InvalidArgumentException('Organization hierarchy exceeds the maximum supported depth.');
    }

    private function requireReadPermission(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to Enterprise organization units.');
        }
    }

    private function requireWritePermission(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            throw new DomainException('You do not have permission to manage Enterprise organization units.');
        }
    }

    private function rejectUnsupportedFields(array $input): void
    {
        foreach (array_keys($input) as $field) {
            if (! is_string($field) || ! in_array($field, self::MUTATION_FIELDS, true)) {
                throw new InvalidArgumentException('Unsupported organization unit mutation field.');
            }
        }
    }

    /** @return array{unit_code:string,name:string,unit_type:string,parent_unit_id:int,status:string,metadata_json:string} */
    private function normalize(array $input): array
    {
        $name = $this->text($input['name'] ?? '', 191, true, 'Organization unit name');
        $unitCode = $this->text($input['unit_code'] ?? '', 100, false, 'Organization unit code');
        $unitType = strtolower($this->text($input['unit_type'] ?? '', 32, true, 'Organization unit type'));
        if (! OrgUnitPolicy::isType($unitType)) {
            throw new InvalidArgumentException('Organization unit type is not supported.');
        }

        $status = strtolower($this->text($input['status'] ?? OrgUnitPolicy::STATUS_ACTIVE, 20, true, 'Organization unit status'));
        if (! OrgUnitPolicy::isStatus($status)) {
            throw new InvalidArgumentException('Organization unit status is not supported.');
        }

        $parentUnitId = 0;
        if (array_key_exists('parent_unit_id', $input) && $input['parent_unit_id'] !== null && trim((string) $input['parent_unit_id']) !== '') {
            $parentUnitId = (int) $input['parent_unit_id'];
            if ($parentUnitId <= 0) {
                throw new InvalidArgumentException('Parent organization unit ID must be positive when supplied.');
            }
        }

        $metadata = $input['metadata'] ?? [];
        if (! is_array($metadata)) {
            throw new InvalidArgumentException('Organization unit metadata must be an object/array.');
        }
        $metadataJson = $metadata === [] ? '' : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($metadataJson === false) {
            throw new InvalidArgumentException('Organization unit metadata could not be encoded.');
        }
        if (strlen($metadataJson) > 20000) {
            throw new InvalidArgumentException('Organization unit metadata must not exceed 20000 encoded bytes.');
        }

        return [
            'unit_code' => $unitCode,
            'name' => $name,
            'unit_type' => $unitType,
            'parent_unit_id' => $parentUnitId,
            'status' => $status,
            'metadata_json' => $metadataJson,
        ];
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
            throw new RuntimeException('Unable to generate organization unit UUID.', 0, $error);
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
