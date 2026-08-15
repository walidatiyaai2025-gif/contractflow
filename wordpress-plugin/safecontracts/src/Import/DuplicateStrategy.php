<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use InvalidArgumentException;

final class DuplicateStrategy
{
    public const FAIL = 'fail';
    public const SKIP = 'skip';
    public const UPDATE = 'update';

    public static function normalize(mixed $value): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            throw new InvalidArgumentException('Duplicate strategy is invalid.');
        }
        $value = strtolower(trim((string) $value));
        if (! in_array($value, [self::FAIL, self::SKIP, self::UPDATE], true)) {
            throw new InvalidArgumentException('Duplicate strategy must be fail, skip or update.');
        }
        return $value;
    }
}
