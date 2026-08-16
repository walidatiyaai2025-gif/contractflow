<?php

declare(strict_types=1);

namespace SafeContracts\Customers;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantScope;

final class CustomerRepository
{
    public function find(int $customerId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_customers';
        $tenant = $this->tenantCondition();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, internal_code, name, contact_name, email, phone, notes, is_active FROM {$table} WHERE id = %d{$tenant} LIMIT 1",
            $customerId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return $rows[0];
    }

    public function create(array $data, int $actorId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_customers';
        $tenantId = CoreTenantScope::tenantId();
        $codeSql = $this->nullableStringSql((string) $data['internal_code']);
        if ($tenantId === null) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (internal_code, name, contact_name, email, phone, notes, is_active, created_by, created_at, updated_at)
                 VALUES ({$codeSql}, %s, %s, %s, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                $data['name'], $data['contact_name'], $data['email'], $data['phone'], $data['notes'], $data['is_active'] ? 1 : 0, $actorId
            );
        } else {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (tenant_id, internal_code, name, contact_name, email, phone, notes, is_active, created_by, created_at, updated_at)
                 VALUES (%d, {$codeSql}, %s, %s, %s, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                $tenantId, $data['name'], $data['contact_name'], $data['email'], $data['phone'], $data['notes'], $data['is_active'] ? 1 : 0, $actorId
            );
        }
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create customer.');
        }
        return (int) $wpdb->insert_id;
    }

    public function update(int $customerId, array $data): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_customers';
        $codeSql = $this->nullableStringSql((string) $data['internal_code']);
        $tenant = $this->tenantCondition();
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET internal_code = {$codeSql}, name = %s, contact_name = %s, email = %s, phone = %s, notes = %s, is_active = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}",
            $data['name'], $data['contact_name'], $data['email'], $data['phone'], $data['notes'], $data['is_active'] ? 1 : 0, $customerId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update customer.');
        }
    }

    private function tenantCondition(string $column = 'tenant_id'): string
    {
        $tenantId = CoreTenantScope::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }

    private function nullableStringSql(string $value): string
    {
        return $value === '' ? 'NULL' : "'" . addslashes($value) . "'";
    }
}
