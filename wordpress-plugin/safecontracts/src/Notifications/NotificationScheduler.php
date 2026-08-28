<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use SafeContracts\Roles\Capabilities;
use Throwable;

final class NotificationScheduler
{
    public const HOOK = 'safecontracts_notification_schedule_tick';
    public const CRON_KEY = 'safecontracts_five_minutes';
    public const MANUAL_SYNC_ACTION = 'safecontracts_notification_schedule_sync_now';
    public const SEEDED_VERSION_OPTION = 'safecontracts_notification_schedule_seeded_version';
    public const LAST_FULL_SYNC_OPTION = 'safecontracts_notification_schedule_last_full_sync';
    public const LAST_FULL_SYNC_COUNT_OPTION = 'safecontracts_notification_schedule_last_full_sync_count';
    public const LAST_FULL_SYNC_SOURCE_OPTION = 'safecontracts_notification_schedule_last_full_sync_source';

    public static function register(): void
    {
        add_filter('cron_schedules', [self::class, 'cronSchedules']);
        add_action('init', [self::class, 'ensureScheduled']);
        add_action(self::HOOK, [self::class, 'run']);
        add_action('admin_post_' . self::MANUAL_SYNC_ACTION, [self::class, 'handleManualSync']);
        add_action('admin_notices', [self::class, 'renderManualSyncControl'], 9);

        // Keep the persisted schedule current at the same transaction boundary
        // as payment business events. WP-Cron remains the durable safety net.
        add_action('safecontracts_payment_created', [self::class, 'reconcilePayment'], 10, 1);
        add_action('safecontracts_payment_dates_changed', [self::class, 'reconcilePayment'], 10, 1);
        add_action('safecontracts_payment_status_changed', [self::class, 'reconcilePayment'], 10, 1);
        add_action('safecontracts_payment_settled', [self::class, 'reconcilePayment'], 10, 1);
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
        // Cron registration and full reconciliation are deliberately independent.
        // A production site with delayed/disabled WP-Cron must still repair its
        // persisted schedule immediately after a plugin upgrade.
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            if (wp_next_scheduled(self::HOOK) === false) {
                wp_schedule_event(time() + 60, self::CRON_KEY, self::HOOK);
            }
        }

        self::syncAfterUpgrade();
    }

    public static function syncAfterUpgrade(): void
    {
        $version = defined('SAFECONTRACTS_VERSION') ? (string) SAFECONTRACTS_VERSION : '';
        if ($version === '') {
            return;
        }

        $seeded = (string) get_option('safecontracts_notification_schedule_seeded', '');
        $seededVersion = (string) get_option(self::SEEDED_VERSION_OPTION, '');
        if ($seeded === '1' && $seededVersion === $version) {
            return;
        }

        try {
            $count = (new NotificationScheduleService())->sync();
            update_option('safecontracts_notification_schedule_seeded', '1', false);
            update_option(self::SEEDED_VERSION_OPTION, $version, false);
            self::recordFullSync($count, 'upgrade');
        } catch (Throwable $error) {
            // Do not mark the version as reconciled on failure. The next normal
            // request retries from authoritative payment/rule data without
            // waiting for WP-Cron.
            error_log('SafeContracts notification schedule upgrade sync failed: ' . $error->getMessage());
        }
    }

    public static function handleManualSync(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(self::adminText(
                'You do not have permission to synchronize notification schedules.',
                'ليس لديك صلاحية لمزامنة جدولة الإشعارات.'
            ));
        }

        check_admin_referer(self::MANUAL_SYNC_ACTION);
        $status = 'success';
        $count = 0;
        try {
            $count = (new NotificationScheduleService())->sync();
            $version = defined('SAFECONTRACTS_VERSION') ? (string) SAFECONTRACTS_VERSION : '';
            if ($version !== '') {
                update_option('safecontracts_notification_schedule_seeded', '1', false);
                update_option(self::SEEDED_VERSION_OPTION, $version, false);
            }
            self::recordFullSync($count, 'manual');
        } catch (Throwable $error) {
            $status = 'failed';
            error_log('SafeContracts manual notification schedule sync failed: ' . $error->getMessage());
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'safecontracts-notification-schedule',
            'safecontracts_schedule_sync' => $status,
            'safecontracts_schedule_count' => max(0, $count),
        ], admin_url('admin.php')));
        exit;
    }

    public static function renderManualSyncControl(): void
    {
        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page !== 'safecontracts-notification-schedule' || ! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            return;
        }

        $syncStatus = isset($_GET['safecontracts_schedule_sync']) && is_scalar($_GET['safecontracts_schedule_sync'])
            ? sanitize_key((string) $_GET['safecontracts_schedule_sync'])
            : '';
        $syncCount = isset($_GET['safecontracts_schedule_count']) ? absint($_GET['safecontracts_schedule_count']) : 0;
        $lastFullSync = (string) get_option(self::LAST_FULL_SYNC_OPTION, '');
        $lastCount = max(0, (int) get_option(self::LAST_FULL_SYNC_COUNT_OPTION, 0));
        $lastSource = sanitize_key((string) get_option(self::LAST_FULL_SYNC_SOURCE_OPTION, ''));
        ?>
        <div class="notice <?php echo $syncStatus === 'failed' ? 'notice-error' : 'notice-info'; ?> safecontracts-notification-sync-control" style="padding:14px 16px;display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
            <div>
                <strong><?php echo esc_html(self::adminText('Schedule reconciliation', 'مزامنة الجدولة')); ?></strong>
                <p style="margin:4px 0 0;">
                    <?php if ($syncStatus === 'success') : ?>
                        <?php echo esc_html(sprintf(self::adminText('Synchronization completed. %d schedule occurrences were reconciled.', 'اكتملت المزامنة. تمت تسوية %d حالة جدولة.'), $syncCount)); ?>
                    <?php elseif ($syncStatus === 'failed') : ?>
                        <?php echo esc_html(self::adminText('Synchronization failed. Check Runtime Inspector/server logs before retrying.', 'فشلت المزامنة. راجع Runtime Inspector أو سجل الخادم ثم أعد المحاولة.')); ?>
                    <?php else : ?>
                        <?php echo esc_html(self::adminText('A full reconciliation also runs once automatically after every plugin upgrade, without waiting for WP-Cron.', 'تعمل مزامنة كاملة تلقائياً مرة واحدة بعد كل ترقية للبلجن بدون انتظار WP-Cron.')); ?>
                    <?php endif; ?>
                    <?php if ($lastFullSync !== '') : ?>
                        <br><small><?php echo esc_html(self::adminText('Last full sync (UTC):', 'آخر مزامنة كاملة (UTC):')); ?> <code dir="ltr"><?php echo esc_html($lastFullSync); ?></code> · <?php echo esc_html(sprintf(self::adminText('%d occurrences', '%d حالة'), $lastCount)); ?><?php if ($lastSource !== '') : ?> · <code dir="ltr"><?php echo esc_html($lastSource); ?></code><?php endif; ?></small>
                    <?php endif; ?>
                </p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::MANUAL_SYNC_ACTION); ?>">
                <?php wp_nonce_field(self::MANUAL_SYNC_ACTION); ?>
                <button type="submit" class="button button-primary"><?php echo esc_html(self::adminText('Sync schedule now', 'مزامنة الجدولة الآن')); ?></button>
            </form>
        </div>
        <?php
    }

    public static function reconcilePayment(mixed $paymentId): void
    {
        $paymentId = (int) $paymentId;
        if ($paymentId <= 0) {
            return;
        }
        try {
            (new NotificationPaymentScheduleReconciler())->reconcile($paymentId);
        } catch (Throwable $error) {
            // Payment writes are authoritative and must not be rolled back just
            // because schedule maintenance is temporarily unavailable. Cron will
            // retry from source-of-truth payment data on its next run.
            error_log('SafeContracts payment notification reconciliation failed for payment #' . $paymentId . ': ' . $error->getMessage());
        }
    }

    public static function run(): void
    {
        try {
            $service = new NotificationScheduleService();
            $count = $service->sync();
            self::recordFullSync($count, 'cron');
            $service->dispatchDue();
            update_option('safecontracts_notification_schedule_last_run', gmdate('c'), false);
        } catch (Throwable $error) {
            error_log('SafeContracts notification scheduler failed: ' . $error->getMessage());
        }
    }

    public static function clear(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }
    }

    private static function recordFullSync(int $count, string $source): void
    {
        update_option(self::LAST_FULL_SYNC_OPTION, gmdate('c'), false);
        update_option(self::LAST_FULL_SYNC_COUNT_OPTION, max(0, $count), false);
        update_option(self::LAST_FULL_SYNC_SOURCE_OPTION, sanitize_key($source), false);
    }

    private static function adminText(string $english, string $arabic): string
    {
        if (function_exists('is_rtl') && is_rtl()) {
            return $arabic;
        }
        $locale = function_exists('get_user_locale') ? strtolower((string) get_user_locale()) : '';
        return str_starts_with($locale, 'ar') ? $arabic : $english;
    }
}
