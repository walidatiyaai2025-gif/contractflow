<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class PartyRoleRepository
{
    /** @return list<string> */
    public function activeRoles(int $partyId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_party_roles';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT role_code FROM {$table} WHERE tenant_id = %d AND party_id = %d AND status = 'active' ORDER BY role_code ASC",
            $tenantId,
            $partyId
        ), ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        $roles = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['role_code']) && is_string($row['role_code'])) {
                $roles[] = $row['role_code'];
            }
        }
        return $roles;
    }

    public function assign(int $partyId, string $roleCode, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_party_roles';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, party_id, role_code, status, assigned_by, revoked_by, created_at, updated_at, revoked_at)
             VALUES (%d, %d, %s, 'active', %d, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL)
             ON DUPLICATE KEY UPDATE
                assigned_by = IF(status = 'active', assigned_by, VALUES(assigned_by)),
                revoked_by = IF(status = 'active', revoked_by, NULL),
                revoked_at = IF(status = 'active', revoked_at, NULL),
                updated_at = IF(status = 'active', updated_at, UTC_TIMESTAMP()),
                status = 'active'",
            $tenantId,
            $partyId,
            $roleCode,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to assign Enterprise Party role.');
        }
    }

    public function revoke(int $partyId, string $roleCode, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_party_roles';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET revoked_by = IF(status = 'active', %d, revoked_by),
                 revoked_at = IF(status = 'active', UTC_TIMESTAMP(), revoked_at),
                 updated_at = IF(status = 'active', UTC_TIMESTAMP(), updated_at),
                 status = 'inactive'
             WHERE tenant_id = %d AND party_id = %d AND role_code = %s",
            $actorId,
            $tenantId,
            $partyId,
            $roleCode
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to revoke Enterprise Party role.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Party role access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
