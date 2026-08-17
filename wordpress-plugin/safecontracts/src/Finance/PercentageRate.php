<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use OverflowException;

final class PercentageRate
{
    public const SCALE = 4;
    public const MAX = '100.0000';

    private function __construct(private string $value)
    {
    }

    public static function of(mixed $value): self
    {
        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_string($value)) {
            $raw = trim($value);
        } else {
            throw new InvalidArgumentException('Enterprise percentage rate must be an integer or plain decimal string.');
        }

        if ($raw === '' || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $raw) !== 1) {
            throw new InvalidArgumentException('Enterprise percentage rate must use non-negative plain decimal notation.');
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($fraction) > self::SCALE) {
            throw new InvalidArgumentException('Enterprise percentage rate exceeds the four-decimal scale.');
        }
        if (strlen($whole) > 3) {
            throw new OverflowException('Enterprise percentage rate exceeds 100 percent.');
        }

        $fraction = str_pad($fraction, self::SCALE, '0', STR_PAD_RIGHT);
        $normalized = $whole . '.' . $fraction;
        if (self::compareNormalized($normalized, self::MAX) > 0) {
            throw new OverflowException('Enterprise percentage rate exceeds 100 percent.');
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function compareNormalized(string $left, string $right): int
    {
        $leftDigits = ltrim(str_replace('.', '', $left), '0');
        $rightDigits = ltrim(str_replace('.', '', $right), '0');
        $leftDigits = $leftDigits === '' ? '0' : $leftDigits;
        $rightDigits = $rightDigits === '' ? '0' : $rightDigits;

        $lengthComparison = strlen($leftDigits) <=> strlen($rightDigits);
        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return strcmp($leftDigits, $rightDigits) <=> 0;
    }
}
