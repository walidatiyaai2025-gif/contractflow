<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class PartyRoleService
{
    public function __construct(
        private ?PartyRepository $parties = null,
        private ?PartyRoleRepository $roles = null
    ) {
        $this->parties ??= new PartyRepository();
        $this->roles ??= new PartyRoleRepository();
    }

    /** @return list<string> */
    public function rolesForParty(int $partyId): array
    {
        $this->requireReadPermission();
        $this->requireParty($partyId);
        return $this->roles->activeRoles($partyId);
    }

    public function assign(int $partyId, string $roleCode): void
    {
        $this->requireWritePermission();
        $roleCode = $this->role($roleCode);
        $this->requireParty($partyId);

        $actorId = get_current_user_id();
        $this->roles->assign($partyId, $roleCode, $actorId);
        do_action('safecontracts_enterprise_party_role_assigned', $partyId, $roleCode, $actorId);
    }

    public function revoke(int $partyId, string $roleCode): void
    {
        $this->requireWritePermission();
        $roleCode = $this->role($roleCode);
        $this->requireParty($partyId);

        $actorId = get_current_user_id();
        $this->roles->revoke($partyId, $roleCode, $actorId);
        do_action('safecontracts_enterprise_party_role_revoked', $partyId, $roleCode, $actorId);
    }

    private function requireParty(int $partyId): void
    {
        if ($partyId <= 0) {
            throw new InvalidArgumentException('Party ID must be positive.');
        }
        if ($this->parties->find($partyId) === null) {
            throw new InvalidArgumentException('Party was not found in the current tenant.');
        }
    }

    private function role(string $roleCode): string
    {
        $roleCode = PartyRolePolicy::normalize($roleCode);
        if (! PartyRolePolicy::isSupported($roleCode)) {
            throw new InvalidArgumentException('Party business role is not supported.');
        }
        return $roleCode;
    }

    private function requireReadPermission(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to Enterprise Party roles.');
        }
    }

    private function requireWritePermission(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            throw new DomainException('You do not have permission to manage Enterprise Party roles.');
        }
    }
}
