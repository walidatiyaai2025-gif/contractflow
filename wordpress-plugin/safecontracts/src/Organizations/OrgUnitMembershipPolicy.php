<?php

declare(strict_types=1);

namespace SafeContracts\Organizations;

final class OrgUnitMembershipPolicy
{
    public const MEMBER = 'member';
    public const MANAGER = 'manager';
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';

    /** @return list<string> */
    public static function roles(): array
    {
        return [self::MEMBER, self::MANAGER];
    }

    public static function normalize(string $role): string
    {
        return strtolower(trim($role));
    }

    public static function isSupported(string $role): bool
    {
        return in_array(self::normalize($role), self::roles(), true);
    }
}
