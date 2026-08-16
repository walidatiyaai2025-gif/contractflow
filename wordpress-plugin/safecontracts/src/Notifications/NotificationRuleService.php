<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DomainException;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class NotificationRuleService
{
    public function __construct(private ?NotificationRuleRepository $repository = null)
    {
        $this->repository ??= new NotificationRuleRepository();
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
