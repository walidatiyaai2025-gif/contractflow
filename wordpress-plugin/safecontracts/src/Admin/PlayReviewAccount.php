<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use WP_User;

final class PlayReviewAccount
{
    public const EMAIL = 'playreview@alkenzy.com';
    public const LOGIN = 'playreview';
    public const CREATE_ACTION = 'safecontracts_create_play_review_account';
    public const DISABLE_ACTION = 'safecontracts_disable_play_review_account';
    private const META_KEY = 'safecontracts_play_review_account';

    /** @return array{exists:bool,managed:bool,user_id:int,email:string,login:string} */
    public static function status(): array
    {
        $user = get_user_by('email', self::EMAIL);
        if (! $user instanceof WP_User) {
            return [
                'exists' => false,
                'managed' => false,
                'user_id' => 0,
                'email' => self::EMAIL,
                'login' => self::LOGIN,
            ];
        }

        return [
            'exists' => true,
            'managed' => (string) get_user_meta((int) $user->ID, self::META_KEY, true) === '1',
            'user_id' => (int) $user->ID,
            'email' => (string) $user->user_email,
            'login' => (string) $user->user_login,
        ];
    }

    public static function handleCreate(): void
    {
        self::requireManage();
        check_admin_referer(self::CREATE_ACTION);

        $password = isset($_POST['review_password']) && is_string($_POST['review_password'])
            ? $_POST['review_password']
            : '';
        if (strlen($password) < 6 || strlen($password) > 128) {
            self::redirect('review_password_invalid');
        }

        $existing = get_user_by('email', self::EMAIL);
        if ($existing instanceof WP_User) {
            if ((string) get_user_meta((int) $existing->ID, self::META_KEY, true) !== '1') {
                self::redirect('review_conflict');
            }

            wp_set_password($password, (int) $existing->ID);
            $existing->set_role(RoleRegistrar::VIEWER);
            update_user_meta((int) $existing->ID, self::META_KEY, '1');
            self::redirect('review_ready');
        }

        RoleRegistrar::registerDefaults();
        $userId = wp_insert_user([
            'user_login' => self::LOGIN,
            'user_email' => self::EMAIL,
            'user_pass' => $password,
            'display_name' => 'Google Play Review',
            'role' => RoleRegistrar::VIEWER,
        ]);
        if (is_wp_error($userId)) {
            self::redirect('review_create_failed');
        }

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
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage the Google Play review account.', 'safecontracts'));
        }
    }

    private static function redirect(string $status): never
    {
        wp_safe_redirect(add_query_arg([
            'page' => MobileConfigurationPage::SLUG,
            'safecontracts_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }
}
