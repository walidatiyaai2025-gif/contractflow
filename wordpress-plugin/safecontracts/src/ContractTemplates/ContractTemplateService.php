<?php

declare(strict_types=1);

namespace SafeContracts\ContractTemplates;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\ContractTypes\ContractTypePolicy;
use SafeContracts\ContractTypes\ContractTypeRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractTemplateService
{
    /** @var list<string> */
    private const CREATE_FIELDS = ['contract_type_id', 'template_code', 'name', 'description', 'status'];
    /** @var list<string> */
    private const UPDATE_FIELDS = ['name', 'description'];

    public function __construct(
        private ?ContractTypeRepository $contractTypes = null,
        private ?ContractTemplateRepository $repository = null
    ) {
        $this->contractTypes ??= new ContractTypeRepository();
        $this->repository ??= new ContractTemplateRepository();
    }

    public function findTemplate(int $templateId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($templateId, 'Contract Template ID');
        return $this->repository->findTemplate($templateId);
    }

    /** @return list<array<string,mixed>> */
    public function searchTemplates(string $search = '', string $status = '', int $contractTypeId = 0, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $search = trim(strip_tags($search));
        if (strlen($search) > 191) {
            throw new InvalidArgumentException('Contract Template search must not exceed 191 characters.');
        }
        $status = trim($status);
        if ($status !== '') {
            $status = ContractTemplatePolicy::normalizeStatus($status);
        }
        if ($contractTypeId > 0) {
            $this->requireContractType($contractTypeId, false);
        }
        return $this->repository->searchTemplates($search, $status, $contractTypeId, $limit, $offset);
    }

    public function createTemplate(array $input): int
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->rejectUnsupportedFields($input, self::CREATE_FIELDS);
        $contractTypeId = (int) ($input['contract_type_id'] ?? 0);
        $this->requireContractType($contractTypeId, true);

        $data = [
            'contract_type_id' => $contractTypeId,
            'template_code' => ContractTemplatePolicy::normalizeCode((string) ($input['template_code'] ?? '')),
            'name' => $this->text($input['name'] ?? '', 191, true, 'Contract Template name'),
            'description' => $this->text($input['description'] ?? '', 5000, false, 'Contract Template description'),
            'status' => ContractTemplatePolicy::normalizeStatus((string) ($input['status'] ?? ContractTemplatePolicy::STATUS_ACTIVE)),
        ];
        $actorId = get_current_user_id();
        $templateId = $this->repository->createTemplate($data, $this->uuid(), $actorId);
        do_action('safecontracts_enterprise_contract_template_created', $templateId, $data['template_code'], $contractTypeId, $actorId);
        return $templateId;
    }

    public function updateTemplate(int $templateId, array $changes): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requirePositive($templateId, 'Contract Template ID');
        $this->rejectUnsupportedFields($changes, self::UPDATE_FIELDS);
        $template = $this->requireTemplate($templateId, false);
        $name = array_key_exists('name', $changes)
            ? $this->text($changes['name'], 191, true, 'Contract Template name')
            : (string) ($template['name'] ?? '');
        $description = array_key_exists('description', $changes)
            ? $this->text($changes['description'], 5000, false, 'Contract Template description')
            : (string) ($template['description'] ?? '');
        $actorId = get_current_user_id();
        $this->repository->updateTemplateMetadata($templateId, $name, $description, $actorId);
        do_action('safecontracts_enterprise_contract_template_updated', $templateId, $actorId);
    }

    public function deactivateTemplate(int $templateId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requirePositive($templateId, 'Contract Template ID');
        $this->requireTemplate($templateId, false);
        $actorId = get_current_user_id();
        $this->repository->deactivateTemplate($templateId, $actorId);
        do_action('safecontracts_enterprise_contract_template_deactivated', $templateId, $actorId);
    }

    public function findVersion(int $templateId, int $versionId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireTemplate($templateId, false);
        $this->requirePositive($versionId, 'Contract Template version ID');
        return $this->repository->findVersion($templateId, $versionId);
    }

    /** @return list<array<string,mixed>> */
    public function listVersions(int $templateId, int $limit = 50, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireTemplate($templateId, false);
        return $this->repository->listVersions($templateId, $limit, $offset);
    }

    public function createDraftVersion(int $templateId, mixed $definition, string $notes = ''): int
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $template = $this->requireTemplate($templateId, true);
        $this->requireContractType((int) ($template['contract_type_id'] ?? 0), true);
        $definitionJson = ContractTemplatePolicy::encodeDefinition($definition);
        $notes = $this->text($notes, 5000, false, 'Contract Template version notes');
        $actorId = get_current_user_id();
        $versionId = $this->repository->createDraftVersion($templateId, $definitionJson, $notes, $actorId);
        do_action('safecontracts_enterprise_contract_template_draft_created', $templateId, $versionId, $actorId);
        return $versionId;
    }

    public function updateDraftVersion(int $templateId, int $versionId, mixed $definition, string $notes = ''): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $template = $this->requireTemplate($templateId, true);
        $this->requireContractType((int) ($template['contract_type_id'] ?? 0), true);
        $this->requirePositive($versionId, 'Contract Template version ID');
        $version = $this->repository->findVersion($templateId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Contract Template version was not found in the current tenant/template.');
        }
        if ((string) ($version['version_status'] ?? '') !== ContractTemplatePolicy::VERSION_DRAFT) {
            throw new InvalidArgumentException('Published Contract Template versions are immutable.');
        }
        $definitionJson = ContractTemplatePolicy::encodeDefinition($definition);
        $notes = $this->text($notes, 5000, false, 'Contract Template version notes');
        $actorId = get_current_user_id();
        $this->repository->updateDraftVersion($templateId, $versionId, $definitionJson, $notes, $actorId);
        do_action('safecontracts_enterprise_contract_template_draft_updated', $templateId, $versionId, $actorId);
    }

    public function publishVersion(int $templateId, int $versionId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $template = $this->requireTemplate($templateId, true);
        $this->requireContractType((int) ($template['contract_type_id'] ?? 0), true);
        $this->requirePositive($versionId, 'Contract Template version ID');
        $version = $this->repository->findVersion($templateId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Contract Template version was not found in the current tenant/template.');
        }
        if ((string) ($version['version_status'] ?? '') !== ContractTemplatePolicy::VERSION_DRAFT) {
            throw new InvalidArgumentException('Only draft Contract Template versions can be published.');
        }
        $actorId = get_current_user_id();
        $this->repository->publishDraftVersion($templateId, $versionId, $actorId);
        do_action('safecontracts_enterprise_contract_template_version_published', $templateId, $versionId, $actorId);
    }

    private function requireContractType(int $contractTypeId, bool $requireActive): array
    {
        $this->requirePositive($contractTypeId, 'Contract Type ID');
        $type = $this->contractTypes->find($contractTypeId);
        if ($type === null) {
            throw new InvalidArgumentException('Contract Type was not found in the current tenant.');
        }
        if ($requireActive && (string) ($type['status'] ?? '') !== ContractTypePolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Contract Type must be active for Contract Template authoring/publishing.');
        }
        return $type;
    }

    private function requireTemplate(int $templateId, bool $requireActive): array
    {
        $this->requirePositive($templateId, 'Contract Template ID');
        $template = $this->repository->findTemplate($templateId);
        if ($template === null) {
            throw new InvalidArgumentException('Contract Template was not found in the current tenant.');
        }
        if ($requireActive && (string) ($template['status'] ?? '') !== ContractTemplatePolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Contract Template must be active for version authoring/publishing.');
        }
        return $template;
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Template access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Contract Template operation.');
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

    /** @param list<string> $allowed */
    private function rejectUnsupportedFields(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Contract Template field.');
            }
        }
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
            throw new RuntimeException('Unable to generate Contract Template UUID.', 0, $error);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
