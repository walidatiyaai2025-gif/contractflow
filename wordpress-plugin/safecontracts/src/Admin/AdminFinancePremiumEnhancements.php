<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class AdminFinancePremiumEnhancements
{
    public static function register(): void
    {
        add_action('admin_footer', [self::class, 'render'], 31);
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            return;
        }
        $page = isset($_GET['page']) && is_scalar($_GET['page'])
            ? sanitize_key((string) $_GET['page'])
            : '';
        if ($page !== FinancePage::SLUG) {
            return;
        }
        ?>
        <style>
            .safecontracts-finance>.safecontracts-section-heading:first-child{display:none!important}
            .safecontracts-cash-flow-chart{margin:16px 0 22px;padding:18px;border:1px solid #dfe6ee;border-radius:20px;background:linear-gradient(145deg,#fff,#f7f9fc);box-shadow:0 12px 30px rgba(10,42,73,.08)}
            .safecontracts-cash-flow-chart__head{display:flex;justify-content:space-between;align-items:end;gap:12px;margin-bottom:16px}.safecontracts-cash-flow-chart__head h2{margin:0;color:#0b3154}.safecontracts-cash-flow-chart__head p{margin:0;color:#69798c}
            .safecontracts-cash-flow-chart__legend{display:flex;gap:10px;flex-wrap:wrap;margin:-4px 0 12px;color:#6b7887;font-size:11px}.safecontracts-cash-flow-chart__legend span{display:inline-flex;align-items:center;gap:5px}.safecontracts-cash-flow-chart__legend i{width:9px;height:9px;border-radius:50%;background:#448b70}.safecontracts-cash-flow-chart__legend .is-out i{background:#b77b6d}
            .safecontracts-cash-flow-chart__bars{height:220px;display:flex;align-items:end;gap:8px;border-bottom:1px solid #ccd7e3;padding:0 6px;overflow-x:auto}
            .safecontracts-cash-flow-chart__item{height:100%;flex:1;min-width:48px;display:flex;flex-direction:column;justify-content:end;align-items:center}.safecontracts-cash-flow-chart__value{font-size:10px;font-weight:800;color:#314a64;max-width:58px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:5px}.safecontracts-cash-flow-chart__bar{width:min(44px,76%);min-height:10px;border-radius:10px 10px 3px 3px;background:#173b65}.safecontracts-cash-flow-chart__item.is-inflow .safecontracts-cash-flow-chart__bar{background:#448b70}.safecontracts-cash-flow-chart__item.is-outflow .safecontracts-cash-flow-chart__bar{background:#b77b6d}.safecontracts-cash-flow-chart__date,.safecontracts-cash-flow-chart__currency{font-size:10px;color:#6c7b8c;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}.safecontracts-cash-flow-chart__currency{font-weight:800;color:#405a73;margin-top:1px}
            @media(max-width:800px){.safecontracts-cash-flow-chart__bars{justify-content:flex-start}.safecontracts-cash-flow-chart__item{flex:0 0 54px}}
        </style>
        <script>
        (() => {
            const finance = document.querySelector('.safecontracts-finance');
            if (!finance || finance.dataset.cashFlowEnhanced === '1') return;
            finance.dataset.cashFlowEnhanced = '1';

            const cards = Array.from(finance.querySelectorAll('.safecontracts-table-card'));
            const cashCard = cards.find((card) => {
                const heading = (card.querySelector('h2')?.textContent || '').toLowerCase();
                return heading.includes('cash flow') || heading.includes('التدفق');
            });
            const table = cashCard?.querySelector('table');
            if (!table) return;

            const sourceRows = Array.from(table.querySelectorAll('tbody tr')).slice(0, 90);
            if (!sourceRows.length) return;
            const parsed = sourceRows.map((row) => {
                const cells = Array.from(row.querySelectorAll('td')).map((cell) => (cell.textContent || '').trim());
                const numeric = (cells[3] || '').replace(/[^0-9.,-]/g, '').replace(/,/g, '');
                return {
                    date: cells[0] || '',
                    flow: (cells[1] || '').toLowerCase(),
                    currency: cells[2] || '—',
                    amountText: cells[3] || '',
                    amount: Math.abs(Number(numeric) || 0),
                };
            }).filter((row) => row.date !== '' || row.amount > 0);
            if (!parsed.length) return;

            const maxima = {};
            parsed.forEach((row) => {
                maxima[row.currency] = Math.max(maxima[row.currency] || 0, row.amount);
            });

            const chart = document.createElement('section');
            chart.className = 'safecontracts-cash-flow-chart';
            chart.setAttribute('role', 'img');
            chart.setAttribute('aria-label', <?php echo wp_json_encode(__('Cash flow', 'safecontracts')); ?>);

            const head = document.createElement('div');
            head.className = 'safecontracts-cash-flow-chart__head';
            head.innerHTML = '<div><h2>' + <?php echo wp_json_encode(__('Cash flow', 'safecontracts')); ?> + '</h2><p>' + <?php echo wp_json_encode(__('Expected inflows and outflows from the current finance scope.', 'safecontracts')); ?> + '</p></div>';
            chart.appendChild(head);

            const legend = document.createElement('div');
            legend.className = 'safecontracts-cash-flow-chart__legend';
            legend.innerHTML = '<span><i></i>' + <?php echo wp_json_encode(__('Money coming in', 'safecontracts')); ?> + '</span><span class="is-out"><i></i>' + <?php echo wp_json_encode(__('Money going out', 'safecontracts')); ?> + '</span>';
            chart.appendChild(legend);

            const bars = document.createElement('div');
            bars.className = 'safecontracts-cash-flow-chart__bars';
            parsed.forEach((row) => {
                const maximum = Math.max(1, maxima[row.currency] || 0);
                const item = document.createElement('div');
                const isOut = row.flow.includes('out') || row.flow.includes('pay') || row.flow.includes('خارج') || row.flow.includes('دفع');
                item.className = 'safecontracts-cash-flow-chart__item ' + (isOut ? 'is-outflow' : 'is-inflow');
                item.title = [row.date, row.currency, row.amountText].filter(Boolean).join(' · ');

                const value = document.createElement('span');
                value.className = 'safecontracts-cash-flow-chart__value';
                value.textContent = row.amountText;

                const bar = document.createElement('div');
                bar.className = 'safecontracts-cash-flow-chart__bar';
                bar.style.height = Math.max(10, Math.round((row.amount / maximum) * 125)) + 'px';

                const date = document.createElement('span');
                date.className = 'safecontracts-cash-flow-chart__date';
                date.textContent = row.date;

                const currency = document.createElement('span');
                currency.className = 'safecontracts-cash-flow-chart__currency';
                currency.textContent = row.currency;

                item.append(value, bar, date, currency);
                bars.appendChild(item);
            });
            chart.appendChild(bars);
            cashCard.insertAdjacentElement('beforebegin', chart);
        })();
        </script>
        <?php
    }
}
