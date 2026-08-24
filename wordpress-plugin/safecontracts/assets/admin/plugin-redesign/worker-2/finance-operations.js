(() => {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const page = params.get('page') || '';
  const status = params.get('safecontracts_status') || '';
  const root = document.querySelector('.safecontracts-settings');

  if (!root) {
    return;
  }

  const lang = (document.documentElement.lang || '').toLowerCase().startsWith('ar') ? 'ar' : 'en';
  const text = (en, ar) => (lang === 'ar' ? ar : en);

  const noticeMessages = {
    'safecontracts-payments': {
      saved: [
        'Payment saved with the contract-authoritative direction and currency.',
        'تم حفظ الدفعة مع الحفاظ على اتجاه الحساب والعملة المعتمدين من العقد.'
      ],
      deleted: [
        'Payment archived. Protected settlement history was not rewritten.',
        'تمت أرشفة الدفعة دون إعادة كتابة سجل التسويات المحمي.'
      ],
      delete_failed: [
        'Payment could not be archived. Payments with settlement history must keep their financial audit trail.',
        'تعذر أرشفة الدفعة. الدفعات التي لها سجل تسويات يجب أن تحتفظ بسجلها المالي.'
      ]
    },
    'safecontracts-collections': {
      saved: [
        'Settlement recorded and the payment balance was reconciled from the authoritative ledger.',
        'تم تسجيل التسوية ومطابقة رصيد الدفعة من دفتر التسويات المعتمد.'
      ],
      deleted: [
        'Settlement reversed safely and the payment balance was reconciled from the remaining active ledger.',
        'تم عكس التسوية بأمان وإعادة مطابقة رصيد الدفعة من سجل التسويات النشط المتبقي.'
      ],
      delete_failed: [
        'Settlement reversal could not be completed. No success state has been assumed.',
        'تعذر إكمال عكس التسوية ولم يتم افتراض نجاح العملية.'
      ]
    },
    'safecontracts-followups': {
      saved: [
        'Follow-up action recorded in the append-only history.',
        'تم تسجيل إجراء المتابعة في السجل الإضافي غير القابل لإعادة الكتابة.'
      ],
      invalid: [
        'Follow-up action was not saved. Review the selected action and required fields.',
        'لم يتم حفظ إجراء المتابعة. راجع الإجراء المختار والحقول المطلوبة.'
      ]
    },
    'safecontracts-imports': {
      uploaded: [
        'Workbook uploaded to private staging. Review discovery and mapping before execution.',
        'تم رفع ملف Excel إلى منطقة التخزين الخاصة. راجع الاكتشاف وربط الأعمدة قبل التنفيذ.'
      ],
      invalid_upload: [
        'Workbook was not accepted. Check file type, size and workbook safety constraints.',
        'لم يتم قبول ملف Excel. تحقق من النوع والحجم وضوابط أمان الملف.'
      ],
      mapped: [
        'Column mapping saved. Preview and server validation remain required before execution.',
        'تم حفظ ربط الأعمدة. ما زالت المعاينة والتحقق على الخادم مطلوبين قبل التنفيذ.'
      ],
      invalid_mapping: [
        'Column mapping was not saved. Required fields must map to valid discovered columns.',
        'لم يتم حفظ ربط الأعمدة. يجب ربط الحقول المطلوبة بأعمدة مكتشفة صحيحة.'
      ],
      execution_failed: [
        'Import execution failed. Review the run summary and row errors; no success state has been assumed.',
        'فشل تنفيذ الاستيراد. راجع ملخص التشغيل وأخطاء الصفوف ولم يتم افتراض نجاح العملية.'
      ]
    }
  };

  const appendNotice = (kind, message) => {
    if (root.querySelector(`.safecontracts-w2-runtime-notice[data-status="${CSS.escape(status)}"]`)) {
      return;
    }
    const notice = document.createElement('div');
    notice.className = `notice notice-${kind} inline safecontracts-w2-runtime-notice`;
    notice.dataset.status = status;
    const paragraph = document.createElement('p');
    paragraph.textContent = message;
    notice.appendChild(paragraph);
    const heading = root.querySelector(':scope > .safecontracts-section-heading');
    if (heading && heading.parentNode) {
      heading.insertAdjacentElement('afterend', notice);
    } else {
      root.prepend(notice);
    }
  };

  const message = noticeMessages[page]?.[status];
  if (message) {
    appendNotice(status.includes('failed') || status.includes('invalid') ? 'error' : 'success', text(message[0], message[1]));
  }

  // WordPress renders these routes synchronously. If the server rendered a
  // table with zero rows, turn that genuinely empty tbody into an accessible
  // empty state instead of manufacturing placeholder records.
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
    strong.textContent = text('No records match the current scope.', 'لا توجد سجلات مطابقة للنطاق الحالي.');
    const detail = document.createElement('span');
    detail.textContent = text('Adjust the available filters or choose another record.', 'عدّل الفلاتر المتاحة أو اختر سجلاً آخر.');
    empty.append(strong, detail);
    cell.appendChild(empty);
  });

  // Client pagination is presentation-only. Every authorized server row stays
  // in the DOM and no monetary value, total, status or direction is recomputed.
  const pageSize = 25;
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
    nav.setAttribute('aria-label', text('Table pagination', 'ترقيم صفحات الجدول'));

    const previous = document.createElement('button');
    previous.type = 'button';
    previous.className = 'button button-small';
    previous.textContent = text('Previous', 'السابق');

    const position = document.createElement('span');
    position.className = 'safecontracts-w2-pagination__position';
    position.setAttribute('aria-live', 'polite');

    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'button button-small';
    next.textContent = text('Next', 'التالي');

    const render = () => {
      const start = (current - 1) * pageSize;
      const end = start + pageSize;
      rows.forEach((row, index) => {
        row.hidden = index < start || index >= end;
      });
      previous.disabled = current === 1;
      next.disabled = current === pages;
      position.textContent = text(
        `Page ${current} of ${pages} · ${rows.length} rows`,
        `صفحة ${current} من ${pages} · ${rows.length} صف`
      );
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
