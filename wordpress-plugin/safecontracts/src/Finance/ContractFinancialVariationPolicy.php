<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;

final class ContractFinancialVariationPolicy
{
    public const DIRECTION_ADDITION = 'addition';
    public const DIRECTION_DISCOUNT = 'discount';
    public const STATE_PROPOSED = 'proposed';
    public const STATE_VOIDED = 'voided';
    public const MAX_VARIATIONS = 200;
    public const MAX_REVISION = 2147483647;

    public static function normalizeDirection(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise financial variation direction must be a string.');
        }
        $direction = strtolower(trim($value));
        if (! in_array($direction, [self::DIRECTION_ADDITION, self::DIRECTION_DISCOUNT], true)) {
            throw new InvalidArgumentException('Enterprise financial variation direction must be addition or discount.');
        }
        return $direction;
    }

    public static function normalizeState(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise financial variation state must be a string.');
        }
        $state = strtolower(trim($value));
        if (! in_array($state, [self::STATE_PROPOSED, self::STATE_VOIDED], true)) {
            throw new InvalidArgumentException('Enterprise financial variation state must be proposed or voided.');
        }
        return $state;
    }

    public static function normalizeDescription(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Enterprise financial variation description must be text.');
        }
        $description = trim((string) $value);
        if ($description === '' || strlen($description) > 191) {
            throw new InvalidArgumentException('Enterprise financial variation description is required and must not exceed 191 characters.');
        }
        return $description;
    }

    public static function normalizeUuid(mixed $value, string $field = 'UUID'): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Enterprise financial variation {$field} must be a UUIDv4 string.");
        }
        $uuid = strtolower(trim($value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw new InvalidArgumentException("Enterprise financial variation {$field} must be UUIDv4.");
        }
        return $uuid;
    }
}
