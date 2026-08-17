<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DateTimeImmutable;
use InvalidArgumentException;
use OverflowException;

final class ContractFinancialPaymentSchedulePolicy
{
    public const STATE_SCHEDULED = 'scheduled';
    public const STATE_VOIDED = 'voided';
    public const MAX_ENTRIES = 500;
    public const MAX_REVISION = 2147483647;
    public const MAX_SEQUENCE = 2147483647;
    public const MAX_REFERENCE_BYTES = 100;

    public static function normalizeState(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule state must be a string.');
        }
        $state = strtolower(trim($value));
        if (! in_array($state, [self::STATE_SCHEDULED, self::STATE_VOIDED], true)) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule state must be scheduled or voided.');
        }
        return $state;
    }

    public static function normalizeSequence(mixed $value): int
    {
        if (is_int($value)) {
            $sequence = $value;
        } elseif (is_string($value) && preg_match('/^[0-9]+$/D', trim($value)) === 1) {
            $raw = ltrim(trim($value), '0');
            $raw = $raw === '' ? '0' : $raw;
            if (strlen($raw) > 10 || (strlen($raw) === 10 && strcmp($raw, (string) self::MAX_SEQUENCE) > 0)) {
                throw new OverflowException('Enterprise Contract payment schedule sequence exceeds the supported range.');
            }
            $sequence = (int) $raw;
        } else {
            throw new InvalidArgumentException('Enterprise Contract payment schedule sequence must be a positive integer.');
        }

        if ($sequence <= 0) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule sequence must be positive.');
        }
        if ($sequence > self::MAX_SEQUENCE) {
            throw new OverflowException('Enterprise Contract payment schedule sequence exceeds the supported range.');
        }
        return $sequence;
    }

    public static function normalizeReference(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule reference must be text or null.');
        }
        $reference = trim((string) $value);
        if ($reference === '') {
            return null;
        }
        if (strlen($reference) > self::MAX_REFERENCE_BYTES) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule reference must not exceed 100 bytes.');
        }
        return $reference;
    }

    public static function normalizeDueDate(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule due date must use YYYY-MM-DD.');
        }
        $date = trim($value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule due date must be a valid YYYY-MM-DD calendar date.');
        }
        return $date;
    }

    public static function normalizeUuid(mixed $value, string $field = 'UUID'): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Enterprise Contract payment schedule {$field} must be a UUIDv4 string.");
        }
        $uuid = strtolower(trim($value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw new InvalidArgumentException("Enterprise Contract payment schedule {$field} must be UUIDv4.");
        }
        return $uuid;
    }
}
