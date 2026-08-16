<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Brand;

final class AdminShell
{
    public const SLUG = 'safecontracts';
    public const STYLE_HANDLE = 'safecontracts-admin';
    public const CORE_STYLE_HANDLE = 'safecontracts-admin-core';
    public const OPS_STYLE_HANDLE = 'safecontracts-admin-ops';
    public const SETTINGS_STYLE_HANDLE = 'safecontracts-admin-settings';
    public const RESPONSIVE_STYLE_HANDLE = 'safecontracts-admin-responsive';

    public static function register(): void
    {
        add_menu_page(
            Brand::NAME,
            Brand::NAME,
            Capabilities::ACCESS,
            self::SLUG,
            [self::class, 'render'],
            Brand::iconDataUri(),
            2
        );
    }

    public static function enqueueAssets(): void
    {
        if (! self::isSafeContractsPage()) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin.css',
            [],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::CORE_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-core.css',
            [self::STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::OPS_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-ops.css',
            [self::CORE_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::SETTINGS_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-settings.css',
            [self::OPS_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::RESPONSIVE_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-responsive.css',
            [self::SETTINGS_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );

        if (function_exists('wp_add_inline_style')) {
            wp_add_inline_style(
                self::RESPONSIVE_STYLE_HANDLE,
                '.safecontracts-admin-shell__brand-image{width:58px;height:58px;object-fit:cover;border-radius:18px;display:block;box-shadow:0 10px 24px rgba(19,53,88,.18)}'
            );
        }
    }

    public static function isSafeContractsPage(): bool
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        return $page === self::SLUG || str_starts_with($page, 'safecontracts-');
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access Safe Contracts.', 'safecontracts'));
        }
        ?>
        <div class="wrap safecontracts-admin-shell" dir="auto">
            <header class="safecontracts-admin-shell__hero">
                <img class="safecontracts-admin-shell__brand-image" src="<?php echo Brand::iconDataUri(); // Trusted embedded brand constant. ?>" alt="" aria-hidden="true">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Contract Operations', 'safecontracts'); ?></p>
                    <h1><?php echo Brand::NAME; // Trusted constant. ?></h1>
                    <p><?php echo esc_html__('Secure contract, receivable, collection, follow-up and notification operations from one workspace.', 'safecontracts'); ?></p>
                </div>
            </header>
            <main class="safecontracts-admin-shell__content safecontracts-admin-shell__content--dashboard">
                <?php DashboardPage::renderContent(); ?>
            </main>
        </div>
        <?php
    }
}
