(() => {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const page = params.get('page') || '';
  const root = document.querySelector('.safecontracts-settings');
  const ui = window.safecontractsWorker2Ui || {};

  if (!root) {
    return;
  }

  // WordPress renders these routes synchronously. For the financial workspaces,
  // convert a genuinely empty server tbody into a translated empty state. The
  // text is provided by wp_localize_script/gettext in PHP; JavaScript owns no
  // parallel Arabic/English dictionary.
  const emptyStateRoutes = new Set([
    'safecontracts-payments',
    'safecontracts-collections',
    'safecontracts-followups',
    'safecontracts-finance',
    'safecontracts-reports'
  ]);
  if (emptyStateRoutes.has(page) && ui.emptyScope) {
    root.querySelectorAll('.safecontracts-table-card table.widefat').forEach((table) => {
      const tbody = table.tBodies[0];
      if (!tbody || tbody.rows.length !== 0) {
        return;
      }
      const row = tbody.insertRow();
      row.className = 'safecontracts-w2-empty-row';
      const cell = row.insertCell();
      cell.colSpan = Math.max(1, table.tHead?.rows[0]?.cells.length || 1);
      const empty = document.createElement('div');
      empty.className = 'safecontracts-w2-empty';
      const strong = document.createElement('strong');
      strong.textContent = ui.emptyScope;
      empty.append(strong);
      cell.appendChild(empty);
    });
  }

  // Client pagination is presentation-only. Every authorized server row stays
  // in the DOM and no monetary value, total, status or direction is recomputed.
  // Symbols and numerals are intentionally language-neutral; arrow direction
  // flips with the actual WordPress document direction.
  const pageSize = 25;
  const rtl = (document.documentElement.dir || '').toLowerCase() === 'rtl'
    || window.getComputedStyle(document.body).direction === 'rtl';

  root.querySelectorAll('.safecontracts-table-card table.widefat').forEach((table) => {
    const tbody = table.tBodies[0];
    if (!tbody) {
      return;
    }
    const rows = Array.from(tbody.rows).filter((row) => !row.classList.contains('safecontracts-w2-empty-row'));
    if (rows.length <= pageSize) {
      return;
    }

    let current = 1;
    const pages = Math.ceil(rows.length / pageSize);
    const nav = document.createElement('nav');
    nav.className = 'safecontracts-w2-pagination';

    const previous = document.createElement('button');
    previous.type = 'button';
    previous.className = 'button button-small';
    previous.textContent = rtl ? '›' : '‹';

    const position = document.createElement('span');
    position.className = 'safecontracts-w2-pagination__position';
    position.setAttribute('aria-live', 'polite');

    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'button button-small';
    next.textContent = rtl ? '‹' : '›';

    const render = () => {
      const start = (current - 1) * pageSize;
      const end = start + pageSize;
      rows.forEach((row, index) => {
        row.hidden = index < start || index >= end;
      });
      previous.disabled = current === 1;
      next.disabled = current === pages;
      position.textContent = `${current} / ${pages} · ${rows.length}`;
    };

    previous.addEventListener('click', () => {
      if (current > 1) {
        current -= 1;
        render();
        table.scrollIntoView({block: 'nearest'});
      }
    });
    next.addEventListener('click', () => {
      if (current < pages) {
        current += 1;
        render();
        table.scrollIntoView({block: 'nearest'});
      }
    });

    nav.append(previous, position, next);
    table.insertAdjacentElement('afterend', nav);
    render();
  });

  // Follow-up operations are fixed by the backend. Progressive disclosure only
  // hides irrelevant date inputs; it does not create new actions or mutations.
  if (page === 'safecontracts-followups') {
    const operation = root.querySelector('select[name="followup_operation"]');
    const promised = root.querySelector('input[name="promised_date"]');
    const deferred = root.querySelector('input[name="deferred_until"]');
    const toggleFollowUpDates = () => {
      if (!operation) {
        return;
      }
      if (promised?.closest('label')) {
        promised.closest('label').hidden = operation.value !== 'promise';
      }
      if (deferred?.closest('label')) {
        deferred.closest('label').hidden = operation.value !== 'defer';
      }
    };
    operation?.addEventListener('change', toggleFollowUpDates);
    toggleFollowUpDates();
  }
})();
