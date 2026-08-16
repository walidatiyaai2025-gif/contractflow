(function () {
    'use strict';

    const messages = window.SafeContractsAdminFeedback || {};

    function ensureStack() {
        let stack = document.querySelector('[data-safecontracts-toast-stack]');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'safecontracts-toast-stack';
            stack.dataset.safecontractsToastStack = '';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'true');
            document.body.appendChild(stack);
        }
        return stack;
    }

    function closeToast(toast) {
        if (!toast) return;
        toast.classList.add('is-leaving');
        window.setTimeout(function () { toast.remove(); }, 180);
    }

    function showToast(type, title, message, autoDismiss) {
        const stack = ensureStack();
        const toast = document.createElement('div');
        toast.className = 'safecontracts-toast safecontracts-toast--' + type;
        toast.dataset.safecontractsToast = '';
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

        const icon = document.createElement('div');
        icon.className = 'safecontracts-toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = type === 'error' ? '!' : '✓';

        const body = document.createElement('div');
        body.className = 'safecontracts-toast__body';
        const strong = document.createElement('strong');
        strong.textContent = title || '';
        const paragraph = document.createElement('p');
        paragraph.textContent = message || '';
        body.appendChild(strong);
        body.appendChild(paragraph);

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'safecontracts-toast__close';
        close.dataset.safecontractsToastClose = '';
        close.setAttribute('aria-label', messages.closeLabel || 'Close message');
        close.textContent = '×';

        toast.appendChild(icon);
        toast.appendChild(body);
        toast.appendChild(close);
        stack.appendChild(toast);
        close.addEventListener('click', function () { closeToast(toast); });
        if (autoDismiss) window.setTimeout(function () { closeToast(toast); }, 5200);
        return toast;
    }

    document.querySelectorAll('[data-safecontracts-toast]').forEach(function (toast) {
        const close = toast.querySelector('[data-safecontracts-toast-close]');
        if (close) close.addEventListener('click', function () { closeToast(toast); });
        if (toast.dataset.autoDismiss === '1') {
            window.setTimeout(function () { closeToast(toast); }, 5200);
        }
    });

    function fieldLabel(field) {
        if (!field) return '';
        if (field.id) {
            const explicit = document.querySelector('label[for="' + CSS.escape(field.id) + '"]');
            if (explicit) return explicit.textContent.trim().replace(/\s+/g, ' ');
        }
        const parentLabel = field.closest('label');
        if (parentLabel) return parentLabel.textContent.trim().replace(/\s+/g, ' ');
        return field.getAttribute('aria-label') || field.name || '';
    }

    document.querySelectorAll('form[method="post"]').forEach(function (form) {
        if (!form.closest('.safecontracts-settings, .safecontracts-admin-shell, .safecontracts-dashboard')) return;

        form.noValidate = true;
        form.addEventListener('submit', function (event) {
            form.querySelectorAll('.safecontracts-field-invalid').forEach(function (field) {
                field.classList.remove('safecontracts-field-invalid');
                field.removeAttribute('aria-invalid');
            });

            const invalid = Array.from(form.querySelectorAll('input, select, textarea')).filter(function (field) {
                return !field.disabled && !field.checkValidity();
            });
            if (invalid.length > 0) {
                event.preventDefault();
                invalid.forEach(function (field) {
                    field.classList.add('safecontracts-field-invalid');
                    field.setAttribute('aria-invalid', 'true');
                });
                const first = invalid[0];
                const label = fieldLabel(first);
                const detail = label
                    ? (messages.validationMessage || '') + ' ' + (messages.fieldPrefix || '') + ' ' + label
                    : (messages.validationMessage || '');
                showToast('error', messages.validationTitle || 'Check the form', detail, false);
                first.focus({ preventScroll: false });
                return;
            }

            if (form.matches('[data-safecontracts-delete-form]')) {
                const confirmation = form.dataset.deleteMessage || messages.deleteConfirm || 'Delete this record?';
                if (!window.confirm(confirmation)) event.preventDefault();
            }
        });

        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            ['input', 'change'].forEach(function (eventName) {
                field.addEventListener(eventName, function () {
                    if (field.checkValidity()) {
                        field.classList.remove('safecontracts-field-invalid');
                        field.removeAttribute('aria-invalid');
                    }
                });
            });
        });
    });
})();
