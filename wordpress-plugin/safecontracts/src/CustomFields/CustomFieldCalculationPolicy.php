<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use InvalidArgumentException;

final class CustomFieldCalculationPolicy
{
    public const MAX_DIGITS = 38;
    public const MAX_FRACTION_DIGITS = 12;
    public const MAX_AST_DEPTH = 16;
    public const MAX_AST_NODES = 128;
    public const MAX_DEPENDENCIES = 32;
    public const MAX_GRAPH_NODES = 200;
    public const MAX_GRAPH_EDGES = 6400;
    public const MAX_GRAPH_DEPTH = 64;

    /**
     * @return array{ast:array<string,mixed>,expression_json:string,dependencies:list<int>}
     */
    public static function normalizeExpression(mixed $expression): array
    {
        $nodes = 0;
        $dependencies = [];
        $ast = self::normalizeNode($expression, 1, $nodes, $dependencies);
        $dependencyIds = array_map('intval', array_keys($dependencies));
        sort($dependencyIds, SORT_NUMERIC);
        if (count($dependencyIds) > self::MAX_DEPENDENCIES) {
            throw new InvalidArgumentException('Dynamic Field calculation expression exceeds the unique dependency limit.');
        }
        $json = json_encode($ast, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException('Dynamic Field calculation expression could not be encoded.');
        }
        return [
            'ast' => $ast,
            'expression_json' => $json,
            'dependencies' => $dependencyIds,
        ];
    }

    /** @return array<string,mixed> */
    public static function decodeExpression(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException('Stored Dynamic Field calculation expression is not valid JSON.', 0, $error);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Stored Dynamic Field calculation expression must be a JSON object.');
        }
        return $decoded;
    }

    public static function canonicalNumber(mixed $value): string
    {
        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_string($value)) {
            $raw = trim($value);
        } else {
            throw new InvalidArgumentException('Dynamic Field calculation numeric value must be a plain decimal string or integer.');
        }
        if ($raw === '' || preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/', $raw) !== 1) {
            throw new InvalidArgumentException('Dynamic Field calculation numeric value must use plain decimal notation.');
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($fraction, '0');
        $canonical = $fraction === '' ? $whole : $whole . '.' . $fraction;
        if ($canonical === '0') {
            $negative = false;
        }

        if (strlen($fraction) > self::MAX_FRACTION_DIGITS) {
            throw new InvalidArgumentException('Dynamic Field calculation numeric scale exceeds 12 fractional digits.');
        }
        $digitCount = strlen($whole) + strlen($fraction);
        if ($digitCount > self::MAX_DIGITS) {
            throw new InvalidArgumentException('Dynamic Field calculation numeric precision exceeds 38 digits.');
        }
        return $negative ? '-' . $canonical : $canonical;
    }

    public static function numericSourceValue(string $dataType, mixed $value): string
    {
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType($dataType);
        if (! in_array($dataType, ['integer', 'decimal'], true)) {
            throw new InvalidArgumentException('Dynamic Field calculation source must be numeric.');
        }
        if ($dataType === 'integer' && ! is_int($value)) {
            throw new InvalidArgumentException('Dynamic Field integer calculation source must decode to an integer.');
        }
        if ($dataType === 'decimal' && ! is_string($value)) {
            throw new InvalidArgumentException('Dynamic Field decimal calculation source must decode to a canonical decimal string.');
        }
        $canonical = self::canonicalNumber($value);
        if ($dataType === 'integer' && ! self::isIntegral($canonical)) {
            throw new InvalidArgumentException('Dynamic Field integer calculation source must be integral.');
        }
        return $canonical;
    }

    /** @param array<int,string> $sourceValues */
    public static function evaluate(array $ast, array $sourceValues): string
    {
        $normalized = self::normalizeExpression($ast);
        return self::evaluateNode($normalized['ast'], $sourceValues);
    }

    public static function isIntegral(string $value): bool
    {
        return ! str_contains(self::canonicalNumber($value), '.');
    }

    /**
     * @param array<int,true> $dependencies
     * @return array<string,mixed>
     */
    private static function normalizeNode(mixed $node, int $depth, int &$nodes, array &$dependencies): array
    {
        if ($depth > self::MAX_AST_DEPTH) {
            throw new InvalidArgumentException('Dynamic Field calculation AST exceeds the depth limit.');
        }
        if (! is_array($node) || array_is_list($node)) {
            throw new InvalidArgumentException('Each Dynamic Field calculation AST node must be an object.');
        }
        $nodes++;
        if ($nodes > self::MAX_AST_NODES) {
            throw new InvalidArgumentException('Dynamic Field calculation AST exceeds the node limit.');
        }
        if (! array_key_exists('kind', $node) || ! is_string($node['kind'])) {
            throw new InvalidArgumentException('Dynamic Field calculation AST node kind is required.');
        }
        $kind = strtolower(trim($node['kind']));

        if ($kind === 'field') {
            self::assertKeys($node, ['kind', 'definition_id'], ['kind', 'definition_id']);
            $definitionId = self::positiveId($node['definition_id']);
            $dependencies[$definitionId] = true;
            if (count($dependencies) > self::MAX_DEPENDENCIES) {
                throw new InvalidArgumentException('Dynamic Field calculation expression exceeds the unique dependency limit.');
            }
            return ['kind' => 'field', 'definition_id' => $definitionId];
        }
        if ($kind === 'constant') {
            self::assertKeys($node, ['kind', 'value'], ['kind', 'value']);
            return ['kind' => 'constant', 'value' => self::canonicalNumber($node['value'])];
        }
        if (! in_array($kind, ['add', 'subtract', 'multiply', 'negate'], true)) {
            throw new InvalidArgumentException('Unsupported Dynamic Field calculation operator.');
        }
        self::assertKeys($node, ['kind', 'children'], ['kind', 'children']);
        $children = $node['children'];
        if (! is_array($children) || ! array_is_list($children)) {
            throw new InvalidArgumentException('Dynamic Field calculation operator children must be an ordered list.');
        }
        $count = count($children);
        if ($kind === 'add' && ($count < 2 || $count > 16)) {
            throw new InvalidArgumentException('Dynamic Field calculation add requires 2 to 16 children.');
        }
        if ($kind === 'multiply' && ($count < 2 || $count > 8)) {
            throw new InvalidArgumentException('Dynamic Field calculation multiply requires 2 to 8 children.');
        }
        if ($kind === 'subtract' && $count !== 2) {
            throw new InvalidArgumentException('Dynamic Field calculation subtract requires exactly two children.');
        }
        if ($kind === 'negate' && $count !== 1) {
            throw new InvalidArgumentException('Dynamic Field calculation negate requires exactly one child.');
        }
        $normalizedChildren = [];
        foreach ($children as $child) {
            $normalizedChildren[] = self::normalizeNode($child, $depth + 1, $nodes, $dependencies);
        }
        return ['kind' => $kind, 'children' => $normalizedChildren];
    }

    /** @param array<string,mixed> $node @param list<string> $allowed @param list<string> $required */
    private static function assertKeys(array $node, array $allowed, array $required): void
    {
        foreach (array_keys($node) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Dynamic Field calculation AST property.');
            }
        }
        foreach ($required as $key) {
            if (! array_key_exists($key, $node)) {
                throw new InvalidArgumentException('Dynamic Field calculation AST node is missing a required property.');
            }
        }
    }

    private static function positiveId(mixed $value): int
    {
        if (is_int($value)) {
            if ($value <= 0) {
                throw new InvalidArgumentException('Dynamic Field calculation source definition ID must be positive.');
            }
            return $value;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('Dynamic Field calculation source definition ID must be an integer.');
        }
        $raw = trim($value);
        if ($raw === '' || preg_match('/^[0-9]+$/', $raw) !== 1) {
            throw new InvalidArgumentException('Dynamic Field calculation source definition ID must be an integer.');
        }
        $raw = ltrim($raw, '0');
        $raw = $raw === '' ? '0' : $raw;
        $validated = filter_var($raw, FILTER_VALIDATE_INT);
        if ($validated === false || (int) $validated <= 0) {
            throw new InvalidArgumentException('Dynamic Field calculation source definition ID is outside the supported range.');
        }
        return (int) $validated;
    }

    /** @param array<string,mixed> $node @param array<int,string> $sourceValues */
    private static function evaluateNode(array $node, array $sourceValues): string
    {
        $kind = (string) $node['kind'];
        if ($kind === 'field') {
            $id = (int) $node['definition_id'];
            if (! array_key_exists($id, $sourceValues)) {
                throw new InvalidArgumentException('Dynamic Field calculation source value is missing.');
            }
            return self::canonicalNumber($sourceValues[$id]);
        }
        if ($kind === 'constant') {
            return self::canonicalNumber($node['value']);
        }
        $values = [];
        foreach ($node['children'] as $child) {
            $values[] = self::evaluateNode($child, $sourceValues);
        }
        if ($kind === 'add') {
            $result = '0';
            foreach ($values as $value) {
                $result = self::add($result, $value);
            }
            return $result;
        }
        if ($kind === 'subtract') {
            return self::add($values[0], self::negate($values[1]));
        }
        if ($kind === 'multiply') {
            $result = '1';
            foreach ($values as $value) {
                $result = self::multiply($result, $value);
            }
            return $result;
        }
        if ($kind === 'negate') {
            return self::negate($values[0]);
        }
        throw new InvalidArgumentException('Unsupported Dynamic Field calculation operator.');
    }

    private static function negate(string $value): string
    {
        $value = self::canonicalNumber($value);
        if ($value === '0') {
            return '0';
        }
        return str_starts_with($value, '-') ? substr($value, 1) : '-' . $value;
    }

    private static function add(string $left, string $right): string
    {
        [$leftSign, $leftDigits, $leftScale] = self::parts($left);
        [$rightSign, $rightDigits, $rightScale] = self::parts($right);
        $scale = max($leftScale, $rightScale);
        $leftDigits .= str_repeat('0', $scale - $leftScale);
        $rightDigits .= str_repeat('0', $scale - $rightScale);

        if ($leftSign === 0) {
            return self::formatScaled($rightSign, $rightDigits, $scale);
        }
        if ($rightSign === 0) {
            return self::formatScaled($leftSign, $leftDigits, $scale);
        }
        if ($leftSign === $rightSign) {
            return self::formatScaled($leftSign, self::addAbs($leftDigits, $rightDigits), $scale);
        }
        $comparison = self::compareAbs($leftDigits, $rightDigits);
        if ($comparison === 0) {
            return '0';
        }
        if ($comparison > 0) {
            return self::formatScaled($leftSign, self::subtractAbs($leftDigits, $rightDigits), $scale);
        }
        return self::formatScaled($rightSign, self::subtractAbs($rightDigits, $leftDigits), $scale);
    }

    private static function multiply(string $left, string $right): string
    {
        [$leftSign, $leftDigits, $leftScale] = self::parts($left);
        [$rightSign, $rightDigits, $rightScale] = self::parts($right);
        if ($leftSign === 0 || $rightSign === 0) {
            return '0';
        }
        $digits = self::multiplyAbs($leftDigits, $rightDigits);
        return self::formatScaled($leftSign * $rightSign, $digits, $leftScale + $rightScale);
    }

    /** @return array{int,string,int} */
    private static function parts(string $value): array
    {
        $value = self::canonicalNumber($value);
        $sign = 1;
        if (str_starts_with($value, '-')) {
            $sign = -1;
            $value = substr($value, 1);
        }
        if ($value === '0') {
            $sign = 0;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $digits = self::stripZeros($whole . $fraction);
        return [$sign, $digits, strlen($fraction)];
    }

    private static function formatScaled(int $sign, string $digits, int $scale): string
    {
        $digits = self::stripZeros($digits);
        if ($digits === '0' || $sign === 0) {
            return '0';
        }
        if ($scale > 0) {
            if (strlen($digits) <= $scale) {
                $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
            }
            $whole = substr($digits, 0, -$scale);
            $fraction = substr($digits, -$scale);
            $raw = $whole . '.' . $fraction;
        } else {
            $raw = $digits;
        }
        if ($sign < 0) {
            $raw = '-' . $raw;
        }
        return self::canonicalNumber($raw);
    }

    private static function stripZeros(string $digits): string
    {
        $digits = ltrim($digits, '0');
        return $digits === '' ? '0' : $digits;
    }

    private static function compareAbs(string $left, string $right): int
    {
        $left = self::stripZeros($left);
        $right = self::stripZeros($right);
        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }
        return strcmp($left, $right) <=> 0;
    }

    private static function addAbs(string $left, string $right): string
    {
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        $carry = 0;
        $out = '';
        while ($i >= 0 || $j >= 0 || $carry > 0) {
            $sum = $carry;
            if ($i >= 0) {
                $sum += ord($left[$i--]) - 48;
            }
            if ($j >= 0) {
                $sum += ord($right[$j--]) - 48;
            }
            $out = (string) ($sum % 10) . $out;
            $carry = intdiv($sum, 10);
        }
        return self::stripZeros($out);
    }

    /** left must be >= right */
    private static function subtractAbs(string $left, string $right): string
    {
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        $borrow = 0;
        $out = '';
        while ($i >= 0) {
            $digit = (ord($left[$i--]) - 48) - $borrow;
            $other = $j >= 0 ? ord($right[$j--]) - 48 : 0;
            if ($digit < $other) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $out = (string) ($digit - $other) . $out;
        }
        return self::stripZeros($out);
    }

    private static function multiplyAbs(string $left, string $right): string
    {
        $left = self::stripZeros($left);
        $right = self::stripZeros($right);
        if ($left === '0' || $right === '0') {
            return '0';
        }
        $result = array_fill(0, strlen($left) + strlen($right), 0);
        for ($i = strlen($left) - 1; $i >= 0; $i--) {
            $a = ord($left[$i]) - 48;
            for ($j = strlen($right) - 1; $j >= 0; $j--) {
                $b = ord($right[$j]) - 48;
                $position = $i + $j + 1;
                $sum = $result[$position] + ($a * $b);
                $result[$position] = $sum % 10;
                $result[$position - 1] += intdiv($sum, 10);
            }
        }
        for ($i = count($result) - 1; $i > 0; $i--) {
            if ($result[$i] >= 10) {
                $carry = intdiv($result[$i], 10);
                $result[$i] %= 10;
                $result[$i - 1] += $carry;
            }
        }
        return self::stripZeros(implode('', $result));
    }
}
