<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class NotificationRuleRepository
{
    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $where = $activeOnly ? ' WHERE is_active = 1' : '';
        $rows = $wpdb->get_results(
            "SELECT id, code, name, trigger_type, days_before, recipient_roles_json, target_assigned_accountant, is_active, created_by, updated_by, created_at, updated_at
             FROM {$table}{$where}
             ORDER BY is_active DESC, days_before ASC, name ASC",
            ARRAY_A
        );

        return array_map(static fn (array $row): array => NotificationRule::fromRow($row), is_array($rows) ? $rows : []);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, code, name, trigger_type, days_before, recipient_roles_json, target_assigned_accountant, is_active, created_by, updated_by, created_at, updated_at
                 FROM {$table} WHERE code = %s LIMIT 1",
                $code
            ),
            ARRAY_A
        );
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return NotificationRule::fromRow($rows[0]);
    }

    /** @param array<string, mixed> $rule */
    public function save(array $rule, int $actorId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rolesJson = json_encode($rule['recipient_roles'], JSON_UNESCAPED_SLASHES);
        if (! is_string($rolesJson)) {
            $rolesJson = '[]';
        }
        $now = gmdate('Y-m-d H:i:s');

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (code, name, trigger_type, days_before, recipient_roles_json, target_assigned_accountant, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%s, %s, %s, %d, %s, %d, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                trigger_type = VALUES(trigger_type),
                days_before = VALUES(days_before),
                recipient_roles_json = VALUES(recipient_roles_json),
                target_assigned_accountant = VALUES(target_assigned_accountant),
                is_active = VALUES(is_active),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)",
            $rule['code'],
            $rule['name'],
            $rule['trigger_type'],
            $rule['days_before'],
            $rolesJson,
            $rule['target_assigned_accountant'] ? 1 : 0,
            $rule['is_active'] ? 1 : 0,
            $actorId,
            $actorId,
            $now,
            $now
        ));
    }

    /** @return list<array<string, mixed>> */
    public function activeBeforeDue(int $daysBefore): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, code, name, trigger_type, days_before, recipient_roles_json, target_assigned_accountant, is_active, created_by, updated_by, created_at, updated_at
                 FROM {$table}
                 WHERE is_active = 1 AND trigger_type = %s AND days_before = %d
                 ORDER BY id ASC",
                NotificationRule::TRIGGER_BEFORE_DUE,
                $daysBefore
            ),
            ARRAY_A
        );
        return array_map(static fn (array $row): array => NotificationRule::fromRow($row), is_array($rows) ? $rows : []);
    }
}
