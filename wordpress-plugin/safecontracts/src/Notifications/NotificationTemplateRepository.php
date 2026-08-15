<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class NotificationTemplateRepository
{
    /** @return array<string, mixed>|null */
    public function findActiveByCode(string $code): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_templates';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, code, title_template, body_template, is_active, created_by, updated_by, created_at, updated_at
                 FROM {$table} WHERE code = %s AND is_active = 1 LIMIT 1",
                NotificationRule::normalizeCode($code)
            ),
            ARRAY_A
        );
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return NotificationTemplate::fromRow($rows[0]);
    }

    /** @param array{code:string,title_template:string,body_template:string,is_active:bool} $template */
    public function save(array $template, int $actorId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_templates';
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (code, title_template, body_template, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%s, %s, %s, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                title_template = VALUES(title_template),
                body_template = VALUES(body_template),
                is_active = VALUES(is_active),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)",
            $template['code'],
            $template['title_template'],
            $template['body_template'],
            $template['is_active'] ? 1 : 0,
            $actorId,
            $actorId,
            $now,
            $now
        ));
    }
}
