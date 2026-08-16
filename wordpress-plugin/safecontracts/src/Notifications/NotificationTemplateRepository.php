<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use RuntimeException;
use SafeContracts\Tenancy\NonCoreTenantScope;

final class NotificationTemplateRepository
{
    private const SELECT_FIELDS = 'id, code, title_template, body_template, email_subject_template, email_body_template, icon_key, is_active, created_by, updated_by, created_at, updated_at';

    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_templates';
        $where = $activeOnly ? ' WHERE is_active = 1' : ' WHERE 1 = 1';
        $rows = $wpdb->get_results(
            'SELECT ' . self::SELECT_FIELDS . " FROM {$table}{$where}" . NonCoreTenantScope::condition() . ' ORDER BY is_active DESC, code ASC',
            ARRAY_A
        );
        return array_map(
            static fn (array $row): array => NotificationTemplate::fromRow($row),
            is_array($rows) ? $rows : []
        );
    }

    /** @return array<string, mixed>|null */
    public function findActiveByCode(string $code): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_templates';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_FIELDS . " FROM {$table} WHERE code = %s AND is_active = 1" . NonCoreTenantScope::condition() . ' LIMIT 1',
                NotificationRule::normalizeCode($code)
            ),
            ARRAY_A
        );
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return NotificationTemplate::fromRow($rows[0]);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_templates';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . self::SELECT_FIELDS . " FROM {$table} WHERE code = %s" . NonCoreTenantScope::condition() . ' LIMIT 1',
                NotificationRule::normalizeCode($code)
            ),
            ARRAY_A
        );
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return NotificationTemplate::fromRow($rows[0]);
    }

    /** @param array<string,mixed> $template */
    public function save(array $template, int $actorId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_templates';
        $now = gmdate('Y-m-d H:i:s');
        $tenantId = NonCoreTenantScope::tenantId();

        if ($tenantId === null) {
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table}
                    (code, title_template, body_template, email_subject_template, email_body_template, icon_key, is_active, created_by, updated_by, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, %s, %d, %d, %d, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    title_template = VALUES(title_template), body_template = VALUES(body_template),
                    email_subject_template = VALUES(email_subject_template), email_body_template = VALUES(email_body_template),
                    icon_key = VALUES(icon_key), is_active = VALUES(is_active), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
                $template['code'], $template['title_template'], $template['body_template'], $template['email_subject_template'],
                $template['email_body_template'], $template['icon_key'], $template['is_active'] ? 1 : 0,
                $actorId, $actorId, $now, $now
            ));
            if ($result === false) {
                throw new RuntimeException('SafeContracts notification template persistence failed.');
            }
            return;
        }

        $existing = $this->findByCode((string) $template['code']);
        if ($existing !== null) {
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET title_template = %s, body_template = %s, email_subject_template = %s,
                    email_body_template = %s, icon_key = %s, is_active = %d, updated_by = %d, updated_at = %s
                 WHERE id = %d AND tenant_id = %d",
                $template['title_template'], $template['body_template'], $template['email_subject_template'],
                $template['email_body_template'], $template['icon_key'], $template['is_active'] ? 1 : 0,
                $actorId, $now, (int) $existing['id'], $tenantId
            ));
            if ($result === false) {
                throw new RuntimeException('Enterprise notification template update failed.');
            }
            return;
        }

        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (tenant_id, code, title_template, body_template, email_subject_template, email_body_template, icon_key, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%d, %s, %s, %s, %s, %s, %s, %d, %d, %d, %s, %s)",
            $tenantId, $template['code'], $template['title_template'], $template['body_template'], $template['email_subject_template'],
            $template['email_body_template'], $template['icon_key'], $template['is_active'] ? 1 : 0,
            $actorId, $actorId, $now, $now
        ));
        if ($result === false) {
            throw new RuntimeException('Enterprise notification template insert failed; a legacy cross-tenant template-code collision may require reviewed schema hardening.');
        }
    }
}
