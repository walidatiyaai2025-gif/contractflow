<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class NotificationInboxRepository
{
    /** @return list<array<string,mixed>> */
    public function forUser(int $userId, int $limit = 100): array
    {
        global $wpdb;
        $userId = max(0, $userId);
        $limit = max(1, min(500, $limit));
        if ($userId < 1) {
            return [];
        }

        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, payment_id, template_code, scheduled_for, attempt_no, status, created_at
                 FROM {$table}
                 WHERE user_id = %d AND status = %s
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d",
                $userId,
                'sent',
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return array<string,mixed>|null */
    public function findOwned(int $notificationId, int $userId): ?array
    {
        global $wpdb;
        if ($notificationId < 1 || $userId < 1) {
            return null;
        }
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, payment_id, template_code, scheduled_for, attempt_no, status, created_at
                 FROM {$table}
                 WHERE id = %d AND user_id = %d AND status = %s
                 LIMIT 1",
                $notificationId,
                $userId,
                'sent'
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }
}
