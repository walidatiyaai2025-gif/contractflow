<?php

declare(strict_types=1);

namespace SafeContracts\Audit;

use Throwable;

final class NotificationScheduleAuditRecorder
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        add_action('safecontracts_notification_schedule_dispatched', [self::class, 'record'], 10, 8);
    }

    public static function record(
        int $scheduleId,
        int $paymentId,
        string $status,
        int $actorId,
        bool $manual,
        int $sent,
        int $failed,
        ?string $errorCode
    ): void {
        try {
            (new AuditRepository())->append(
                'notification_schedule',
                $scheduleId > 0 ? $scheduleId : null,
                $manual ? 'notification_manual_dispatch' : 'notification_automatic_dispatch',
                $actorId > 0 ? $actorId : null,
                null,
                [
                    'payment_id' => $paymentId,
                    'status' => $status,
                    'sent_count' => max(0, $sent),
                    'failed_count' => max(0, $failed),
                ],
                $errorCode === null || $errorCode === '' ? null : ['error_code' => $errorCode]
            );
        } catch (Throwable $error) {
            error_log('SafeContracts notification dispatch audit failed: ' . $error->getMessage());
        }
    }
}
