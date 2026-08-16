<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class CustomFieldValuePolicy
{
    public const MAX_TEXT_BYTES = 10000;
    public const MAX_LONG_TEXT_BYTES = 200000;
    public const MAX_NUMERIC_INPUT_BYTES = 256;

    /**
     * @param array<string,mixed> $definition
     * @return array{value:mixed,value_json:string,config_hash:string}
     */
    public static function canonicalize(array $definition, mixed $value): array
    {
        $type = CustomFieldDefinitionPolicy::normalizeDataType((string) ($definition['data_type'] ?? ''));
        $optionsJson = trim((string) ($definition['options_json'] ?? ''));
        $validationJson = trim((string) ($definition['validation_json'] ?? ''));
        $options = self::decodeObjectOrList($optionsJson, true, 'Custom Field options');
        $validation = self::decodeObjectOrList($validationJson, false, 'Custom Field validation');

        $canonical = match ($type) {
            'text' => self::text($value, self::MAX_TEXT_BYTES, $validation),
            'long_text' => self::text($value, self::MAX_LONG_TEXT_BYTES, $validation),
            'integer' => self::integer($value, $validation),
            'decimal' => self::decimal($value, $validation),
            'boolean' => self::boolean($value),
            'date' => self::date($value, $validation),
            'datetime' => self::dateTime($value, $validation),
            'select' => self::select($value, $options),
            'multi_select' => self::multiSelect($value, $options, $validation),
            default => throw new InvalidArgumentException('Custom Field data type is not supported.'),
        };

        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new InvalidArgumentException('Custom Field value could not be encoded.');
        }

        return [
            'value' => $canonical,
            'value_json' => $json,
            'config_hash' => self::configurationHash($definition),
        ];
    }

    /** @param array<string,mixed> $definition */
    public static function configurationHash(array $definition): string
    {
        $parts = [
            (string) ($definition['contract_type_id'] ?? ''),
            (string) ($definition['field_code'] ?? ''),
            (string) ($definition['data_type'] ?? ''),
            trim((string) ($definition['options_json'] ?? '')),
            trim((string) ($definition['validation_json'] ?? '')),
        ];
        return hash('sha256', implode("\n", $parts));
    }

    public static function decodeStored(string $json): mixed
    {
        try {
            return json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException('Stored Custom Field value is not valid JSON.', 0, $error);
        }
    }

    /** @param array<string,mixed> $validation */
    private static function text(mixed $value, int $hardMax, array $validation): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Custom Field text value must be a string.');
        }
        $value = trim($value);
        $length = strlen($value);
        if ($length > $hardMax) {
            throw new InvalidArgumentException('Custom Field text value exceeds the hard size limit.');
        }
        if (isset($validation['min_length']) && $length < (int) $validation['min_length']) {
            throw new InvalidArgumentException('Custom Field text value is shorter than the configured minimum.');
        }
        if (isset($validation['max_length']) && $length > (int) $validation['max_length']) {
            throw new InvalidArgumentException('Custom Field text value exceeds the configured maximum.');
        }
        return $value;
    }

    /** @param array<string,mixed> $validation */
    private static function integer(mixed $value, array $validation): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value)) {
            $raw = trim($value);
            if ($raw === '' || strlen($raw) > self::MAX_NUMERIC_INPUT_BYTES || preg_match('/^-?[0-9]+$/', $raw) !== 1) {
                throw new InvalidArgumentException('Custom Field integer value must be an integer.');
            }
            $normalized = self::canonicalIntegerString($raw);
            $validated = filter_var($normalized, FILTER_VALIDATE_INT);
            if ($validated === false) {
                throw new InvalidArgumentException('Custom Field integer value is outside the supported integer range.');
            }
            $integer = (int) $validated;
        } else {
            throw new InvalidArgumentException('Custom Field integer value must be an integer.');
        }
        if (array_key_exists('min', $validation) && $integer < (int) $validation['min']) {
            throw new InvalidArgumentException('Custom Field integer value is below the configured minimum.');
        }
        if (array_key_exists('max', $validation) && $integer > (int) $validation['max']) {
            throw new InvalidArgumentException('Custom Field integer value exceeds the configured maximum.');
        }
        return $integer;
    }

    /** @param array<string,mixed> $validation */
    private static function decimal(mixed $value, array $validation): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Custom Field decimal value must be numeric.');
        }
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException('Custom Field decimal value must be finite.');
        }
        $raw = is_float($value) ? sprintf('%.14F', $value) : (string) $value;
        $canonical = self::canonicalDecimal($raw);
        if (array_key_exists('min', $validation) && self::compareDecimal($canonical, self::canonicalDecimal(self::boundaryString($validation['min']))) < 0) {
            throw new InvalidArgumentException('Custom Field decimal value is below the configured minimum.');
        }
        if (array_key_exists('max', $validation) && self::compareDecimal($canonical, self::canonicalDecimal(self::boundaryString($validation['max']))) > 0) {
            throw new InvalidArgumentException('Custom Field decimal value exceeds the configured maximum.');
        }
        return $canonical;
    }

    private static function boolean(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException('Custom Field boolean value must be a JSON boolean.');
        }
        return $value;
    }

    /** @param array<string,mixed> $validation */
    private static function date(mixed $value, array $validation): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('Custom Field date value must use YYYY-MM-DD.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Custom Field date value is not a valid calendar date.');
        }
        self::assertLexicalRange($value, $validation, 'date');
        return $value;
    }

    /** @param array<string,mixed> $validation */
    private static function dateTime(mixed $value, array $validation): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw new InvalidArgumentException('Custom Field datetime value must be an ISO-8601 timestamp with timezone.');
        }
        try {
            $parsed = new DateTimeImmutable($value);
        } catch (\Throwable $error) {
            throw new InvalidArgumentException('Custom Field datetime value is invalid.', 0, $error);
        }
        $canonical = $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        foreach (['min', 'max'] as $key) {
            if (! array_key_exists($key, $validation)) {
                continue;
            }
            try {
                $boundary = (new DateTimeImmutable((string) $validation[$key]))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            } catch (\Throwable $error) {
                throw new InvalidArgumentException('Stored Custom Field datetime validation boundary is invalid.', 0, $error);
            }
            if ($key === 'min' && $canonical < $boundary) {
                throw new InvalidArgumentException('Custom Field datetime value is below the configured minimum.');
            }
            if ($key === 'max' && $canonical > $boundary) {
                throw new InvalidArgumentException('Custom Field datetime value exceeds the configured maximum.');
            }
        }
        return $canonical;
    }

    /** @param list<mixed> $options */
    private static function select(mixed $value, array $options): string|int|float|bool
    {
        $identity = self::scalarIdentity($value);
        foreach ($options as $option) {
            if (is_array($option) && array_key_exists('value', $option) && self::scalarIdentity($option['value']) === $identity) {
                return self::scalar($option['value']);
            }
        }
        throw new InvalidArgumentException('Custom Field select value is not one of the configured options.');
    }

    /** @param list<mixed> $options @param array<string,mixed> $validation @return list<string|int|float|bool> */
    private static function multiSelect(mixed $value, array $options, array $validation): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException('Custom Field multi_select value must be a list.');
        }
        $result = [];
        $seen = [];
        foreach ($value as $item) {
            $canonical = self::select($item, $options);
            $identity = self::scalarIdentity($canonical);
            if (isset($seen[$identity])) {
                throw new InvalidArgumentException('Custom Field multi_select values must be unique.');
            }
            $seen[$identity] = true;
            $result[] = $canonical;
        }
        $count = count($result);
        if (isset($validation['min_items']) && $count < (int) $validation['min_items']) {
            throw new InvalidArgumentException('Custom Field multi_select value has too few items.');
        }
        if (isset($validation['max_items']) && $count > (int) $validation['max_items']) {
            throw new InvalidArgumentException('Custom Field multi_select value has too many items.');
        }
        return $result;
    }

    /** @return array<mixed> */
    private static function decodeObjectOrList(string $json, bool $expectList, string $label): array
    {
        if ($json === '') {
            return [];
        }
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException("{$label} is not valid JSON.", 0, $error);
        }
        if (! is_array($decoded) || ($expectList && ! array_is_list($decoded)) || (! $expectList && array_is_list($decoded) && $decoded !== [])) {
            throw new InvalidArgumentException("{$label} has an invalid shape.");
        }
        return $decoded;
    }

    /** @return string|int|float|bool */
    private static function scalar(mixed $value): string|int|float|bool
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
            throw new InvalidArgumentException('Custom Field option value has an invalid scalar type.');
        }
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException('Custom Field option value must be finite.');
        }
        return $value;
    }

    private static function scalarIdentity(mixed $value): string
    {
        $scalar = self::scalar($value);
        $json = json_encode($scalar, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new InvalidArgumentException('Custom Field option value could not be encoded.');
        }
        return get_debug_type($scalar) . ':' . $json;
    }

    /** @param array<string,mixed> $validation */
    private static function assertLexicalRange(string $value, array $validation, string $label): void
    {
        if (isset($validation['min']) && $value < (string) $validation['min']) {
            throw new InvalidArgumentException("Custom Field {$label} value is below the configured minimum.");
        }
        if (isset($validation['max']) && $value > (string) $validation['max']) {
            throw new InvalidArgumentException("Custom Field {$label} value exceeds the configured maximum.");
        }
    }

    private static function boundaryString(mixed $value): string
    {
        if (is_int($value) || is_string($value)) {
            return (string) $value;
        }
        if (is_float($value) && is_finite($value)) {
            return sprintf('%.14F', $value);
        }
        throw new InvalidArgumentException('Custom Field decimal validation boundary is invalid.');
    }

    private static function canonicalIntegerString(string $value): string
    {
        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = substr($value, 1);
        }
        $value = ltrim($value, '0');
        if ($value === '') {
            return '0';
        }
        return $negative ? '-' . $value : $value;
    }

    private static function canonicalDecimal(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > self::MAX_NUMERIC_INPUT_BYTES || preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/', $value) !== 1) {
            throw new InvalidArgumentException('Custom Field decimal value must use plain decimal notation.');
        }
        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = substr($value, 1);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($fraction, '0');
        $canonical = $fraction === '' ? $whole : $whole . '.' . $fraction;
        if ($canonical === '0') {
            return '0';
        }
        return $negative ? '-' . $canonical : $canonical;
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
        $cmp = strcmp(str_pad($lf, $length, '0'), str_pad($rf, $length, '0'));
        $cmp = $cmp <=> 0;
        return $leftNegative ? -$cmp : $cmp;
    }
}
