<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class TenantDirectoryRepository
{
    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        global $wpdb;
        $memberships = $wpdb->prefix . 'safecontracts_tenant_memberships';
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.uuid, t.slug, t.name, t.legal_name, t.country_code,
                    t.timezone, t.default_currency, t.locale,
                    m.role_code, m.is_owner
             FROM {$memberships} m
             INNER JOIN {$tenants} t ON t.id = m.tenant_id
             WHERE m.user_id = %d AND m.status = 'active' AND t.status = 'active'
             ORDER BY m.is_owner DESC, t.name ASC, t.id ASC",
            $userId
        ), ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $tenantId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($tenantId <= 0) {
                continue;
            }
            $items[] = [
                'id' => $tenantId,
                'uuid' => (string) ($row['uuid'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'legal_name' => ($row['legal_name'] ?? null) === null ? null : (string) $row['legal_name'],
                'country_code' => ($row['country_code'] ?? null) === null ? null : (string) $row['country_code'],
                'timezone' => (string) ($row['timezone'] ?? 'UTC'),
                'default_currency' => (string) ($row['default_currency'] ?? 'USD'),
                'locale' => (string) ($row['locale'] ?? 'en_US'),
                'role_code' => (string) ($row['role_code'] ?? 'member'),
                'is_owner' => (bool) ((int) ($row['is_owner'] ?? 0)),
            ];
        }

        return $items;
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $tenantId, int $userId): ?array
    {
        foreach ($this->forUser($userId) as $tenant) {
            if ((int) $tenant['id'] === $tenantId) {
                return $tenant;
            }
        }
        return null;
    }
}
