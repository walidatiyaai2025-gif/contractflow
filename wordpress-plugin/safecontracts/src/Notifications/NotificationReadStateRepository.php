<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use SafeContracts\Tenancy\NonCoreTenantScope;

final class NotificationReadStateRepository
{
    private const META_KEY = 'safecontracts_notification_read_ids';
    private const MAX_IDS = 500;

    /** @return list<int> */
    public function idsForUser(int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Notification read state requires a valid user.');
        }
        $raw = get_user_meta($userId, $this->metaKey(), true);
        if (! is_array($raw)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $raw),
            static fn (int $id): bool => $id > 0
        )));
        rsort($ids, SORT_NUMERIC);
        return array_slice($ids, 0, self::MAX_IDS);
    }

    public function markRead(int $userId, int $notificationId): void
    {
        if ($userId <= 0 || $notificationId <= 0) {
            throw new InvalidArgumentException('Notification read state identifiers are invalid.');
        }
        $ids = $this->idsForUser($userId);
        if (! in_array($notificationId, $ids, true)) {
            array_unshift($ids, $notificationId);
        }
        $ids = array_slice(array_values(array_unique($ids)), 0, self::MAX_IDS);
        update_user_meta($userId, $this->metaKey(), $ids);
    }

    private function metaKey(): string
    {
        $tenantId = NonCoreTenantScope::tenantId();
        if ($tenantId === null) {
            return self::META_KEY;
        }
        return self::META_KEY . '_tenant_' . $tenantId;
    }
}
