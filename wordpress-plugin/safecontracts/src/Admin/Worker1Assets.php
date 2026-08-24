<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

final class Worker1Assets
{
    private const STYLE_HANDLE = 'safecontracts-plugin-redesign-worker-1';
    private const MOBILE_STYLE_HANDLE = 'safecontracts-plugin-redesign-worker-1-mobile';
    private const ROUTES = [
        'safecontracts-customers',
        'safecontracts-suppliers',
        'safecontracts-contracts',
        'safecontracts-archive',
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        $page = isset($_GET['page']) && is_scalar($_GET['page'])
            ? sanitize_key((string) $_GET['page'])
            : '';
        if (! in_array($page, self::ROUTES, true)) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/worker-1/parties-contracts.css',
            [AdminShell::PREMIUM_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::MOBILE_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/worker-1/mobile-controls.css',
            [self::STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
    }
}