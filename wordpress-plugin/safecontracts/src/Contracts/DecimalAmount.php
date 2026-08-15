<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use DomainException;
use InvalidArgumentException;

final class DecimalAmount
{
    private const SCALE = 4;
    private const MAX_INTEGER_DIGITS = 16;

    public static function normalize(mixed $value): string
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Amount must be finite.');
            }
            $value = number_format($value, self::SCALE, '.', '');
        }

        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal with up to four decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > self::MAX_INTEGER_DIGITS) {
            throw new InvalidArgumentException('Amount exceeds SafeContracts financial precision.');
        }

        $fraction = str_pad($fraction, self::SCALE, '0');
        return $whole . '.' . $fraction;
    }

    public static function isZero(mixed $value): bool
    {
        return self::normalize($value) === '0.0000';
    }

    public static function add(mixed ...$amounts): string
    {
        $sum = '0';
        foreach ($amounts as $amount) {
            $sum = self::addUnsigned($sum, self::digits(self::normalize($amount)));
        }

        return self::fromDigits($sum);
    }

    public static function subtractNonNegative(mixed $left, mixed $right): string
    {
        $leftDigits = self::digits(self::normalize($left));
        $rightDigits = self::digits(self::normalize($right));
        if (self::compareUnsigned($leftDigits, $rightDigits) < 0) {
            throw new DomainException('Financial reconciliation cannot produce a negative contract value.');
        }

        return self::fromDigits(self::subtractUnsigned($leftDigits, $rightDigits));
    }

    private static function digits(string $normalized): string
    {
        $digits = ltrim(str_replace('.', '', $normalized), '0');
        return $digits === '' ? '0' : $digits;
    }

    private static function fromDigits(string $digits): string
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;
        $digits = str_pad($digits, self::SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -self::SCALE);
        $fraction = substr($digits, -self::SCALE);
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole) . '.' . $fraction;
    }

    private static function addUnsigned(string $left, string $right): string
    {
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($i >= 0 || $j >= 0 || $carry > 0) {
            $sum = $carry;
            if ($i >= 0) {
                $sum += (int) $left[$i--];
            }
            if ($j >= 0) {
                $sum += (int) $right[$j--];
            }
            $result = (string) ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function compareUnsigned(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';
        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }

    private static function subtractUnsigned(string $left, string $right): string
    {
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        $borrow = 0;
        $result = '';

        while ($i >= 0) {
            $digit = (int) $left[$i--] - $borrow - ($j >= 0 ? (int) $right[$j--] : 0);
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result = (string) $digit . $result;
        }

        return ltrim($result, '0') ?: '0';
    }
}
