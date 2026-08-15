<?php

declare(strict_types=1);

namespace SafeContracts\Customers;

use RuntimeException;

final class CustomerRepository
{
    public function find(int $customerId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_customers';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, internal_code, name, contact_name, email, phone, notes, is_active FROM {$table} WHERE id = %d LIMIT 1",
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
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (internal_code, name, contact_name, email, phone, notes, is_active, created_by, created_at, updated_at)
             VALUES (%s, %s, %s, %s, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $data['internal_code'], $data['name'], $data['contact_name'], $data['email'], $data['phone'], $data['notes'], $data['is_active'] ? 1 : 0, $actorId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create customer.');
        }
        return (int) $wpdb->insert_id;
    }

    public function update(int $customerId, array $data): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_customers';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET internal_code = %s, name = %s, contact_name = %s, email = %s, phone = %s, notes = %s, is_active = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d",
            $data['internal_code'], $data['name'], $data['contact_name'], $data['email'], $data['phone'], $data['notes'], $data['is_active'] ? 1 : 0, $customerId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update customer.');
        }
    }
}
