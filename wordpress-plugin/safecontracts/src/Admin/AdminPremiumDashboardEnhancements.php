<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

/**
 * Additive interaction layer for the executive dashboard.
 *
 * Financial values remain server-rendered by DashboardV2Page. This class only
 * rearranges authorized panels, adds the accepted month filter, exposes
 * permission-aware navigation actions, and visualizes already-rendered counts.
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

        $month = isset($_GET['month']) && is_scalar($_GET['month'])
            ? max(0, min(12, (int) $_GET['month']))
            : 0;
        $actions = self::actions();
        ?>
        <?php if ($actions !== []) : ?>
            <nav class="safecontracts-premium-actions" id="safecontracts-premium-actions" aria-label="<?php echo esc_attr__('Dashboard actions', 'safecontracts'); ?>">
                <?php foreach ($actions as $action) : ?>
                    <a class="safecontracts-premium-action safecontracts-premium-action--<?php echo esc_attr($action['tone']); ?>" href="<?php echo esc_url($action['url']); ?>">
                        <span class="safecontracts-premium-action__icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr($action['icon']); ?>"></span></span>
                        <span class="safecontracts-premium-action__copy">
                            <strong><?php echo esc_html($action['label']); ?></strong>
                            <small><?php echo esc_html($action['description']); ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>

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

            const toolbar = document.getElementById('safecontracts-premium-actions');
            if (toolbar) {
                dashboard.prepend(toolbar);
            }

            const filter = dashboard.querySelector('.safecontracts-dashboard-v2__filters');
            if (filter && !filter.querySelector('[name="month"]')) {
                const label = document.createElement('label');
                label.append(document.createTextNode(<?php echo wp_json_encode(__('Month', 'safecontracts')); ?>));
                const select = document.createElement('select');
                select.name = 'month';
                const monthNames = [
                    <?php echo wp_json_encode(__('All months', 'safecontracts')); ?>,
                    <?php for ($i = 1; $i <= 12; $i++) : ?><?php echo wp_json_encode(wp_date('F', gmmktime(0, 0, 0, $i, 1, 2024))); ?><?php echo $i < 12 ? ',' : ''; ?>
                    <?php endfor; ?>
                ];
                monthNames.forEach((name, index) => {
                    const option = document.createElement('option');
                    option.value = String(index);
                    option.textContent = name;
                    option.selected = index === <?php echo (int) $month; ?>;
                    select.appendChild(option);
                });
                label.appendChild(select);
                const apply = filter.querySelector('button[type="submit"]');
                filter.insertBefore(label, apply || null);
            }

            const receivable = dashboard.querySelector('.safecontracts-dashboard-v2__lane--receivable');
            const payable = dashboard.querySelector('.safecontracts-dashboard-v2__lane--payable');
            const totals = dashboard.querySelector('.safecontracts-dashboard-v2__net-section');
            if ((receivable || payable || totals) && !dashboard.querySelector('.safecontracts-premium-three-column')) {
                const grid = document.createElement('div');
                grid.className = 'safecontracts-premium-three-column';
                [receivable, payable, totals].forEach((panel) => panel && grid.appendChild(panel));
                dashboard.appendChild(grid);
            }

            const kpis = Array.from(dashboard.querySelectorAll('.safecontracts-dashboard-v2__kpis .safecontracts-dashboard-v2__kpi')).slice(0, 3);
            if (kpis.length && !dashboard.querySelector('.safecontracts-premium-chart')) {
                const values = kpis.map((kpi) => {
                    const raw = (kpi.querySelector('strong')?.textContent || '0').replace(/[^0-9.-]/g, '');
                    return Math.max(0, Number(raw) || 0);
                });
                const max = Math.max(1, ...values);
                const chart = document.createElement('section');
                chart.className = 'safecontracts-premium-chart';
                const title = document.createElement('div');
                title.className = 'safecontracts-premium-chart__title';
                title.innerHTML = '<div><h3>' + <?php echo wp_json_encode(__('Contract portfolio', 'safecontracts')); ?> + '</h3><p>' + <?php echo wp_json_encode(__('All visible contract types in the selected period.', 'safecontracts')); ?> + '</p></div>';
                chart.appendChild(title);
                const bars = document.createElement('div');
                bars.className = 'safecontracts-premium-chart__bars';
                kpis.forEach((kpi, index) => {
                    const item = document.createElement('div');
                    item.className = 'safecontracts-premium-chart__item';
                    const value = document.createElement('div');
                    value.className = 'safecontracts-premium-chart__value';
                    value.textContent = String(values[index]);
                    const bar = document.createElement('div');
                    bar.className = 'safecontracts-premium-chart__bar';
                    bar.style.height = Math.max(12, Math.round((values[index] / max) * 130)) + 'px';
                    const itemLabel = document.createElement('div');
                    itemLabel.className = 'safecontracts-premium-chart__label';
                    itemLabel.textContent = kpi.querySelector('span')?.textContent?.trim() || '';
                    item.append(value, bar, itemLabel);
                    bars.appendChild(item);
                });
                chart.appendChild(bars);
                // Visualizations are deliberately appended after all dashboard
                // data panels so the chart is always the final dashboard block.
                dashboard.appendChild(chart);
            }

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
