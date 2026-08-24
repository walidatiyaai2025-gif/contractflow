<?php

declare(strict_types=1);

namespace SafeContracts\Admin\Worker2;

use SafeContracts\Admin\AdminShell;

/**
 * Worker #2 route-scoped presentation assets.
 *
 * The shared AdminShell remains Lead-owned. This registrar is intentionally
 * called from a Worker #2-owned page registration and only enqueues the
 * stylesheet for the seven frozen Worker #2 routes.
 */
final class RedesignAssets
{
    public const STYLE_HANDLE = 'safecontracts-plugin-redesign-worker-2';

    /** @var list<string> */
    private const SLUGS = [
        'safecontracts-payments',
        'safecontracts-collections',
        'safecontracts-followups',
        'safecontracts-finance',
        'safecontracts-reports',
        'safecontracts-imports',
        'safecontracts-payment-methods',
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 40);
    }

    public static function enqueue(): void
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if (! in_array($page, self::SLUGS, true)) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/worker-2/finance-operations.css',
            [AdminShell::PREMIUM_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
    }
}
