<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use InvalidArgumentException;

final class CustomFieldDefinitionPolicy
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const MAX_OPTIONS = 200;
    public const MAX_OPTIONS_BYTES = 32768;
    public const MAX_VALIDATION_BYTES = 20000;

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    /** @return list<string> */
    public static function dataTypes(): array
    {
        return ['text', 'long_text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'select', 'multi_select'];
    }

    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (! in_array($status, self::statuses(), true)) {
            throw new InvalidArgumentException('Custom Field status is not supported.');
        }
        return $status;
    }

    public static function normalizeDataType(string $type): string
    {
        $type = strtolower(trim($type));
        if (! in_array($type, self::dataTypes(), true)) {
            throw new InvalidArgumentException('Custom Field data type is not supported.');
        }
        return $type;
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/\s+/', '_', $code) ?? '';
        if ($code === '' || strlen($code) > 100 || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Custom Field code must be 1-100 lowercase machine-code characters using letters, numbers, dot, underscore or hyphen.');
        }
        return $code;
    }

    public static function encodeOptions(string $dataType, mixed $options): string
    {
        $dataType = self::normalizeDataType($dataType);
        $isSelect = in_array($dataType, ['select', 'multi_select'], true);
        if (! $isSelect) {
            if ($options === null || $options === [] || $options === '') {
                return '';
            }
            throw new InvalidArgumentException('Custom Field options are supported only for select and multi_select types.');
        }
        if (! is_array($options) || ! array_is_list($options) || $options === []) {
            throw new InvalidArgumentException('Select Custom Field options must be a non-empty list.');
        }
        if (count($options) > self::MAX_OPTIONS) {
            throw new InvalidArgumentException('Custom Field options exceed the maximum option count.');
        }

        $normalized = [];
        $seen = [];
        foreach ($options as $option) {
            if (! is_array($option) || array_is_list($option) || array_diff(array_keys($option), ['value', 'label']) !== []) {
                throw new InvalidArgumentException('Each Custom Field option must contain only value and label keys.');
            }
            if (! array_key_exists('value', $option) || ! array_key_exists('label', $option)) {
                throw new InvalidArgumentException('Each Custom Field option requires value and label.');
            }
            $value = self::scalarOptionValue($option['value']);
            $label = self::scalarLabel($option['label']);
            $identity = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            if ($identity === false || isset($seen[$identity])) {
                throw new InvalidArgumentException('Custom Field option values must be unique.');
            }
            $seen[$identity] = true;
            $normalized[] = ['value' => $value, 'label' => $label];
        }

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false || strlen($json) > self::MAX_OPTIONS_BYTES) {
            throw new InvalidArgumentException('Custom Field options exceed the encoded size limit.');
        }
        return $json;
    }

    public static function encodeValidation(string $dataType, mixed $validation): string
    {
        $dataType = self::normalizeDataType($dataType);
        if ($validation === null || $validation === [] || $validation === '') {
            return '';
        }
        if (! is_array($validation) || array_is_list($validation)) {
            throw new InvalidArgumentException('Custom Field validation must be a declarative object.');
        }

        $allowed = match ($dataType) {
            'text', 'long_text' => ['min_length', 'max_length'],
            'integer', 'decimal' => ['min', 'max'],
            'date', 'datetime' => ['min', 'max'],
            'multi_select' => ['min_items', 'max_items'],
            'boolean', 'select' => [],
            default => [],
        };
        foreach (array_keys($validation) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Custom Field validation rule is not supported for this data type.');
            }
        }

        $normalized = [];
        if (in_array($dataType, ['text', 'long_text'], true)) {
            foreach (['min_length', 'max_length'] as $key) {
                if (array_key_exists($key, $validation)) {
                    $normalized[$key] = self::boundedInteger($validation[$key], 0, 100000, $key);
                }
            }
            self::assertOrdered($normalized, 'min_length', 'max_length');
        } elseif (in_array($dataType, ['integer', 'decimal'], true)) {
            foreach (['min', 'max'] as $key) {
                if (array_key_exists($key, $validation)) {
                    if (! is_int($validation[$key]) && ! is_float($validation[$key])) {
                        throw new InvalidArgumentException("Custom Field {$key} must be numeric.");
                    }
                    $number = (float) $validation[$key];
                    if (! is_finite($number)) {
                        throw new InvalidArgumentException("Custom Field {$key} must be finite.");
                    }
                    $normalized[$key] = $dataType === 'integer' ? (int) $validation[$key] : $number;
                    if ($dataType === 'integer' && (float) $normalized[$key] !== $number) {
                        throw new InvalidArgumentException("Custom Field {$key} must be an integer.");
                    }
                }
            }
            self::assertOrdered($normalized, 'min', 'max');
        } elseif (in_array($dataType, ['date', 'datetime'], true)) {
            foreach (['min', 'max'] as $key) {
                if (array_key_exists($key, $validation)) {
                    $value = trim((string) $validation[$key]);
                    $format = $dataType === 'date' ? '/^\d{4}-\d{2}-\d{2}$/' : '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})$/';
                    if (preg_match($format, $value) !== 1) {
                        throw new InvalidArgumentException("Custom Field {$key} has an invalid {$dataType} boundary.");
                    }
                    $normalized[$key] = $value;
                }
            }
            self::assertOrdered($normalized, 'min', 'max');
        } elseif ($dataType === 'multi_select') {
            foreach (['min_items', 'max_items'] as $key) {
                if (array_key_exists($key, $validation)) {
                    $normalized[$key] = self::boundedInteger($validation[$key], 0, self::MAX_OPTIONS, $key);
                }
            }
            self::assertOrdered($normalized, 'min_items', 'max_items');
        }

        if ($normalized === []) {
            return '';
        }
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false || strlen($json) > self::MAX_VALIDATION_BYTES) {
            throw new InvalidArgumentException('Custom Field validation exceeds the encoded size limit.');
        }
        return $json;
    }

    private static function scalarOptionValue(mixed $value): string|int|float|bool
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
            throw new InvalidArgumentException('Custom Field option values must be scalar JSON values.');
        }
        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException('Custom Field option values must be finite.');
        }
        if (is_string($value) && ($value === '' || strlen($value) > 191)) {
            throw new InvalidArgumentException('Custom Field string option values must be 1-191 characters.');
        }
        return $value;
    }

    private static function scalarLabel(mixed $label): string
    {
        if (! is_string($label) && ! is_int($label) && ! is_float($label) && ! is_bool($label)) {
            throw new InvalidArgumentException('Custom Field option labels must be scalar.');
        }
        if (is_float($label) && ! is_finite($label)) {
            throw new InvalidArgumentException('Custom Field option labels must be finite.');
        }
        $label = trim(strip_tags((string) $label));
        if ($label === '' || strlen($label) > 191) {
            throw new InvalidArgumentException('Custom Field option labels must be 1-191 characters.');
        }
        return $label;
    }

    private static function boundedInteger(mixed $value, int $min, int $max, string $label): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("Custom Field {$label} must be an integer.");
        }
        $value = (int) $value;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Custom Field {$label} is out of range.");
        }
        return $value;
    }

    private static function assertOrdered(array $rules, string $minKey, string $maxKey): void
    {
        if (array_key_exists($minKey, $rules) && array_key_exists($maxKey, $rules) && $rules[$minKey] > $rules[$maxKey]) {
            throw new InvalidArgumentException("Custom Field {$minKey} cannot exceed {$maxKey}.");
        }
    }
}
