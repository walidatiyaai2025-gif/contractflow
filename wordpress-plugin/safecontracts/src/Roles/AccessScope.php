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
        $global = self::globalScope();
        $tenantCeiling = TenantAuthorization::scopeCeiling();

        if ($tenantCeiling === null || $tenantCeiling === 'inherit') {
            return $global;
        }
        if ($global === self::NONE || $tenantCeiling === 'none') {
            return self::NONE;
        }

        // A tenant role is a narrowing ceiling only. It can reduce VIEW_ALL to
        // assigned scope, but it can never turn a global assigned grant into all.
        if ($tenantCeiling === self::ASSIGNED) {
            return self::ASSIGNED;
        }

        return $global;
    }

    public static function canAccess(): bool
    {
        return current_user_can(Capabilities::ACCESS)
            && self::current() !== self::NONE
            && TenantAuthorization::currentUserHasActiveMembership()
            && TenantAuthorization::allowsCapability(Capabilities::ACCESS);
    }

    private static function globalScope(): string
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return self::ALL;
        }

        if (current_user_can(Capabilities::VIEW_ASSIGNED)) {
            return self::ASSIGNED;
        }

        return self::NONE;
    }
}
