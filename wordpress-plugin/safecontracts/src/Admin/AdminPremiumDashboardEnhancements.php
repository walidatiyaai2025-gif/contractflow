<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

/**
 * Additive interaction layer for the executive dashboard.
 *
 * Financial values remain server-rendered by DashboardV2Page. This class only
 * exposes permission-aware navigation actions and keeps the floating quick-add
 * interaction accessible. Dashboard data/layout are server-rendered.
 */
final class AdminPremiumDashboardEnhancements
{
    public static function register(): void
    {
        add_action('admin_footer', [self::class, 'render'], 30);
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            return;
        }
        $page = isset($_GET['page']) && is_scalar($_GET['page'])
            ? sanitize_key((string) $_GET['page'])
            : '';
        if ($page !== AdminShell::SLUG) {
            return;
        }

        $actions = self::actions();
        ?>
        <?php if ($actions !== []) : ?>
            <div class="safecontracts-premium-fab" id="safecontracts-premium-fab">
                <div class="safecontracts-premium-fab__menu" id="safecontracts-premium-fab-menu">
                    <?php foreach ($actions as $action) : ?>
                        <a href="<?php echo esc_url($action['url']); ?>">
                            <span class="dashicons <?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></span>
                            <span><?php echo esc_html($action['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="safecontracts-premium-fab__button" aria-expanded="false" aria-controls="safecontracts-premium-fab-menu" aria-label="<?php echo esc_attr__('Quick add', 'safecontracts'); ?>">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                </button>
            </div>
        <?php endif; ?>

        <script>
        (() => {
            const dashboard = document.querySelector('.safecontracts-dashboard-v2');
            if (!dashboard || dashboard.dataset.premiumEnhanced === '1') return;
            dashboard.dataset.premiumEnhanced = '1';

            dashboard.querySelectorAll('[data-safecontracts-confirm]').forEach((button) => {
                const form = button.closest('form');
                form?.addEventListener('submit', (event) => {
                    if (form.dataset.safecontractsSubmitting === '1') {
                        event.preventDefault();
                        return;
                    }
                    const message = button.getAttribute('data-safecontracts-confirm') || '';
                    if (message && !window.confirm(message)) {
                        event.preventDefault();
                        return;
                    }
                    form.dataset.safecontractsSubmitting = '1';
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    const busyLabel = button.getAttribute('data-safecontracts-busy-label') || '';
                    if (busyLabel) {
                        const icon = button.querySelector('.dashicons');
                        button.replaceChildren();
                        if (icon) button.append(icon);
                        button.append(document.createTextNode(busyLabel));
                    }
                });
            });

            const fab = document.getElementById('safecontracts-premium-fab');
            const button = fab?.querySelector('.safecontracts-premium-fab__button');
            const closeFab = () => {
                if (!fab || !button) return;
                fab.classList.remove('is-open');
                button.setAttribute('aria-expanded', 'false');
            };
            button?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const open = fab.classList.toggle('is-open');
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            document.addEventListener('click', (event) => {
                if (fab && event.target instanceof Node && !fab.contains(event.target)) {
                    closeFab();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeFab();
            });
        })();
        </script>
        <?php
    }

    /** @return list<array{label:string,description:string,url:string,icon:string,tone:string}> */
    private static function actions(): array
    {
        $actions = [];
        if (current_user_can(Capabilities::CREATE_CUSTOMERS)) {
            $actions[] = [
                'label' => __('Add customer', 'safecontracts'),
                'description' => __('Create a new customer', 'safecontracts'),
                'url' => add_query_arg(['page' => CustomersPage::SLUG, 'action' => 'new'], admin_url('admin.php')),
                'icon' => 'dashicons-admin-users',
                'tone' => 'customer',
            ];
        }
        if (current_user_can(Capabilities::CREATE_CONTRACTS)) {
            $actions[] = [
                'label' => __('Add contract', 'safecontracts'),
                'description' => __('Create customer or supplier contract', 'safecontracts'),
                'url' => add_query_arg(['page' => ContractsPage::SLUG, 'action' => 'new'], admin_url('admin.php')),
                'icon' => 'dashicons-media-document',
                'tone' => 'contract',
            ];
        }
        if (current_user_can(Capabilities::CREATE_PAYMENTS)) {
            $actions[] = [
                'label' => __('Add payment', 'safecontracts'),
                'description' => __('Create a scheduled obligation', 'safecontracts'),
                'url' => add_query_arg(['page' => PaymentsPage::SLUG, 'action' => 'new'], admin_url('admin.php')),
                'icon' => 'dashicons-money-alt',
                'tone' => 'payment',
            ];
        }
        if (current_user_can(Capabilities::MANAGE_SYSTEM)) {
            $landingUrl = add_query_arg(['page' => MobileConfigurationPage::SLUG], admin_url('admin.php'));
            $actions[] = [
                'label' => __('App landing', 'safecontracts'),
                'description' => __('Edit the mobile landing page', 'safecontracts'),
                'url' => $landingUrl . '#safecontracts-mobile-landing-content',
                'icon' => 'dashicons-smartphone',
                'tone' => 'landing',
            ];
            $actions[] = [
                'label' => __('Settings', 'safecontracts'),
                'description' => __('Open system settings', 'safecontracts'),
                'url' => add_query_arg(['page' => GeneralSettingsPage::SLUG], admin_url('admin.php')),
                'icon' => 'dashicons-admin-generic',
                'tone' => 'settings',
            ];
        }
        return $actions;
    }
}
