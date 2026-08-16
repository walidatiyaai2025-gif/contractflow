<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use InvalidArgumentException;

final class CustomFieldVisibilityPolicy
{
    public const MAX_CONDITIONS = 32;
    public const MAX_GRAPH_NODES = 200;
    public const MAX_GRAPH_EDGES = 6400;
    public const MAX_GRAPH_DEPTH = 64;

    /** @return list<string> */
    public static function matchModes(): array
    {
        return ['all', 'any'];
    }

    public static function normalizeMatchMode(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Dynamic Field visibility match mode must be a string.');
        }
        $value = strtolower(trim($value));
        if (! in_array($value, self::matchModes(), true)) {
            throw new InvalidArgumentException('Dynamic Field visibility match mode must be all or any.');
        }
        return $value;
    }

    /** @return list<string> */
    public static function operatorsFor(string $dataType): array
    {
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType($dataType);
        $operators = ['is_set', 'is_not_set', 'eq', 'neq'];
        if (in_array($dataType, ['integer', 'decimal', 'date', 'datetime'], true)) {
            array_push($operators, 'gt', 'gte', 'lt', 'lte');
        }
        if ($dataType === 'multi_select') {
            $operators[] = 'contains';
        }
        return $operators;
    }

    public static function normalizeOperator(string $dataType, mixed $operator): string
    {
        if (! is_string($operator)) {
            throw new InvalidArgumentException('Dynamic Field visibility operator must be a string.');
        }
        $operator = strtolower(trim($operator));
        if (! in_array($operator, self::operatorsFor($dataType), true)) {
            throw new InvalidArgumentException('Dynamic Field visibility operator is incompatible with the source field data type.');
        }
        return $operator;
    }

    /**
     * @param array<string,mixed> $definition
     */
    public static function canonicalizeOperand(array $definition, string $operator, bool $provided, mixed $operand): ?string
    {
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType((string) ($definition['data_type'] ?? ''));
        $operator = self::normalizeOperator($dataType, $operator);
        if (in_array($operator, ['is_set', 'is_not_set'], true)) {
            if ($provided && $operand !== null) {
                throw new InvalidArgumentException('Set-state visibility operators do not accept an operand.');
            }
            return null;
        }
        if (! $provided) {
            throw new InvalidArgumentException('Dynamic Field visibility operator requires an operand.');
        }

        if ($operator === 'contains') {
            $selectDefinition = $definition;
            $selectDefinition['data_type'] = 'select';
            $selectDefinition['validation_json'] = '';
            return CustomFieldValuePolicy::canonicalize($selectDefinition, $operand)['value_json'];
        }

        return CustomFieldValuePolicy::canonicalize($definition, $operand)['value_json'];
    }

    /**
     * @param array<string,mixed> $definition
     */
    public static function evaluate(
        array $definition,
        string $operator,
        bool $isSet,
        ?string $valueJson,
        ?string $operandJson
    ): bool {
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType((string) ($definition['data_type'] ?? ''));
        $operator = self::normalizeOperator($dataType, $operator);
        if ($operator === 'is_set') {
            return $isSet;
        }
        if ($operator === 'is_not_set') {
            return ! $isSet;
        }
        if (! $isSet || $valueJson === null || $valueJson === '') {
            return false;
        }
        if ($operandJson === null || $operandJson === '') {
            throw new InvalidArgumentException('Stored Dynamic Field visibility condition is missing its operand.');
        }

        $decodedValue = CustomFieldValuePolicy::decodeStored($valueJson);
        $canonicalValue = CustomFieldValuePolicy::canonicalize($definition, $decodedValue);
        $canonicalJson = $canonicalValue['value_json'];

        if ($operator === 'eq') {
            return $canonicalJson === $operandJson;
        }
        if ($operator === 'neq') {
            return $canonicalJson !== $operandJson;
        }
        if ($operator === 'contains') {
            $value = $canonicalValue['value'];
            if (! is_array($value) || ! array_is_list($value)) {
                throw new InvalidArgumentException('Stored multi-select value has an invalid shape.');
            }
            foreach ($value as $item) {
                if (self::canonicalScalarJson($item) === $operandJson) {
                    return true;
                }
            }
            return false;
        }

        $operand = CustomFieldValuePolicy::decodeStored($operandJson);
        $comparison = self::compare($dataType, $canonicalValue['value'], $operand);
        return match ($operator) {
            'gt' => $comparison > 0,
            'gte' => $comparison >= 0,
            'lt' => $comparison < 0,
            'lte' => $comparison <= 0,
            default => throw new InvalidArgumentException('Unsupported Dynamic Field visibility comparison operator.'),
        };
    }

    private static function compare(string $dataType, mixed $left, mixed $right): int
    {
        return match ($dataType) {
            'integer' => ((int) $left) <=> ((int) $right),
            'decimal' => self::compareDecimal((string) $left, (string) $right),
            'date', 'datetime' => strcmp((string) $left, (string) $right) <=> 0,
            default => throw new InvalidArgumentException('Dynamic Field data type does not support ordered visibility comparison.'),
        };
    }

    private static function canonicalScalarJson(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
            throw new InvalidArgumentException('Dynamic Field visibility scalar has an invalid type.');
        }
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException('Dynamic Field visibility scalar must be finite.');
        }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new InvalidArgumentException('Dynamic Field visibility scalar could not be encoded.');
        }
        return $json;
    }

    private static function compareDecimal(string $left, string $right): int
    {
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        if ($leftNegative !== $rightNegative) {
            return $leftNegative ? -1 : 1;
        }
        $l = $leftNegative ? substr($left, 1) : $left;
        $r = $rightNegative ? substr($right, 1) : $right;
        [$li, $lf] = array_pad(explode('.', $l, 2), 2, '');
        [$ri, $rf] = array_pad(explode('.', $r, 2), 2, '');
        if (strlen($li) !== strlen($ri)) {
            $cmp = strlen($li) <=> strlen($ri);
            return $leftNegative ? -$cmp : $cmp;
        }
        $cmp = strcmp($li, $ri);
        if ($cmp !== 0) {
            $cmp = $cmp <=> 0;
            return $leftNegative ? -$cmp : $cmp;
        }
        $length = max(strlen($lf), strlen($rf));
        $cmp = strcmp(str_pad($lf, $length, '0'), str_pad($rf, $length, '0')) <=> 0;
        return $leftNegative ? -$cmp : $cmp;
    }
}
