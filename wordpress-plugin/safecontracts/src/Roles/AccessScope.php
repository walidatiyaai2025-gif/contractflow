<?php

declare(strict_types=1);

namespace SafeContracts\Roles;

use SafeContracts\Tenancy\TenantAuthorization;

final class AccessScope
{
    public const ALL = 'all';
    public const ASSIGNED = 'assigned';
    public const NONE = 'none';

    public static function current(): string
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return self::ALL;
        }

        if (current_user_can(Capabilities::VIEW_ASSIGNED)) {
            return self::ASSIGNED;
        }

        return self::NONE;
    }

    public static function canAccess(): bool
    {
        return current_user_can(Capabilities::ACCESS)
            && self::current() !== self::NONE
            && TenantAuthorization::currentUserHasActiveMembership();
    }
}
