<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\NonCoreTenantScope;

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
                 WHERE is_active = 1 AND ((scope_type = %s AND scope_id = %d) OR (scope_type = %s AND scope_id = %d))" . NonCoreTenantScope::condition() . "
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
        $tenantId = NonCoreTenantScope::tenantId();

        if ($tenantId === null) {
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table} (scope_type, scope_id, reason, is_active, created_by, updated_by, created_at, updated_at)
                 VALUES (%s, %d, %s, %d, %d, %d, %s, %s)
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason), is_active = VALUES(is_active), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
                $scopeType, $scopeId, $reason, $suppressed ? 1 : 0, $actorId, $actorId, $now, $now
            ));
            if ($result === false) {
                throw new RuntimeException('Unable to persist notification suppression.');
            }
            do_action('safecontracts_notification_suppression_changed', $scopeType, $scopeId, $suppressed, $reason, $actorId);
            return;
        }

        $this->assertScopeOwnership($tenantId, $scopeType, $scopeId);
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE tenant_id = %d AND scope_type = %s AND scope_id = %d LIMIT 1",
            $tenantId,
            $scopeType,
            $scopeId
        ), ARRAY_A);
        if (is_array($existing) && $existing !== []) {
            $suppressionId = (int) ($existing[0]['id'] ?? 0);
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET reason = %s, is_active = %d, updated_by = %d, updated_at = %s
                 WHERE id = %d AND tenant_id = %d",
                $reason,
                $suppressed ? 1 : 0,
                $actorId,
                $now,
                $suppressionId,
                $tenantId
            ));
            if ($result === false) {
                throw new RuntimeException('Unable to update Enterprise notification suppression.');
            }
        } else {
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table} (tenant_id, scope_type, scope_id, reason, is_active, created_by, updated_by, created_at, updated_at)
                 VALUES (%d, %s, %d, %s, %d, %d, %d, %s, %s)",
                $tenantId,
                $scopeType,
                $scopeId,
                $reason,
                $suppressed ? 1 : 0,
                $actorId,
                $actorId,
                $now,
                $now
            ));
            if ($result === false) {
                throw new RuntimeException('Enterprise notification suppression insert failed; a legacy cross-tenant scope collision may require reviewed schema hardening.');
            }
        }
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
             FROM {$table} WHERE is_active = 1" . NonCoreTenantScope::condition() . " ORDER BY updated_at DESC, id DESC LIMIT {$limit}",
            ARRAY_A
        );
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function assertScopeOwnership(int $tenantId, string $scopeType, int $scopeId): void
    {
        global $wpdb;
        $table = $scopeType === self::PAYMENT
            ? $wpdb->prefix . 'safecontracts_scheduled_payments'
            : $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $scopeId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Notification suppression scope does not belong to the active Enterprise tenant.');
        }
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
