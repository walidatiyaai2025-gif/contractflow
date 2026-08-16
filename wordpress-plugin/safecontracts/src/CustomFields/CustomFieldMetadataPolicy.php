<?php

declare(strict_types=1);

namespace SafeContracts\CustomFields;

use InvalidArgumentException;

final class CustomFieldMetadataPolicy
{
    public const MAX_REPORT_LABEL_BYTES = 191;

    /** @return list<string> */
    public static function reportDataClasses(): array
    {
        return ['dimension', 'measure', 'date', 'identifier', 'text'];
    }

    /** @return list<string> */
    public static function aggregationPolicies(): array
    {
        return ['none', 'sum', 'avg', 'min', 'max'];
    }

    /** @return array<string,mixed> */
    public static function defaults(string $dataType): array
    {
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType($dataType);
        return [
            'show_in_form' => true,
            'show_in_summary' => false,
            'show_in_mobile' => true,
            'show_in_print' => false,
            'filterable' => false,
            'sortable' => false,
            'groupable' => false,
            'exportable' => false,
            'dashboard_visible' => false,
            'report_label' => '',
            'report_data_class' => self::defaultReportClass($dataType),
            'aggregation_policy' => 'none',
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function normalize(string $dataType, array $input): array
    {
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType($dataType);
        $allowed = [
            'show_in_form', 'show_in_summary', 'show_in_mobile', 'show_in_print',
            'filterable', 'sortable', 'groupable', 'exportable', 'dashboard_visible',
            'report_label', 'report_data_class', 'aggregation_policy',
        ];
        foreach (array_keys($input) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Dynamic Field presentation/reporting metadata property.');
            }
        }

        $normalized = self::defaults($dataType);
        foreach (['show_in_form', 'show_in_summary', 'show_in_mobile', 'show_in_print', 'filterable', 'sortable', 'groupable', 'exportable', 'dashboard_visible'] as $flag) {
            if (array_key_exists($flag, $input)) {
                if (! is_bool($input[$flag])) {
                    throw new InvalidArgumentException("Dynamic Field metadata {$flag} must be boolean.");
                }
                $normalized[$flag] = $input[$flag];
            }
        }

        if (array_key_exists('report_label', $input)) {
            if (! is_string($input['report_label'])) {
                throw new InvalidArgumentException('Dynamic Field report label must be a string.');
            }
            $label = trim(strip_tags($input['report_label']));
            if (strlen($label) > self::MAX_REPORT_LABEL_BYTES) {
                throw new InvalidArgumentException('Dynamic Field report label is too long.');
            }
            $normalized['report_label'] = $label;
        }

        if (array_key_exists('report_data_class', $input)) {
            if (! is_string($input['report_data_class'])) {
                throw new InvalidArgumentException('Dynamic Field report data class must be a string.');
            }
            $class = strtolower(trim($input['report_data_class']));
            if (! in_array($class, self::reportDataClasses(), true)) {
                throw new InvalidArgumentException('Unsupported Dynamic Field report data class.');
            }
            $normalized['report_data_class'] = $class;
        }

        if (array_key_exists('aggregation_policy', $input)) {
            if (! is_string($input['aggregation_policy'])) {
                throw new InvalidArgumentException('Dynamic Field aggregation policy must be a string.');
            }
            $aggregation = strtolower(trim($input['aggregation_policy']));
            if (! in_array($aggregation, self::aggregationPolicies(), true)) {
                throw new InvalidArgumentException('Unsupported Dynamic Field aggregation policy.');
            }
            $normalized['aggregation_policy'] = $aggregation;
        }

        self::assertCompatible($dataType, $normalized);
        return $normalized;
    }

    /** @param array<string,mixed> $metadata */
    public static function assertCompatible(string $dataType, array $metadata): void
    {
        $dataType = CustomFieldDefinitionPolicy::normalizeDataType($dataType);
        $class = (string) ($metadata['report_data_class'] ?? '');
        $aggregation = (string) ($metadata['aggregation_policy'] ?? 'none');

        $classTypes = [
            'measure' => ['integer', 'decimal'],
            'date' => ['date', 'datetime'],
            'identifier' => ['text', 'integer', 'select'],
            'text' => ['text', 'long_text', 'select', 'multi_select', 'boolean'],
            'dimension' => ['text', 'select', 'boolean', 'integer', 'date', 'datetime'],
        ];
        if (! isset($classTypes[$class]) || ! in_array($dataType, $classTypes[$class], true)) {
            throw new InvalidArgumentException('Dynamic Field report data class is incompatible with the field data type.');
        }

        if ($aggregation !== 'none') {
            if (! in_array($dataType, ['integer', 'decimal'], true) || $class !== 'measure') {
                throw new InvalidArgumentException('Dynamic Field aggregation is allowed only for numeric measure fields.');
            }
        }

        if (($metadata['sortable'] ?? false) && in_array($dataType, ['long_text', 'multi_select'], true)) {
            throw new InvalidArgumentException('Dynamic Field data type is not eligible for sortable reporting metadata.');
        }
        if (($metadata['groupable'] ?? false) && in_array($dataType, ['long_text', 'multi_select', 'decimal'], true)) {
            throw new InvalidArgumentException('Dynamic Field data type is not eligible for groupable reporting metadata.');
        }
    }

    private static function defaultReportClass(string $dataType): string
    {
        return match ($dataType) {
            'integer', 'decimal' => 'measure',
            'date', 'datetime' => 'date',
            'select', 'boolean' => 'dimension',
            default => 'text',
        };
    }
}
