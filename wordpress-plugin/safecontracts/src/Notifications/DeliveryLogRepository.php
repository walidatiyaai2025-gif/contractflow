<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;

final class DeliveryLogRepository
{
    public function append(
        ?int $ruleId,
        int $paymentId,
        int $userId,
        int $deviceTokenId,
        string $templateCode,
        string $scheduledFor,
        int $attemptNo,
        string $status,
        ?int $responseCode,
        ?string $errorCode
    ): void {
        global $wpdb;
        if (! in_array($status, ['sent', 'failed'], true)) {
            throw new InvalidArgumentException('Notification delivery status is invalid.');
        }
        $errorCode = $this->normalizeErrorCode($errorCode);
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (rule_id, payment_id, user_id, device_token_id, template_code, scheduled_for, attempt_no, status, response_code, error_code, created_at)
             VALUES (%d, %d, %d, %d, %s, %s, %d, %s, %d, %s, %s)",
            $ruleId ?? 0,
            $paymentId,
            $userId,
            $deviceTokenId,
            NotificationRule::normalizeCode($templateCode),
            $scheduledFor,
            $attemptNo,
            $status,
            $responseCode ?? 0,
            $errorCode ?? '',
            gmdate('Y-m-d H:i:s')
        ));
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 100): array
    {
        global $wpdb;
        $limit = max(1, min(500, $limit));
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, rule_id, payment_id, user_id, device_token_id, template_code,
                        scheduled_for, attempt_no, status, response_code, error_code, created_at
                 FROM {$table}
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * Return only delivery data needed by the authenticated user's mobile inbox.
     * Device tokens, transport responses and delivery errors stay server-internal.
     *
     * @return list<array<string,mixed>>
     */
    public function recentForUser(int $userId, int $limit = 51, int $offset = 0): array
    {
        global $wpdb;
        if ($userId <= 0) {
            throw new InvalidArgumentException('Notification inbox requires a valid user.');
        }
        $limit = max(1, min(101, $limit));
        $offset = max(0, min(500, $offset));
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, payment_id, user_id, template_code, scheduled_for, created_at
                 FROM {$table}
                 WHERE user_id = %d AND status = 'sent'
                 ORDER BY created_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                $userId,
                $limit,
                $offset
            ),
            ARRAY_A
        );
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function normalizeErrorCode(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_.:-]/', '_', $value) ?? 'delivery_error';
        return substr($value, 0, 100);
    }
}
