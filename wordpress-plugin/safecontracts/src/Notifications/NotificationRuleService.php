<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DomainException;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class NotificationRuleService
{
    public function __construct(
        private ?NotificationRuleRepository $repository = null,
        private ?NotificationScheduleService $schedule = null
    ) {
        $this->repository ??= new NotificationRuleRepository();
        $this->schedule ??= new NotificationScheduleService();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $this->requireCapability();
        return $this->repository->all(false);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $this->requireCapability();
        return $this->repository->findByCode(NotificationRule::normalizeCode($code));
    }

    /** @return array<string, mixed> */
    public function save(array $input): array
    {
        $this->requireCapability();
        $input = $this->preserveExtendedFields($input);
        $rule = NotificationRule::normalizeInput($input);
        $actorId = get_current_user_id();
        $this->repository->save($rule, $actorId);
        do_action('safecontracts_notification_rule_saved', $rule['code'], $actorId, $rule);
        return $rule;
    }

    /** @return array<string, mixed> */
    public function saveAndReconcile(array $input): array
    {
        $this->requireCapability();
        $input = $this->preserveExtendedFields($input);
        $rule = NotificationRule::normalizeInput($input);
        $existing = $this->repository->findByCode($rule['code']);
        $actorId = get_current_user_id();

        if ($existing !== null) {
            $ruleId = (int) ($existing['id'] ?? 0);
            $this->schedule->assertRuleIdle($ruleId);
            $this->repository->setActiveById($ruleId, false, $actorId);
            $this->schedule->removeRuleSchedule($ruleId);
        }

        try {
            $this->repository->save($rule, $actorId);
            $saved = $this->repository->findByCode($rule['code']);
            if ($saved === null) {
                throw new DomainException('Notification rule could not be reloaded after save.');
            }
            if (! empty($saved['is_active'])) {
                $this->schedule->reconcileRule((int) $saved['id']);
            }
            do_action('safecontracts_notification_rule_saved', $rule['code'], $actorId, $rule);
            return $saved;
        } catch (Throwable $error) {
            if ($existing !== null) {
                try {
                    $this->repository->save($existing, $actorId);
                    if (! empty($existing['is_active'])) {
                        $this->schedule->reconcileRule((int) $existing['id']);
                    }
                } catch (Throwable) {
                    // Preserve the original failure; recovery can be retried by an administrator.
                }
            }
            throw $error;
        }
    }

    public function toggleActiveWithSchedule(string $code): bool
    {
        $this->requireCapability();
        $rule = $this->repository->findByCode(NotificationRule::normalizeCode($code));
        if ($rule === null) {
            throw new DomainException('Notification rule was not found.');
        }
        $id = (int) $rule['id'];
        $this->schedule->assertRuleIdle($id);
        $actorId = get_current_user_id();
        $next = empty($rule['is_active']);

        // Disable first so cron cannot claim a new pending occurrence while the
        // schedule is being replaced. Re-activation happens only after cleanup.
        $this->repository->setActiveById($id, false, $actorId);
        $this->schedule->removeRuleSchedule($id);
        if ($next) {
            $this->repository->setActiveById($id, true, $actorId);
            $this->schedule->reconcileRule($id);
        }
        do_action('safecontracts_notification_rule_activation_changed', $id, $next, $actorId);
        return $next;
    }

    public function deleteWithSchedule(string $code): void
    {
        $this->requireCapability();
        $rule = $this->repository->findByCode(NotificationRule::normalizeCode($code));
        if ($rule === null) {
            throw new DomainException('Notification rule was not found.');
        }
        $id = (int) $rule['id'];
        $this->schedule->assertRuleIdle($id);
        $actorId = get_current_user_id();
        $this->repository->setActiveById($id, false, $actorId);
        $this->schedule->removeRuleSchedule($id);
        if (! $this->repository->deleteById($id)) {
            throw new DomainException('Notification rule could not be deleted.');
        }
        do_action('safecontracts_notification_rule_deleted', $id, (string) $rule['code'], $actorId);
    }

    /** @return list<array<string, mixed>> */
    public function activeBeforeDue(int $daysBefore): array
    {
        if ($daysBefore < 1 || $daysBefore > 365) {
            return [];
        }
        return $this->repository->activeBeforeDue($daysBefore);
    }

    /** @return list<array<string, mixed>> */
    public function activeDueDay(): array
    {
        return $this->repository->activeForTrigger(NotificationRule::TRIGGER_DUE_DAY);
    }

    /** @return list<array<string, mixed>> */
    public function activeOverdue(): array
    {
        return $this->repository->activeForTrigger(NotificationRule::TRIGGER_OVERDUE);
    }

    /** @return array<string,mixed> */
    private function preserveExtendedFields(array $input): array
    {
        if (array_key_exists('recipient_user_ids', $input) && array_key_exists('push_enabled', $input) && array_key_exists('email_enabled', $input)) {
            return $input;
        }
        $code = sanitize_key((string) ($input['code'] ?? ''));
        if ($code === '') {
            return array_merge([
                'recipient_user_ids' => [],
                'push_enabled' => true,
                'email_enabled' => false,
            ], $input);
        }
        try {
            $existing = $this->repository->findByCode($code);
        } catch (Throwable) {
            $existing = null;
        }
        return array_merge([
            'recipient_user_ids' => is_array($existing['recipient_user_ids'] ?? null) ? $existing['recipient_user_ids'] : [],
            'push_enabled' => $existing !== null ? ! empty($existing['push_enabled']) : true,
            'email_enabled' => $existing !== null ? ! empty($existing['email_enabled']) : false,
        ], $input);
    }

    private function requireCapability(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            throw new DomainException('You do not have permission to manage SafeContracts notification rules.');
        }
    }
}
