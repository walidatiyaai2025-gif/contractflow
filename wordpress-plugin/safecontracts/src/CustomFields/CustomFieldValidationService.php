<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomFieldValidationService
{
    private const MAX_SCAN = 500;

    public function __construct(private ?CustomFieldValidationRepository $repository = null)
    {
        $this->repository ??= new CustomFieldValidationRepository();
    }

    /**
     * @return array{
     *   ready:bool,
     *   error_count:int,
     *   warning_count:int,
     *   definition_count:int,
     *   set_value_count:int,
     *   issues:list<array<string,mixed>>
     * }
     */
    public function validateContract(int $contractId): array
    {
        $this->authorize();
        $contract = $this->requireContract($contractId);
        $this->assertScope($contract);
        $binding = $this->repository->findBinding($contractId);
        if ($binding === null || (int) ($binding['contract_type_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Dynamic Field validation requires an Enterprise Contract Type binding.');
        }
        $contractTypeId = (int) $binding['contract_type_id'];

        $definitions = $this->repository->listActiveDefinitions($contractTypeId, self::MAX_SCAN + 1);
        $values = $this->repository->listSetValuesWithDefinitions($contractId, self::MAX_SCAN + 1);

        $issues = [];
        if (count($definitions) > self::MAX_SCAN) {
            $issues[] = $this->issue('validation_limit_exceeded', 'error', null, '', 'Active Dynamic Field definition count exceeds the bounded validation limit.');
            $definitions = array_slice($definitions, 0, self::MAX_SCAN);
        }
        if (count($values) > self::MAX_SCAN) {
            $issues[] = $this->issue('validation_limit_exceeded', 'error', null, '', 'Set Dynamic Field value count exceeds the bounded validation limit.');
            $values = array_slice($values, 0, self::MAX_SCAN);
        }

        $setDefinitionIds = [];
        foreach ($values as $valueRow) {
            $definitionId = (int) ($valueRow['definition_id'] ?? 0);
            if ($definitionId > 0) {
                $setDefinitionIds[$definitionId] = true;
            }
        }

        foreach ($definitions as $definition) {
            $definitionId = (int) ($definition['id'] ?? 0);
            if ((int) ($definition['is_required'] ?? 0) === 1 && ! isset($setDefinitionIds[$definitionId])) {
                $issues[] = $this->issue(
                    'missing_required',
                    'error',
                    $definitionId,
                    (string) ($definition['field_code'] ?? ''),
                    'Required Dynamic Field has no set value.'
                );
            }
        }

        foreach ($values as $row) {
            $definitionId = (int) ($row['definition_id'] ?? 0);
            $fieldCode = (string) ($row['field_code'] ?? '');
            $currentDefinitionId = (int) ($row['current_definition_id'] ?? 0);
            if ($currentDefinitionId <= 0) {
                $issues[] = $this->issue('orphan_value', 'error', $definitionId, '', 'Set Dynamic Field value references a definition that no longer exists in the tenant.');
                continue;
            }

            $currentTypeId = (int) ($row['current_contract_type_id'] ?? 0);
            if ($currentTypeId !== $contractTypeId) {
                $issues[] = $this->issue('orphan_value', 'error', $definitionId, $fieldCode, 'Set Dynamic Field value belongs to a definition outside the contract bound Contract Type.');
                continue;
            }

            if ((string) ($row['definition_status'] ?? '') !== CustomFieldDefinitionPolicy::STATUS_ACTIVE) {
                $issues[] = $this->issue('orphan_value', 'warning', $definitionId, $fieldCode, 'Set Dynamic Field value references an inactive historical definition.');
                continue;
            }

            $currentDataType = (string) ($row['data_type'] ?? '');
            if ((string) ($row['data_type_snapshot'] ?? '') !== $currentDataType) {
                $issues[] = $this->issue('type_snapshot_mismatch', 'error', $definitionId, $fieldCode, 'Stored Dynamic Field data type snapshot does not match the current definition.');
            }

            $definition = [
                'id' => $definitionId,
                'contract_type_id' => $currentTypeId,
                'field_code' => $fieldCode,
                'data_type' => $currentDataType,
                'options_json' => (string) ($row['options_json'] ?? ''),
                'validation_json' => (string) ($row['validation_json'] ?? ''),
            ];
            $currentHash = CustomFieldValuePolicy::configurationHash($definition);
            if ((string) ($row['definition_config_hash'] ?? '') !== $currentHash) {
                $issues[] = $this->issue('stale_configuration', 'warning', $definitionId, $fieldCode, 'Stored Dynamic Field value was validated against an older definition configuration.');
            }

            $storedJson = (string) ($row['value_json'] ?? '');
            try {
                $decoded = CustomFieldValuePolicy::decodeStored($storedJson);
                $canonical = CustomFieldValuePolicy::canonicalize($definition, $decoded);
            } catch (\Throwable $error) {
                $issues[] = $this->issue('invalid_value', 'error', $definitionId, $fieldCode, 'Stored Dynamic Field value is invalid under the current definition.');
                continue;
            }

            if ($canonical['value_json'] !== $storedJson) {
                $issues[] = $this->issue('noncanonical_value', 'warning', $definitionId, $fieldCode, 'Stored Dynamic Field value validates but is not in canonical representation.');
            }
        }

        $errors = 0;
        $warnings = 0;
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'error') {
                $errors++;
            } elseif (($issue['severity'] ?? '') === 'warning') {
                $warnings++;
            }
        }

        return [
            'ready' => $errors === 0,
            'error_count' => $errors,
            'warning_count' => $warnings,
            'definition_count' => count($definitions),
            'set_value_count' => count($values),
            'issues' => $issues,
        ];
    }

    private function requireContract(int $contractId): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }
        $contract = $this->repository->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found in the current Enterprise tenant.');
        }
        return $contract;
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

    private function authorize(): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new \RuntimeException('Enterprise Dynamic Field validation requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can(Capabilities::ACCESS) || ! TenantAuthorization::allowsCapability(Capabilities::ACCESS)) {
            throw new DomainException('The current tenant role does not allow Dynamic Field validation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }

    /** @return array{code:string,severity:string,definition_id:?int,field_code:string,message:string} */
    private function issue(string $code, string $severity, ?int $definitionId, string $fieldCode, string $message): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'definition_id' => $definitionId,
            'field_code' => $fieldCode,
            'message' => $message,
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }
}
