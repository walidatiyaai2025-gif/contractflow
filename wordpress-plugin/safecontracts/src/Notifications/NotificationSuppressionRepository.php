<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;

final class NotificationSuppressionRepository
{
    public const CONTRACT = 'contract';
    public const PAYMENT = 'payment';

    /** @return list<string> */
    public static function scopeTypes(): array
    {
        return [self::CONTRACT, self::PAYMENT];
    }

    public function isSuppressed(int $paymentId, int $contractId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_suppressions';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE is_active = 1 AND ((scope_type = %s AND scope_id = %d) OR (scope_type = %s AND scope_id = %d))
                 LIMIT 1",
                self::PAYMENT,
                $paymentId,
                self::CONTRACT,
                $contractId
            ),
            ARRAY_A
        );
        return is_array($rows) && $rows !== [];
    }

    public function set(string $scopeType, int $scopeId, bool $suppressed, string $reason, int $actorId): void
    {
        global $wpdb;
        $scopeType = $this->normalizeScopeType($scopeType);
        if ($scopeId <= 0) {
            throw new InvalidArgumentException('Notification suppression scope ID must be positive.');
        }
        $reason = trim(sanitize_text_field($reason));
        if (strlen($reason) > 191) {
            throw new InvalidArgumentException('Notification suppression reason must not exceed 191 characters.');
        }
        $table = $wpdb->prefix . 'safecontracts_notification_suppressions';
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (scope_type, scope_id, reason, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%s, %d, %s, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), is_active = VALUES(is_active), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
            $scopeType,
            $scopeId,
            $reason,
            $suppressed ? 1 : 0,
            $actorId,
            $actorId,
            $now,
            $now
        ));
        do_action('safecontracts_notification_suppression_changed', $scopeType, $scopeId, $suppressed, $reason, $actorId);
    }

    /** @return list<array<string,mixed>> */
    public function active(int $limit = 250): array
    {
        global $wpdb;
        $limit = max(1, min(1000, $limit));
        $table = $wpdb->prefix . 'safecontracts_notification_suppressions';
        $rows = $wpdb->get_results(
            "SELECT id, scope_type, scope_id, reason, created_by, updated_by, created_at, updated_at
             FROM {$table} WHERE is_active = 1 ORDER BY updated_at DESC, id DESC LIMIT {$limit}",
            ARRAY_A
        );
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function normalizeScopeType(string $value): string
    {
        $value = sanitize_key($value);
        if (! in_array($value, self::scopeTypes(), true)) {
            throw new InvalidArgumentException('Unsupported notification suppression scope.');
        }
        return $value;
    }
}
