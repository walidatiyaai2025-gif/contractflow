<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class RecipientResolver
{
    /**
     * Resolve notification recipient user IDs entirely server-side.
     * Missing assigned Accountant never broadens to all Accountants.
     * Explicit user IDs are validated against existing WordPress users.
     *
     * @param array<string, mixed> $rule
     * @return list<int>
     */
    public function resolve(array $rule, ?int $assignedAccountantUserId): array
    {
        $roles = NotificationRule::normalizeRecipientRoles($rule['recipient_roles'] ?? []);
        $specificUsers = NotificationRule::normalizeRecipientUserIds($rule['recipient_user_ids'] ?? []);
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

        foreach ($specificUsers as $userId) {
            if ($this->userExists($userId)) {
                $ids[$userId] = $userId;
            }
        }

        if ($targetAssigned && $assignedAccountantUserId !== null && $assignedAccountantUserId > 0 && $this->userExists($assignedAccountantUserId)) {
            $ids[$assignedAccountantUserId] = $assignedAccountantUserId;
        }

        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
    }

    private function userExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (! function_exists('get_userdata')) {
            return true;
        }
        return get_userdata($userId) !== false;
    }
}
