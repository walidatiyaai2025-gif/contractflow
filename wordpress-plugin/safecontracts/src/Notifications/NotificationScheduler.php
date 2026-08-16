<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use SafeContracts\Tenancy\NonCoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Tenancy\TenantDirectoryRepository;
use Throwable;

final class NotificationScheduler
{
    public const HOOK = 'safecontracts_notification_schedule_tick';
    public const CRON_KEY = 'safecontracts_five_minutes';

    public static function register(): void
    {
        add_filter('cron_schedules', [self::class, 'cronSchedules']);
        add_action('init', [self::class, 'ensureScheduled']);
        add_action(self::HOOK, [self::class, 'run']);
    }

    /** @param array<string,array<string,mixed>> $schedules @return array<string,array<string,mixed>> */
    public static function cronSchedules(array $schedules): array
    {
        $schedules[self::CRON_KEY] = [
            'interval' => 300,
            'display' => __('Every five minutes (SafeContracts notifications)', 'safecontracts'),
        ];
        return $schedules;
    }

    public static function ensureScheduled(): void
    {
        if (! function_exists('wp_next_scheduled') || ! function_exists('wp_schedule_event')) {
            return;
        }
        if (wp_next_scheduled(self::HOOK) === false) {
            wp_schedule_event(time() + 60, self::CRON_KEY, self::HOOK);
        }

        if (! NonCoreTenantEnforcement::isEnabled()) {
            self::ensureLegacySeed();
            return;
        }

        self::forEachActiveTenant(static function (int $tenantId): void {
            $seedKey = 'safecontracts_notification_schedule_seeded_tenant_' . $tenantId;
            if (get_option($seedKey, '') === '1') {
                return;
            }
            (new NotificationScheduleService())->sync();
            update_option($seedKey, '1', false);
        }, 'seed');
    }

    public static function run(): void
    {
        if (! NonCoreTenantEnforcement::isEnabled()) {
            try {
                $service = new NotificationScheduleService();
                $service->sync();
                $service->dispatchDue();
                update_option('safecontracts_notification_schedule_last_run', gmdate('c'), false);
            } catch (Throwable $error) {
                error_log('SafeContracts notification scheduler failed: ' . $error->getMessage());
            }
            return;
        }

        self::forEachActiveTenant(static function (int $tenantId): void {
            $service = new NotificationScheduleService();
            $service->sync();
            $service->dispatchDue();
            update_option('safecontracts_notification_schedule_last_run_tenant_' . $tenantId, gmdate('c'), false);
        }, 'run');
        update_option('safecontracts_notification_schedule_last_run', gmdate('c'), false);
    }

    public static function clear(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }
    }

    private static function ensureLegacySeed(): void
    {
        if (get_option('safecontracts_notification_schedule_seeded', '') === '1') {
            return;
        }
        try {
            (new NotificationScheduleService())->sync();
            update_option('safecontracts_notification_schedule_seeded', '1', false);
        } catch (Throwable $error) {
            error_log('SafeContracts notification schedule seed failed: ' . $error->getMessage());
        }
    }

    /** @param callable(int):void $callback */
    private static function forEachActiveTenant(callable $callback, string $operation): void
    {
        $tenantIds = (new TenantDirectoryRepository())->activeIds();
        foreach ($tenantIds as $tenantId) {
            TenantContextStore::reset();
            try {
                TenantContextStore::context()->setTenantId($tenantId);
                $callback($tenantId);
            } catch (Throwable $error) {
                error_log(
                    'Enterprise notification scheduler ' . $operation . ' failed for tenant ' .
                    $tenantId . ': ' . $error->getMessage()
                );
            } finally {
                // A stale tenant context must never leak into the next queue slice.
                TenantContextStore::reset();
            }
        }
    }
}
