<?php

declare(strict_types=1);

namespace SafeContracts\Suppliers;

use InvalidArgumentException;

final class SupplierStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const SUSPENDED = 'suspended';
    public const ARCHIVED = 'archived';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::SUSPENDED, self::ARCHIVED];
    }

    public static function normalize(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        if (! in_array($status, self::all(), true)) {
            throw new InvalidArgumentException('Supplier status is invalid.');
        }
        return $status;
    }
}
