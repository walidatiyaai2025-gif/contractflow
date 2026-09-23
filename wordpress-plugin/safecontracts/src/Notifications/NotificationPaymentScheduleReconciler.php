<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Keeps one payment's future notification schedule current immediately after
 * business mutations instead of waiting for the next WP-Cron sweep.
 *
 * Sent/partial/processing rows are immutable operational evidence. Mutable
 * pending/failed/skipped rows may be rebuilt from the payment's current state;
 * delivery-attempt evidence remains in the delivery log.
 */
final class NotificationPaymentScheduleReconciler
{
    public function __construct(
        private ?NotificationScheduleRepository $schedule = null,
        private ?NotificationRuleRepository $rules = null,
        private ?NotificationEngine $engine = null,
        private ?NotificationScheduleSettings $settings = null
    ) {
        $this->schedule ??= new NotificationScheduleRepository();
        $this->rules ??= new NotificationRuleRepository();
        $this->engine ??= new NotificationEngine();
        $this->settings ??= new NotificationScheduleSettings();
    }

    public function reconcile(int $paymentId): int
    {
        if ($paymentId <= 0) {
            return 0;
        }

        $this->assertPaymentIdle($paymentId);
        $this->deleteMutableOccurrences($paymentId);

        $payment = $this->schedule->payment($paymentId);
        if ($payment === null) {
            do_action('safecontracts_notification_payment_schedule_reconciled', $paymentId, 0);
            return 0;
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $timezone);
        $count = 0;

        foreach ($this->rules->all(true) as $rule) {
            $repeatMax = max(0, (int) ($rule['max_repeats'] ?? 0));
            for ($attemptNo = 0; $attemptNo <= $repeatMax; $attemptNo++) {
                try {
                    $target = NotificationRule::targetDate($rule, $payment['due_date'] ?? '', $attemptNo);
                    if ($target < $today) {
                        continue;
                    }
                    $plan = $this->engine->plan($rule, $payment, $target, $attemptNo);
                    if ($plan === null) {
                        continue;
                    }
                    $date = $target->format('Y-m-d');
                    $this->schedule->upsert(
                        $plan,
                        $attemptNo,
                        $date,
                        $this->settings->scheduledUtc($date)
                    );
                    $count++;
                } catch (Throwable $error) {
                    do_action(
                        'safecontracts_notification_payment_reconcile_error',
                        $paymentId,
                        (int) ($rule['id'] ?? 0),
                        $attemptNo,
                        sanitize_key($error->getMessage())
                    );
                }
            }
        }

        do_action('safecontracts_notification_payment_schedule_reconciled', $paymentId, $count);
        return $count;
    }

    private function assertPaymentIdle(int $paymentId): void
    {
        global $wpdb;
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts payment notification reconciliation requires WordPress $wpdb.');
        }
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE payment_id = %d AND status = 'processing'",
            $paymentId
        ));
        if ((int) $count > 0) {
            throw new RuntimeException('Payment has an in-flight notification dispatch. Retry reconciliation after it finishes.');
        }
    }

    private function deleteMutableOccurrences(int $paymentId): void
    {
        global $wpdb;
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts payment notification reconciliation requires WordPress $wpdb.');
        }
        $table = $wpdb->prefix . 'safecontracts_notification_schedule';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE payment_id = %d AND status IN ('pending','failed','skipped')",
            $paymentId
        ));
        if ($deleted === false) {
            throw new RuntimeException('Unable to clear mutable notification occurrences for payment reconciliation.');
        }
    }
}
