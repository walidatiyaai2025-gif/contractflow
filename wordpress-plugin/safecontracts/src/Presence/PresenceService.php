<?php

declare(strict_types=1);

namespace SafeContracts\Presence;

use SafeContracts\Roles\Capabilities;

final class PresenceService
{
    public const MOBILE_META = 'safecontracts_last_mobile_seen';
    public const ADMIN_META = 'safecontracts_last_admin_seen';
    public const ACTIVE_WINDOW_SECONDS = 300;
    private const WRITE_THROTTLE_SECONDS = 60;

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'touchAdminRequest']);
        add_filter('heartbeat_received', [self::class, 'heartbeat'], 10, 2);
    }

    public static function touchMobile(int $userId): void
    {
        self::touch($userId, self::MOBILE_META);
    }

    public static function touchAdminRequest(): void
    {
        if (! is_user_logged_in() || ! current_user_can(Capabilities::ACCESS)) {
            return;
        }
        self::touch(get_current_user_id(), self::ADMIN_META);
    }

    /** @param array<string,mixed> $response @param array<string,mixed> $data @return array<string,mixed> */
    public static function heartbeat(array $response, array $data): array
    {
        unset($data);
        if (is_user_logged_in() && current_user_can(Capabilities::ACCESS)) {
            self::touch(get_current_user_id(), self::ADMIN_META);
            $response['safecontracts_presence'] = time();
        }
        return $response;
    }

    public static function isActive(int $timestamp): bool
    {
        return $timestamp > 0 && $timestamp >= time() - self::ACTIVE_WINDOW_SECONDS;
    }

    private static function touch(int $userId, string $metaKey): void
    {
        if ($userId <= 0) {
            return;
        }
        $now = time();
        $last = (int) get_user_meta($userId, $metaKey, true);
        if ($last > 0 && $now - $last < self::WRITE_THROTTLE_SECONDS) {
            return;
        }
        update_user_meta($userId, $metaKey, $now);
    }
}
