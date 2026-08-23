<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

/**
 * Additive visual/interaction layer for the 0.3.2 executive dashboard.
 *
 * Financial data remains server-rendered by DashboardV2Page. This class only
 * rearranges those already-authorized panels, adds the month input accepted by
 * DashboardFilters, and visualizes already-rendered contract counts.
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
        <style>
            .safecontracts-premium-three-column{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;align-items:start;margin-top:18px}
            .safecontracts-premium-three-column>.safecontracts-dashboard-v2__lane,.safecontracts-premium-three-column>.safecontracts-dashboard-v2__net-section{margin:0;min-width:0;height:100%}
            .safecontracts-premium-three-column .safecontracts-dashboard-v2__lane-grid,.safecontracts-premium-three-column .safecontracts-dashboard-v2__net-grid{grid-template-columns:1fr}
            .safecontracts-premium-chart{margin:18px 0;background:linear-gradient(145deg,#fff,#f7f9fc);border:1px solid #dfe6ee;border-radius:20px;padding:18px;box-shadow:0 12px 32px rgba(10,42,73,.08)}
            .safecontracts-premium-chart__title{display:flex;justify-content:space-between;gap:12px;align-items:end;margin-bottom:18px}.safecontracts-premium-chart__title h3{margin:0;color:#0b3154}.safecontracts-premium-chart__title p{margin:0;color:#68798c}
            .safecontracts-premium-chart__bars{height:190px;display:flex;gap:18px;align-items:end;border-bottom:1px solid #ced8e4;padding:0 8px 0}
            .safecontracts-premium-chart__item{flex:1;height:100%;display:flex;flex-direction:column;justify-content:end;align-items:center;min-width:0}
            .safecontracts-premium-chart__value{font-weight:800;color:#173b65;margin-bottom:7px}.safecontracts-premium-chart__bar{width:min(80px,75%);min-height:12px;border-radius:14px 14px 4px 4px;background:linear-gradient(180deg,#b77b6d,#173b65);box-shadow:0 8px 18px rgba(23,59,101,.16)}
            .safecontracts-premium-chart__label{font-size:12px;font-weight:700;color:#596b80;padding:8px 2px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
            .safecontracts-premium-fab{position:fixed;inset-inline-end:28px;bottom:32px;z-index:10020;display:flex;flex-direction:column;align-items:end;gap:10px}
            .safecontracts-premium-fab__menu{display:none;min-width:220px;padding:10px;background:#fff;border:1px solid #dfe6ee;border-radius:18px;box-shadow:0 18px 46px rgba(8,37,64,.2)}
            .safecontracts-premium-fab.is-open .safecontracts-premium-fab__menu{display:grid;gap:6px}.safecontracts-premium-fab__menu a{display:flex;align-items:center;gap:9px;text-decoration:none;padding:10px 12px;border-radius:12px;color:#173b65;font-weight:700}.safecontracts-premium-fab__menu a:hover{background:#f3e3df;color:#0b3154}
            .safecontracts-premium-fab__button{width:58px;height:58px;border-radius:50%;border:0;background:linear-gradient(145deg,#b77b6d,#8f5549);color:#fff;font-size:30px;line-height:1;cursor:pointer;box-shadow:0 14px 30px rgba(143,85,73,.35)}
            @media(max-width:1180px){.safecontracts-premium-three-column{grid-template-columns:1fr}.safecontracts-premium-fab{inset-inline-end:16px;bottom:20px}}
        </style>
        <script>
        (() => {
            const dashboard = document.querySelector('.safecontracts-dashboard-v2');
            if (!dashboard || dashboard.dataset.premiumEnhanced === '1') return;
            dashboard.dataset.premiumEnhanced = '1';

            const filter = dashboard.querySelector('.safecontracts-dashboard-v2__filters');
            if (filter && !filter.querySelector('[name="month"]')) {
                const label = document.createElement('label');
                label.textContent = <?php echo wp_json_encode(__('Month', 'safecontracts')); ?>;
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
            if (kpis.length) {
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
                    const label = document.createElement('div');
                    label.className = 'safecontracts-premium-chart__label';
                    label.textContent = kpi.querySelector('span')?.textContent?.trim() || '';
                    item.append(value, bar, label);
                    bars.appendChild(item);
                });
                chart.appendChild(bars);
                const kpiGrid = dashboard.querySelector('.safecontracts-dashboard-v2__kpis');
                kpiGrid?.insertAdjacentElement('afterend', chart);
            }

            const fab = document.getElementById('safecontracts-premium-fab');
            const button = fab?.querySelector('.safecontracts-premium-fab__button');
            button?.addEventListener('click', () => {
                const open = fab.classList.toggle('is-open');
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        })();
        </script>
        <?php if ($actions !== []) : ?>
            <div class="safecontracts-premium-fab" id="safecontracts-premium-fab">
                <div class="safecontracts-premium-fab__menu">
                    <?php foreach ($actions as $action) : ?>
                        <a href="<?php echo esc_url($action['url']); ?>"><span aria-hidden="true"><?php echo esc_html($action['icon']); ?></span><?php echo esc_html($action['label']); ?></a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="safecontracts-premium-fab__button" aria-expanded="false" aria-label="<?php echo esc_attr__('Quick add', 'safecontracts'); ?>">+</button>
            </div>
        <?php endif; ?>
        <?php
    }

    /** @return list<array{label:string,url:string,icon:string}> */
    private static function actions(): array
    {
        $actions = [];
        if (current_user_can(Capabilities::CREATE_CONTRACTS)) {
            $actions[] = ['label' => __('Add contract', 'safecontracts'), 'url' => add_query_arg(['page' => ContractsPage::SLUG, 'action' => 'new'], admin_url('admin.php')), 'icon' => '▣'];
        }
        if (current_user_can(Capabilities::CREATE_PAYMENTS)) {
            $actions[] = ['label' => __('Add payment', 'safecontracts'), 'url' => add_query_arg(['page' => PaymentsPage::SLUG, 'action' => 'new'], admin_url('admin.php')), 'icon' => '◈'];
        }
        if (current_user_can(Capabilities::CREATE_CUSTOMERS)) {
            $actions[] = ['label' => __('Add customer', 'safecontracts'), 'url' => add_query_arg(['page' => CustomersPage::SLUG, 'action' => 'new'], admin_url('admin.php')), 'icon' => '◎'];
        }
        if (current_user_can(Capabilities::CREATE_SUPPLIERS)) {
            $actions[] = ['label' => __('Add supplier', 'safecontracts'), 'url' => add_query_arg(['page' => SuppliersPage::SLUG, 'action' => 'new'], admin_url('admin.php')), 'icon' => '◇'];
        }
        return $actions;
    }
}
