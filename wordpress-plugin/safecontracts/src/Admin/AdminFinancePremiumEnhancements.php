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
        <style>.safecontracts-finance>.safecontracts-section-heading:first-child{display:none!important}</style>
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

            const sourceRows = Array.from(table.querySelectorAll('tbody tr')).slice(0, 120);
            if (!sourceRows.length) return;

            const parsed = sourceRows.map((row) => {
                const cells = Array.from(row.querySelectorAll('td')).map((cell) => (cell.textContent || '').trim());
                const numeric = (cells[3] || '').replace(/[^0-9.,-]/g, '').replace(/,/g, '');
                const flow = (cells[1] || '').toLowerCase();
                const isOut = flow.includes('out')
                    || flow.includes('pay')
                    || flow.includes('supplier')
                    || flow.includes('خارج')
                    || flow.includes('دفع')
                    || flow.includes('علينا');
                return {
                    date: cells[0] || '',
                    isOut,
                    currency: cells[2] || '—',
                    amountText: cells[3] || '',
                    amount: Math.abs(Number(numeric) || 0),
                };
            }).filter((row) => row.date !== '' || row.amount > 0);
            if (!parsed.length) return;

            // Keep each currency in its own scale and totals. Cross-currency
            // arithmetic is intentionally forbidden in this visualization.
            const groups = new Map();
            parsed.forEach((row) => {
                if (!groups.has(row.currency)) {
                    groups.set(row.currency, new Map());
                }
                const dates = groups.get(row.currency);
                if (!dates.has(row.date)) {
                    dates.set(row.date, {inflow: 0, outflow: 0});
                }
                const bucket = dates.get(row.date);
                if (row.isOut) {
                    bucket.outflow += row.amount;
                } else {
                    bucket.inflow += row.amount;
                }
            });

            const chart = document.createElement('section');
            chart.className = 'safecontracts-cash-flow-chart';
            chart.setAttribute('aria-label', <?php echo wp_json_encode(__('Cash flow', 'safecontracts')); ?>);

            const head = document.createElement('div');
            head.className = 'safecontracts-cash-flow-chart__head';
            head.innerHTML = '<div><h2>' + <?php echo wp_json_encode(__('Cash flow trend', 'safecontracts')); ?> + '</h2><p>' + <?php echo wp_json_encode(__('Incoming and outgoing obligations over time, independently scaled for each currency.', 'safecontracts')); ?> + '</p></div>';
            chart.appendChild(head);

            const legend = document.createElement('div');
            legend.className = 'safecontracts-cash-flow-chart__legend';
            legend.innerHTML = '<span><i></i>' + <?php echo wp_json_encode(__('Money coming in', 'safecontracts')); ?> + '</span><span class="is-out"><i></i>' + <?php echo wp_json_encode(__('Money going out', 'safecontracts')); ?> + '</span>';
            chart.appendChild(legend);

            const svgNs = 'http://www.w3.org/2000/svg';
            const formatNumber = (value) => new Intl.NumberFormat(document.documentElement.lang || undefined, {
                maximumFractionDigits: 0,
            }).format(value);

            groups.forEach((dateMap, currencyCode) => {
                const rows = Array.from(dateMap.entries()).map(([date, values]) => ({date, ...values}));
                if (!rows.length) return;

                const currencySection = document.createElement('section');
                currencySection.className = 'safecontracts-cash-flow-chart__currency';

                const currencyHead = document.createElement('div');
                currencyHead.className = 'safecontracts-cash-flow-chart__currency-head';
                const currencyTitle = document.createElement('strong');
                currencyTitle.textContent = currencyCode;
                const totals = document.createElement('div');
                totals.className = 'safecontracts-cash-flow-chart__totals';
                const inflowTotal = rows.reduce((sum, row) => sum + row.inflow, 0);
                const outflowTotal = rows.reduce((sum, row) => sum + row.outflow, 0);
                const inChip = document.createElement('span');
                inChip.className = 'safecontracts-cash-flow-chart__chip';
                inChip.textContent = <?php echo wp_json_encode(__('Incoming', 'safecontracts')); ?> + ': ' + formatNumber(inflowTotal) + ' ' + currencyCode;
                const outChip = document.createElement('span');
                outChip.className = 'safecontracts-cash-flow-chart__chip is-out';
                outChip.textContent = <?php echo wp_json_encode(__('Outgoing', 'safecontracts')); ?> + ': ' + formatNumber(outflowTotal) + ' ' + currencyCode;
                totals.append(inChip, outChip);
                currencyHead.append(currencyTitle, totals);
                currencySection.appendChild(currencyHead);

                const plot = document.createElement('div');
                plot.className = 'safecontracts-cash-flow-chart__plot';
                const svg = document.createElementNS(svgNs, 'svg');
                const width = 960;
                const height = 270;
                const left = 72;
                const right = 24;
                const top = 18;
                const bottom = 52;
                const innerWidth = width - left - right;
                const innerHeight = height - top - bottom;
                svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
                svg.setAttribute('role', 'img');
                svg.setAttribute('aria-label', currencyCode + ' ' + <?php echo wp_json_encode(__('cash flow trend', 'safecontracts')); ?>);

                const maximum = Math.max(1, ...rows.flatMap((row) => [row.inflow, row.outflow]));
                for (let step = 0; step <= 4; step += 1) {
                    const y = top + (innerHeight * step / 4);
                    const line = document.createElementNS(svgNs, 'line');
                    line.setAttribute('x1', String(left));
                    line.setAttribute('x2', String(width - right));
                    line.setAttribute('y1', String(y));
                    line.setAttribute('y2', String(y));
                    line.setAttribute('class', 'safecontracts-cash-flow-chart__grid');
                    svg.appendChild(line);

                    const yLabel = document.createElementNS(svgNs, 'text');
                    yLabel.setAttribute('x', String(left - 10));
                    yLabel.setAttribute('y', String(y + 4));
                    yLabel.setAttribute('text-anchor', 'end');
                    yLabel.setAttribute('class', 'safecontracts-cash-flow-chart__axis-label');
                    yLabel.textContent = formatNumber(maximum * (1 - step / 4));
                    svg.appendChild(yLabel);
                }

                const xAt = (index) => rows.length === 1
                    ? left + innerWidth / 2
                    : left + (innerWidth * index / (rows.length - 1));
                const yAt = (value) => top + innerHeight - ((value / maximum) * innerHeight);

                const inPoints = [];
                const outPoints = [];
                rows.forEach((row, index) => {
                    const x = xAt(index);
                    inPoints.push(`${x},${yAt(row.inflow)}`);
                    outPoints.push(`${x},${yAt(row.outflow)}`);
                });

                const inLine = document.createElementNS(svgNs, 'polyline');
                inLine.setAttribute('points', inPoints.join(' '));
                inLine.setAttribute('class', 'safecontracts-cash-flow-chart__line--in');
                svg.appendChild(inLine);
                const outLine = document.createElementNS(svgNs, 'polyline');
                outLine.setAttribute('points', outPoints.join(' '));
                outLine.setAttribute('class', 'safecontracts-cash-flow-chart__line--out');
                svg.appendChild(outLine);

                const labelEvery = Math.max(1, Math.ceil(rows.length / 6));
                rows.forEach((row, index) => {
                    const x = xAt(index);
                    [
                        {value: row.inflow, className: 'safecontracts-cash-flow-chart__point--in', label: <?php echo wp_json_encode(__('Incoming', 'safecontracts')); ?>},
                        {value: row.outflow, className: 'safecontracts-cash-flow-chart__point--out', label: <?php echo wp_json_encode(__('Outgoing', 'safecontracts')); ?>},
                    ].forEach((point) => {
                        const circle = document.createElementNS(svgNs, 'circle');
                        circle.setAttribute('cx', String(x));
                        circle.setAttribute('cy', String(yAt(point.value)));
                        circle.setAttribute('r', '4.5');
                        circle.setAttribute('class', point.className);
                        const title = document.createElementNS(svgNs, 'title');
                        title.textContent = `${row.date} · ${point.label}: ${formatNumber(point.value)} ${currencyCode}`;
                        circle.appendChild(title);
                        svg.appendChild(circle);
                    });

                    if (index % labelEvery === 0 || index === rows.length - 1) {
                        const dateLabel = document.createElementNS(svgNs, 'text');
                        dateLabel.setAttribute('x', String(x));
                        dateLabel.setAttribute('y', String(height - 18));
                        dateLabel.setAttribute('text-anchor', 'middle');
                        dateLabel.setAttribute('class', 'safecontracts-cash-flow-chart__axis-label');
                        dateLabel.textContent = row.date;
                        svg.appendChild(dateLabel);
                    }
                });

                plot.appendChild(svg);
                currencySection.appendChild(plot);
                chart.appendChild(currencySection);
            });

            // The chart is always the final Finance block. The authoritative
            // table remains visible above it for exact row-level details.
            finance.appendChild(chart);
        })();
        </script>
        <?php
    }
}
