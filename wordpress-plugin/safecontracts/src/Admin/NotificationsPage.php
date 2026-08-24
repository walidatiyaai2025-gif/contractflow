<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\DeliveryLogRepository;
use SafeContracts\Notifications\NotificationRuleService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\RuntimeLabels;

final class NotificationsPage
{
    public const SLUG = 'safecontracts-notifications';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Notifications', 'safecontracts'), __('Notifications', 'safecontracts'), Capabilities::MANAGE_NOTIFICATIONS, self::SLUG, [self::class, 'render']);
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage notifications.', 'safecontracts'));
        }
        $filters = DashboardFilters::normalize($_GET);
        $statusFilter = isset($_GET['delivery_status']) && is_scalar($_GET['delivery_status']) ? sanitize_key((string) $_GET['delivery_status']) : '';
        $rules = (new NotificationRuleService())->all();
        $allDeliveries = empty($filters['date_range_error'])
            ? (new DeliveryLogRepository())->recent(100, $filters['date_from'], $filters['date_to'])
            : [];
        $availableStatuses = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => sanitize_key((string) ($row['status'] ?? '')),
            $allDeliveries
        ))));
        sort($availableStatuses);
        if ($statusFilter !== '' && ! in_array($statusFilter, $availableStatuses, true)) {
            $statusFilter = '';
        }
        $deliveries = $statusFilter === '' ? $allDeliveries : array_values(array_filter(
            $allDeliveries,
            static fn (array $row): bool => sanitize_key((string) ($row['status'] ?? '')) === $statusFilter
        ));
        $sent = self::countStatuses($allDeliveries, ['sent', 'success', 'delivered']);
        $failed = self::countStatuses($allDeliveries, ['failed', 'error']);
        $pending = self::countStatuses($allDeliveries, ['pending', 'processing', 'queued']);
        $suppressedOrRetry = self::countStatuses($allDeliveries, ['suppressed', 'retry', 'retrying', 'retry_pending']);
        ?>
        <div class="wrap safecontracts-settings safecontracts-notification-delivery" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Notification operations', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Notification Delivery Activity', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Inspect server-side delivery attempts without hiding failed, pending, suppressed or retry states.', 'safecontracts'); ?></p>
                </div>
            </div>
            <?php AdminSummaryCards::render([
                ['label' => __('Sent', 'safecontracts'), 'value' => $sent],
                ['label' => __('Pending', 'safecontracts'), 'value' => $pending],
                ['label' => __('Failed', 'safecontracts'), 'value' => $failed],
                ['label' => __('Suppressed / retry', 'safecontracts'), 'value' => $suppressedOrRetry],
            ]); ?>

            <form class="safecontracts-filter-bar" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <?php AdminPeriodFilter::renderFields($filters); ?>
                <label><?php echo esc_html__('Delivery state', 'safecontracts'); ?><select name="delivery_status"><option value=""><?php echo esc_html__('All states', 'safecontracts'); ?></option><?php foreach ($availableStatuses as $state) : ?><option value="<?php echo esc_attr($state); ?>" <?php selected($statusFilter, $state); ?>><?php echo esc_html(self::stateLabel($state)); ?></option><?php endforeach; ?></select></label>
                <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear', 'safecontracts'); ?></a>
            </form>
            <p class="description"><?php echo esc_html__('The period filter applies to notification delivery-log creation time. Rule definitions remain configuration and are intentionally not date-filtered.', 'safecontracts'); ?></p>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Rules in effect', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('These are the real configured rules currently available to the notification engine.', 'safecontracts'); ?></p></div></div>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Rule', 'safecontracts'); ?></th><th><?php echo esc_html__('Trigger', 'safecontracts'); ?></th><th><?php echo esc_html__('Recipients', 'safecontracts'); ?></th><th><?php echo esc_html__('Template', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th></tr></thead><tbody>
                <?php if ($rules === []) : ?><tr><td colspan="5"><?php echo esc_html__('No notification rules are configured.', 'safecontracts'); ?></td></tr><?php endif; ?>
                <?php foreach ($rules as $rule) : ?>
                    <?php $roles = is_array($rule['recipient_roles'] ?? null) ? $rule['recipient_roles'] : []; $active = ! empty($rule['is_active']); ?>
                    <tr><td><strong><?php echo esc_html((string) $rule['name']); ?></strong><br><code dir="ltr"><?php echo esc_html((string) $rule['code']); ?></code></td><td><?php echo esc_html(self::triggerLabel((string) $rule['trigger_type'])); ?></td><td><?php echo esc_html(implode(', ', array_map([self::class, 'roleLabel'], array_map('strval', $roles)))); ?><?php echo ! empty($rule['target_assigned_accountant']) ? ' + ' . esc_html(RuntimeLabels::text('Assigned Accountant')) : ''; ?></td><td><code dir="ltr"><?php echo esc_html((string) $rule['template_code']); ?></code></td><td><span class="safecontracts-state-chip <?php echo $active ? 'is-success' : 'is-warning'; ?>"><?php echo $active ? esc_html__('Active', 'safecontracts') : esc_html__('Disabled', 'safecontracts'); ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Delivery attempts', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('Result values below come directly from the server-side delivery log. Errors stay errors.', 'safecontracts'); ?></p></div></div>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('When', 'safecontracts'); ?></th><th><?php echo esc_html__('Related payment', 'safecontracts'); ?></th><th><?php echo esc_html__('Recipient', 'safecontracts'); ?></th><th><?php echo esc_html__('Template', 'safecontracts'); ?></th><th><?php echo esc_html__('Attempt', 'safecontracts'); ?></th><th><?php echo esc_html__('Result', 'safecontracts'); ?></th></tr></thead><tbody>
                <?php if ($deliveries === []) : ?><tr><td colspan="6"><?php echo esc_html__('No delivery attempts match the selected period and state.', 'safecontracts'); ?></td></tr><?php endif; ?>
                <?php foreach ($deliveries as $delivery) : $state = sanitize_key((string) ($delivery['status'] ?? '')); ?>
                    <tr><td><?php echo esc_html((string) ($delivery['created_at'] ?? '')); ?></td><td>#<?php echo esc_html((string) (int) ($delivery['payment_id'] ?? 0)); ?></td><td>#<?php echo esc_html((string) (int) ($delivery['user_id'] ?? 0)); ?></td><td><code dir="ltr"><?php echo esc_html((string) ($delivery['template_code'] ?? '')); ?></code></td><td><?php echo esc_html((string) ($delivery['attempt_no'] ?? '')); ?></td><td><span class="safecontracts-state-chip <?php echo esc_attr(self::stateClass($state)); ?>"><?php echo esc_html(self::stateLabel($state)); ?></span><?php if (! empty($delivery['error_code'])) : ?><br><code dir="ltr"><?php echo esc_html((string) $delivery['error_code']); ?></code><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <p class="description"><?php echo esc_html__('Settled-payment suppression and retry rules remain enforced by the notification engine; this page does not rewrite delivery semantics.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $statuses */
    private static function countStatuses(array $rows, array $statuses): int
    {
        return count(array_filter($rows, static fn (array $row): bool => in_array(sanitize_key((string) ($row['status'] ?? '')), $statuses, true)));
    }

    public static function roleLabel(string $role): string
    {
        return RuntimeLabels::text(ucwords(str_replace(['safecontracts_', '_'], ['', ' '], $role)));
    }

    private static function triggerLabel(string $trigger): string
    {
        $normalized = strtolower(trim($trigger));
        $source = match ($normalized) {
            'before_due', 'due_before' => 'Before due',
            'due_today', 'on_due' => 'Due today',
            'overdue', 'after_due' => 'Overdue',
            default => ucwords(str_replace('_', ' ', $normalized)),
        };
        return RuntimeLabels::text($source);
    }

    private static function stateLabel(string $state): string
    {
        return RuntimeLabels::text(ucwords(str_replace('_', ' ', $state !== '' ? $state : 'unknown')));
    }

    private static function stateClass(string $state): string
    {
        return match ($state) {
            'sent', 'success', 'delivered' => 'is-success',
            'failed', 'error' => 'is-danger',
            'suppressed', 'pending', 'processing', 'queued', 'retry', 'retrying', 'retry_pending' => 'is-warning',
            default => '',
        };
    }
}
