<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class RecipientResolver
{
    /**
     * Resolve notification recipient user IDs entirely server-side.
     * Missing assigned Accountant never broadens to all Accountants.
     *
     * @param array<string, mixed> $rule
     * @return list<int>
     */
    public function resolve(array $rule, ?int $assignedAccountantUserId): array
    {
        $roles = NotificationRule::normalizeRecipientRoles($rule['recipient_roles'] ?? []);
        $targetAssigned = NotificationRule::normalizeBool($rule['target_assigned_accountant'] ?? false);
        $ids = [];

        foreach ($roles as $role) {
            $users = get_users([
                'role' => $role,
                'fields' => 'ID',
            ]);
            if (! is_array($users)) {
                continue;
            }
            foreach ($users as $userId) {
                $id = (int) $userId;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        if ($targetAssigned && $assignedAccountantUserId !== null && $assignedAccountantUserId > 0) {
            $ids[$assignedAccountantUserId] = $assignedAccountantUserId;
        }

        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
    }
}
