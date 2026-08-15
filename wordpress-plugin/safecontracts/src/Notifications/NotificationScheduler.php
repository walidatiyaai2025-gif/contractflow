<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use Throwable;

final class NotificationScheduler
{
    public const HOOK = 'safecontracts_process_notifications';
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        add_action(self::HOOK, [self::class, 'run'], 10, 0);
        add_action('safecontracts_payment_settled', [self::class, 'onPaymentSettled'], 20, 9);

        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && ! wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::HOOK);
        }
    }

    public static function run(): void
    {
        try {
            $generated = (new NotificationGenerationService())->generate();
            $delivery = (new NotificationDeliveryService())->process();
            do_action('safecontracts_notifications_processed', $generated, $delivery);
        } catch (Throwable $error) {
            error_log('SafeContracts notification worker failed: ' . $error->getMessage());
        }
    }

    public static function onPaymentSettled(
        int $paymentId,
        mixed $collectionAmount,
        mixed $newPaid,
        mixed $newRemaining,
        mixed $newStatus,
        int $actorId,
        mixed $oldPaid = null,
        mixed $oldRemaining = null,
        mixed $oldStatus = null
    ): void {
        unset($collectionAmount, $newPaid, $actorId, $oldPaid, $oldRemaining, $oldStatus);
        if (strtolower((string) $newStatus) !== 'paid' && (string) $newRemaining !== '0.0000') {
            return;
        }
        (new NotificationRepository())->suppressQueuedForPayment($paymentId);
        do_action('safecontracts_payment_notifications_suppressed', $paymentId);
    }
}
