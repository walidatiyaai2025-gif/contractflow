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

final class CustomFieldCalculationService
{
    public function __construct(
        private ?CustomFieldDefinitionRepository $definitions = null,
        private ?CustomFieldCalculationRepository $repository = null
    ) {
        $this->definitions ??= new CustomFieldDefinitionRepository();
        $this->repository ??= new CustomFieldCalculationRepository();
    }

    /** @return array<string,mixed> */
    public function getRule(int $targetDefinitionId): array
    {
        $this->authorize(Capabilities::ACCESS);
        $target = $this->requireNumericDefinition($targetDefinitionId, false);
        $rule = $this->repository->findRule($targetDefinitionId);
        if ($rule === null) {
            return [
                'configured' => false,
                'target_definition_id' => $targetDefinitionId,
                'contract_type_id' => (int) ($target['contract_type_id'] ?? 0),
                'expression' => null,
                'dependencies' => [],
            ];
        }
        $expressionJson = (string) ($rule['expression_json'] ?? '');
        $normalized = CustomFieldCalculationPolicy::normalizeExpression(
            CustomFieldCalculationPolicy::decodeExpression($expressionJson)
        );
        if ($normalized['expression_json'] !== $expressionJson) {
            throw new RuntimeException('Stored Dynamic Field calculation expression is noncanonical.');
        }
        $dependencies = $this->repository->listDependencies((int) ($rule['id'] ?? 0));
        if (count($dependencies) > CustomFieldCalculationPolicy::MAX_DEPENDENCIES) {
            throw new RuntimeException('Stored Dynamic Field calculation rule exceeds the dependency limit.');
        }
        $dependencyIds = array_map(static fn (array $row): int => (int) ($row['source_definition_id'] ?? 0), $dependencies);
        sort($dependencyIds, SORT_NUMERIC);
        if ($dependencyIds !== $normalized['dependencies']) {
            throw new RuntimeException('Stored Dynamic Field calculation dependencies do not match the canonical expression.');
        }
        return [
            'configured' => true,
            'target_definition_id' => $targetDefinitionId,
            'contract_type_id' => (int) ($rule['contract_type_id'] ?? 0),
            'expression' => $normalized['ast'],
            'dependencies' => $dependencyIds,
        ];
    }

    public function replaceRule(int $targetDefinitionId, mixed $expression): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $target = $this->requireNumericDefinition($targetDefinitionId, true);
        $contractTypeId = (int) ($target['contract_type_id'] ?? 0);
        $normalized = CustomFieldCalculationPolicy::normalizeExpression($expression);

        $sources = [];
        foreach ($normalized['dependencies'] as $sourceId) {
            if ($sourceId === $targetDefinitionId) {
                throw new InvalidArgumentException('Dynamic Field calculation rule cannot depend on itself.');
            }
            $source = $this->requireNumericDefinition($sourceId, true);
            if ((int) ($source['contract_type_id'] ?? 0) !== $contractTypeId) {
                throw new InvalidArgumentException('Dynamic Field calculation source must belong to the target Contract Type.');
            }
            $sources[] = $source;
        }
        usort($sources, static fn (array $left, array $right): int => (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));

        $this->assertAcyclic($contractTypeId, $targetDefinitionId, $normalized['dependencies']);
        $this->repository->replaceRule($target, $normalized['expression_json'], $sources, get_current_user_id());
        do_action('safecontracts_enterprise_custom_field_calculation_rule_replaced', $targetDefinitionId, count($sources), get_current_user_id());
    }

    public function resetRule(int $targetDefinitionId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $target = $this->requireNumericDefinition($targetDefinitionId, true);
        if ($this->repository->findRule($targetDefinitionId) === null) {
            return;
        }
        $this->repository->resetRule($target);
        do_action('safecontracts_enterprise_custom_field_calculation_rule_reset', $targetDefinitionId, get_current_user_id());
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
        $target = $this->requireNumericDefinition($targetDefinitionId, false);
        if ((int) ($target['contract_type_id'] ?? 0) !== $boundTypeId) {
            throw new InvalidArgumentException('Calculation target does not belong to the Contract bound Contract Type.');
        }
        if ((string) ($target['status'] ?? '') !== 'active') {
            return $this->invalidEvaluation($targetDefinitionId, 'inactive_target', 'Calculation target definition is inactive.');
        }

        $rule = $this->repository->findRule($targetDefinitionId);
        if ($rule === null) {
            return [
                'configured' => false,
                'valid' => true,
                'status' => 'not_configured',
                'result' => null,
                'target_data_type' => (string) ($target['data_type'] ?? ''),
                'dependencies' => [],
                'diagnostics' => [],
            ];
        }
        if ((int) ($rule['contract_type_id'] ?? 0) !== $boundTypeId
            || (string) ($rule['target_field_code_snapshot'] ?? '') !== (string) ($target['field_code'] ?? '')
            || (string) ($rule['target_data_type_snapshot'] ?? '') !== (string) ($target['data_type'] ?? '')
            || (string) ($rule['target_config_hash'] ?? '') !== CustomFieldValuePolicy::configurationHash($target)) {
            return $this->invalidEvaluation($targetDefinitionId, 'stale_rule', 'Calculation target definition configuration changed.');
        }

        $expressionJson = (string) ($rule['expression_json'] ?? '');
        try {
            $normalized = CustomFieldCalculationPolicy::normalizeExpression(
                CustomFieldCalculationPolicy::decodeExpression($expressionJson)
            );
        } catch (InvalidArgumentException $error) {
            return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', $error->getMessage());
        }
        if ($normalized['expression_json'] !== $expressionJson) {
            return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', 'Calculation expression is noncanonical.');
        }

        $dependencyRows = $this->repository->listDependencies((int) ($rule['id'] ?? 0));
        if (count($dependencyRows) > CustomFieldCalculationPolicy::MAX_DEPENDENCIES) {
            return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', 'Calculation rule exceeds the dependency limit.');
        }
        $sourceDefinitions = [];
        $dependencyIds = [];
        foreach ($dependencyRows as $index => $row) {
            $sourceId = (int) ($row['source_definition_id'] ?? 0);
            if ((int) ($row['position_no'] ?? 0) !== $index + 1 || $sourceId <= 0 || (int) ($row['current_source_id'] ?? 0) !== $sourceId) {
                return $this->invalidEvaluation($targetDefinitionId, 'stale_rule', 'Calculation dependency identity no longer matches its stored snapshot.');
            }
            $definition = [
                'id' => $sourceId,
                'contract_type_id' => (int) ($row['current_contract_type_id'] ?? 0),
                'field_code' => (string) ($row['current_field_code'] ?? ''),
                'data_type' => (string) ($row['current_data_type'] ?? ''),
                'status' => (string) ($row['current_status'] ?? ''),
                'options_json' => trim((string) ($row['current_options_json'] ?? '')),
                'validation_json' => trim((string) ($row['current_validation_json'] ?? '')),
            ];
            if ($definition['status'] !== 'active'
                || $definition['contract_type_id'] !== $boundTypeId
                || ! in_array($definition['data_type'], ['integer', 'decimal'], true)
                || (string) ($row['source_field_code_snapshot'] ?? '') !== $definition['field_code']
                || (string) ($row['source_data_type_snapshot'] ?? '') !== $definition['data_type']
                || (string) ($row['source_config_hash'] ?? '') !== CustomFieldValuePolicy::configurationHash($definition)) {
                return $this->invalidEvaluation($targetDefinitionId, 'stale_rule', 'Calculation source definition configuration changed.');
            }
            $sourceDefinitions[$sourceId] = $definition;
            $dependencyIds[] = $sourceId;
        }
        $sortedDependencyIds = $dependencyIds;
        sort($sortedDependencyIds, SORT_NUMERIC);
        if ($sortedDependencyIds !== $normalized['dependencies'] || count($dependencyIds) !== count(array_unique($dependencyIds))) {
            return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', 'Calculation dependency rows do not match the canonical expression.');
        }

        $sourceValues = [];
        if ($normalized['dependencies'] !== []) {
            $values = $this->repository->listValues($contractId, $normalized['dependencies']);
            foreach ($normalized['dependencies'] as $sourceId) {
                $definition = $sourceDefinitions[$sourceId] ?? null;
                if (! is_array($definition)) {
                    return $this->invalidEvaluation($targetDefinitionId, 'invalid_rule', 'Calculation source definition snapshot is missing.');
                }
                $valueRow = $values[$sourceId] ?? null;
                if (! is_array($valueRow) || (int) ($valueRow['is_set'] ?? 0) !== 1 || ($valueRow['value_json'] ?? null) === null) {
                    return $this->invalidEvaluation($targetDefinitionId, 'missing_source', 'A required calculation source value is missing or cleared.', $sourceId);
                }
                if ((string) ($valueRow['data_type_snapshot'] ?? '') !== $definition['data_type']
                    || (string) ($valueRow['definition_config_hash'] ?? '') !== CustomFieldValuePolicy::configurationHash($definition)) {
                    return $this->invalidEvaluation($targetDefinitionId, 'stale_value', 'A calculation source value was validated under stale field configuration.', $sourceId);
                }
                $valueJson = (string) $valueRow['value_json'];
                try {
                    $decoded = CustomFieldValuePolicy::decodeStored($valueJson);
                    $canonical = CustomFieldValuePolicy::canonicalize($definition, $decoded);
                    if ($canonical['value_json'] !== $valueJson) {
                        return $this->invalidEvaluation($targetDefinitionId, 'invalid_source', 'A calculation source value is noncanonical.', $sourceId);
                    }
                    $sourceValues[$sourceId] = CustomFieldCalculationPolicy::numericSourceValue($definition['data_type'], $canonical['value']);
                } catch (InvalidArgumentException $error) {
                    return $this->invalidEvaluation($targetDefinitionId, 'invalid_source', $error->getMessage(), $sourceId);
                }
            }
        }

        try {
            $result = CustomFieldCalculationPolicy::evaluate($normalized['ast'], $sourceValues);
        } catch (InvalidArgumentException $error) {
            return $this->invalidEvaluation($targetDefinitionId, 'calculation_error', $error->getMessage());
        }
        if ((string) ($target['data_type'] ?? '') === 'integer' && ! CustomFieldCalculationPolicy::isIntegral($result)) {
            return $this->invalidEvaluation($targetDefinitionId, 'fractional_result', 'Integer calculation target produced a fractional result.');
        }

        return [
            'configured' => true,
            'valid' => true,
            'status' => 'calculated',
            'result' => $result,
            'target_data_type' => (string) ($target['data_type'] ?? ''),
            'dependencies' => $normalized['dependencies'],
            'diagnostics' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function requireNumericDefinition(int $definitionId, bool $requireActive): array
    {
        if ($definitionId <= 0) {
            throw new InvalidArgumentException('Dynamic Field definition ID must be positive.');
        }
        $definition = $this->definitions->find($definitionId);
        if ($definition === null) {
            throw new InvalidArgumentException('Dynamic Field definition was not found in the current tenant.');
        }
        if (! in_array((string) ($definition['data_type'] ?? ''), ['integer', 'decimal'], true)) {
            throw new InvalidArgumentException('Dynamic Field calculation definitions must use integer or decimal data types.');
        }
        if ($requireActive && (string) ($definition['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('Dynamic Field definition must be active for calculation authoring.');
        }
        return $definition;
    }

    /** @param list<int> $proposedSources */
    private function assertAcyclic(int $contractTypeId, int $targetDefinitionId, array $proposedSources): void
    {
        $edges = $this->repository->listDependencyEdges($contractTypeId, CustomFieldCalculationPolicy::MAX_GRAPH_EDGES + 1);
        if (count($edges) > CustomFieldCalculationPolicy::MAX_GRAPH_EDGES) {
            throw new RuntimeException('Dynamic Field calculation dependency graph exceeds the bounded edge limit.');
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
        if (count($nodes) > CustomFieldCalculationPolicy::MAX_GRAPH_NODES) {
            throw new RuntimeException('Dynamic Field calculation dependency graph exceeds the bounded node limit.');
        }

        $state = [];
        $visit = function (int $node, int $depth) use (&$visit, &$state, $graph): void {
            if ($depth > CustomFieldCalculationPolicy::MAX_GRAPH_DEPTH) {
                throw new RuntimeException('Dynamic Field calculation dependency graph exceeds the bounded depth limit.');
            }
            if (($state[$node] ?? 0) === 1) {
                throw new InvalidArgumentException('Dynamic Field calculation dependency cycle is not allowed.');
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
            throw new DomainException('The current user cannot access this contract calculation context.');
        }
    }

    /** @return array<string,mixed> */
    private function invalidEvaluation(int $targetDefinitionId, string $status, string $message, ?int $sourceDefinitionId = null): array
    {
        $diagnostic = [
            'code' => $status,
            'target_definition_id' => $targetDefinitionId,
            'message' => $message,
        ];
        if ($sourceDefinitionId !== null) {
            $diagnostic['source_definition_id'] = $sourceDefinitionId;
        }
        return [
            'configured' => true,
            'valid' => false,
            'status' => $status,
            'result' => null,
            'target_data_type' => null,
            'dependencies' => [],
            'diagnostics' => [$diagnostic],
        ];
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Dynamic Field calculation access requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Dynamic Field calculation operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }
}
