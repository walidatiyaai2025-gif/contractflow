<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;

final class ContractFinancialTaxRulePolicy
{
    public const KIND_TAX = 'tax';
    public const KIND_VAT = 'vat';
    public const STATE_CONFIGURED = 'configured';
    public const STATE_VOIDED = 'voided';
    public const MAX_RULES = 20;
    public const MAX_REVISION = 2147483647;
    public const MAX_LABEL_BYTES = 120;

    public static function normalizeKind(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract tax rule kind must be a string.');
        }
        $kind = strtolower(trim($value));
        if (! in_array($kind, [self::KIND_TAX, self::KIND_VAT], true)) {
            throw new InvalidArgumentException('Enterprise Contract tax rule kind must be tax or vat.');
        }
        return $kind;
    }

    public static function normalizeState(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract tax rule state must be a string.');
        }
        $state = strtolower(trim($value));
        if (! in_array($state, [self::STATE_CONFIGURED, self::STATE_VOIDED], true)) {
            throw new InvalidArgumentException('Enterprise Contract tax rule state must be configured or voided.');
        }
        return $state;
    }

    public static function normalizeLabel(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Enterprise Contract tax rule label must be text.');
        }
        $label = trim((string) $value);
        if ($label === '' || strlen($label) > self::MAX_LABEL_BYTES) {
            throw new InvalidArgumentException('Enterprise Contract tax rule label is required and must not exceed 120 bytes.');
        }
        return $label;
    }

    public static function normalizeUuid(mixed $value, string $field = 'UUID'): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Enterprise Contract tax rule {$field} must be a UUIDv4 string.");
        }
        $uuid = strtolower(trim($value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw new InvalidArgumentException("Enterprise Contract tax rule {$field} must be UUIDv4.");
        }
        return $uuid;
    }
}
