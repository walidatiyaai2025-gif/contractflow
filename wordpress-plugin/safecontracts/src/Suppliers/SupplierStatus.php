<?php

declare(strict_types=1);

namespace SafeContracts\Suppliers;

use InvalidArgumentException;

final class SupplierStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const SUSPENDED = 'suspended';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::SUSPENDED];
    }

    public static function normalize(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('Supplier status must be active, inactive, or suspended.');
        }
        return $status;
    }

    public static function isActive(string $status): bool
    {
        return self::normalize($status) === self::ACTIVE;
    }
}
