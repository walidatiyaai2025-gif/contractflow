<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use RuntimeException;

final class NotificationRepository
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUPPRESSED = 'suppressed';

    /** @return list<array<string, mixed>> */
    public function eligiblePayments(int $limit = 500): array
    {
        global $wpdb;
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $limit = max(1, min(1000, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id AS payment_id, p.reference AS payment_reference, p.due_date, p.expected_payment_date,
                    p.original_amount, p.remaining_amount, p.status,
                    c.id AS contract_id, c.contract_number, c.accountant_user_id,
                    cu.id AS customer_id, cu.name AS client_name
             FROM {$payments} p
             INNER JOIN {$contracts} c ON c.id = p.contract_id
             INNER JOIN {$customers} cu ON cu.id = c.customer_id
             WHERE c.is_archived = 0 AND p.remaining_amount > 0 AND p.status <> 'paid'
             ORDER BY p.due_date ASC, p.id ASC LIMIT %d",
            $limit
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $row */
    public function enqueue(array $row): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notifications';
        $dataJson = json_encode($row['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($dataJson)) {
            throw new RuntimeException('Unable to encode SafeContracts notification data.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (rule_id, payment_id, user_id, occurrence_date, occurrence_index, dedupe_key, template_code,
                 title, body, data_json, status, attempt_count, next_attempt_at, created_at, updated_at)
             VALUES (%d, %d, %d, %s, %d, %s, %s, %s, %s, %s, 'queued', 0, %s, %s, %s)
             ON DUPLICATE KEY UPDATE dedupe_key = VALUES(dedupe_key)",
            $row['rule_id'], $row['payment_id'], $row['user_id'], $row['occurrence_date'], $row['occurrence_index'],
            $row['dedupe_key'], $row['template_code'], $row['title'], $row['body'], $dataJson, $now, $now, $now
        ));
        if ($result === false) {
            throw new RuntimeException('Unable to queue SafeContracts notification.');
        }
        return $result === 1;
    }

    /** @return list<array<string, mixed>> */
    public function ready(int $limit = 100): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notifications';
        $limit = max(1, min(500, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, rule_id, payment_id, user_id, occurrence_date, occurrence_index, dedupe_key,
                    template_code, title, body, data_json, status, attempt_count, next_attempt_at
             FROM {$table}
             WHERE status IN ('queued','failed') AND attempt_count < 5
               AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP())
             ORDER BY COALESCE(next_attempt_at, created_at) ASC, id ASC LIMIT %d",
            $limit
        ), ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }
        foreach ($rows as &$row) {
            $data = json_decode((string) ($row['data_json'] ?? '{}'), true);
            $row['data'] = is_array($data) ? $data : [];
        }
        unset($row);
        return $rows;
    }

    public function claim(int $notificationId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notifications';
        $now = gmdate('Y-m-d H:i:s');
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'processing', updated_at = %s
             WHERE id = %d AND status IN ('queued','failed') AND attempt_count < 5",
            $now, $notificationId
        )) === 1;
    }

    public function paymentStillEligible(int $paymentId): bool
    {
        global $wpdb;
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id FROM {$payments} p INNER JOIN {$contracts} c ON c.id = p.contract_id
             WHERE p.id = %d AND c.is_archived = 0 AND p.remaining_amount > 0 AND p.status <> 'paid' LIMIT 1",
            $paymentId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [];
    }

    public function markSent(int $notificationId, int $attemptCount): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notifications';
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'sent', attempt_count = %d, sent_at = %s, next_attempt_at = NULL,
                last_error_code = NULL, last_error_message = NULL, updated_at = %s WHERE id = %d",
            $attemptCount, $now, $now, $notificationId
        ));
    }

    public function markFailed(int $notificationId, int $attemptCount, ?string $nextAttemptAt, string $errorCode, string $errorMessage): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notifications';
        $code = substr(trim($errorCode), 0, 100);
        $message = substr(trim(strip_tags($errorMessage)), 0, 1000);
        $nextSql = $nextAttemptAt === null ? 'NULL' : '%s';
        $sql = "UPDATE {$table} SET status = 'failed', attempt_count = %d, next_attempt_at = {$nextSql},
                last_error_code = %s, last_error_message = %s, updated_at = %s WHERE id = %d";
        $args = [$attemptCount];
        if ($nextAttemptAt !== null) {
            $args[] = $nextAttemptAt;
        }
        $args[] = $code;
        $args[] = $message;
        $args[] = gmdate('Y-m-d H:i:s');
        $args[] = $notificationId;
        $wpdb->query($wpdb->prepare($sql, ...$args));
    }

    public function markSuppressed(int $notificationId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notifications';
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'suppressed', suppressed_at = %s, next_attempt_at = NULL, updated_at = %s WHERE id = %d",
            $now, $now, $notificationId
        ));
    }

    public function suppressQueuedForPayment(int $paymentId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notifications';
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'suppressed', suppressed_at = %s, next_attempt_at = NULL, updated_at = %s
             WHERE payment_id = %d AND status IN ('queued','failed','processing')",
            $now, $now, $paymentId
        ));
    }

    public function logAttempt(int $notificationId, ?int $deviceTokenId, int $attemptNo, array $result): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_notification_delivery_log';
        $status = $result['success'] ?? false ? 'sent' : 'failed';
        $httpStatus = isset($result['http_status']) ? (int) $result['http_status'] : 0;
        $errorCode = substr(trim((string) ($result['error_code'] ?? '')), 0, 100);
        $errorMessage = substr(trim(strip_tags((string) ($result['error_message'] ?? ''))), 0, 1000);
        $messageId = substr(trim((string) ($result['message_id'] ?? '')), 0, 255);
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (notification_id, device_token_id, attempt_no, status, http_status, error_code, error_message, provider_message_id, created_at)
             VALUES (%d, %d, %d, %s, %d, %s, %s, %s, %s)",
            $notificationId, $deviceTokenId ?? 0, $attemptNo, $status, $httpStatus, $errorCode, $errorMessage, $messageId, gmdate('Y-m-d H:i:s')
        ));
    }
}
