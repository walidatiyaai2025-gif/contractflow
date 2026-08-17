<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;

final class CurrencyCode
{
    private function __construct(private string $code)
    {
    }

    public static function from(mixed $value): self
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Enterprise currency code must be a three-letter string.');
        }

        $code = strtoupper(trim($value));
        if (preg_match('/^[A-Z]{3}$/D', $code) !== 1) {
            throw new InvalidArgumentException('Enterprise currency code must contain exactly three ASCII letters.');
        }

        return new self($code);
    }

    public function value(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
