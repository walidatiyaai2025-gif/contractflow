<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use InvalidArgumentException;

final class ContractMoney
{
    public const SCALE = 4;

    public static function normalizeNonNegative(mixed $value): string
    {
        $raw = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $raw)) {
            throw new InvalidArgumentException('Financial amount must be a non-negative number with at most 4 decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 16) {
            throw new InvalidArgumentException('Financial amount exceeds DECIMAL(20,4) capacity.');
        }

        return $whole . '.' . str_pad($fraction, self::SCALE, '0');
    }

    /** @param list<string> $amounts */
    public static function sum(array $amounts): string
    {
        $scaled = '0';
        foreach ($amounts as $amount) {
            $scaled = self::addScaled($scaled, self::toScaled(self::normalizeNonNegative($amount)));
        }

        return self::fromScaled($scaled);
    }

    public static function reconcile(string $base, string $items, string $additions, string $discounts): string
    {
        $gross = self::addScaled(
            self::addScaled(self::toScaled(self::normalizeNonNegative($base)), self::toScaled(self::normalizeNonNegative($items))),
            self::toScaled(self::normalizeNonNegative($additions))
        );
        $discountScaled = self::toScaled(self::normalizeNonNegative($discounts));

        $comparison = self::compareScaled($gross, $discountScaled);
        if ($comparison >= 0) {
            return self::fromScaled(self::subtractScaled($gross, $discountScaled));
        }

        return '-' . self::fromScaled(self::subtractScaled($discountScaled, $gross));
    }

    private static function toScaled(string $normalized): string
    {
        return ltrim(str_replace('.', '', $normalized), '0') ?: '0';
    }

    private static function fromScaled(string $scaled): string
    {
        $scaled = ltrim($scaled, '0') ?: '0';
        $scaled = str_pad($scaled, self::SCALE + 1, '0', STR_PAD_LEFT);
        return substr($scaled, 0, -self::SCALE) . '.' . substr($scaled, -self::SCALE);
    }

    private static function addScaled(string $left, string $right): string
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

    private static function compareScaled(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';
        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }

    private static function subtractScaled(string $left, string $right): string
    {
        $right = str_pad($right, strlen($left), '0', STR_PAD_LEFT);
        $borrow = 0;
        $result = '';

        for ($i = strlen($left) - 1; $i >= 0; $i--) {
            $digit = (int) $left[$i] - $borrow - (int) $right[$i];
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
