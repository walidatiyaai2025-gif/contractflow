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
        $rules = (new NotificationRuleService())->all();
        $deliveries = empty($filters['date_range_error'])
            ? (new DeliveryLogRepository())->recent(100, $filters['date_from'], $filters['date_to'])
            : [];
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Notification operations', 'safecontracts'); ?></p><h1><?php echo esc_html__('Notifications', 'safecontracts'); ?></h1></div></div>
            <?php AdminPeriodFilter::render(self::SLUG, $filters); ?>
            <p class="description"><?php echo esc_html__('The period filter applies to notification delivery-log creation time. Rule definitions remain configuration and are intentionally not date-filtered.', 'safecontracts'); ?></p>
            <section class="safecontracts-admin-card safecontracts-table-card">
                <h2><?php echo esc_html__('Rules', 'safecontracts'); ?></h2>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Rule', 'safecontracts'); ?></th><th><?php echo esc_html__('Trigger', 'safecontracts'); ?></th><th><?php echo esc_html__('Recipients', 'safecontracts'); ?></th><th><?php echo esc_html__('Template', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th></tr></thead><tbody>
                <?php foreach ($rules as $rule) : ?>
                    <?php $roles = is_array($rule['recipient_roles'] ?? null) ? $rule['recipient_roles'] : []; ?>
                    <tr><td><strong><?php echo esc_html((string) $rule['name']); ?></strong><br><code><?php echo esc_html((string) $rule['code']); ?></code></td><td><?php echo esc_html(self::triggerLabel((string) $rule['trigger_type'])); ?></td><td><?php echo esc_html(implode(', ', array_map([self::class, 'roleLabel'], array_map('strval', $roles)))); ?><?php echo ! empty($rule['target_assigned_accountant']) ? ' + ' . esc_html(RuntimeLabels::text('Assigned Accountant')) : ''; ?></td><td><?php echo esc_html((string) $rule['template_code']); ?></td><td><?php echo ! empty($rule['is_active']) ? esc_html__('Active', 'safecontracts') : esc_html__('Disabled', 'safecontracts'); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <p class="description"><?php echo esc_html__('This operational screen intentionally exposes no Firebase credentials, service-account material or device-token values. Notification configuration is handled by dedicated settings tasks.', 'safecontracts'); ?></p>
            </section>
            <section class="safecontracts-admin-card safecontracts-table-card">
                <h2><?php echo esc_html__('Recent delivery log', 'safecontracts'); ?></h2>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('When', 'safecontracts'); ?></th><th><?php echo esc_html__('Payment', 'safecontracts'); ?></th><th><?php echo esc_html__('User', 'safecontracts'); ?></th><th><?php echo esc_html__('Template', 'safecontracts'); ?></th><th><?php echo esc_html__('Attempt', 'safecontracts'); ?></th><th><?php echo esc_html__('Result', 'safecontracts'); ?></th></tr></thead><tbody>
                <?php foreach ($deliveries as $delivery) : ?>
                    <tr><td><?php echo esc_html((string) $delivery['created_at']); ?></td><td>#<?php echo esc_html((string) $delivery['payment_id']); ?></td><td>#<?php echo esc_html((string) $delivery['user_id']); ?></td><td><?php echo esc_html((string) $delivery['template_code']); ?></td><td><?php echo esc_html((string) $delivery['attempt_no']); ?></td><td><?php echo esc_html(self::stateLabel((string) $delivery['status'])); ?><?php if (! empty($delivery['error_code'])) : ?><br><code><?php echo esc_html((string) $delivery['error_code']); ?></code><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <p class="description"><?php echo esc_html__('Delivery state is read from the server-side log. Settled-payment suppression and retry rules remain enforced by the notification engine.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
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
        return RuntimeLabels::text(ucwords(str_replace('_', ' ', $state)));
    }
}
