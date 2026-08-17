<?php

declare(strict_types=1);

namespace SafeContracts\Counterparties;

use InvalidArgumentException;
use SafeContracts\Finance\FinancialDirection;

final class CounterpartyType
{
    public const CUSTOMER = 'customer';
    public const SUPPLIER = 'supplier';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::CUSTOMER, self::SUPPLIER];
    }

    public static function normalize(mixed $value): string
    {
        $type = strtolower(trim((string) $value));
        if (! in_array($type, self::all(), true)) {
            throw new InvalidArgumentException('Counterparty type must be customer or supplier.');
        }

        return $type;
    }

    public static function financialDirection(string $type): string
    {
        return match (self::normalize($type)) {
            self::CUSTOMER => FinancialDirection::RECEIVABLE,
            self::SUPPLIER => FinancialDirection::PAYABLE,
        };
    }
}
