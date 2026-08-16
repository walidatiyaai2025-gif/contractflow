<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Support\Brand;

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
        if (function_exists('wp_add_inline_style')) {
            wp_add_inline_style(
                self::STYLE_HANDLE,
                'body.login h1 a{background-image:url("' . Brand::iconDataUri() . '") !important;}'
            );
        }
    }

    public static function headerUrl(string $url): string
    {
        unset($url);
        return home_url('/');
    }

    public static function headerText(string $text): string
    {
        unset($text);
        return Brand::NAME . ' — Secure Contract Operations';
    }
}
