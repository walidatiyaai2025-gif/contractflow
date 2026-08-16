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

final class CustomFieldVisibilityService
{
    public function __construct(
        private ?CustomFieldDefinitionRepository $definitions = null,
        private ?CustomFieldVisibilityRepository $repository = null
    ) {
        $this->definitions ??= new CustomFieldDefinitionRepository();
        $this->repository ??= new CustomFieldVisibilityRepository();
    }

    /** @return array<string,mixed> */
    public function getRule(int $targetDefinitionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $target = $this->requireDefinition($targetDefinitionId, false);
        $rule = $this->repository->findRule($targetDefinitionId);
        if ($rule === null) {
            return [
                'configured' => false,
                'target_definition_id' => $targetDefinitionId,
                'contract_type_id' => (int) ($target['contract_type_id'] ?? 0),
                'match_mode' => null,
                'conditions' => [],
            ];
        }
        $conditions = $this->repository->listConditions((int) ($rule['id'] ?? 0));
        if (count($conditions) > CustomFieldVisibilityPolicy::MAX_CONDITIONS) {
            throw new RuntimeException('Stored Dynamic Field visibility rule exceeds the condition limit.');
        }
        return [
            'configured' => true,
            'target_definition_id' => $targetDefinitionId,
            'contract_type_id' => (int) ($rule['contract_type_id'] ?? 0),
            'match_mode' => (string) ($rule['match_mode'] ?? ''),
            'conditions' => array_map(static function (array $row): array {
                return [
                    'position_no' => (int) ($row['position_no'] ?? 0),
                    'source_definition_id' => (int) ($row['source_definition_id'] ?? 0),
                    'operator' => (string) ($row['operator_code'] ?? ''),
                    'operand' => ($row['operand_json'] ?? null) === null
                        ? null
                        : CustomFieldValuePolicy::decodeStored((string) $row['operand_json']),
                ];
            }, $conditions),
        ];
    }

    /**
     * @param list<array<string,mixed>> $conditionsInput
     */
    public function replaceRule(int $targetDefinitionId, mixed $matchMode, array $conditionsInput): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $target = $this->requireDefinition($targetDefinitionId, true);
        $contractTypeId = (int) ($target['contract_type_id'] ?? 0);
        $matchMode = CustomFieldVisibilityPolicy::normalizeMatchMode($matchMode);
        if (! array_is_list($conditionsInput)) {
            throw new InvalidArgumentException('Dynamic Field visibility conditions must be an ordered list.');
        }
        $count = count($conditionsInput);
        if ($count < 1 || $count > CustomFieldVisibilityPolicy::MAX_CONDITIONS) {
            throw new InvalidArgumentException('Dynamic Field visibility rule must contain between 1 and 32 conditions.');
        }

        $conditions = [];
        $semanticSeen = [];
        $proposedSources = [];
        foreach ($conditionsInput as $index => $input) {
            if (! is_array($input)) {
                throw new InvalidArgumentException('Each Dynamic Field visibility condition must be an object.');
            }
            foreach (array_keys($input) as $key) {
                if (! is_string($key) || ! in_array($key, ['source_definition_id', 'operator', 'operand'], true)) {
                    throw new InvalidArgumentException('Unsupported Dynamic Field visibility condition property.');
                }
            }
            $sourceId = (int) ($input['source_definition_id'] ?? 0);
            if ($sourceId <= 0) {
                throw new InvalidArgumentException('Dynamic Field visibility source definition ID must be positive.');
            }
            if ($sourceId === $targetDefinitionId) {
                throw new InvalidArgumentException('Dynamic Field visibility rule cannot depend on itself.');
            }
            $source = $this->requireDefinition($sourceId, true);
            if ((int) ($source['contract_type_id'] ?? 0) !== $contractTypeId) {
                throw new InvalidArgumentException('Dynamic Field visibility source must belong to the target Contract Type.');
            }
            $operator = CustomFieldVisibilityPolicy::normalizeOperator((string) ($source['data_type'] ?? ''), $input['operator'] ?? null);
            $operandJson = CustomFieldVisibilityPolicy::canonicalizeOperand(
                $source,
                $operator,
                array_key_exists('operand', $input),
                $input['operand'] ?? null
            );
            $semanticKey = $sourceId . '|' . $operator . '|' . ($operandJson ?? '<null>');
            if (isset($semanticSeen[$semanticKey])) {
                throw new InvalidArgumentException('Duplicate Dynamic Field visibility condition is not allowed.');
            }
            $semanticSeen[$semanticKey] = true;
            $proposedSources[] = $sourceId;
            $conditions[] = [
                'position_no' => $index + 1,
                'source_definition_id' => $sourceId,
                'operator_code' => $operator,
                'operand_json' => $operandJson,
                'source_definition' => $source,
            ];
        }

        $this->assertAcyclic($contractTypeId, $targetDefinitionId, $proposedSources);
        $this->repository->replaceRule($target, $matchMode, $conditions, get_current_user_id());
        do_action('safecontracts_enterprise_custom_field_visibility_rule_replaced', $targetDefinitionId, $count, get_current_user_id());
    }

    public function resetRule(int $targetDefinitionId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $target = $this->requireDefinition($targetDefinitionId, true);
        if ($this->repository->findRule($targetDefinitionId) === null) {
            return;
        }
        $this->repository->resetRule($target);
        do_action('safecontracts_enterprise_custom_field_visibility_rule_reset', $targetDefinitionId, get_current_user_id());
    }

    /** @return array<string,mixed> */
    public function evaluate(int $contractId, int $targetDefinitionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        if ($contractId <= 0 || $targetDefinitionId <= 0) {
            throw new InvalidArgumentException('Contract and Dynamic Field definition IDs must be positive.');
        }
        $contract = $this->repository->findContract($contractId);
        if ($contract === null) {
            throw new InvalidArgumentException('Contract was not found in the current tenant.');
        }
        $this->assertContractScope($contract);
        $binding = $this->repository->findBinding($contractId);
        if ($binding === null) {
            throw new InvalidArgumentException('Contract has no Enterprise Contract Type binding.');
        }
        $boundTypeId = (int) ($binding['contract_type_id'] ?? 0);
        $target = $this->requireDefinition($targetDefinitionId, false);
        if ((int) ($target['contract_type_id'] ?? 0) !== $boundTypeId) {
            throw new InvalidArgumentException('Visibility target does not belong to the Contract bound Contract Type.');
        }
        if ((string) ($target['status'] ?? '') !== 'active') {
            return $this->invalidEvaluation($targetDefinitionId, 'inactive_target', 'Visibility target definition is inactive.');
        }

        $rule = $this->repository->findRule($targetDefinitionId);
        if ($rule === null) {
            return [
                'configured' => false,
                'valid' => true,
                'conditional_visible' => true,
                'status' => 'not_configured',
                'match_mode' => null,
                'evaluated_conditions' => 0,
                'conditions' => [],
                'diagnostics' => [],
            ];
        }
        if ((int) ($rule['contract_type_id'] ?? 0) !== $boundTypeId) {
            return $this->invalidEvaluation($targetDefinitionId, 'stale_rule', 'Visibility rule Contract Type no longer matches the contract.');
        }
        if ((string) ($rule['target_field_code_snapshot'] ?? '') !== (string) ($target['field_code'] ?? '')
            || (string) ($rule['target_data_type_snapshot'] ?? '') !== (string) ($target['data_type'] ?? '')
            || (string) ($rule['target_config_hash'] ?? '') !== CustomFieldValuePolicy::configurationHash($target)) {
            return $this->invalidEvaluation($targetDefinitionId, 'stale_rule', 'Visibility target definition configuration changed.');
        }

        try {
            $matchMode = CustomFieldVisibilityPolicy::normalizeMatchMode((string) ($rule['match_mode'] ?? ''));
        } catch (InvalidArgumentException $error) {
            return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', $error->getMessage());
        }
        $conditions = $this->repository->listConditions((int) ($rule['id'] ?? 0));
        if ($conditions === [] || count($conditions) > CustomFieldVisibilityPolicy::MAX_CONDITIONS) {
            return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', 'Visibility rule has an invalid condition count.');
        }

        $sourceDefinitions = [];
        $sourceIds = [];
        foreach ($conditions as $condition) {
            $sourceId = (int) ($condition['source_definition_id'] ?? 0);
            $currentId = (int) ($condition['current_source_id'] ?? 0);
            if ($sourceId <= 0 || $currentId !== $sourceId) {
                return $this->invalidEvaluation($targetDefinitionId, 'stale_rule', 'Visibility source definition no longer exists.');
            }
            $definition = [
                'id' => $sourceId,
                'contract_type_id' => (int) ($condition['current_contract_type_id'] ?? 0),
                'field_code' => (string) ($condition['current_field_code'] ?? ''),
                'data_type' => (string) ($condition['current_data_type'] ?? ''),
                'status' => (string) ($condition['current_status'] ?? ''),
                'options_json' => trim((string) ($condition['current_options_json'] ?? '')),
                'validation_json' => trim((string) ($condition['current_validation_json'] ?? '')),
            ];
            if ($definition['status'] !== 'active' || $definition['contract_type_id'] !== $boundTypeId
                || (string) ($condition['source_field_code_snapshot'] ?? '') !== $definition['field_code']
                || (string) ($condition['source_data_type_snapshot'] ?? '') !== $definition['data_type']
                || (string) ($condition['source_config_hash'] ?? '') !== CustomFieldValuePolicy::configurationHash($definition)) {
                return $this->invalidEvaluation($targetDefinitionId, 'stale_rule', 'Visibility source definition configuration changed.');
            }
            $sourceDefinitions[$sourceId] = $definition;
            $sourceIds[] = $sourceId;
        }

        $values = $this->repository->listValues($contractId, $sourceIds);
        $conditionResults = [];
        foreach ($conditions as $condition) {
            $sourceId = (int) ($condition['source_definition_id'] ?? 0);
            $definition = $sourceDefinitions[$sourceId];
            $valueRow = $values[$sourceId] ?? null;
            $isSet = is_array($valueRow) && (int) ($valueRow['is_set'] ?? 0) === 1;
            $valueJson = $isSet ? (string) ($valueRow['value_json'] ?? '') : null;
            if ($isSet) {
                if ((string) ($valueRow['data_type_snapshot'] ?? '') !== $definition['data_type']
                    || (string) ($valueRow['definition_config_hash'] ?? '') !== CustomFieldValuePolicy::configurationHash($definition)) {
                    return $this->invalidEvaluation($targetDefinitionId, 'stale_value', 'Visibility source value was validated under stale field configuration.');
                }
            }
            $operator = (string) ($condition['operator_code'] ?? '');
            $operandJson = ($condition['operand_json'] ?? null) === null ? null : (string) $condition['operand_json'];
            try {
                $canonicalOperand = null;
                if ($operandJson !== null) {
                    $decodedOperand = CustomFieldValuePolicy::decodeStored($operandJson);
                    $canonicalOperand = CustomFieldVisibilityPolicy::canonicalizeOperand($definition, $operator, true, $decodedOperand);
                    if ($canonicalOperand !== $operandJson) {
                        return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', 'Visibility condition operand is noncanonical.');
                    }
                } else {
                    $canonicalOperand = CustomFieldVisibilityPolicy::canonicalizeOperand($definition, $operator, false, null);
                }
                $matched = CustomFieldVisibilityPolicy::evaluate($definition, $operator, $isSet, $valueJson, $canonicalOperand);
            } catch (InvalidArgumentException $error) {
                return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', $error->getMessage());
            }
            $conditionResults[] = [
                'position_no' => (int) ($condition['position_no'] ?? 0),
                'source_definition_id' => $sourceId,
                'operator' => $operator,
                'matched' => $matched,
                'is_set' => $isSet,
            ];
        }

        $matches = array_map(static fn (array $result): bool => (bool) $result['matched'], $conditionResults);
        $visible = $matchMode === 'all'
            ? ! in_array(false, $matches, true)
            : in_array(true, $matches, true);

        return [
            'configured' => true,
            'valid' => true,
            'conditional_visible' => $visible,
            'status' => $visible ? 'matched' : 'not_matched',
            'match_mode' => $matchMode,
            'evaluated_conditions' => count($conditionResults),
            'conditions' => $conditionResults,
            'diagnostics' => [],
        ];
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
            throw new InvalidArgumentException('Dynamic Field definition must be active for visibility authoring.');
        }
        return $definition;
    }

    /** @param list<int> $proposedSources */
    private function assertAcyclic(int $contractTypeId, int $targetDefinitionId, array $proposedSources): void
    {
        $edges = $this->repository->listDependencyEdges($contractTypeId, CustomFieldVisibilityPolicy::MAX_GRAPH_EDGES + 1);
        if (count($edges) > CustomFieldVisibilityPolicy::MAX_GRAPH_EDGES) {
            throw new RuntimeException('Dynamic Field visibility dependency graph exceeds the bounded edge limit.');
        }
        $graph = [];
        foreach ($edges as $edge) {
            $target = (int) ($edge['target_definition_id'] ?? 0);
            $source = (int) ($edge['source_definition_id'] ?? 0);
            if ($target <= 0 || $source <= 0 || $target === $targetDefinitionId) {
                continue;
            }
            $graph[$target][] = $source;
        }
        $graph[$targetDefinitionId] = array_values(array_unique($proposedSources));

        $nodes = [];
        foreach ($graph as $target => $sources) {
            $nodes[(int) $target] = true;
            foreach ($sources as $source) {
                $nodes[(int) $source] = true;
            }
        }
        if (count($nodes) > CustomFieldVisibilityPolicy::MAX_GRAPH_NODES) {
            throw new RuntimeException('Dynamic Field visibility dependency graph exceeds the bounded node limit.');
        }

        $state = [];
        $visit = function (int $node, int $depth) use (&$visit, &$state, $graph): void {
            if ($depth > CustomFieldVisibilityPolicy::MAX_GRAPH_DEPTH) {
                throw new RuntimeException('Dynamic Field visibility dependency graph exceeds the bounded depth limit.');
            }
            if (($state[$node] ?? 0) === 1) {
                throw new InvalidArgumentException('Dynamic Field visibility dependency cycle is not allowed.');
            }
            if (($state[$node] ?? 0) === 2) {
                return;
            }
            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $source) {
                $visit((int) $source, $depth + 1);
            }
            $state[$node] = 2;
        };
        foreach (array_keys($nodes) as $node) {
            $visit((int) $node, 1);
        }
    }

    /** @param array<string,mixed> $contract */
    private function assertContractScope(array $contract): void
    {
        $canViewAll = current_user_can(Capabilities::VIEW_ALL) && TenantAuthorization::allowsCapability(Capabilities::VIEW_ALL);
        if ($canViewAll) {
            return;
        }
        $canViewAssigned = current_user_can(Capabilities::VIEW_ASSIGNED) && TenantAuthorization::allowsCapability(Capabilities::VIEW_ASSIGNED);
        if (! $canViewAssigned || (int) ($contract['accountant_user_id'] ?? 0) !== get_current_user_id()) {
            throw new DomainException('The current user cannot access this contract visibility context.');
        }
    }

    /** @return array<string,mixed> */
    private function invalidEvaluation(int $targetDefinitionId, string $status, string $message): array
    {
        return [
            'configured' => true,
            'valid' => false,
            'conditional_visible' => false,
            'status' => $status,
            'match_mode' => null,
            'evaluated_conditions' => 0,
            'conditions' => [],
            'diagnostics' => [[
                'code' => $status,
                'target_definition_id' => $targetDefinitionId,
                'message' => $message,
            ]],
        ];
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Dynamic Field visibility access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Dynamic Field visibility operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }
}
