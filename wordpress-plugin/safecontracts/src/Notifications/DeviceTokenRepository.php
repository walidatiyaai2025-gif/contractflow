<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\NonCoreTenantScope;

final class DeviceTokenRepository
{
    public function register(int $userId, string $token, string $platform): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $hash = hash('sha256', $token);
        $now = gmdate('Y-m-d H:i:s');
        $tenantId = NonCoreTenantScope::tenantId();

        if ($tenantId === null) {
            $result = $wpdb->query($wpdb->prepare(
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
            if ($result === false) {
                throw new RuntimeException('SafeContracts device token persistence failed.');
            }
            return;
        }

        // Do not use ON DUPLICATE KEY while the legacy global token_hash unique
        // can still exist. An explicit tenant predicate prevents a duplicate in
        // another tenant from being updated before schema hardening replaces it.
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET user_id = %d, token = %s, platform = %s, is_active = 1,
                 last_seen_at = %s, updated_at = %s
             WHERE tenant_id = %d AND token_hash = %s",
            $userId,
            $token,
            $platform,
            $now,
            $now,
            $tenantId,
            $hash
        ));
        if ($updated === false) {
            throw new RuntimeException('Enterprise device token update failed.');
        }
        if ((int) $updated > 0) {
            return;
        }

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (tenant_id, user_id, token_hash, token, platform, is_active, last_seen_at, created_at, updated_at)
             VALUES (%d, %d, %s, %s, %s, 1, %s, %s, %s)",
            $tenantId,
            $userId,
            $hash,
            $token,
            $platform,
            $now,
            $now,
            $now
        ));
        if ($inserted === false) {
            throw new RuntimeException('Enterprise device token insert failed; a legacy cross-tenant token collision may require reviewed schema hardening.');
        }
    }

    public function revokeOwned(int $userId, string $token): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 0, updated_at = %s WHERE user_id = %d AND token_hash = %s" . NonCoreTenantScope::condition(),
            gmdate('Y-m-d H:i:s'),
            $userId,
            hash('sha256', $token)
        ));
        if ($result === false) {
            throw new RuntimeException('SafeContracts device token revocation failed.');
        }
    }

    public function deactivateOwnedById(int $userId, int $deviceId): void
    {
        global $wpdb;
        if ($userId <= 0 || $deviceId <= 0) {
            throw new InvalidArgumentException('Device deactivation requires valid user and device IDs.');
        }
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 0, updated_at = %s WHERE user_id = %d AND id = %d AND is_active = 1" . NonCoreTenantScope::condition(),
            gmdate('Y-m-d H:i:s'),
            $userId,
            $deviceId
        ));
        if ($result === false) {
            throw new RuntimeException('SafeContracts owned device deactivation failed.');
        }
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
                 WHERE is_active = 1 AND user_id IN ({$placeholders})" . NonCoreTenantScope::condition() . "
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
     * Safe operator diagnostics. No token/hash material is selected or returned.
     *
     * @return array{current_user_active_devices:int,active_devices:int,active_users:int,truncated:bool}
     */
    public function activeDiagnostics(int $currentUserId): array
    {
        global $wpdb;
        if ($currentUserId <= 0) {
            throw new InvalidArgumentException('Device diagnostics require a valid current user.');
        }
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $rows = $wpdb->get_results(
            "SELECT user_id, COUNT(*) AS device_count FROM {$table}
             WHERE is_active = 1" . NonCoreTenantScope::condition() . "
             GROUP BY user_id
             ORDER BY user_id ASC
             LIMIT 501",
            ARRAY_A
        );

        $activeDevices = 0;
        $activeUsers = 0;
        $currentUserDevices = 0;
        $normalizedRows = is_array($rows) ? $rows : [];
        $truncated = count($normalizedRows) > 500;
        foreach (array_slice($normalizedRows, 0, 500) as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $deviceCount = max(0, (int) ($row['device_count'] ?? 0));
            if ($userId <= 0 || $deviceCount <= 0) {
                continue;
            }
            $activeUsers++;
            $activeDevices += $deviceCount;
            if ($userId === $currentUserId) {
                $currentUserDevices = $deviceCount;
            }
        }

        return [
            'current_user_active_devices' => $currentUserDevices,
            'active_devices' => $activeDevices,
            'active_users' => $activeUsers,
            'truncated' => $truncated,
        ];
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
                 WHERE user_id = %d" . NonCoreTenantScope::condition() . "
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
