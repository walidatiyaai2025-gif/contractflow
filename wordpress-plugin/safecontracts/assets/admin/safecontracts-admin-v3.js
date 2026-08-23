(() => {
  'use strict';

  const ready = (fn) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
    else fn();
  };

  const numeric = (text) => {
    const cleaned = String(text || '').replace(/[^0-9.,-]/g, '').replace(/,/g, '');
    const value = Number.parseFloat(cleaned);
    return Number.isFinite(value) ? Math.abs(value) : 0;
  };

  const enhanceCashFlow = () => {
    const finance = document.querySelector('.safecontracts-finance');
    if (!finance) return;
    const sections = Array.from(finance.querySelectorAll('.safecontracts-table-card'));
    const section = sections.find((candidate) => {
      const heading = candidate.querySelector('h2');
      const text = (heading?.textContent || '').toLowerCase();
      return text.includes('cash flow') || text.includes('cashflow') || text.includes('تدفق') || text.includes('التدفق');
    });
    if (!section || section.querySelector('.safecontracts-cashflow-chart')) return;

    const rows = Array.from(section.querySelectorAll('tbody tr')).map((tr) => {
      const cells = Array.from(tr.querySelectorAll('td'));
      if (cells.length < 4) return null;
      return {
        date: (cells[0].textContent || '').trim(),
        flow: (cells[1].textContent || '').trim(),
        currency: (cells[2].textContent || '').trim() || '—',
        amount: numeric(cells[3].textContent),
      };
    }).filter(Boolean);
    if (!rows.length) return;

    const maxima = {};
    rows.forEach((row) => { maxima[row.currency] = Math.max(maxima[row.currency] || 0, row.amount); });

    const chart = document.createElement('div');
    chart.className = 'safecontracts-cashflow-chart';
    chart.setAttribute('role', 'img');
    chart.setAttribute('aria-label', document.documentElement.dir === 'rtl' ? 'رسم بياني للتدفق المالي حسب العملة' : 'Cash-flow chart by currency');

    const title = document.createElement('div');
    title.className = 'safecontracts-cashflow-chart__title';
    title.innerHTML = document.documentElement.dir === 'rtl'
      ? '<strong>التدفق المالي المتوقع</strong><small>كل عملة تُقاس بشكل مستقل · بدون جمع العملات</small>'
      : '<strong>Expected cash flow</strong><small>Each currency scales independently · no cross-currency total</small>';
    chart.appendChild(title);

    const bars = document.createElement('div');
    bars.className = 'safecontracts-cashflow-chart__bars';
    rows.slice(0, 90).forEach((row) => {
      const maximum = maxima[row.currency] || 1;
      const height = Math.max(6, Math.round((row.amount / maximum) * 100));
      const item = document.createElement('div');
      item.className = 'safecontracts-cashflow-chart__item';
      item.title = `${row.date} · ${row.flow} · ${row.currency} · ${row.amount}`;
      const bar = document.createElement('span');
      bar.className = 'safecontracts-cashflow-chart__bar';
      const flow = row.flow.toLowerCase();
      if (flow.includes('out') || flow.includes('pay') || flow.includes('صرف') || flow.includes('خارج')) bar.classList.add('is-outflow');
      bar.style.height = `${height}%`;
      const label = document.createElement('small');
      label.textContent = `${row.date.slice(5)}\n${row.currency}`;
      item.append(bar, label);
      bars.appendChild(item);
    });
    chart.appendChild(bars);
    const table = section.querySelector('table');
    if (table) section.insertBefore(chart, table);
  };

  const explainContractCover = () => {
    const page = new URLSearchParams(window.location.search).get('page');
    if (page !== 'safecontracts-contracts') return;
    const input = document.querySelector('input[type="file"][name^="safecontracts_files"]');
    if (!input || document.querySelector('.safecontracts-contract-cover-help')) return;
    const note = document.createElement('p');
    note.className = 'description safecontracts-contract-cover-help';
    note.textContent = document.documentElement.dir === 'rtl'
      ? 'يمكن رفع صورة للعقد هنا. أول صورة مرفوعة تُستخدم كصورة غلاف العقد في واجهة الموبايل الجديدة؛ وإذا لم توجد صورة يُستخدم شعار الشركة تلقائيًا.'
      : 'You can upload a contract image here. The first uploaded image is used as the new mobile contract cover; when no image exists, the company logo is used automatically.';
    input.insertAdjacentElement('afterend', note);
  };

  ready(() => {
    enhanceCashFlow();
    explainContractCover();
  });
})();
