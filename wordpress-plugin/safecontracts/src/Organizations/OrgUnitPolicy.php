<?php

declare(strict_types=1);

namespace SafeContracts\Organizations;

final class OrgUnitPolicy
{
    public const TYPE_DEPARTMENT = 'department';
    public const TYPE_TEAM = 'team';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const MAX_HIERARCHY_DEPTH = 64;

    /** @return list<string> */
    public static function types(): array
    {
        return [self::TYPE_DEPARTMENT, self::TYPE_TEAM];
    }

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    public static function isType(string $type): bool
    {
        return in_array($type, self::types(), true);
    }

    public static function isStatus(string $status): bool
    {
        return in_array($status, self::statuses(), true);
    }
}
