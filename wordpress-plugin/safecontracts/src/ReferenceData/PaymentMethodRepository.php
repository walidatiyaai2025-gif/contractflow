<?php

declare(strict_types=1);

namespace SafeContracts\ReferenceData;

use InvalidArgumentException;
use RuntimeException;

final class PaymentMethodRepository
{
    /** @return list<array{id:int, code:string, name:string, sort_order:int, is_active:bool}> */
    public function all(bool $activeOnly = false): array
    {
        global $wpdb;

        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts payment methods require WordPress $wpdb.');
        }

        $table = $wpdb->prefix . 'safecontracts_payment_methods';
        $where = $activeOnly ? ' WHERE is_active = 1' : '';
        $sql = "SELECT id, code, name, sort_order, is_active FROM {$table}{$where} ORDER BY sort_order ASC, name ASC";
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        return array_map(
            static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => (bool) ($row['is_active'] ?? false),
            ],
            $rows
        );
    }

    /** @param array{code:mixed, name:mixed, sort_order?:mixed, is_active?:mixed} $input */
    public function save(array $input): array
    {
        global $wpdb;

        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts payment methods require WordPress $wpdb.');
        }

        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $sortOrder = max(0, (int) ($input['sort_order'] ?? 0));
        $isActive = ! empty($input['is_active']) ? 1 : 0;

        if ($code === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $code)) {
            throw new InvalidArgumentException('Payment method code must be 2-50 lowercase letters, numbers, underscores or hyphens.');
        }

        if ($name === '' || strlen($name) > 120) {
            throw new InvalidArgumentException('Payment method name is required and must not exceed 120 characters.');
        }

        $table = $wpdb->prefix . 'safecontracts_payment_methods';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (code, name, sort_order, is_active, created_at, updated_at)
             VALUES (%s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                sort_order = VALUES(sort_order),
                is_active = VALUES(is_active),
                updated_at = UTC_TIMESTAMP()",
            $code,
            $name,
            $sortOrder,
            $isActive
        );

        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to save payment method.');
        }

        do_action('safecontracts_payment_method_saved', $code, $name, $sortOrder, (bool) $isActive);

        return [
            'code' => $code,
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => (bool) $isActive,
        ];
    }
}
