<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use InvalidArgumentException;

final class FinancialItemType
{
    public const LINE = 'line';
    public const ADDITION = 'addition';
    public const DISCOUNT = 'discount';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::LINE, self::ADDITION, self::DISCOUNT];
    }

    public static function normalize(string $type): string
    {
        $type = strtolower(trim($type));
        if (! in_array($type, self::all(), true)) {
            throw new InvalidArgumentException('Unsupported contract financial item type.');
        }

        return $type;
    }
}
