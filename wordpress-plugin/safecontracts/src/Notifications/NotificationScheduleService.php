<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class NotificationScheduleService
{
    public function __construct(
        private ?NotificationScheduleRepository $schedule = null,
        private ?NotificationRuleRepository $rules = null,
        private ?NotificationEngine $engine = null,
        private ?NotificationScheduleSettings $settings = null,
        private ?NotificationDeliveryService $delivery = null
    ) {
        $this->schedule ??= new NotificationScheduleRepository();
        $this->rules ??= new NotificationRuleRepository();
        $this->engine ??= new NotificationEngine();
        $this->settings ??= new NotificationScheduleSettings();
        $this->delivery ??= new NotificationDeliveryService();
    }

    public function sync(int $paymentLimit = 5000): int
    {
        $rules = $this->rules->all(true);
        $payments = $this->schedule->candidatePayments($paymentLimit);
        $count = 0;

        foreach ($rules as $rule) {
            $count += $this->syncRule($rule, $payments, null);
        }

        do_action('safecontracts_notification_schedule_synced', $count);
        return $count;
    }

    public function assertRuleIdle(int $ruleId): void
    {
        if ($this->schedule->hasProcessingForRule($ruleId)) {
            throw new RuntimeException('Notification rule has an in-flight schedule dispatch. Retry after it finishes.');
        }
    }

    public function removeRuleSchedule(int $ruleId): int
    {
        return $this->schedule->deleteForRule($ruleId);
    }

    public function reconcileRule(int $ruleId, int $paymentLimit = 5000): int
    {
        $this->assertRuleIdle($ruleId);
        $this->schedule->deleteForRule($ruleId);
        $rule = $this->rules->findById($ruleId);
        if ($rule === null || empty($rule['is_active'])) {
            do_action('safecontracts_notification_rule_schedule_reconciled', $ruleId, 0);
            return 0;
        }

        $payments = $this->schedule->candidatePayments($paymentLimit);
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $timezone);
        $count = $this->syncRule($rule, $payments, $today);
        do_action('safecontracts_notification_rule_schedule_reconciled', $ruleId, $count);
        return $count;
    }

    /**
     * @param array<string,mixed> $rule
     * @param list<array<string,mixed>> $payments
     */
    private function syncRule(array $rule, array $payments, ?DateTimeImmutable $notBefore): int
    {
        $count = 0;
        $repeatMax = max(0, (int) ($rule['max_repeats'] ?? 0));
        for ($attemptNo = 0; $attemptNo <= $repeatMax; $attemptNo++) {
            foreach ($payments as $payment) {
                try {
                    $payment = NotificationPaymentScope::canonicalize($payment);
                    $target = NotificationRule::targetDate($rule, $payment['due_date'] ?? '', $attemptNo);
                    if ($notBefore !== null && $target < $notBefore) {
                        continue;
                    }
                    $plan = $this->engine->plan($rule, $payment, $target, $attemptNo);
                    if ($plan === null) {
                        continue;
                    }
                    $date = $target->format('Y-m-d');
                    $this->schedule->upsert($plan, $attemptNo, $date, $this->settings->scheduledUtc($date));
                    $count++;
                } catch (Throwable $error) {
                    do_action('safecontracts_notification_schedule_sync_error', (int) ($rule['id'] ?? 0), (int) ($payment['id'] ?? 0), $attemptNo, sanitize_key($error->getMessage()));
                }
            }
        }
        return $count;
    }

    public function dispatchDue(int $limit = 50): int
    {
        $processed = 0;
        foreach ($this->schedule->due($limit) as $row) {
            if ($this->dispatch((int) $row['id'], false, 0)) {
                $processed++;
            }
        }
        return $processed;
    }

    public function dispatchManual(int $scheduleId, int $actorId): bool
    {
        return $this->dispatch($scheduleId, true, $actorId);
    }

    private function dispatch(int $scheduleId, bool $manual, int $actorId): bool
    {
        $row = $this->schedule->find($scheduleId);
        if ($row === null || ! $this->schedule->claim($scheduleId, $manual)) {
            return false;
        }

        $rule = $this->rules->findById((int) ($row['rule_id'] ?? 0));
        $payment = $this->schedule->payment((int) ($row['payment_id'] ?? 0));
        if ($rule === null || $payment === null || empty($rule['is_active'])) {
            $this->schedule->complete($scheduleId, 'skipped', 0, 0, 'rule_or_payment_unavailable');
            $this->recordDispatch($scheduleId, $row, 'skipped', $actorId, $manual, 0, 0, 'rule_or_payment_unavailable');
            return true;
        }
        $payment = NotificationPaymentScope::canonicalize($payment);

        try {
            $attemptNo = max(0, (int) ($row['attempt_no'] ?? 0));
            $target = NotificationRule::targetDate($rule, $payment['due_date'] ?? '', $attemptNo);
            $plan = $this->engine->plan($rule, $payment, $target, $attemptNo);
            if ($plan === null) {
                $this->schedule->complete($scheduleId, 'skipped', 0, 0, 'suppressed_or_rule_mismatch');
                $this->recordDispatch($scheduleId, $row, 'skipped', $actorId, $manual, 0, 0, 'suppressed_or_rule_mismatch');
                return true;
            }

            // The rule occurrence number (0..max_repeats) is business cadence,
            // not a Firebase transport retry counter. A fresh schedule dispatch
            // starts at transport retry 0 even when this is occurrence 27/28/29.
            $result = $this->delivery->deliver($plan, $attemptNo, 0);
            $sent = (int) ($result['sent'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $attempted = (int) ($result['attempted'] ?? 0);
            $status = $sent > 0 && $failed === 0 ? 'sent' : ($sent > 0 ? 'partial' : 'failed');
            $error = $attempted === 0 ? 'no_delivery_targets' : ($failed > 0 ? 'delivery_failed' : null);
            $this->schedule->complete($scheduleId, $status, $sent, $failed, $error);
            $this->recordDispatch($scheduleId, $row, $status, $actorId, $manual, $sent, $failed, $error);
            return true;
        } catch (Throwable $error) {
            $code = sanitize_key($error->getMessage());
            if ($code === '') {
                $code = 'dispatch_exception';
            }
            $this->schedule->complete($scheduleId, 'failed', 0, 1, $code);
            $this->recordDispatch($scheduleId, $row, 'failed', $actorId, $manual, 0, 1, $code);
            return true;
        }
    }

    /** @param array<string,mixed> $row */
    private function recordDispatch(int $scheduleId, array $row, string $status, int $actorId, bool $manual, int $sent, int $failed, ?string $error): void
    {
        do_action(
            'safecontracts_notification_schedule_dispatched',
            $scheduleId,
            (int) ($row['payment_id'] ?? 0),
            $status,
            $actorId,
            $manual,
            $sent,
            $failed,
            $error
        );
    }
}
