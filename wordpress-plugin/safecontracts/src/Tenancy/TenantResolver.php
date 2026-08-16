<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use RuntimeException;

final class TenantResolver
{
    public function __construct(
        private readonly TenantMembershipRepository $memberships,
        private readonly TenantContext $context
    ) {}

    public function resolveForUser(int $userId, ?int $requestedTenantId = null): int
    {
        if ($userId <= 0) {
            throw new RuntimeException('Authenticated user is required to resolve tenant context.');
        }

        if ($requestedTenantId !== null) {
            if ($requestedTenantId <= 0 || ! $this->memberships->isActiveMember($requestedTenantId, $userId)) {
                throw new RuntimeException('Requested tenant is not available to the current user.');
            }
            $this->context->setTenantId($requestedTenantId);
            return $requestedTenantId;
        }

        $tenantIds = $this->memberships->activeTenantIdsForUser($userId);
        if ($tenantIds === []) {
            throw new RuntimeException('Current user has no active Enterprise tenant membership.');
        }
        if (count($tenantIds) !== 1) {
            throw new RuntimeException('Explicit tenant selection is required for users with multiple active tenants.');
        }

        $tenantId = $tenantIds[0];
        $this->context->setTenantId($tenantId);
        return $tenantId;
    }
}
