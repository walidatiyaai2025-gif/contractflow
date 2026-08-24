<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Notifications\NotificationScheduleRepository;
use SafeContracts\Notifications\NotificationScheduleService;
use SafeContracts\Notifications\NotificationScheduleSettings;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\RuntimeLabels;
use Throwable;

final class NotificationSchedulePage
{
    public const SLUG = 'safecontracts-notification-schedule';
    public const MANUAL_SEND_ACTION = 'safecontracts_notification_manual_send';
    public const SAVE_TIME_ACTION = 'safecontracts_notification_schedule_time';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Notification Schedule', 'safecontracts'), __('Notification Schedule', 'safecontracts'), Capabilities::MANAGE_NOTIFICATIONS, self::SLUG, [self::class, 'render']);
    }

    public static function handleManualSend(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to send notifications.', 'safecontracts'));
        }
        $scheduleId = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        check_admin_referer(self::MANUAL_SEND_ACTION . '_' . $scheduleId);
        $status = 'notification_failed';
        if ($scheduleId > 0) {
            try {
                $sent = (new NotificationScheduleService())->dispatchManual($scheduleId, get_current_user_id());
                $status = $sent ? 'notification_sent' : 'notification_busy';
            } catch (Throwable) {
                $status = 'notification_failed';
            }
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function handleSaveTime(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notification scheduling.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_TIME_ACTION);
        $status = 'schedule_time_saved';
        try {
            (new NotificationScheduleSettings())->saveDispatchTime($_POST['dispatch_time'] ?? '');
            (new NotificationScheduleService())->sync();
        } catch (Throwable) {
            $status = 'schedule_time_invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notifications.', 'safecontracts'));
        }

        $filters = DashboardFilters::normalize($_GET);
        $status = isset($_GET['notification_status']) && is_scalar($_GET['notification_status']) ? sanitize_key((string) $_GET['notification_status']) : '';
        if (! in_array($status, NotificationScheduleRepository::statuses(), true)) {
            $status = '';
        }
        $repository = new NotificationScheduleRepository();
        $rows = empty($filters['date_range_error']) ? $repository->recent($filters['date_from'], $filters['date_to'], $status, 300) : [];
        $deliveries = new DeliveryLogRepository();
        $settings = new NotificationScheduleSettings();
        $lastRun = (string) get_option('safecontracts_notification_schedule_last_run', '');
        $pending = self::countStates($rows, ['pending', 'queued']);
        $processing = self::countStates($rows, ['processing']);
        $failed = self::countStates($rows, ['failed']);
        $sent = self::countStates($rows, ['sent']);
        ?>
        <div class="wrap safecontracts-settings safecontracts-notification-schedule" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Notification operations', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Notification Schedule', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Control dispatch time and inspect the real persisted schedule without changing scheduler semantics.', 'safecontracts'); ?></p>
                </div>
                <span class="safecontracts-state-chip <?php echo $lastRun !== '' ? 'is-success' : 'is-warning'; ?>"><?php echo $lastRun !== '' ? esc_html__('Scheduler has run', 'safecontracts') : esc_html__('No run recorded', 'safecontracts'); ?></span>
            </div>
            <?php self::renderNotice(); ?>

            <?php AdminSummaryCards::render([
                ['label' => __('Pending', 'safecontracts'), 'value' => $pending],
                ['label' => __('Processing', 'safecontracts'), 'value' => $processing],
                ['label' => __('Sent', 'safecontracts'), 'value' => $sent],
                ['label' => __('Failed', 'safecontracts'), 'value' => $failed],
            ]); ?>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Schedule controls', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('Filters do not alter schedules. Changing dispatch time invokes the existing schedule reconciliation service.', 'safecontracts'); ?></p></div></div>
                <div class="safecontracts-field-row">
                    <form class="safecontracts-filter-bar" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                        <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                        <label><?php echo esc_html__('From', 'safecontracts'); ?> <input type="date" name="date_from" value="<?php echo esc_attr((string) ($filters['date_from'] ?? '')); ?>"></label>
                        <label><?php echo esc_html__('To', 'safecontracts'); ?> <input type="date" name="date_to" value="<?php echo esc_attr((string) ($filters['date_to'] ?? '')); ?>"></label>
                        <label><?php echo esc_html__('Status', 'safecontracts'); ?><select name="notification_status"><option value=""><?php echo esc_html__('All', 'safecontracts'); ?></option><?php foreach (NotificationScheduleRepository::statuses() as $option) : ?><option value="<?php echo esc_attr($option); ?>" <?php selected($status, $option); ?>><?php echo esc_html(self::stateLabel($option)); ?></option><?php endforeach; ?></select></label>
                        <?php submit_button(__('Apply filters', 'safecontracts'), 'secondary', '', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_TIME_ACTION); ?>"><?php wp_nonce_field(self::SAVE_TIME_ACTION); ?>
                        <label><?php echo esc_html__('Daily dispatch time', 'safecontracts'); ?><input type="time" name="dispatch_time" required value="<?php echo esc_attr($settings->dispatchTime()); ?>"></label>
                        <?php submit_button(__('Save time', 'safecontracts'), 'secondary', '', false); ?>
                    </form>
                </div>
                <?php if (! empty($filters['date_range_error'])) : ?><p class="notice notice-error inline"><strong><?php echo esc_html__('Invalid period: the From date must not be after the To date.', 'safecontracts'); ?></strong></p><?php endif; ?>
                <p class="description"><?php echo esc_html__('Schedule dates follow the configured WordPress/site timezone. Dispatch runs every five minutes through WP-Cron; actual execution depends on site traffic or the server cron that invokes WordPress cron.', 'safecontracts'); ?><?php if ($lastRun !== '') : ?> <?php echo esc_html__('Last scheduler run (UTC):', 'safecontracts'); ?> <code dir="ltr"><?php echo esc_html($lastRun); ?></code><?php endif; ?></p>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Actual scheduled notifications', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('State, attempts and error codes below are backend values, not presentation guesses.', 'safecontracts'); ?></p></div></div>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Scheduled', 'safecontracts'); ?></th><th><?php echo esc_html__('Notification', 'safecontracts'); ?></th><th><?php echo esc_html__('Recipients / result', 'safecontracts'); ?></th><th><?php echo esc_html__('Sent via', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th><th><?php echo esc_html__('Last attempt', 'safecontracts'); ?></th><th><?php echo esc_html__('Action', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php if ($rows === []) : ?><tr><td colspan="7"><?php echo esc_html__('No scheduled notifications match this period and status.', 'safecontracts'); ?></td></tr><?php endif; ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php $outcomes = $deliveries->outcomesForOccurrence((int) $row['rule_id'], (int) $row['payment_id'], (string) $row['scheduled_date'], (int) $row['attempt_no']); $recipientIds = is_array($row['recipient_ids'] ?? null) ? $row['recipient_ids'] : []; $rowState = sanitize_key((string) ($row['status'] ?? '')); ?>
                        <tr>
                            <td><strong><?php echo esc_html(self::localTime((string) $row['scheduled_for'])); ?></strong><br><small><?php echo esc_html__('Local/site time', 'safecontracts'); ?></small></td>
                            <td><strong><?php echo esc_html((string) ($row['rule_name'] ?? $row['rule_code'] ?? '')); ?></strong><br><?php echo esc_html__('Contract', 'safecontracts'); ?>: <code dir="ltr"><?php echo esc_html((string) ($row['contract_number'] ?? '')); ?></code><br><?php echo esc_html__('Customer', 'safecontracts'); ?>: <?php echo esc_html((string) ($row['customer_name'] ?? '')); ?><br><?php echo esc_html__('Payment', 'safecontracts'); ?>: #<?php echo esc_html((string) $row['payment_id']); ?><?php if (! empty($row['payment_reference'])) : ?> · <?php echo esc_html((string) $row['payment_reference']); ?><?php endif; ?><br><small><?php echo esc_html__('Rule attempt', 'safecontracts'); ?>: <?php echo esc_html((string) $row['attempt_no']); ?></small></td>
                            <td><?php self::renderRecipients($recipientIds, $outcomes); ?></td>
                            <td><strong><?php echo esc_html(self::channelLabel((string) ($row['channel'] ?? 'push'))); ?></strong><br><small><?php echo esc_html(self::channelDetail((string) ($row['channel'] ?? 'push'))); ?></small></td>
                            <td><span class="safecontracts-state-chip <?php echo esc_attr(self::stateClass($rowState)); ?>"><?php echo esc_html(self::stateLabel($rowState)); ?></span><br><small><?php echo esc_html(sprintf(__('Sent %d / Failed %d / Recipients %d', 'safecontracts'), (int) $row['sent_count'], (int) $row['failed_count'], (int) $row['recipient_count'])); ?></small><?php if (! empty($row['last_error_code'])) : ?><br><code dir="ltr"><?php echo esc_html((string) $row['last_error_code']); ?></code><?php endif; ?></td>
                            <td><?php echo ! empty($row['last_attempt_at']) ? esc_html(self::localTime((string) $row['last_attempt_at'])) : '—'; ?><?php if ((int) $row['manual_attempts'] > 0) : ?><br><small><?php echo esc_html(sprintf(__('Manual attempts: %d', 'safecontracts'), (int) $row['manual_attempts'])); ?></small><?php endif; ?></td>
                            <td><?php if ($rowState === 'processing') : ?><button class="button" disabled><?php echo esc_html__('Sending…', 'safecontracts'); ?></button><?php else : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Send this notification now using the current rule, recipients and configured delivery channels?', 'safecontracts')); ?>');"><input type="hidden" name="action" value="<?php echo esc_attr(self::MANUAL_SEND_ACTION); ?>"><input type="hidden" name="schedule_id" value="<?php echo esc_attr((string) $row['id']); ?>"><?php wp_nonce_field(self::MANUAL_SEND_ACTION . '_' . (int) $row['id']); ?><button type="submit" class="button button-secondary"><?php echo esc_html($rowState === 'sent' ? __('Resend manually', 'safecontracts') : __('Send manually', 'safecontracts')); ?></button></form><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table>
                <p class="description"><?php echo esc_html__('Manual Send never bypasses settled-payment suppression, contract/payment suppression, active-rule checks, recipient resolution, configured delivery channels, delivery logging or audit recording.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $states */
    private static function countStates(array $rows, array $states): int
    {
        return count(array_filter($rows, static fn (array $row): bool => in_array(sanitize_key((string) ($row['status'] ?? '')), $states, true)));
    }

    /** @param list<int> $recipientIds @param array<int,array{status:string,error_code:?string,attempts:int}> $outcomes */
    private static function renderRecipients(array $recipientIds, array $outcomes): void
    {
        if ($recipientIds === []) {
            echo '—';
            return;
        }
        foreach ($recipientIds as $userId) {
            $userId = (int) $userId;
            $user = function_exists('get_userdata') ? get_userdata($userId) : false;
            $name = $user && isset($user->display_name) && trim((string) $user->display_name) !== '' ? (string) $user->display_name : __('Unnamed WordPress user', 'safecontracts');
            $outcome = $outcomes[$userId] ?? null;
            $state = is_array($outcome) ? (string) $outcome['status'] : 'pending';
            echo '<div><strong>' . esc_html($name) . '</strong> — <span class="safecontracts-state-chip ' . esc_attr(self::stateClass($state)) . '">' . esc_html(self::recipientStateLabel($state)) . '</span>';
            if (is_array($outcome) && ! empty($outcome['error_code'])) {
                echo ' <code dir="ltr">' . esc_html((string) $outcome['error_code']) . '</code>';
            }
            echo '</div>';
        }
    }

    private static function channelLabel(string $channel): string
    {
        return match ($channel) {
            'push+email' => __('Push + Email', 'safecontracts'),
            'email' => __('Email', 'safecontracts'),
            default => __('Push', 'safecontracts'),
        };
    }

    private static function channelDetail(string $channel): string
    {
        return match ($channel) {
            'push+email' => 'Firebase Cloud Messaging + WordPress Mail',
            'email' => 'WordPress Mail',
            default => 'Firebase Cloud Messaging',
        };
    }

    private static function localTime(string $utc): string
    {
        if ($utc === '') {
            return '—';
        }
        return function_exists('get_date_from_gmt') ? get_date_from_gmt($utc, 'Y-m-d H:i') : $utc;
    }

    private static function stateLabel(string $state): string
    {
        return RuntimeLabels::text(ucwords(str_replace('_', ' ', $state !== '' ? $state : 'unknown')));
    }

    private static function stateClass(string $state): string
    {
        return match (sanitize_key($state)) {
            'sent', 'success', 'delivered' => 'is-success',
            'failed', 'error' => 'is-danger',
            'pending', 'queued', 'processing', 'suppressed', 'retry', 'retrying', 'retry_pending' => 'is-warning',
            default => '',
        };
    }

    private static function recipientStateLabel(string $state): string
    {
        return match ($state) {
            'sent' => RuntimeLabels::text('Sent'),
            'failed' => RuntimeLabels::text('Not sent'),
            default => RuntimeLabels::text('Pending'),
        };
    }

    private static function renderNotice(): void
    {
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status']) ? sanitize_key((string) $_GET['safecontracts_status']) : '';
        $message = match ($status) {
            'notification_sent' => __('Manual notification dispatch completed.', 'safecontracts'),
            'notification_busy' => __('This notification is already being processed.', 'safecontracts'),
            'notification_failed' => __('Manual notification dispatch could not be completed.', 'safecontracts'),
            'schedule_time_saved' => __('Notification dispatch time was saved and pending schedules were refreshed.', 'safecontracts'),
            'schedule_time_invalid' => __('Notification dispatch time is invalid.', 'safecontracts'),
            default => '',
        };
        if ($message !== '') {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
