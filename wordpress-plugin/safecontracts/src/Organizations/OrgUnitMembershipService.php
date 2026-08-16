<?php

declare(strict_types=1);

namespace SafeContracts\Organizations;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Tenancy\TenantMembershipRepository;

final class OrgUnitMembershipService
{
    public function __construct(
        private ?OrgUnitRepository $orgUnits = null,
        private ?TenantMembershipRepository $tenantMemberships = null,
        private ?OrgUnitMembershipRepository $assignments = null
    ) {
        $this->orgUnits ??= new OrgUnitRepository();
        $this->tenantMemberships ??= new TenantMembershipRepository();
        $this->assignments ??= new OrgUnitMembershipRepository();
    }

    /** @return list<array<string,mixed>> */
    public function listForUnit(int $orgUnitId, int $limit = 100, int $offset = 0): array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requireOrgUnit($orgUnitId);
        return $this->assignments->listForUnit($orgUnitId, $limit, $offset);
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId, int $limit = 100, int $offset = 0): array
    {
        $tenantId = $this->authorize(Capabilities::ACCESS);
        $this->requireActiveTenantUser($tenantId, $userId);
        return $this->assignments->listForUser($userId, $limit, $offset);
    }

    public function assign(int $orgUnitId, int $userId, string $assignmentRole = OrgUnitMembershipPolicy::MEMBER): void
    {
        $tenantId = $this->authorize(Capabilities::MANAGE_USERS);
        $assignmentRole = OrgUnitMembershipPolicy::normalize($assignmentRole);
        if (! OrgUnitMembershipPolicy::isSupported($assignmentRole)) {
            throw new InvalidArgumentException('Organization-unit assignment role is not supported.');
        }

        $this->requireOrgUnit($orgUnitId);
        $this->requireActiveTenantUser($tenantId, $userId);
        $actorId = get_current_user_id();
        $this->assignments->assign($orgUnitId, $userId, $assignmentRole, $actorId);
        do_action('safecontracts_enterprise_org_unit_member_assigned', $orgUnitId, $userId, $assignmentRole, $actorId);
    }

    public function revoke(int $orgUnitId, int $userId): void
    {
        $this->authorize(Capabilities::MANAGE_USERS);
        $this->requireOrgUnit($orgUnitId);
        if ($userId <= 0) {
            throw new InvalidArgumentException('Organization-unit member user ID must be positive.');
        }

        $actorId = get_current_user_id();
        $this->assignments->revoke($orgUnitId, $userId, $actorId);
        do_action('safecontracts_enterprise_org_unit_member_revoked', $orgUnitId, $userId, $actorId);
    }

    private function authorize(string $capability): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise organization-unit membership requires core tenant enforcement.');
        }
        $tenantId = TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this organization-unit membership operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
        return $tenantId;
    }

    private function requireOrgUnit(int $orgUnitId): void
    {
        if ($orgUnitId <= 0) {
            throw new InvalidArgumentException('Organization unit ID must be positive.');
        }
        if ($this->orgUnits->find($orgUnitId) === null) {
            throw new InvalidArgumentException('Organization unit was not found in the current tenant.');
        }
    }

    private function requireActiveTenantUser(int $tenantId, int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Organization-unit member user ID must be positive.');
        }
        if ($this->tenantMemberships->findActiveMembership($tenantId, $userId) === null) {
            throw new InvalidArgumentException('Target user is not an active member of the current tenant.');
        }
    }
}
