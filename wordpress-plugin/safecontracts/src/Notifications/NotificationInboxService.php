<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class NotificationInboxService
{
    public function __construct(
        private ?NotificationInboxRepository $repository = null,
        private ?DeviceTokenRepository $tokens = null
    ) {
        $this->repository ??= new NotificationInboxRepository();
        $this->tokens ??= new DeviceTokenRepository();
    }

    /** @return list<array<string,mixed>> */
    public function inbox(int $userId, int $limit = 100): array
    {
        $readIds = array_fill_keys($this->readIds($userId), true);
        $items = [];
        foreach ($this->repository->forUser($userId, $limit) as $row) {
            $id = (int) ($row['id'] ?? 0);
            $paymentId = (int) ($row['payment_id'] ?? 0);
            if ($id < 1 || $paymentId < 1) {
                continue;
            }
            $items[] = [
                'id' => $id,
                'payment_id' => $paymentId,
                'template_code' => (string) ($row['template_code'] ?? ''),
                'scheduled_for' => (string) ($row['scheduled_for'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'is_read' => isset($readIds[$id]),
                'deep_link' => [
                    'destination' => 'payments',
                    'id' => $paymentId,
                ],
            ];
        }
        return $items;
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        if ($this->repository->findOwned($notificationId, $userId) === null) {
            return false;
        }

        $ids = $this->readIds($userId);
        if (! in_array($notificationId, $ids, true)) {
            array_unshift($ids, $notificationId);
        }
        $ids = array_values(array_unique(array_slice($ids, 0, 500)));
        update_option($this->readOption($userId), $ids, false);
        return true;
    }

    /** @return array{active_device_count:int,platforms:list<string>} */
    public function deviceStatus(int $userId): array
    {
        $rows = $this->tokens->activeForUsers([$userId]);
        $platforms = [];
        foreach ($rows as $row) {
            $platform = strtolower(trim((string) ($row['platform'] ?? '')));
            if ($platform !== '' && ! in_array($platform, $platforms, true)) {
                $platforms[] = $platform;
            }
        }
        sort($platforms, SORT_STRING);
        return [
            'active_device_count' => count($rows),
            'platforms' => $platforms,
        ];
    }

    /** @return list<int> */
    private function readIds(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        $stored = get_option($this->readOption($userId), []);
        if (! is_array($stored)) {
            return [];
        }
        $ids = [];
        foreach ($stored as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0 && ! in_array((int) $id, $ids, true)) {
                $ids[] = (int) $id;
            }
        }
        return array_slice($ids, 0, 500);
    }

    private function readOption(int $userId): string
    {
        return 'safecontracts_notification_reads_' . max(0, $userId);
    }
}
