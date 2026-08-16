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
        if (! self::membershipBoundaryIsActive()) {
            return true;
        }

        $context = TenantContextStore::context();
        if (! $context->hasTenant()) {
            // Legacy/non-tenant authorization remains unchanged until a tenant-owned
            // operation has explicitly locked context.
            return true;
        }

        $userId = get_current_user_id();
        if ($userId <= 0) {
            return false;
        }

        return (new TenantMembershipRepository())->isActiveMember(
            $context->requireTenantId(),
            $userId
        );
    }

    private static function membershipBoundaryIsActive(): bool
    {
        return CoreTenantEnforcement::isEnabled() || NonCoreTenantEnforcement::isEnabled();
    }
}
