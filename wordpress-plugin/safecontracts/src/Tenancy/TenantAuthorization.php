<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class TenantAuthorization
{
    /**
     * WordPress capabilities remain the global permission ceiling, but once an
     * Enterprise tenant context is locked every tenant-owned authorization check
     * must also confirm that the current user is still an active member of that
     * active tenant. This catches stale membership after context resolution and
     * protects service/admin paths that reuse a locked context.
     */
    public static function currentUserHasActiveMembership(): bool
    {
        if (! self::tenantRoleBoundaryApplies()) {
            return true;
        }

        return self::activeMembership() !== null;
    }

    /**
     * Tenant roles only narrow an already-existing global WordPress grant.
     * Callers must still check current_user_can($capability) separately.
     */
    public static function allowsCapability(string $capability): bool
    {
        if (! self::tenantRoleBoundaryApplies()) {
            return true;
        }

        $membership = self::activeMembership();
        if ($membership === null) {
            return false;
        }

        return TenantRolePolicy::allowsCapability(
            (string) $membership['role_code'],
            (bool) $membership['is_owner'],
            $capability
        );
    }

    /**
     * @return 'all'|'assigned'|'inherit'|'none'|null
     * Null means tenant-role narrowing does not apply to the current operation.
     */
    public static function scopeCeiling(): ?string
    {
        if (! self::tenantRoleBoundaryApplies()) {
            return null;
        }

        $membership = self::activeMembership();
        if ($membership === null) {
            return 'none';
        }

        return TenantRolePolicy::scopeCeiling(
            (string) $membership['role_code'],
            (bool) $membership['is_owner']
        );
    }

    private static function tenantRoleBoundaryApplies(): bool
    {
        if (! self::membershipBoundaryIsActive()) {
            return false;
        }
        return TenantContextStore::context()->hasTenant();
    }

    /** @return array{id:int,tenant_id:int,user_id:int,role_code:string,is_owner:bool}|null */
    private static function activeMembership(): ?array
    {
        $context = TenantContextStore::context();
        if (! $context->hasTenant()) {
            return null;
        }

        $userId = get_current_user_id();
        if ($userId <= 0) {
            return null;
        }

        return (new TenantMembershipRepository())->findActiveMembership(
            $context->requireTenantId(),
            $userId
        );
    }

    private static function membershipBoundaryIsActive(): bool
    {
        return CoreTenantEnforcement::isEnabled() || NonCoreTenantEnforcement::isEnabled();
    }
}
