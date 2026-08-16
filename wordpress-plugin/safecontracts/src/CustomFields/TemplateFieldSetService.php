<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\ContractTemplates\ContractTemplatePolicy;
use SafeContracts\ContractTemplates\ContractTemplateRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class TemplateFieldSetService
{
    public const MAX_FIELDS = 200;

    public function __construct(
        private ?ContractTemplateRepository $templates = null,
        private ?CustomFieldDefinitionRepository $definitions = null,
        private ?TemplateFieldSetRepository $repository = null
    ) {
        $this->templates ??= new ContractTemplateRepository();
        $this->definitions ??= new CustomFieldDefinitionRepository();
        $this->repository ??= new TemplateFieldSetRepository();
    }

    /** @return list<array<string,mixed>> */
    public function list(int $templateId, int $versionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireTemplateAndVersion($templateId, $versionId, false);
        return array_map([$this, 'hydrate'], $this->repository->list($templateId, $versionId));
    }

    /**
     * @param list<array{definition_id:int,required_override?:bool|null}> $items
     */
    public function replace(int $templateId, int $versionId, array $items): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $context = $this->requireTemplateAndVersion($templateId, $versionId, true);
        if (count($items) > self::MAX_FIELDS) {
            throw new InvalidArgumentException('Template Dynamic Field set exceeds the maximum field count.');
        }
        if (! array_is_list($items)) {
            throw new InvalidArgumentException('Template Dynamic Field set must be an ordered list.');
        }

        $contractTypeId = (int) ($context['template']['contract_type_id'] ?? 0);
        $seen = [];
        $snapshots = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Each Template Dynamic Field item must be an object.');
            }
            foreach (array_keys($item) as $key) {
                if (! in_array($key, ['definition_id', 'required_override'], true)) {
                    throw new InvalidArgumentException('Unsupported Template Dynamic Field item property.');
                }
            }
            $definitionId = (int) ($item['definition_id'] ?? 0);
            if ($definitionId <= 0) {
                throw new InvalidArgumentException('Dynamic Field definition ID must be positive.');
            }
            if (isset($seen[$definitionId])) {
                throw new InvalidArgumentException('Template Dynamic Field set cannot contain duplicate definitions.');
            }
            $seen[$definitionId] = true;

            $requiredOverride = null;
            if (array_key_exists('required_override', $item) && $item['required_override'] !== null) {
                if (! is_bool($item['required_override'])) {
                    throw new InvalidArgumentException('Template Dynamic Field required override must be boolean or null.');
                }
                $requiredOverride = $item['required_override'];
            }

            $definition = $this->definitions->find($definitionId);
            if ($definition === null) {
                throw new InvalidArgumentException('Dynamic Field definition was not found in the current tenant.');
            }
            if ((int) ($definition['contract_type_id'] ?? 0) !== $contractTypeId) {
                throw new InvalidArgumentException('Dynamic Field definition does not belong to the Template Contract Type.');
            }
            if ((string) ($definition['status'] ?? '') !== 'active') {
                throw new InvalidArgumentException('Only active Dynamic Field definitions can be attached to a draft Template Version.');
            }

            $snapshots[] = [
                'definition_id' => $definitionId,
                'position_no' => $index + 1,
                'field_code_snapshot' => (string) ($definition['field_code'] ?? ''),
                'data_type_snapshot' => (string) ($definition['data_type'] ?? ''),
                'label_snapshot' => (string) ($definition['label'] ?? ''),
                'help_text_snapshot' => (string) ($definition['help_text'] ?? ''),
                'definition_required_snapshot' => (int) ($definition['is_required'] ?? 0) === 1 ? 1 : 0,
                'required_override' => $requiredOverride === null ? null : ($requiredOverride ? 1 : 0),
                'options_json_snapshot' => trim((string) ($definition['options_json'] ?? '')),
                'validation_json_snapshot' => trim((string) ($definition['validation_json'] ?? '')),
                'definition_config_hash' => CustomFieldValuePolicy::configurationHash($definition),
            ];
        }

        $actorId = get_current_user_id();
        $this->repository->replaceDraftFieldSet($templateId, $versionId, $contractTypeId, $snapshots, $actorId);
        do_action('safecontracts_enterprise_template_field_set_replaced', $templateId, $versionId, count($snapshots), $actorId);
    }

    /** @return array{template:array<string,mixed>,version:array<string,mixed>} */
    private function requireTemplateAndVersion(int $templateId, int $versionId, bool $requireDraft): array
    {
        if ($templateId <= 0 || $versionId <= 0) {
            throw new InvalidArgumentException('Contract Template and version IDs must be positive.');
        }
        $template = $this->templates->findTemplate($templateId);
        if ($template === null) {
            throw new InvalidArgumentException('Contract Template was not found in the current tenant.');
        }
        if ($requireDraft && (string) ($template['status'] ?? '') !== ContractTemplatePolicy::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Contract Template must be active for Dynamic Field authoring.');
        }
        $version = $this->templates->findVersion($templateId, $versionId);
        if ($version === null) {
            throw new InvalidArgumentException('Contract Template Version was not found in the current tenant/template.');
        }
        if ($requireDraft && (string) ($version['version_status'] ?? '') !== ContractTemplatePolicy::VERSION_DRAFT) {
            throw new InvalidArgumentException('Published Contract Template Version field sets are immutable.');
        }
        return ['template' => $template, 'version' => $version];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrate(array $row): array
    {
        $override = $row['required_override'] ?? null;
        $baseRequired = (int) ($row['definition_required_snapshot'] ?? 0) === 1;
        $row['effective_required'] = $override === null ? $baseRequired : ((int) $override === 1);
        $row['definition_required_snapshot'] = $baseRequired;
        $row['required_override'] = $override === null ? null : ((int) $override === 1);
        return $row;
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Template Dynamic Field access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Template Dynamic Field operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }
}
