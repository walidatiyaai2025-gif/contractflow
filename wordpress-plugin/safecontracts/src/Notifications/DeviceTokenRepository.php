<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;

final class DeviceTokenRepository
{
    public function register(int $userId, string $token, string $platform): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $hash = hash('sha256', $token);
        $now = gmdate('Y-m-d H:i:s');

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (user_id, token_hash, token, platform, is_active, last_seen_at, created_at, updated_at)
             VALUES (%d, %s, %s, %s, 1, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                token = VALUES(token),
                platform = VALUES(platform),
                is_active = 1,
                last_seen_at = VALUES(last_seen_at),
                updated_at = VALUES(updated_at)",
            $userId,
            $hash,
            $token,
            $platform,
            $now,
            $now,
            $now
        ));
    }

    public function revokeOwned(int $userId, string $token): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 0, updated_at = %s WHERE user_id = %d AND token_hash = %s",
            gmdate('Y-m-d H:i:s'),
            $userId,
            hash('sha256', $token)
        ));
    }

    /** @param list<int> $userIds @return list<array{id:int,user_id:int,token:string,platform:string}> */
    public function activeForUsers(array $userIds): array
    {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $ids = array_slice($ids, 0, 500);
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, token, platform FROM {$table}
                 WHERE is_active = 1 AND user_id IN ({$placeholders})
                 ORDER BY user_id ASC, id ASC",
                ...$ids
            ),
            ARRAY_A
        );

        $normalized = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $normalized[] = [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'token' => (string) ($row['token'] ?? ''),
                'platform' => (string) ($row['platform'] ?? ''),
            ];
        }
        return $normalized;
    }

    /**
     * Safe current-user projection for mobile profile/device state.
     * Raw token/hash material is intentionally excluded.
     *
     * @return list<array{id:int,platform:string,is_active:bool,last_seen_at:string,created_at:string,updated_at:string}>
     */
    public function safeForUser(int $userId): array
    {
        global $wpdb;
        if ($userId <= 0) {
            throw new InvalidArgumentException('Device lookup requires a valid user.');
        }
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, platform, is_active, last_seen_at, created_at, updated_at
                 FROM {$table}
                 WHERE user_id = %d
                 ORDER BY is_active DESC, updated_at DESC, id DESC
                 LIMIT 100",
                $userId
            ),
            ARRAY_A
        );

        $normalized = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            $platform = strtolower(trim((string) ($row['platform'] ?? '')));
            if ($id <= 0 || ! in_array($platform, ['android', 'ios', 'web'], true)) {
                continue;
            }
            $normalized[] = [
                'id' => $id,
                'platform' => $platform,
                'is_active' => (bool) ((int) ($row['is_active'] ?? 0)),
                'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        return $normalized;
    }
}
