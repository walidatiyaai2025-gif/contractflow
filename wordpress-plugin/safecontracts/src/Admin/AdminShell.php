<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Brand;
use SafeContracts\Translations\TranslationCatalog;

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
    public const POLISH_STYLE_HANDLE = 'safecontracts-admin-polish';
    public const REDESIGN_TOKENS_STYLE_HANDLE = 'safecontracts-plugin-redesign-tokens';
    public const REDESIGN_PRIMITIVES_STYLE_HANDLE = 'safecontracts-plugin-redesign-primitives';
    public const REDESIGN_SUMMARY_CARDS_STYLE_HANDLE = 'safecontracts-plugin-redesign-summary-cards';
    public const REDESIGN_NAVIGATION_STYLE_HANDLE = 'safecontracts-plugin-redesign-navigation';
    public const REDESIGN_LEAD_SCREENS_STYLE_HANDLE = 'safecontracts-plugin-redesign-lead-screens';

    public static function register(): void
    {
        add_filter('admin_footer_text', [self::class, 'footerText']);
        add_filter('update_footer', [self::class, 'footerVersion'], 20);

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

    public static function footerText(string $current): string
    {
        if (! self::isSafeContractsPage()) {
            return $current;
        }

        return TranslationCatalog::currentLanguage() === 'ar'
            ? 'ALKENZY ADV — النسخة المعتمدة'
            : 'ALKENZY ADV — Approved release';
    }

    public static function footerVersion(string $current): string
    {
        if (! self::isSafeContractsPage()) {
            return $current;
        }

        $label = TranslationCatalog::currentLanguage() === 'ar' ? 'الإصدار' : 'Version';
        return $label . ' ' . SAFECONTRACTS_VERSION;
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
        wp_enqueue_style(self::POLISH_STYLE_HANDLE, SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-polish.css', [self::PREMIUM_STYLE_HANDLE], SAFECONTRACTS_VERSION);

        wp_enqueue_style(
            self::REDESIGN_TOKENS_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/tokens.css',
            [self::POLISH_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::REDESIGN_PRIMITIVES_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/primitives.css',
            [self::REDESIGN_TOKENS_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::REDESIGN_SUMMARY_CARDS_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/summary-cards.css',
            [self::REDESIGN_PRIMITIVES_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::REDESIGN_NAVIGATION_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/navigation.css',
            [self::REDESIGN_SUMMARY_CARDS_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::REDESIGN_LEAD_SCREENS_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/lead-screens.css',
            [self::REDESIGN_NAVIGATION_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );

        if (function_exists('wp_add_inline_style')) {
            wp_add_inline_style(
                self::POLISH_STYLE_HANDLE,
                '.safecontracts-admin-shell__hero,.safecontracts-summary-injector,.safecontracts-admin-shell__identity-proof{display:none!important}' .
                '.safecontracts-admin-shell__content{padding-top:0!important}' .
                '.safecontracts-settings select[multiple]{min-height:150px}'
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
                    <?php self::renderDashboardHeader(); ?>
                    <?php DashboardV2Page::renderContent(); ?>
                <?php endif; ?>
            </main>
        </div>
        <?php
    }
    private static function renderDashboardHeader(): void
    {
        ?>
        <div class="safecontracts-page-heading safecontracts-dashboard-reference-heading">
            <div class="safecontracts-dashboard-reference-heading__identity">
                <img class="safecontracts-dashboard-reference-heading__brand" src="<?php echo Brand::iconDataUri(); // Trusted embedded brand constant. ?>" alt="" aria-hidden="true">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Safe Contracts workspace', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Dashboard', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html__('A real-time overview of contracts, receivables, payables, settlements and notifications.', 'safecontracts'); ?></p>
                </div>
            </div>
            <div class="safecontracts-dashboard-reference-heading__assurance">
                <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                <span><?php echo esc_html__('Server-side authorization', 'safecontracts'); ?></span>
            </div>
        </div>
        <nav class="safecontracts-action-strip" aria-label="<?php echo esc_html__('Dashboard quick actions', 'safecontracts'); ?>">
            <?php if (current_user_can(Capabilities::CREATE_CUSTOMERS)) : ?>
                <?php self::actionTile(CustomersPage::SLUG, 'dashicons-admin-users', __('Add customer', 'safecontracts'), __('Create or manage a customer record', 'safecontracts')); ?>
            <?php endif; ?>
            <?php if (current_user_can(Capabilities::CREATE_CONTRACTS)) : ?>
                <?php self::actionTile(ContractsPage::SLUG, 'dashicons-media-document', __('Add contract', 'safecontracts'), __('Create a customer or supplier contract', 'safecontracts')); ?>
            <?php endif; ?>
            <?php if (current_user_can(Capabilities::CREATE_PAYMENTS)) : ?>
                <?php self::actionTile(PaymentsPage::SLUG, 'dashicons-money-alt', __('Add payment', 'safecontracts'), __('Create a scheduled contract payment', 'safecontracts')); ?>
            <?php endif; ?>
            <?php if (current_user_can(Capabilities::CREATE_SUPPLIERS)) : ?>
                <?php self::actionTile(SuppliersPage::SLUG, 'dashicons-store', __('Suppliers', 'safecontracts'), __('Create or manage supplier records', 'safecontracts')); ?>
            <?php endif; ?>
            <?php if (current_user_can(Capabilities::MANAGE_SYSTEM)) : ?>
                <?php self::actionTile(GeneralSettingsPage::SLUG, 'dashicons-admin-generic', __('Settings', 'safecontracts'), __('System and organization preferences', 'safecontracts')); ?>
            <?php endif; ?>
        </nav>
        <?php
    }

    private static function actionTile(string $slug, string $icon, string $title, string $detail): void
    {
        $url = add_query_arg(['page' => $slug], admin_url('admin.php'));
        ?>
        <a class="safecontracts-action-tile" href="<?php echo esc_url($url); ?>">
            <span class="safecontracts-action-tile__icon dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            <span class="safecontracts-action-tile__copy">
                <span class="safecontracts-action-tile__title"><?php echo esc_html($title); ?></span>
                <span class="safecontracts-action-tile__detail"><?php echo esc_html($detail); ?></span>
            </span>
        </a>
        <?php
    }
}
