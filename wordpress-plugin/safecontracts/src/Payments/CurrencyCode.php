<?php

declare(strict_types=1);

namespace SafeContracts\Payments;

use InvalidArgumentException;
use SafeContracts\Settings\GeneralSettings;

final class CurrencyCode
{
    public const UNKNOWN = 'XXX';

    public static function normalize(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency code must be a three-letter ISO-style code.');
        }
        return $currency;
    }

    public static function fromInputOrSettings(mixed $value): string
    {
        if ($value !== null && trim((string) $value) !== '') {
            return self::normalize($value);
        }
        $configured = (new GeneralSettings())->read()['currency_code'];
        return $configured !== '' ? self::normalize($configured) : self::UNKNOWN;
    }
}
