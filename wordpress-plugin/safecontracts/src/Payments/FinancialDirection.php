<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use InvalidArgumentException;

final class FinancialDirection
{
    public const RECEIVABLE = 'receivable';
    public const PAYABLE = 'payable';

    public static function normalize(mixed $value): string
    {
        $direction = strtolower(trim((string) $value));
        if (! in_array($direction, [self::RECEIVABLE, self::PAYABLE], true)) {
            throw new InvalidArgumentException('Financial direction must be receivable or payable.');
        }
        return $direction;
    }
}
