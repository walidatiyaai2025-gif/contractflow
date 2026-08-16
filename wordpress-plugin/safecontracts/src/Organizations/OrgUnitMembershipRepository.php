<?php

declare(strict_types=1);

namespace SafeContracts\Organizations;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class OrgUnitMembershipRepository
{
    /** @return list<array<string,mixed>> */
    public function listForUnit(int $orgUnitId, int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_org_unit_memberships';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, org_unit_id, user_id, assignment_role, status, created_by, updated_by, created_at, updated_at
             FROM {$table}
             WHERE tenant_id = %d AND org_unit_id = %d AND status = 'active'
             ORDER BY assignment_role DESC, user_id ASC, id ASC
             LIMIT %d OFFSET %d",
            $tenantId,
            $orgUnitId,
            $limit,
            $offset
        ), ARRAY_A);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId, int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_org_unit_memberships';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, org_unit_id, user_id, assignment_role, status, created_by, updated_by, created_at, updated_at
             FROM {$table}
             WHERE tenant_id = %d AND user_id = %d AND status = 'active'
             ORDER BY org_unit_id ASC, id ASC
             LIMIT %d OFFSET %d",
            $tenantId,
            $userId,
            $limit,
            $offset
        ), ARRAY_A);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function assign(int $orgUnitId, int $userId, string $assignmentRole, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_org_unit_memberships';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (tenant_id, org_unit_id, user_id, assignment_role, status, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %d, %d, %s, 'active', %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                assignment_role = VALUES(assignment_role),
                status = 'active',
                updated_by = VALUES(updated_by),
                updated_at = UTC_TIMESTAMP()",
            $tenantId,
            $orgUnitId,
            $userId,
            $assignmentRole,
            $actorId,
            $actorId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to save Enterprise organization-unit membership.');
        }
    }

    public function revoke(int $orgUnitId, int $userId, int $actorId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_org_unit_memberships';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET status = 'inactive', updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE tenant_id = %d AND org_unit_id = %d AND user_id = %d AND status <> 'inactive'",
            $actorId,
            $tenantId,
            $orgUnitId,
            $userId
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to revoke Enterprise organization-unit membership.');
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise organization-unit membership access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
