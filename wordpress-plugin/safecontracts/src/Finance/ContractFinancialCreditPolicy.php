<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;

final class ContractFinancialCreditPolicy
{
    public const STATE_PROPOSED = 'proposed';
    public const STATE_VOIDED = 'voided';
    public const MAX_CREDITS = 100;
    public const MAX_REVISION = 2147483647;
    public const MAX_REASON_BYTES = 191;

    public static function normalizeState(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract credit state must be a string.');
        }
        $state = strtolower(trim($value));
        if (! in_array($state, [self::STATE_PROPOSED, self::STATE_VOIDED], true)) {
            throw new InvalidArgumentException('Enterprise Contract credit state must be proposed or voided.');
        }
        return $state;
    }

    public static function normalizeReason(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Enterprise Contract credit reason must be text.');
        }
        $reason = trim((string) $value);
        if ($reason === '' || strlen($reason) > self::MAX_REASON_BYTES) {
            throw new InvalidArgumentException('Enterprise Contract credit reason is required and must not exceed 191 bytes.');
        }
        return $reason;
    }

    public static function normalizeUuid(mixed $value, string $field = 'UUID'): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Enterprise Contract credit {$field} must be a UUIDv4 string.");
        }
        $uuid = strtolower(trim($value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw new InvalidArgumentException("Enterprise Contract credit {$field} must be UUIDv4.");
        }
        return $uuid;
    }
}
