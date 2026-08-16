<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;

final class TenantMembershipAdminService
{
    public function __construct(private ?TenantMembershipRepository $memberships = null)
    {
        $this->memberships ??= new TenantMembershipRepository();
    }

    /** @return list<array{id:int,tenant_id:int,user_id:int,role_code:string,status:string,is_owner:bool}> */
    public function listForCurrentTenant(int $actorUserId): array
    {
        $tenantId = $this->authorizeActor($actorUserId);
        return $this->memberships->listForTenant($tenantId);
    }

    /**
     * Create/reactivate a non-owner membership or change a non-owner role.
     * Owner role/ownership mutation is intentionally not supported by this flow.
     */
    public function assignRole(int $targetUserId, string $roleCode, int $actorUserId): void
    {
        $tenantId = $this->authorizeActor($actorUserId);
        $roleCode = TenantRolePolicy::normalize($roleCode);
        if (! TenantRolePolicy::isAssignable($roleCode)) {
            throw new InvalidArgumentException('A recognized assignable Enterprise tenant role is required.');
        }
        if (! $this->wordpressUserExists($targetUserId)) {
            throw new InvalidArgumentException('The target WordPress user does not exist.');
        }

        $target = $this->memberships->findMembership($tenantId, $targetUserId);
        if ($target !== null && (bool) $target['is_owner']) {
            // Ownership escalation/transfer and owner-role changes require their own
            // deliberate workflow. Keeping owners immutable here also prevents the
            // last owner from being silently demoted through a generic role form.
            throw new RuntimeException('Owner memberships cannot be changed by the generic role-assignment flow.');
        }

        if (! $this->memberships->saveNonOwnerRole($tenantId, $targetUserId, $roleCode, $actorUserId)) {
            throw new RuntimeException('The tenant membership role could not be saved.');
        }
    }

    public function deactivate(int $targetUserId, int $actorUserId): void
    {
        $tenantId = $this->authorizeActor($actorUserId);
        $target = $this->memberships->findMembership($tenantId, $targetUserId);
        if ($target === null || (string) $target['status'] !== 'active') {
            throw new InvalidArgumentException('The active tenant membership was not found.');
        }

        if ((bool) $target['is_owner']) {
            $actor = $this->memberships->findActiveMembership($tenantId, $actorUserId);
            if ($actor === null || ! (bool) $actor['is_owner']) {
                throw new RuntimeException('Only an active tenant owner may deactivate another owner membership.');
            }
        }

        if (! $this->memberships->deactivateSafely($tenantId, $targetUserId)) {
            if ((bool) $target['is_owner']) {
                throw new RuntimeException('The last active tenant owner cannot be deactivated.');
            }
            throw new RuntimeException('The tenant membership could not be deactivated.');
        }
    }

    private function authorizeActor(int $actorUserId): int
    {
        if ($actorUserId <= 0 || $actorUserId !== get_current_user_id()) {
            throw new RuntimeException('The authenticated actor is required for tenant membership administration.');
        }
        if (! current_user_can(Capabilities::MANAGE_USERS)) {
            throw new RuntimeException('The actor lacks the global SafeContracts user-management capability.');
        }

        $context = TenantContextStore::context();
        $tenantId = $context->requireTenantId();
        if (! TenantAuthorization::currentUserHasActiveMembership()) {
            throw new RuntimeException('An active membership in the locked tenant is required.');
        }
        if (! TenantAuthorization::allowsCapability(Capabilities::MANAGE_USERS)) {
            throw new RuntimeException('The tenant role does not allow membership administration.');
        }

        return $tenantId;
    }

    private function wordpressUserExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (! function_exists('get_userdata')) {
            // WordPress always provides get_userdata() in production. This fallback
            // keeps isolated domain tests deterministic without weakening runtime.
            return true;
        }
        return get_userdata($userId) !== false;
    }
}
