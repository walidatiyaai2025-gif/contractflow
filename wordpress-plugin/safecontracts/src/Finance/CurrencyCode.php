<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;

final class CurrencyCode
{
    public static function normalize(mixed $value, bool $allowBlank = false): string
    {
        $currency = strtoupper(trim((string) $value));
        if ($currency === '' && $allowBlank) {
            return '';
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency code must be a three-letter ISO-style code.');
        }

        return $currency;
    }
}
