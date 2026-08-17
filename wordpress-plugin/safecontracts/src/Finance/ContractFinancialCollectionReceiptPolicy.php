<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DateTimeImmutable;
use InvalidArgumentException;

final class ContractFinancialCollectionReceiptPolicy
{
    public const STATE_RECORDED = 'recorded';
    public const STATE_VOIDED = 'voided';
    public const MAX_RECEIPTS = 1000;
    public const MAX_REVISION = 2147483647;
    public const MAX_REFERENCE_BYTES = 120;

    public static function normalizeState(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract collection receipt state must be a string.');
        }
        $state = strtolower(trim($value));
        if (! in_array($state, [self::STATE_RECORDED, self::STATE_VOIDED], true)) {
            throw new InvalidArgumentException('Enterprise Contract collection receipt state must be recorded or voided.');
        }
        return $state;
    }

    public static function normalizeReference(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Enterprise Contract collection receipt reference must be text or null.');
        }
        $reference = trim((string) $value);
        if ($reference === '') {
            return null;
        }
        if (strlen($reference) > self::MAX_REFERENCE_BYTES) {
            throw new InvalidArgumentException('Enterprise Contract collection receipt reference must not exceed 120 bytes.');
        }
        return $reference;
    }

    public static function normalizeReceivedDate(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract collection receipt date must use YYYY-MM-DD.');
        }
        $date = trim($value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Enterprise Contract collection receipt date must be a valid YYYY-MM-DD calendar date.');
        }
        return $date;
    }

    public static function normalizeUuid(mixed $value, string $field = 'UUID'): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Enterprise Contract collection receipt {$field} must be a UUIDv4 string.");
        }
        $uuid = strtolower(trim($value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw new InvalidArgumentException("Enterprise Contract collection receipt {$field} must be UUIDv4.");
        }
        return $uuid;
    }
}
