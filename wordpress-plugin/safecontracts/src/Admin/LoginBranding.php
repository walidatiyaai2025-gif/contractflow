<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

final class LoginBranding
{
    public const STYLE_HANDLE = 'safecontracts-login';

    public static function register(): void
    {
        add_action('login_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_filter('login_headerurl', [self::class, 'headerUrl']);
        add_filter('login_headertext', [self::class, 'headerText']);
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_style(
            self::STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/safecontracts-login.css',
            [],
            SAFECONTRACTS_VERSION
        );
    }

    public static function headerUrl(string $url): string
    {
        unset($url);
        return home_url('/');
    }

    public static function headerText(string $text): string
    {
        unset($text);
        return __('SafeContracts — Secure Contract Operations', 'safecontracts');
    }
}
