<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Customers\CustomerRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantAuthorization;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomerPartyLinkService
{
    public function __construct(
        private ?CustomerRepository $customers = null,
        private ?PartyRepository $parties = null,
        private ?PartyRoleRepository $partyRoles = null,
        private ?CustomerPartyLinkRepository $links = null
    ) {
        $this->customers ??= new CustomerRepository();
        $this->parties ??= new PartyRepository();
        $this->partyRoles ??= new PartyRoleRepository();
        $this->links ??= new CustomerPartyLinkRepository();
    }

    public function findByCustomer(int $customerId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($customerId, 'Customer ID');
        if ($this->customers->find($customerId) === null) {
            throw new InvalidArgumentException('Customer was not found in the current tenant.');
        }
        return $this->links->findByCustomer($customerId);
    }

    public function findByParty(int $partyId): ?array
    {
        $this->authorize(Capabilities::ACCESS);
        $this->requirePositive($partyId, 'Party ID');
        if ($this->parties->find($partyId) === null) {
            throw new InvalidArgumentException('Party was not found in the current tenant.');
        }
        return $this->links->findByParty($partyId);
    }

    public function link(int $customerId, int $partyId): void
    {
        $this->authorize(Capabilities::MANAGE_REFERENCE_DATA);
        $this->requirePositive($customerId, 'Customer ID');
        $this->requirePositive($partyId, 'Party ID');

        if ($this->customers->find($customerId) === null) {
            throw new InvalidArgumentException('Customer was not found in the current tenant.');
        }
        if ($this->parties->find($partyId) === null) {
            throw new InvalidArgumentException('Party was not found in the current tenant.');
        }
        if (! in_array(PartyRolePolicy::CUSTOMER, $this->partyRoles->activeRoles($partyId), true)) {
            throw new InvalidArgumentException('Party must already have the active customer business role before compatibility linking.');
        }

        $byCustomer = $this->links->findByCustomer($customerId);
        if ($byCustomer !== null) {
            if ((int) ($byCustomer['party_id'] ?? 0) !== $partyId) {
                throw new InvalidArgumentException('Customer is already linked to a different Party.');
            }
            return;
        }

        $byParty = $this->links->findByParty($partyId);
        if ($byParty !== null) {
            if ((int) ($byParty['customer_id'] ?? 0) !== $customerId) {
                throw new InvalidArgumentException('Party is already linked to a different Customer.');
            }
            return;
        }

        $actorId = get_current_user_id();
        $this->links->ensureLink($customerId, $partyId, $actorId);

        // Re-read both unique directions after the atomic insert/no-op. This turns
        // a concurrent conflicting link into a fail-closed result without ever
        // rewriting the winner's mapping.
        $byCustomer = $this->links->findByCustomer($customerId);
        $byParty = $this->links->findByParty($partyId);
        if (
            $byCustomer === null
            || $byParty === null
            || (int) ($byCustomer['party_id'] ?? 0) !== $partyId
            || (int) ($byParty['customer_id'] ?? 0) !== $customerId
        ) {
            throw new RuntimeException('Customer Party compatibility link conflicted with another mapping.');
        }

        do_action('safecontracts_enterprise_customer_party_linked', $customerId, $partyId, $actorId);
    }

    private function authorize(string $capability): void
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Customer Party compatibility requires core tenant enforcement.');
        }
        TenantContextStore::context()->requireTenantId();
        if (! current_user_can($capability) || ! TenantAuthorization::allowsCapability($capability)) {
            throw new DomainException('The current tenant role does not allow this Customer Party compatibility operation.');
        }
        if (get_current_user_id() <= 0) {
            throw new DomainException('An authenticated tenant user is required.');
        }
    }

    private function requirePositive(int $value, string $label): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("{$label} must be positive.");
        }
    }
}
