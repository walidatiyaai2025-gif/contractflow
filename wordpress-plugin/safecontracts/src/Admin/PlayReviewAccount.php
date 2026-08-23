<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use WP_User;

final class PlayReviewAccount
{
    public const SLUG = 'safecontracts-play-review';
    public const EMAIL = 'playreview@alkenzy.com';
    public const LOGIN = 'playreview';
    public const CREATE_ACTION = 'safecontracts_create_play_review_account';
    public const DISABLE_ACTION = 'safecontracts_disable_play_review_account';
    private const META_KEY = 'safecontracts_play_review_account';

    public static function registerPage(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Google Play Review', 'safecontracts'), __('Google Play Review', 'safecontracts'), Capabilities::MANAGE_SYSTEM, self::SLUG, [self::class, 'renderPage']);
    }

    /** @return array{exists:bool,managed:bool,user_id:int,email:string,login:string} */
    public static function status(): array
    {
        $user = get_user_by('email', self::EMAIL);
        if (! $user instanceof WP_User) {
            return ['exists' => false, 'managed' => false, 'user_id' => 0, 'email' => self::EMAIL, 'login' => self::LOGIN];
        }
        return [
            'exists' => true,
            'managed' => (string) get_user_meta((int) $user->ID, self::META_KEY, true) === '1',
            'user_id' => (int) $user->ID,
            'email' => (string) $user->user_email,
            'login' => (string) $user->user_login,
        ];
    }

    public static function renderPage(): void
    {
        self::requireManage();
        $status = self::status();
        $notice = isset($_GET['safecontracts_status']) && is_string($_GET['safecontracts_status']) ? sanitize_key($_GET['safecontracts_status']) : '';
        $messages = [
            'review_ready' => __('Google Play review account is ready. Use the email and the password you just entered in Play Console.', 'safecontracts'),
            'review_disabled' => __('Google Play review account was disabled and its password was randomized.', 'safecontracts'),
            'review_password_invalid' => __('Review password must contain between 6 and 128 characters.', 'safecontracts'),
            'review_conflict' => __('A WordPress account already uses the review email but was not created by this tool. It was not modified.', 'safecontracts'),
            'review_create_failed' => __('The Google Play review account could not be created.', 'safecontracts'),
        ];
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <h1><?php echo esc_html__('Google Play Review Account', 'safecontracts'); ?></h1>
            <?php if ($notice !== '' && isset($messages[$notice])): ?><div class="notice <?php echo esc_attr(in_array($notice, ['review_ready', 'review_disabled'], true) ? 'notice-success' : 'notice-error'); ?> is-dismissible"><p><?php echo esc_html($messages[$notice]); ?></p></div><?php endif; ?>
            <section class="safecontracts-admin-card safecontracts-settings-card">
                <p><?php echo esc_html__('This creates a temporary, read-only Safe Contracts viewer for Google Play review. The password is submitted once to WordPress and is never stored in plugin settings or committed to Git.', 'safecontracts'); ?></p>
                <table class="widefat striped" style="max-width:760px"><tbody>
                    <tr><th><?php echo esc_html__('Reviewer email', 'safecontracts'); ?></th><td><code><?php echo esc_html(self::EMAIL); ?></code></td></tr>
                    <tr><th><?php echo esc_html__('WordPress login', 'safecontracts'); ?></th><td><code><?php echo esc_html(self::LOGIN); ?></code></td></tr>
                    <tr><th><?php echo esc_html__('Role', 'safecontracts'); ?></th><td><?php echo esc_html__('SafeContracts Viewer — read-only / assigned-scope access', 'safecontracts'); ?></td></tr>
                    <tr><th><?php echo esc_html__('Current status', 'safecontracts'); ?></th><td><?php echo esc_html($status['managed'] ? __('Ready', 'safecontracts') : ($status['exists'] ? __('Email already exists but is not managed by this tool', 'safecontracts') : __('Not created yet', 'safecontracts'))); ?></td></tr>
                </tbody></table>
                <h2><?php echo esc_html__('Create or reset reviewer credentials', 'safecontracts'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:760px">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::CREATE_ACTION); ?>">
                    <?php wp_nonce_field(self::CREATE_ACTION); ?>
                    <p><label><?php echo esc_html__('Temporary review password', 'safecontracts'); ?><br><input class="regular-text" type="password" name="review_password" minlength="6" maxlength="128" required autocomplete="new-password"></label></p>
                    <?php submit_button($status['managed'] ? __('Reset Review Password', 'safecontracts') : __('Create Review Account', 'safecontracts')); ?>
                </form>
                <?php if ($status['managed']): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::DISABLE_ACTION); ?>"><?php wp_nonce_field(self::DISABLE_ACTION); ?><?php submit_button(__('Disable Review Account', 'safecontracts'), 'secondary'); ?></form><?php endif; ?>
                <p><strong><?php echo esc_html__('Review-data safety:', 'safecontracts'); ?></strong> <?php echo esc_html__('Assign only sanitized demo records to this viewer. Do not grant manager/admin access or expose production customer, supplier, contract, or payment data to the review account.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }

    public static function handleCreate(): void
    {
        self::requireManage();
        check_admin_referer(self::CREATE_ACTION);
        $password = isset($_POST['review_password']) && is_string($_POST['review_password']) ? $_POST['review_password'] : '';
        if (strlen($password) < 6 || strlen($password) > 128) { self::redirect('review_password_invalid'); }
        $existing = get_user_by('email', self::EMAIL);
        if ($existing instanceof WP_User) {
            if ((string) get_user_meta((int) $existing->ID, self::META_KEY, true) !== '1') { self::redirect('review_conflict'); }
            wp_set_password($password, (int) $existing->ID);
            $existing->set_role(RoleRegistrar::VIEWER);
            update_user_meta((int) $existing->ID, self::META_KEY, '1');
            self::redirect('review_ready');
        }
        RoleRegistrar::registerDefaults();
        $userId = wp_insert_user(['user_login' => self::LOGIN, 'user_email' => self::EMAIL, 'user_pass' => $password, 'display_name' => 'Google Play Review', 'role' => RoleRegistrar::VIEWER]);
        if (is_wp_error($userId)) { self::redirect('review_create_failed'); }
        update_user_meta((int) $userId, self::META_KEY, '1');
        self::redirect('review_ready');
    }

    public static function handleDisable(): void
    {
        self::requireManage();
        check_admin_referer(self::DISABLE_ACTION);
        $user = get_user_by('email', self::EMAIL);
        if ($user instanceof WP_User && (string) get_user_meta((int) $user->ID, self::META_KEY, true) === '1') {
            wp_set_password(wp_generate_password(64, true, true), (int) $user->ID);
            $user->set_role('subscriber');
            delete_user_meta((int) $user->ID, self::META_KEY);
        }
        self::redirect('review_disabled');
    }

    private static function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) { wp_die(__('You do not have permission to manage the Google Play review account.', 'safecontracts')); }
    }

    private static function redirect(string $status): never
    {
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }
}
