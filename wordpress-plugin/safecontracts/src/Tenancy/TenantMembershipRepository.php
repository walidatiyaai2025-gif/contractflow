<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class TenantMembershipRepository
{
    /** @return list<int> */
    public function activeTenantIdsForUser(int $userId): array
    {
        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.tenant_id
             FROM {$memberships} m
             INNER JOIN {$tenants} t ON t.id = m.tenant_id
             WHERE m.user_id = %d AND m.status = 'active' AND t.status = 'active'
             ORDER BY m.is_owner DESC, m.tenant_id ASC",
            $userId
        ), ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        $tenantIds = [];
        foreach ($rows as $row) {
            $tenantId = isset($row['tenant_id']) ? (int) $row['tenant_id'] : 0;
            if ($tenantId > 0 && ! in_array($tenantId, $tenantIds, true)) {
                $tenantIds[] = $tenantId;
            }
        }
        return $tenantIds;
    }

    public function isActiveMember(int $tenantId, int $userId): bool
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return false;
        }

        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id
             FROM {$memberships} m
             INNER JOIN {$tenants} t ON t.id = m.tenant_id
             WHERE m.tenant_id = %d AND m.user_id = %d
               AND m.status = 'active' AND t.status = 'active'
             LIMIT 1",
            $tenantId,
            $userId
        ), ARRAY_A);

        return is_array($rows) && $rows !== [];
    }
}
