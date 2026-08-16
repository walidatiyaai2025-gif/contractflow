<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Presence\PresenceService;
use SafeContracts\Roles\Capabilities;

final class ActiveUsersPage
{
    public const SLUG = 'safecontracts-active-users';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Active Users', 'safecontracts'),
            __('Active Users', 'safecontracts'),
            Capabilities::MANAGE_USERS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_USERS)) {
            wp_die(__('You do not have permission to view active users.', 'safecontracts'));
        }
        $users = self::users();
        $appActive = count(array_filter($users, static fn (array $row): bool => $row['mobile_active']));
        $adminActive = count(array_filter($users, static fn (array $row): bool => $row['admin_active']));
        $either = count(array_filter($users, static fn (array $row): bool => $row['mobile_active'] || $row['admin_active']));
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Presence', 'safecontracts'); ?></p><h1><?php echo esc_html__('Active Users', 'safecontracts'); ?></h1></div></div>
            <?php AdminSummaryCards::render([
                ['label' => __('Active on app', 'safecontracts'), 'value' => $appActive, 'detail' => __('Seen in the last 5 minutes', 'safecontracts')],
                ['label' => __('Active on dashboard', 'safecontracts'), 'value' => $adminActive, 'detail' => __('Seen in the last 5 minutes', 'safecontracts')],
                ['label' => __('Active anywhere', 'safecontracts'), 'value' => $either],
                ['label' => __('Safe Contracts users', 'safecontracts'), 'value' => count($users)],
            ]); ?>
            <section class="safecontracts-admin-card safecontracts-table-card">
                <h2><?php echo esc_html__('User presence', 'safecontracts'); ?></h2>
                <p class="description"><?php echo esc_html__('App activity is updated by authenticated mobile API traffic. Dashboard activity is updated by Safe Contracts admin requests and WordPress Heartbeat. No session token or device-token value is exposed here.', 'safecontracts'); ?></p>
                <table class="widefat striped"><thead><tr><th><?php echo esc_html__('User', 'safecontracts'); ?></th><th><?php echo esc_html__('App', 'safecontracts'); ?></th><th><?php echo esc_html__('Last app activity', 'safecontracts'); ?></th><th><?php echo esc_html__('Dashboard', 'safecontracts'); ?></th><th><?php echo esc_html__('Last dashboard activity', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php if ($users === []) : ?><tr><td colspan="5"><?php echo esc_html__('No Safe Contracts users were found.', 'safecontracts'); ?></td></tr><?php endif; ?>
                    <?php foreach ($users as $row) : ?><tr><td><strong><?php echo esc_html($row['name']); ?></strong><br><small><?php echo esc_html($row['email']); ?></small></td><td><?php echo esc_html($row['mobile_active'] ? __('Active', 'safecontracts') : __('Inactive', 'safecontracts')); ?></td><td><?php echo esc_html(self::formatTime($row['mobile_seen'])); ?></td><td><?php echo esc_html($row['admin_active'] ? __('Active', 'safecontracts') : __('Inactive', 'safecontracts')); ?></td><td><?php echo esc_html(self::formatTime($row['admin_seen'])); ?></td></tr><?php endforeach; ?>
                </tbody></table>
            </section>
        </div>
        <?php
    }

    /** @return list<array{id:int,name:string,email:string,mobile_seen:int,admin_seen:int,mobile_active:bool,admin_active:bool}> */
    private static function users(): array
    {
        $rows = get_users(['fields' => ['ID', 'display_name', 'user_email'], 'orderby' => 'display_name', 'order' => 'ASC']);
        $result = [];
        foreach (is_array($rows) ? $rows : [] as $user) {
            if (! is_object($user)) {
                continue;
            }
            $id = (int) ($user->ID ?? 0);
            if ($id <= 0 || ! user_can($id, Capabilities::ACCESS)) {
                continue;
            }
            $mobile = (int) get_user_meta($id, PresenceService::MOBILE_META, true);
            $admin = (int) get_user_meta($id, PresenceService::ADMIN_META, true);
            $result[] = [
                'id' => $id,
                'name' => trim((string) ($user->display_name ?? '')) ?: '#' . $id,
                'email' => (string) ($user->user_email ?? ''),
                'mobile_seen' => $mobile,
                'admin_seen' => $admin,
                'mobile_active' => PresenceService::isActive($mobile),
                'admin_active' => PresenceService::isActive($admin),
            ];
        }
        usort($result, static function (array $a, array $b): int {
            $aActive = ($a['mobile_active'] || $a['admin_active']) ? 1 : 0;
            $bActive = ($b['mobile_active'] || $b['admin_active']) ? 1 : 0;
            return $bActive <=> $aActive ?: strcasecmp($a['name'], $b['name']);
        });
        return $result;
    }

    private static function formatTime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '—';
        }
        if (function_exists('wp_date')) {
            return wp_date('Y-m-d H:i:s', $timestamp);
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
