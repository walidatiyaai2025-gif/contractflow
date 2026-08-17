<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DomainException;
use InvalidArgumentException;
use OverflowException;

final class Money
{
    public const SCALE = 4;
    public const MAX_WHOLE_DIGITS = 16;
    private const MAX_SCALED_DIGITS = self::MAX_WHOLE_DIGITS + self::SCALE;

    private function __construct(
        private string $amount,
        private CurrencyCode $currency
    ) {
    }

    public static function of(mixed $amount, CurrencyCode|string $currency): self
    {
        $currencyCode = is_string($currency) ? CurrencyCode::from($currency) : $currency;

        return new self(self::normalizeAmount($amount), $currencyCode);
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function currencyCode(): string
    {
        return $this->currency->value();
    }

    /** @return array{amount:string,currency:string} */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency->value(),
        ];
    }

    public function isZero(): bool
    {
        return $this->amount === '0.0000';
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency)
            && $this->amount === $other->amount;
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);
        [$leftSign, $leftDigits] = self::parts($this->amount);
        [$rightSign, $rightDigits] = self::parts($other->amount);

        if ($leftSign !== $rightSign) {
            return $leftSign <=> $rightSign;
        }
        if ($leftSign === 0) {
            return 0;
        }

        $comparison = self::compareAbs($leftDigits, $rightDigits);
        return $leftSign > 0 ? $comparison : -$comparison;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        [$leftSign, $leftDigits] = self::parts($this->amount);
        [$rightSign, $rightDigits] = self::parts($other->amount);

        if ($leftSign === 0) {
            return self::fromScaled($rightSign, $rightDigits, $this->currency);
        }
        if ($rightSign === 0) {
            return self::fromScaled($leftSign, $leftDigits, $this->currency);
        }
        if ($leftSign === $rightSign) {
            return self::fromScaled($leftSign, self::addAbs($leftDigits, $rightDigits), $this->currency);
        }

        $comparison = self::compareAbs($leftDigits, $rightDigits);
        if ($comparison === 0) {
            return self::fromScaled(0, '0', $this->currency);
        }
        if ($comparison > 0) {
            return self::fromScaled($leftSign, self::subtractAbs($leftDigits, $rightDigits), $this->currency);
        }

        return self::fromScaled($rightSign, self::subtractAbs($rightDigits, $leftDigits), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->add($other->negate());
    }

    public function negate(): self
    {
        if ($this->isZero()) {
            return new self('0.0000', $this->currency);
        }

        $amount = str_starts_with($this->amount, '-')
            ? substr($this->amount, 1)
            : '-' . $this->amount;

        return new self($amount, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw new DomainException(sprintf(
                'Enterprise Money currency mismatch: %s cannot be combined with %s without an explicit conversion.',
                $this->currency->value(),
                $other->currency->value()
            ));
        }
    }

    private static function normalizeAmount(mixed $value): string
    {
        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_string($value)) {
            $raw = trim($value);
        } else {
            throw new InvalidArgumentException('Enterprise Money amount must be an integer or plain decimal string.');
        }

        if ($raw === '' || preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $raw) !== 1) {
            throw new InvalidArgumentException('Enterprise Money amount must use plain decimal notation.');
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($fraction) > self::SCALE) {
            throw new InvalidArgumentException('Enterprise Money amount exceeds the four-decimal scale.');
        }
        if (strlen($whole) > self::MAX_WHOLE_DIGITS) {
            throw new OverflowException('Enterprise Money amount exceeds DECIMAL(20,4) capacity.');
        }

        $fraction = str_pad($fraction, self::SCALE, '0', STR_PAD_RIGHT);
        if ($whole === '0' && trim($fraction, '0') === '') {
            $negative = false;
        }

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }

    /** @return array{int,string} */
    private static function parts(string $amount): array
    {
        $negative = str_starts_with($amount, '-');
        if ($negative) {
            $amount = substr($amount, 1);
        }

        $digits = str_replace('.', '', $amount);
        $digits = self::stripZeros($digits);
        if ($digits === '0') {
            return [0, '0'];
        }

        return [$negative ? -1 : 1, $digits];
    }

    private static function fromScaled(int $sign, string $digits, CurrencyCode $currency): self
    {
        $digits = self::stripZeros($digits);
        if ($digits === '0' || $sign === 0) {
            return new self('0.0000', $currency);
        }
        if (strlen($digits) > self::MAX_SCALED_DIGITS) {
            throw new OverflowException('Enterprise Money arithmetic exceeds DECIMAL(20,4) capacity.');
        }

        $digits = str_pad($digits, self::SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -self::SCALE);
        $fraction = substr($digits, -self::SCALE);
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        return new self(($sign < 0 ? '-' : '') . $whole . '.' . $fraction, $currency);
    }

    private static function stripZeros(string $digits): string
    {
        $digits = ltrim($digits, '0');
        return $digits === '' ? '0' : $digits;
    }

    private static function compareAbs(string $left, string $right): int
    {
        $left = self::stripZeros($left);
        $right = self::stripZeros($right);
        $lengthComparison = strlen($left) <=> strlen($right);
        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return strcmp($left, $right) <=> 0;
    }

    private static function addAbs(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = $carry;
            if ($leftIndex >= 0) {
                $sum += (int) $left[$leftIndex--];
            }
            if ($rightIndex >= 0) {
                $sum += (int) $right[$rightIndex--];
            }
            $result = (string) ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }

        return self::stripZeros($result);
    }

    private static function subtractAbs(string $left, string $right): string
    {
        if (self::compareAbs($left, $right) < 0) {
            throw new InvalidArgumentException('Enterprise Money internal subtraction requires the left magnitude to be greater than or equal to the right.');
        }

        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $borrow = 0;
        $result = '';

        while ($leftIndex >= 0) {
            $digit = (int) $left[$leftIndex--] - $borrow;
            $borrow = 0;
            if ($rightIndex >= 0) {
                $digit -= (int) $right[$rightIndex--];
            }
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            }
            $result = (string) $digit . $result;
        }

        return self::stripZeros($result);
    }
}
