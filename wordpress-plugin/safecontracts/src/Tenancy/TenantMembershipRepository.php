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

        return in_array($userId, $this->filterActiveUserIds($tenantId, [$userId]), true);
    }

    /**
     * Keep only users with an active membership in an active tenant.
     * This is the shared stale/foreign-membership boundary used before tenant-owned
     * notification fan-out and other user-targeted background work.
     *
     * @param list<int> $userIds
     * @return list<int>
     */
    public function filterActiveUserIds(int $tenantId, array $userIds): array
    {
        if ($tenantId <= 0) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn (int $userId): bool => $userId > 0
        )));
        if ($ids === []) {
            return [];
        }
        $ids = array_slice($ids, 0, 1000);

        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT m.user_id
             FROM {$memberships} m
             INNER JOIN {$tenants} t ON t.id = m.tenant_id
             WHERE m.tenant_id = %d
               AND m.user_id IN ({$placeholders})
               AND m.status = 'active' AND t.status = 'active'
             ORDER BY m.user_id ASC",
            $tenantId,
            ...$ids
        ), ARRAY_A);

        $active = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId > 0) {
                $active[] = $userId;
            }
        }
        return array_values(array_unique($active));
    }
}
