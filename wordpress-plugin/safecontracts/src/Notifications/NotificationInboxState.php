<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;

final class NotificationInboxState
{
    private const META_KEY = 'safecontracts_notification_read_delivery_ids';
    private const MAX_TRACKED = 1000;

    /** @return list<int> */
    public function readIds(int $userId): array
    {
        $this->assertUser($userId);
        $stored = get_user_meta($userId, self::META_KEY, true);
        if (! is_array($stored)) {
            return [];
        }
        $ids = [];
        foreach ($stored as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        krsort($ids, SORT_NUMERIC);
        return array_values(array_slice($ids, 0, self::MAX_TRACKED, true));
    }

    public function isRead(int $userId, int $deliveryId): bool
    {
        if ($deliveryId <= 0) {
            return false;
        }
        return in_array($deliveryId, $this->readIds($userId), true);
    }

    public function markRead(int $userId, int $deliveryId): void
    {
        $this->assertUser($userId);
        if ($deliveryId <= 0) {
            throw new InvalidArgumentException('Notification delivery ID must be positive.');
        }
        $ids = $this->readIds($userId);
        array_unshift($ids, $deliveryId);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_slice($ids, 0, self::MAX_TRACKED);
        update_user_meta($userId, self::META_KEY, $ids);
    }

    /** @param list<int> $deliveryIds */
    public function markManyRead(int $userId, array $deliveryIds): void
    {
        $this->assertUser($userId);
        $ids = $this->readIds($userId);
        foreach ($deliveryIds as $deliveryId) {
            $deliveryId = (int) $deliveryId;
            if ($deliveryId > 0) {
                array_unshift($ids, $deliveryId);
            }
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_slice($ids, 0, self::MAX_TRACKED);
        update_user_meta($userId, self::META_KEY, $ids);
    }

    private function assertUser(int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Notification inbox state requires a valid user.');
        }
    }
}
