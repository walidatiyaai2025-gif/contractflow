<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;

final class FinancialDirection
{
    public const PAYABLE = 'payable';
    public const RECEIVABLE = 'receivable';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PAYABLE, self::RECEIVABLE];
    }

    public static function normalize(mixed $value): string
    {
        $direction = strtolower(trim((string) $value));
        if (! in_array($direction, self::all(), true)) {
            throw new InvalidArgumentException('Financial direction must be payable or receivable.');
        }

        return $direction;
    }

    public static function transactionKind(string $direction): string
    {
        return match (self::normalize($direction)) {
            self::PAYABLE => 'payment',
            self::RECEIVABLE => 'receipt',
        };
    }
}
