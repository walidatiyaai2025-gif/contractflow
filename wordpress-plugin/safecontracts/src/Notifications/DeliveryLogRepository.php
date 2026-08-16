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
        ?int $deviceTokenId,
        string $templateCode,
        string $scheduledFor,
        int $attemptNo,
        string $status,
        ?int $responseCode,
        ?string $errorCode,
        string $channel = 'push'
    ): void {
        global $wpdb;
        if (! in_array($status, ['sent', 'failed'], true)) {
            throw new InvalidArgumentException('Notification delivery status is invalid.');
        }
        $channel = strtolower(trim($channel));
        if (! in_array($channel, ['push', 'email'], true)) {
            throw new InvalidArgumentException('Notification delivery channel is invalid.');
        }
        $errorCode = $this->normalizeErrorCode($errorCode);
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (rule_id, payment_id, user_id, device_token_id, channel, template_code, scheduled_for, attempt_no, status, response_code, error_code, created_at)
             VALUES (%d, %d, %d, NULLIF(%d, 0), %s, %s, %s, %d, %s, %d, %s, %s)",
            $ruleId ?? 0,
            $paymentId,
            $userId,
            $deviceTokenId ?? 0,
            $channel,
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
    public function recent(int $limit = 100, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        global $wpdb;
        $limit = max(1, min(500, $limit));
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $where = ['1 = 1'];
        $args = [];
        if ($dateFrom !== null) {
            $where[] = 'DATE(created_at) >= %s';
            $args[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $where[] = 'DATE(created_at) <= %s';
            $args[] = $dateTo;
        }
        $args[] = $limit;
        $sql = "SELECT id, rule_id, payment_id, user_id, device_token_id, channel, template_code,
                       scheduled_for, attempt_no, status, response_code, error_code, created_at
                FROM {$table}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY created_at DESC, id DESC
                LIMIT %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return list<array<string,mixed>> */
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
                "SELECT id, payment_id, user_id, channel, template_code, scheduled_for, created_at
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

    public function hasSentForUser(int $notificationId, int $userId): bool
    {
        global $wpdb;
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE id = %d AND user_id = %d AND status = 'sent'
                 LIMIT 1",
                $notificationId,
                $userId
            ),
            ARRAY_A
        );
        return is_array($rows) && $rows !== [];
    }

    /** @return array<int,array{status:string,error_code:?string,attempts:int}> */
    public function outcomesForOccurrence(int $ruleId, int $paymentId, string $scheduledDate, int $attemptNo): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id,
                        MAX(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS any_sent,
                        COUNT(*) AS attempts,
                        MAX(NULLIF(error_code, '')) AS error_code
                 FROM {$table}
                 WHERE rule_id = %d AND payment_id = %d AND scheduled_for = %s AND attempt_no = %d
                 GROUP BY user_id ORDER BY user_id ASC",
                $ruleId,
                $paymentId,
                $scheduledDate,
                $attemptNo
            ),
            ARRAY_A
        );
        $result = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $result[$userId] = [
                'status' => ! empty($row['any_sent']) ? 'sent' : 'failed',
                'error_code' => isset($row['error_code']) && trim((string) $row['error_code']) !== '' ? (string) $row['error_code'] : null,
                'attempts' => max(0, (int) ($row['attempts'] ?? 0)),
            ];
        }
        return $result;
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
