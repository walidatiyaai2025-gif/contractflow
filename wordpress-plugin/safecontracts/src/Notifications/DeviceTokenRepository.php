<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use RuntimeException;

final class DeviceTokenRepository
{
    /** @param array{user_id:int,device_id:string,platform:string,token:string,token_hash:string,app_version:?string} $device */
    public function upsert(array $device): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $now = gmdate('Y-m-d H:i:s');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND device_id = %s AND platform = %s LIMIT 1",
            $device['user_id'],
            $device['device_id'],
            $device['platform']
        ), ARRAY_A);
        if (is_array($rows) && $rows !== []) {
            $id = (int) $rows[0]['id'];
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET token = %s, token_hash = %s, app_version = %s, is_active = 1,
                    last_seen_at = %s, last_error_at = NULL, last_error_code = NULL, updated_at = %s WHERE id = %d",
                $device['token'], $device['token_hash'], $device['app_version'] ?? '', $now, $now, $id
            ));
            if ($result === false) {
                throw new RuntimeException('Unable to update SafeContracts device token.');
            }
            return $id;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE token_hash = %s LIMIT 1",
            $device['token_hash']
        ), ARRAY_A);
        if (is_array($rows) && $rows !== []) {
            $id = (int) $rows[0]['id'];
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET user_id = %d, device_id = %s, platform = %s, token = %s, app_version = %s,
                    is_active = 1, last_seen_at = %s, last_error_at = NULL, last_error_code = NULL, updated_at = %s WHERE id = %d",
                $device['user_id'], $device['device_id'], $device['platform'], $device['token'],
                $device['app_version'] ?? '', $now, $now, $id
            ));
            if ($result === false) {
                throw new RuntimeException('Unable to reassign SafeContracts device token.');
            }
            return $id;
        }

        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table}
                (user_id, device_id, platform, token, token_hash, app_version, is_active, last_seen_at, created_at, updated_at)
             VALUES (%d, %s, %s, %s, %s, %s, 1, %s, %s, %s)",
            $device['user_id'], $device['device_id'], $device['platform'], $device['token'], $device['token_hash'],
            $device['app_version'] ?? '', $now, $now, $now
        ));
        if ($result === false) {
            throw new RuntimeException('Unable to register SafeContracts device token.');
        }
        return (int) $wpdb->insert_id;
    }

    /** @return list<array<string, mixed>> */
    public function metadataForUser(int $userId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_id, device_id, platform, app_version, is_active, last_seen_at, last_error_at, last_error_code, created_at, updated_at
             FROM {$table} WHERE user_id = %d ORDER BY last_seen_at DESC, id DESC",
            $userId
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** @param list<int> $userIds @return list<array<string, mixed>> */
    public function activeForUsers(array $userIds): array
    {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_id, device_id, platform, token, token_hash, app_version, last_seen_at
             FROM {$table} WHERE is_active = 1 AND user_id IN ({$placeholders}) ORDER BY user_id ASC, id ASC",
            ...$ids
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function deactivate(int $tokenId, string $errorCode): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $code = substr(trim($errorCode), 0, 100);
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 0, last_error_at = %s, last_error_code = %s, updated_at = %s WHERE id = %d",
            $now, $code, $now, $tokenId
        ));
    }

    public function deactivateForUser(int $tokenId, int $userId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_device_tokens';
        $now = gmdate('Y-m-d H:i:s');
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 0, updated_at = %s WHERE id = %d AND user_id = %d",
            $now, $tokenId, $userId
        )) === 1;
    }
}
