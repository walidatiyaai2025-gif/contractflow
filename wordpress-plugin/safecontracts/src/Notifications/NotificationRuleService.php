<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DomainException;
use SafeContracts\Roles\Capabilities;

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

    private function requireCapability(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            throw new DomainException('You do not have permission to manage SafeContracts notification rules.');
        }
    }
}
