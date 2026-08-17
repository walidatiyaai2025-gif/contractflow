<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;

final class ContractFinancialPenaltyRulePolicy
{
    public const MODE_FIXED_AMOUNT = 'fixed_amount';
    public const MODE_PERCENTAGE = 'percentage';
    public const STATE_CONFIGURED = 'configured';
    public const STATE_VOIDED = 'voided';
    public const MAX_RULES = 20;
    public const MAX_REVISION = 2147483647;
    public const MAX_LABEL_BYTES = 120;

    public static function normalizeMode(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract penalty mode must be a string.');
        }
        $mode = strtolower(trim($value));
        if (! in_array($mode, [self::MODE_FIXED_AMOUNT, self::MODE_PERCENTAGE], true)) {
            throw new InvalidArgumentException('Enterprise Contract penalty mode must be fixed_amount or percentage.');
        }
        return $mode;
    }

    public static function normalizeState(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract penalty state must be a string.');
        }
        $state = strtolower(trim($value));
        if (! in_array($state, [self::STATE_CONFIGURED, self::STATE_VOIDED], true)) {
            throw new InvalidArgumentException('Enterprise Contract penalty state must be configured or voided.');
        }
        return $state;
    }

    public static function normalizeLabel(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Enterprise Contract penalty label must be text.');
        }
        $label = trim((string) $value);
        if ($label === '' || strlen($label) > self::MAX_LABEL_BYTES) {
            throw new InvalidArgumentException('Enterprise Contract penalty label is required and must not exceed 120 bytes.');
        }
        return $label;
    }

    public static function normalizeUuid(mixed $value, string $field = 'UUID'): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Enterprise Contract penalty {$field} must be a UUIDv4 string.");
        }
        $uuid = strtolower(trim($value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw new InvalidArgumentException("Enterprise Contract penalty {$field} must be UUIDv4.");
        }
        return $uuid;
    }
}
