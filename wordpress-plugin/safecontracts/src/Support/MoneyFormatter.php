<?php

declare(strict_types=1);

namespace SafeContracts\Support;

use SafeContracts\Contracts\ContractMoney;

/**
 * Presentation-only monetary formatting.
 *
 * Storage and arithmetic remain DECIMAL(20,4) through ContractMoney. This
 * formatter only controls what users see in admin/report surfaces.
 */
final class MoneyFormatter
{
    public static function format(mixed $value, string $currency = ''): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            $raw = '0';
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }

        $normalized = ContractMoney::normalizeNonNegative($raw);
        $currency = strtoupper(trim($currency));
        $fractionDigits = $currency === 'EGP' ? 0 : 2;
        [$whole, $fraction] = self::round($normalized, $fractionDigits);
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;

        if ($fractionDigits > 0) {
            $fraction = rtrim($fraction, '0');
        }
        $amount = $whole . ($fraction === '' ? '' : '.' . $fraction);
        if ($amount === '0') {
            $negative = false;
        }

        $prefix = $negative ? '− ' : '';
        if ($currency === '' || $currency === '—') {
            return $prefix . $amount;
        }
        return $prefix . $currency . ' ' . $amount;
    }

    /** @return array{0:string,1:string} */
    private static function round(string $normalized, int $digits): array
    {
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, ContractMoney::SCALE, '0');
        if ($digits >= ContractMoney::SCALE) {
            return [$whole, substr($fraction, 0, $digits)];
        }

        $kept = $digits > 0 ? substr($fraction, 0, $digits) : '';
        $roundDigit = (int) ($fraction[$digits] ?? '0');
        if ($roundDigit >= 5) {
            if ($digits === 0) {
                $whole = self::incrementDigits($whole);
            } else {
                $combined = $whole . $kept;
                $combined = self::incrementDigits($combined);
                $combined = str_pad($combined, strlen($whole) + $digits, '0', STR_PAD_LEFT);
                $whole = substr($combined, 0, -$digits);
                $kept = substr($combined, -$digits);
            }
        }

        return [$whole, $kept];
    }

    private static function incrementDigits(string $digits): string
    {
        $carry = 1;
        $result = '';
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $value = (int) $digits[$i] + $carry;
            $result = (string) ($value % 10) . $result;
            $carry = $value >= 10 ? 1 : 0;
        }
        if ($carry > 0) {
            $result = '1' . $result;
        }
        return $result;
    }
}
