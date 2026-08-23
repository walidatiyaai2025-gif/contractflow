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
    public const UX_STYLE_HANDLE = 'safecontracts-admin-v2';
    public const FINANCIAL_STYLE_HANDLE = 'safecontracts-admin-financial-v3';
    public const CONTRACT_TREE_STYLE_HANDLE = 'safecontracts-contract-payment-tree';
    public const PREMIUM_STYLE_HANDLE = 'safecontracts-admin-premium';
    public const DASHBOARD_V3_STYLE_HANDLE = 'safecontracts-admin-dashboard-v3';
    public const DASHBOARD_V3_SCRIPT_HANDLE = 'safecontracts-admin-dashboard-v3';

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

        wp_enqueue_style(self::STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin.css', [], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::CORE_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-core.css', [self::STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::OPS_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-ops.css', [self::CORE_STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::SETTINGS_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-settings.css', [self::OPS_STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::RESPONSIVE_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-responsive.css', [self::SETTINGS_STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::UX_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-v2.css', [self::RESPONSIVE_STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::FINANCIAL_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-financial-v3.css', [self::UX_STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::CONTRACT_TREE_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/contract-payment-tree.css', [self::FINANCIAL_STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::PREMIUM_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-premium.css', [self::CONTRACT_TREE_STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_style(self::DASHBOARD_V3_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-v3.css', [self::PREMIUM_STYLE_HANDLE], SAFECONTRACTS_VERSION);
        wp_enqueue_script(self::DASHBOARD_V3_SCRIPT_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-v3.js', [], SAFECONTRACTS_VERSION, true);

        if (function_exists('wp_add_inline_style')) {
            wp_add_inline_style(
                self::DASHBOARD_V3_STYLE_HANDLE,
                '.safecontracts-admin-shell__brand-image{width:58px;height:58px;object-fit:cover;border-radius:18px;display:block;box-shadow:0 10px 24px rgba(19,53,88,.18)}' .
                '.safecontracts-settings select[multiple]{min-height:150px}' .
                '.safecontracts-navigation-group__title{font-size:18px;line-height:1.35}.safecontracts-navigation-group__card .button{align-self:flex-start;margin-top:auto}.safecontracts-navigation-group__cards{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}'
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
            <main class="safecontracts-admin-shell__content safecontracts-admin-shell__content--dashboard">
                <?php if (! AdminNavigationGroups::renderRequestedGroup()) : ?>
                    <?php DashboardV3Page::renderContent(); ?>
                <?php endif; ?>
            </main>
        </div>
        <?php
    }
}
