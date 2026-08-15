<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;

final class UsersRolesPage
{
    public const SLUG = 'safecontracts-users-roles';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Users & Roles', 'safecontracts'), __('Users & Roles', 'safecontracts'), Capabilities::MANAGE_USERS, self::SLUG, [self::class, 'render']);
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_USERS)) {
            wp_die(__('You do not have permission to view SafeContracts users and roles.', 'safecontracts'));
        }
        $definitions = self::roleDefinitions();
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Authorization directory', 'safecontracts'); ?></p><h1><?php echo esc_html__('Users & Roles', 'safecontracts'); ?></h1></div></div>
            <p class="description"><?php echo esc_html__('This screen is read-only in SC-P6-013. It shows effective WordPress role grants and user membership without exposing passwords, credentials or authentication secrets.', 'safecontracts'); ?></p>
            <div class="safecontracts-role-grid">
            <?php foreach ($definitions as $slug => $label) : ?>
                <?php
                $role = get_role($slug);
                $grants = is_object($role) && isset($role->capabilities) && is_array($role->capabilities) ? $role->capabilities : [];
                $users = get_users(['role' => $slug]);
                ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo esc_html($label); ?></h2>
                    <p><code><?php echo esc_html($slug); ?></code> · <?php echo esc_html(sprintf(__('%d users', 'safecontracts'), count($users))); ?></p>
                    <h3><?php echo esc_html__('Effective SafeContracts capabilities', 'safecontracts'); ?></h3>
                    <ul class="safecontracts-capability-list">
                    <?php foreach (Capabilities::all() as $capability) : ?>
                        <li><span aria-hidden="true"><?php echo ! empty($grants[$capability]) ? '✓' : '—'; ?></span> <code><?php echo esc_html($capability); ?></code></li>
                    <?php endforeach; ?>
                    </ul>
                    <?php if ($users !== []) : ?>
                    <h3><?php echo esc_html__('Members', 'safecontracts'); ?></h3>
                    <ul><?php foreach ($users as $user) : ?><li><?php echo esc_html(self::userLabel($user)); ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /** @return array<string,string> */
    private static function roleDefinitions(): array
    {
        return [
            RoleRegistrar::SYSTEM_ADMIN => __('System Administrator', 'safecontracts'),
            RoleRegistrar::MANAGER => __('Manager', 'safecontracts'),
            RoleRegistrar::ACCOUNTANT => __('Accountant', 'safecontracts'),
            RoleRegistrar::VIEWER => __('Viewer', 'safecontracts'),
        ];
    }

    private static function userLabel(mixed $user): string
    {
        if (is_object($user)) {
            $id = isset($user->ID) ? (int) $user->ID : 0;
            $name = isset($user->display_name) ? (string) $user->display_name : '';
            $email = isset($user->user_email) ? (string) $user->user_email : '';
        } elseif (is_array($user)) {
            $id = (int) ($user['ID'] ?? $user['id'] ?? 0);
            $name = (string) ($user['display_name'] ?? $user['name'] ?? '');
            $email = (string) ($user['user_email'] ?? $user['email'] ?? '');
        } else {
            $id = (int) $user;
            $name = '';
            $email = '';
        }
        $identity = trim($name . ($email !== '' ? ' — ' . $email : ''));
        return '#' . $id . ($identity !== '' ? ' · ' . $identity : '');
    }
}
