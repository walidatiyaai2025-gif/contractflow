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
