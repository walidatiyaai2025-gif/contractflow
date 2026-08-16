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

    /** @return array{id:int,tenant_id:int,user_id:int,role_code:string,is_owner:bool}|null */
    public function findActiveMembership(int $tenantId, int $userId): ?array
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return null;
        }

        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.tenant_id, m.user_id, m.role_code, m.is_owner
             FROM {$memberships} m
             INNER JOIN {$tenants} t ON t.id = m.tenant_id
             WHERE m.tenant_id = %d AND m.user_id = %d
               AND m.status = 'active' AND t.status = 'active'
             LIMIT 1",
            $tenantId,
            $userId
        ), ARRAY_A);

        if (! is_array($rows) || $rows === []) {
            return null;
        }
        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? $tenantId),
            'user_id' => (int) ($row['user_id'] ?? $userId),
            'role_code' => (string) ($row['role_code'] ?? TenantRolePolicy::MEMBER),
            'is_owner' => (bool) ((int) ($row['is_owner'] ?? 0)),
        ];
    }

    /** @return array{id:int,tenant_id:int,user_id:int,role_code:string,status:string,is_owner:bool}|null */
    public function findMembership(int $tenantId, int $userId): ?array
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return null;
        }

        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tenant_id, user_id, role_code, status, is_owner
             FROM {$memberships}
             WHERE tenant_id = %d AND user_id = %d
             LIMIT 1",
            $tenantId,
            $userId
        ), ARRAY_A);

        if (! is_array($rows) || $rows === []) {
            return null;
        }
        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? $tenantId),
            'user_id' => (int) ($row['user_id'] ?? $userId),
            'role_code' => (string) ($row['role_code'] ?? TenantRolePolicy::MEMBER),
            'status' => (string) ($row['status'] ?? 'inactive'),
            'is_owner' => (bool) ((int) ($row['is_owner'] ?? 0)),
        ];
    }

    /** @return list<array{id:int,tenant_id:int,user_id:int,role_code:string,status:string,is_owner:bool}> */
    public function listForTenant(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [];
        }

        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tenant_id, user_id, role_code, status, is_owner
             FROM {$memberships}
             WHERE tenant_id = %d
             ORDER BY is_owner DESC, status ASC, user_id ASC",
            $tenantId
        ), ARRAY_A);

        $items = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'tenant_id' => (int) ($row['tenant_id'] ?? $tenantId),
                'user_id' => $userId,
                'role_code' => (string) ($row['role_code'] ?? TenantRolePolicy::MEMBER),
                'status' => (string) ($row['status'] ?? 'inactive'),
                'is_owner' => (bool) ((int) ($row['is_owner'] ?? 0)),
            ];
        }
        return $items;
    }

    /**
     * Create or reactivate a non-owner membership with an explicit role.
     * The update path cannot touch owner rows, and both update keys are scoped by
     * tenant_id + user_id so a caller cannot mutate another tenant by user ID.
     */
    public function saveNonOwnerRole(int $tenantId, int $userId, string $roleCode, int $actorUserId): bool
    {
        if ($tenantId <= 0 || $userId <= 0 || $actorUserId <= 0 || ! TenantRolePolicy::isAssignable($roleCode)) {
            return false;
        }

        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $now = gmdate('Y-m-d H:i:s');
        $roleCode = TenantRolePolicy::normalize($roleCode);
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$memberships}
             SET role_code = %s, status = 'active', updated_at = %s
             WHERE tenant_id = %d AND user_id = %d AND is_owner = 0",
            $roleCode,
            $now,
            $tenantId,
            $userId
        ));
        if ($updated === false) {
            return false;
        }
        if ($updated > 0) {
            return true;
        }

        // MySQL legitimately reports zero affected rows when the requested active
        // role is already stored. Re-read the tenant+user key before deciding that
        // an INSERT is needed so an idempotent save never turns into a duplicate-key
        // insert, and an owner race can never be overwritten by the generic flow.
        $existing = $this->findMembership($tenantId, $userId);
        if ($existing !== null) {
            return ! $existing['is_owner']
                && $existing['status'] === 'active'
                && TenantRolePolicy::normalize($existing['role_code']) === $roleCode;
        }

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$memberships}
                (tenant_id, user_id, role_code, status, is_owner, created_by, created_at, updated_at)
             VALUES (%d, %d, %s, 'active', 0, %d, %s, %s)",
            $tenantId,
            $userId,
            $roleCode,
            $actorUserId,
            $now,
            $now
        ));
        return $inserted === 1;
    }

    /**
     * Atomically deactivate a tenant membership while preserving at least one
     * active owner. The derived owner-count guard prevents the last owner from
     * being removed even if concurrent requests race between read and write.
     */
    public function deactivateSafely(int $tenantId, int $userId): bool
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return false;
        }

        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $now = gmdate('Y-m-d H:i:s');
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$memberships} AS target
             SET target.status = 'inactive', target.updated_at = %s
             WHERE target.tenant_id = %d
               AND target.user_id = %d
               AND target.status = 'active'
               AND (
                    target.is_owner = 0
                    OR (
                        SELECT owner_count
                        FROM (
                            SELECT COUNT(*) AS owner_count
                            FROM {$memberships}
                            WHERE tenant_id = %d AND status = 'active' AND is_owner = 1
                        ) AS owner_guard
                    ) > 1
               )",
            $now,
            $tenantId,
            $userId,
            $tenantId
        ));
        return $affected === 1;
    }

    public function isActiveMember(int $tenantId, int $userId): bool
    {
        return $this->findActiveMembership($tenantId, $userId) !== null;
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
