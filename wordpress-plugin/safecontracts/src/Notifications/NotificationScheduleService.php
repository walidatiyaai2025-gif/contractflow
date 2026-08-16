<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class NotificationScheduleService
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

    public function sync(int $paymentLimit = 5000): int
    {
        $rules = $this->rules->all(true);
        $payments = $this->schedule->candidatePayments($paymentLimit);
        $count = 0;

        foreach ($rules as $rule) {
            $repeatMax = max(0, (int) ($rule['max_repeats'] ?? 0));
            for ($attemptNo = 0; $attemptNo <= $repeatMax; $attemptNo++) {
                foreach ($payments as $payment) {
                    try {
                        $target = NotificationRule::targetDate($rule, $payment['due_date'] ?? '', $attemptNo);
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
        }

        do_action('safecontracts_notification_schedule_synced', $count);
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

        try {
            $attemptNo = max(0, (int) ($row['attempt_no'] ?? 0));
            $target = NotificationRule::targetDate($rule, $payment['due_date'] ?? '', $attemptNo);
            $plan = $this->engine->plan($rule, $payment, $target, $attemptNo);
            if ($plan === null) {
                $this->schedule->complete($scheduleId, 'skipped', 0, 0, 'suppressed_or_rule_mismatch');
                $this->recordDispatch($scheduleId, $row, 'skipped', $actorId, $manual, 0, 0, 'suppressed_or_rule_mismatch');
                return true;
            }

            $result = (new PushDeliveryService(new FirebasePushTransport()))->deliver($plan, $attemptNo);
            $sent = (int) ($result['sent'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $attempted = (int) ($result['attempted'] ?? 0);
            $status = $sent > 0 && $failed === 0 ? 'sent' : ($sent > 0 ? 'partial' : 'failed');
            $error = $attempted === 0 ? 'no_active_devices' : ($failed > 0 ? 'delivery_failed' : null);
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
