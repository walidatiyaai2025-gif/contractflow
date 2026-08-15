<?php

declare(strict_types=1);

namespace SafeContracts\Support;

use InvalidArgumentException;

final class Input
{
    public static function string(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$field} must be a string.");
        }

        return $value;
    }

    public static function int(mixed $value, string $field, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            $number = (int) trim($value);
        } else {
            throw new InvalidArgumentException("{$field} must be an integer.");
        }

        if ($number < $min || $number > $max) {
            throw new InvalidArgumentException("{$field} is outside the allowed range.");
        }

        return $number;
    }

    /** @param list<string> $allowed */
    public static function oneOf(mixed $value, array $allowed, string $field): string
    {
        $candidate = strtolower(trim(self::string($value, $field)));
        if (! in_array($candidate, $allowed, true)) {
            throw new InvalidArgumentException("{$field} is invalid.");
        }

        return $candidate;
    }
}
