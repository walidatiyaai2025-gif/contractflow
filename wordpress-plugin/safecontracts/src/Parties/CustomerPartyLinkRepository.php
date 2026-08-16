<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class CustomerPartyLinkRepository
{
    public function findByCustomer(int $customerId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_customer_party_links';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, customer_id, party_id, provenance, linked_by, created_at, updated_at FROM {$table} WHERE tenant_id = %d AND customer_id = %d LIMIT 1",
            $tenantId,
            $customerId
        ), ARRAY_A);

        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function findByParty(int $partyId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_customer_party_links';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, customer_id, party_id, provenance, linked_by, created_at, updated_at FROM {$table} WHERE tenant_id = %d AND party_id = %d LIMIT 1",
            $tenantId,
            $partyId
        ), ARRAY_A);

        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function ensureLink(int $customerId, int $partyId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_customer_party_links';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, customer_id, party_id, provenance, linked_by, created_at, updated_at)
             VALUES (%d, %d, %d, 'manual', %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE id = id",
            $tenantId,
            $customerId,
            $partyId,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create Enterprise Customer Party compatibility link.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Customer Party compatibility requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
