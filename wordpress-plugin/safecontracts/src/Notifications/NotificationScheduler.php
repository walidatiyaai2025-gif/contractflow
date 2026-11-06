<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use Throwable;

final class NotificationScheduler
{
    public const HOOK = 'safecontracts_notification_schedule_tick';
    public const CRON_KEY = 'safecontracts_five_minutes';

    public static function register(): void
    {
        add_filter('cron_schedules', [self::class, 'cronSchedules']);
        add_action('init', [self::class, 'ensureScheduled']);
        add_action(self::HOOK, [self::class, 'run']);

        // Keep the persisted schedule current at the same transaction boundary
        // as payment business events. WP-Cron remains the durable safety net.
        add_action('safecontracts_payment_created', [self::class, 'reconcilePayment'], 10, 1);
        add_action('safecontracts_payment_dates_changed', [self::class, 'reconcilePayment'], 10, 1);
        add_action('safecontracts_payment_status_changed', [self::class, 'reconcilePayment'], 10, 1);
        add_action('safecontracts_payment_settled', [self::class, 'reconcilePayment'], 10, 1);
    }

    /** @param array<string,array<string,mixed>> $schedules @return array<string,array<string,mixed>> */
    public static function cronSchedules(array $schedules): array
    {
        $schedules[self::CRON_KEY] = [
            'interval' => 300,
            'display' => __('Every five minutes (SafeContracts notifications)', 'safecontracts'),
        ];
        return $schedules;
    }

    public static function ensureScheduled(): void
    {
        if (! function_exists('wp_next_scheduled') || ! function_exists('wp_schedule_event')) {
            return;
        }
        if (wp_next_scheduled(self::HOOK) === false) {
            wp_schedule_event(time() + 60, self::CRON_KEY, self::HOOK);
        }

        if (get_option('safecontracts_notification_schedule_seeded', '') !== '1') {
            try {
                (new NotificationScheduleService())->sync();
                update_option('safecontracts_notification_schedule_seeded', '1', false);
            } catch (Throwable $error) {
                error_log('SafeContracts notification schedule seed failed: ' . $error->getMessage());
            }
        }
    }

    public static function reconcilePayment(mixed $paymentId): void
    {
        $paymentId = (int) $paymentId;
        if ($paymentId <= 0) {
            return;
        }
        try {
            (new NotificationPaymentScheduleReconciler())->reconcile($paymentId);
        } catch (Throwable $error) {
            // Payment writes are authoritative and must not be rolled back just
            // because schedule maintenance is temporarily unavailable. Cron will
            // retry from source-of-truth payment data on its next run.
            error_log('SafeContracts payment notification reconciliation failed for payment #' . $paymentId . ': ' . $error->getMessage());
        }
    }

    public static function run(): void
    {
        try {
            $service = new NotificationScheduleService();
            $service->sync();
            $service->dispatchDue();
            update_option('safecontracts_notification_schedule_last_run', gmdate('c'), false);
        } catch (Throwable $error) {
            error_log('SafeContracts notification scheduler failed: ' . $error->getMessage());
        }
    }

    public static function clear(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }
    }
}
