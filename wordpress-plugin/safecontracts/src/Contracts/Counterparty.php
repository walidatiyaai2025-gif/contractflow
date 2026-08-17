<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use InvalidArgumentException;

final class Counterparty
{
    public const CUSTOMER = 'customer';
    public const SUPPLIER = 'supplier';

    public static function normalize(mixed $value): string
    {
        $type = strtolower(trim((string) $value));
        if (! in_array($type, [self::CUSTOMER, self::SUPPLIER], true)) {
            throw new InvalidArgumentException('Contract counterparty type must be customer or supplier.');
        }
        return $type;
    }

    public static function defaultFinancialDirection(string $type): string
    {
        return self::normalize($type) === self::SUPPLIER ? 'payable' : 'receivable';
    }
}
