<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class AdminShell
{
    public const SLUG = 'safecontracts';
    public const STYLE_HANDLE = 'safecontracts-admin';

    public static function register(): void
    {
        add_menu_page(
            __('SafeContracts', 'safecontracts'),
            __('SafeContracts', 'safecontracts'),
            Capabilities::ACCESS,
            self::SLUG,
            [self::class, 'render'],
            'dashicons-shield-alt',
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
    }

    public static function isSafeContractsPage(): bool
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        return $page === self::SLUG || str_starts_with($page, 'safecontracts-');
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access SafeContracts.', 'safecontracts'));
        }
        ?>
        <div class="wrap safecontracts-admin-shell" dir="auto">
            <header class="safecontracts-admin-shell__hero">
                <div class="safecontracts-admin-shell__mark" aria-hidden="true">SC</div>
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Contract Operations', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('SafeContracts', 'safecontracts'); ?></h1>
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
