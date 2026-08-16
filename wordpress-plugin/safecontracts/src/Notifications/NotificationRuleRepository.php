<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use RuntimeException;
use SafeContracts\Tenancy\NonCoreTenantScope;

final class NotificationRuleRepository
{
    private const SELECT_FIELDS = 'id, code, name, trigger_type, days_before, days_after, repeat_interval_days, max_repeats, recipient_roles_json, recipient_user_ids_json, escalation_roles_json, target_assigned_accountant, push_enabled, email_enabled, template_code, is_active, created_by, updated_by, created_at, updated_at';

    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $where = $activeOnly ? ' WHERE is_active = 1' : ' WHERE 1 = 1';
        $rows = $wpdb->get_results(
            'SELECT ' . self::SELECT_FIELDS . " FROM {$table}{$where}" . NonCoreTenantScope::condition() . ' ORDER BY is_active DESC, trigger_type ASC, days_before ASC, days_after ASC, name ASC',
            ARRAY_A
        );

        return $this->normalizeRows($rows);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT ' . self::SELECT_FIELDS . " FROM {$table} WHERE code = %s" . NonCoreTenantScope::condition() . ' LIMIT 1', $code),
            ARRAY_A
        );
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return NotificationRule::fromRow($rows[0]);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT ' . self::SELECT_FIELDS . " FROM {$table} WHERE id = %d" . NonCoreTenantScope::condition() . ' LIMIT 1', $id),
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
        $rolesJson = $this->encodeList($rule['recipient_roles']);
        $usersJson = $this->encodeList($rule['recipient_user_ids']);
        $escalationRolesJson = $this->encodeList($rule['escalation_roles']);
        $now = gmdate('Y-m-d H:i:s');
        $tenantId = NonCoreTenantScope::tenantId();

        if ($tenantId === null) {
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table}
                    (code, name, trigger_type, days_before, days_after, repeat_interval_days, max_repeats, recipient_roles_json, recipient_user_ids_json, escalation_roles_json, target_assigned_accountant, push_enabled, email_enabled, template_code, is_active, created_by, updated_by, created_at, updated_at)
                 VALUES (%s, %s, %s, %d, %d, %d, %d, %s, %s, %s, %d, %d, %d, %s, %d, %d, %d, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name), trigger_type = VALUES(trigger_type), days_before = VALUES(days_before), days_after = VALUES(days_after),
                    repeat_interval_days = VALUES(repeat_interval_days), max_repeats = VALUES(max_repeats), recipient_roles_json = VALUES(recipient_roles_json),
                    recipient_user_ids_json = VALUES(recipient_user_ids_json), escalation_roles_json = VALUES(escalation_roles_json),
                    target_assigned_accountant = VALUES(target_assigned_accountant), push_enabled = VALUES(push_enabled), email_enabled = VALUES(email_enabled),
                    template_code = VALUES(template_code), is_active = VALUES(is_active), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
                $rule['code'], $rule['name'], $rule['trigger_type'], $rule['days_before'], $rule['days_after'], $rule['repeat_interval_days'],
                $rule['max_repeats'], $rolesJson, $usersJson, $escalationRolesJson, $rule['target_assigned_accountant'] ? 1 : 0,
                $rule['push_enabled'] ? 1 : 0, $rule['email_enabled'] ? 1 : 0, $rule['template_code'], $rule['is_active'] ? 1 : 0,
                $actorId, $actorId, $now, $now
            ));
            if ($result === false) {
                throw new RuntimeException('SafeContracts notification rule persistence failed.');
            }
            return;
        }

        $existing = $this->findByCode((string) $rule['code']);
        if ($existing !== null) {
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET
                    name = %s, trigger_type = %s, days_before = %d, days_after = %d, repeat_interval_days = %d, max_repeats = %d,
                    recipient_roles_json = %s, recipient_user_ids_json = %s, escalation_roles_json = %s,
                    target_assigned_accountant = %d, push_enabled = %d, email_enabled = %d, template_code = %s, is_active = %d,
                    updated_by = %d, updated_at = %s
                 WHERE id = %d AND tenant_id = %d",
                $rule['name'], $rule['trigger_type'], $rule['days_before'], $rule['days_after'], $rule['repeat_interval_days'], $rule['max_repeats'],
                $rolesJson, $usersJson, $escalationRolesJson, $rule['target_assigned_accountant'] ? 1 : 0,
                $rule['push_enabled'] ? 1 : 0, $rule['email_enabled'] ? 1 : 0, $rule['template_code'], $rule['is_active'] ? 1 : 0,
                $actorId, $now, (int) $existing['id'], $tenantId
            ));
            if ($result === false) {
                throw new RuntimeException('Enterprise notification rule update failed.');
            }
            return;
        }

        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (tenant_id, code, name, trigger_type, days_before, days_after, repeat_interval_days, max_repeats, recipient_roles_json, recipient_user_ids_json, escalation_roles_json, target_assigned_accountant, push_enabled, email_enabled, template_code, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %s, %s, %s, %d, %d, %d, %d, %s, %s, %s, %d, %d, %d, %s, %d, %d, %d, %s, %s)",
            $tenantId, $rule['code'], $rule['name'], $rule['trigger_type'], $rule['days_before'], $rule['days_after'], $rule['repeat_interval_days'],
            $rule['max_repeats'], $rolesJson, $usersJson, $escalationRolesJson, $rule['target_assigned_accountant'] ? 1 : 0,
            $rule['push_enabled'] ? 1 : 0, $rule['email_enabled'] ? 1 : 0, $rule['template_code'], $rule['is_active'] ? 1 : 0,
            $actorId, $actorId, $now, $now
        ));
        if ($result === false) {
            throw new RuntimeException('Enterprise notification rule insert failed; a legacy cross-tenant rule-code collision may require reviewed schema hardening.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function activeBeforeDue(int $daysBefore): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_FIELDS . " FROM {$table}
                 WHERE is_active = 1 AND trigger_type = %s AND days_before = %d" . NonCoreTenantScope::condition() . "
                 ORDER BY id ASC",
                NotificationRule::TRIGGER_BEFORE_DUE,
                $daysBefore
            ),
            ARRAY_A
        );
        return $this->normalizeRows($rows);
    }

    /** @return list<array<string, mixed>> */
    public function activeForTrigger(string $trigger): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_FIELDS . " FROM {$table}
                 WHERE is_active = 1 AND trigger_type = %s" . NonCoreTenantScope::condition() . "
                 ORDER BY days_before ASC, days_after ASC, id ASC",
                $trigger
            ),
            ARRAY_A
        );
        return $this->normalizeRows($rows);
    }

    /** @return list<array<string, mixed>> */
    private function normalizeRows(mixed $rows): array
    {
        return array_map(static fn (array $row): array => NotificationRule::fromRow($row), is_array($rows) ? $rows : []);
    }

    private function encodeList(mixed $items): string
    {
        $json = json_encode(is_array($items) ? array_values($items) : [], JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '[]';
    }
}
