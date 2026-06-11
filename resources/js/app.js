import './assistant';

const sidebarStorageKey = 'operations-core-crm-sidebar-collapsed';

const applySidebarState = (collapsed) => {
    document.body.classList.toggle('sidebar-is-collapsed', collapsed);

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        button.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    });
};

document.addEventListener('DOMContentLoaded', () => {
    applySidebarState(localStorage.getItem(sidebarStorageKey) === '1');
});

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sidebar-toggle]');

    if (! toggle) {
        return;
    }

    const collapsed = ! document.body.classList.contains('sidebar-is-collapsed');
    localStorage.setItem(sidebarStorageKey, collapsed ? '1' : '0');
    applySidebarState(collapsed);
});

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');

    if (! toggle) {
        return;
    }

    const wrapper = toggle.closest('.password-input-wrap');
    const input = wrapper?.querySelector('[data-password-input]');
    const openIcon = toggle.querySelector('[data-eye-open]');
    const closedIcon = toggle.querySelector('[data-eye-closed]');

    if (! input) {
        return;
    }

    const showingPassword = input.type === 'text';
    input.type = showingPassword ? 'password' : 'text';
    toggle.setAttribute('aria-label', showingPassword ? 'Show password' : 'Hide password');
    openIcon?.classList.toggle('hidden', ! showingPassword);
    closedIcon?.classList.toggle('hidden', showingPassword);
});

const syncAssigneeOptions = () => {
    const departmentSelect = document.querySelector('[data-department-select]');
    const assigneeSelect = document.querySelector('[data-assignee-select]');
    const emailInput = document.querySelector('[data-assignee-email]');

    if (! departmentSelect || ! assigneeSelect || ! emailInput) {
        return;
    }

    const selectedDepartmentId = departmentSelect.value;
    const departmentEmail = departmentSelect.selectedOptions[0]?.dataset.email || '';

    Array.from(assigneeSelect.options).forEach((option) => {
        const optionDepartmentId = option.dataset.departmentId || '';
        const isPlaceholder = option.value === '';
        const isVisible = isPlaceholder || optionDepartmentId === selectedDepartmentId;

        option.hidden = ! isVisible;
        option.disabled = ! isVisible;
    });

    const selectedOption = assigneeSelect.selectedOptions[0];
    const selectedOptionVisible = selectedOption && ! selectedOption.disabled;

    if (! selectedOptionVisible) {
        assigneeSelect.value = '';
    }

    const activeOption = assigneeSelect.selectedOptions[0];
    emailInput.value = activeOption?.dataset.notificationEmail || departmentEmail;
};

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-department-select], [data-assignee-select]')) {
        syncAssigneeOptions();
    }
});

document.addEventListener('DOMContentLoaded', syncAssigneeOptions);

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-dismiss]').forEach((message) => {
        setTimeout(() => {
            message.classList.add('is-dismissing');
            setTimeout(() => message.remove(), 550);
        }, 4500);
    });
});

const pluralize = (value, label) => `${value} ${label}${value === 1 ? '' : 's'}`;

const syncCountdown = (element) => {
    const targetDate = new Date(element.dataset.countdownTarget);

    if (Number.isNaN(targetDate.getTime())) {
        return;
    }

    const remaining = targetDate.getTime() - Date.now();
    const isOverdue = remaining < 0;
    const absolute = Math.abs(remaining);
    const days = Math.floor(absolute / 86400000);
    const hours = Math.floor((absolute % 86400000) / 3600000);
    const minutes = Math.floor((absolute % 3600000) / 60000);
    const seconds = Math.floor((absolute % 60000) / 1000);
    let summary;

    if (isOverdue) {
        summary = days > 0
            ? `Overdue by ${pluralize(days, 'day')}`
            : hours > 0
                ? `Overdue by ${pluralize(hours, 'hour')}`
                : 'Overdue by less than 1 hour';
    } else {
        summary = days > 0
            ? `${pluralize(days, 'day')} ${pluralize(hours, 'hour')} remaining`
            : hours > 0
                ? `${pluralize(hours, 'hour')} ${pluralize(minutes, 'minute')} remaining`
                : `${pluralize(minutes, 'minute')} remaining`;
    }

    element.classList.toggle('is-overdue', isOverdue);
    element.querySelector('[data-countdown-value]').textContent = summary;
    element.querySelector('[data-countdown-days]').textContent = String(days).padStart(2, '0');
    element.querySelector('[data-countdown-hours]').textContent = String(hours).padStart(2, '0');
    element.querySelector('[data-countdown-minutes]').textContent = String(minutes).padStart(2, '0');
    element.querySelector('[data-countdown-seconds]').textContent = String(seconds).padStart(2, '0');
};

const syncCountdowns = () => {
    document.querySelectorAll('[data-countdown-target]').forEach(syncCountdown);
};

document.addEventListener('DOMContentLoaded', () => {
    syncCountdowns();

    if (document.querySelector('[data-countdown-target]')) {
        setInterval(syncCountdowns, 1000);
    }
});

const openDocumentPreview = (button) => {
    const viewer = button.closest('[data-document-preview]');
    const modal = viewer?.querySelector('[data-document-preview-modal]');
    const frame = viewer?.querySelector('[data-document-preview-frame]');
    const title = viewer?.querySelector('[data-document-preview-title]');
    const download = viewer?.querySelector('[data-document-preview-download]');

    if (! viewer || ! modal || ! frame || ! title || ! download || ! button.dataset.previewUrl) {
        return;
    }

    title.textContent = button.dataset.title || 'Document';
    download.href = button.dataset.downloadUrl || '#';
    frame.src = button.dataset.previewUrl;
    modal.hidden = false;
    document.body.classList.add('modal-open');
};

const closeDocumentPreview = (target) => {
    const modal = target.closest('[data-document-preview-modal]');
    const frame = modal?.querySelector('[data-document-preview-frame]');

    if (! modal) {
        return;
    }

    frame.removeAttribute('src');
    modal.hidden = true;
    document.body.classList.remove('modal-open');
};

document.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-document-preview-open]');

    if (openButton) {
        openDocumentPreview(openButton);

        return;
    }

    const closeButton = event.target.closest('[data-document-preview-close]');

    if (closeButton) {
        closeDocumentPreview(closeButton);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    document.querySelectorAll('[data-document-preview-modal]:not([hidden])').forEach((modal) => {
        closeDocumentPreview(modal);
    });
});

const money = (value) => Number(value || 0).toFixed(2);

const reindexRequisitionRows = (wrapper) => {
    wrapper.querySelectorAll('[data-requisition-line-row]').forEach((row, index) => {
        row.querySelectorAll('[name^="items["]').forEach((input) => {
            input.name = input.name.replace(/items\[\d+]/, `items[${index}]`);
        });
    });
};

const syncRequisitionTotals = (wrapper) => {
    let total = 0;
    let bankTotal = 0;
    let cashTotal = 0;
    let otherTotal = 0;

    wrapper.querySelectorAll('[data-requisition-line-row]').forEach((row) => {
        const quantity = Number(row.querySelector('[data-requisition-quantity]')?.value || 0);
        const unitCost = Number(row.querySelector('[data-requisition-unit-cost]')?.value || 0);
        const paymentType = row.querySelector('[data-requisition-payment-type]')?.value || 'Cash';
        const lineTotal = quantity * unitCost;
        total += lineTotal;

        if (paymentType === 'Bank') {
            bankTotal += lineTotal;
        } else if (paymentType === 'Cash' || paymentType === 'Revenue') {
            cashTotal += lineTotal;
        } else {
            otherTotal += lineTotal;
        }

        row.querySelector('[data-requisition-line-total]').textContent = money(lineTotal);
    });

    wrapper.querySelector('[data-requisition-bank-total]').textContent = money(bankTotal);
    wrapper.querySelector('[data-requisition-cash-total]').textContent = money(cashTotal);
    wrapper.querySelector('[data-requisition-other-total]').textContent = money(otherTotal);
    wrapper.querySelector('[data-requisition-total]').textContent = money(total);
};

const syncAllRequisitionTotals = () => {
    document.querySelectorAll('[data-requisition-items]').forEach((wrapper) => {
        reindexRequisitionRows(wrapper);
        syncRequisitionTotals(wrapper);
    });
};

const addRequisitionRow = (wrapper) => {
    const body = wrapper.querySelector('[data-requisition-line-body]');
    const source = body?.querySelector('[data-requisition-line-row]');

    if (! body || ! source) {
        return;
    }

    const clone = source.cloneNode(true);
    clone.querySelectorAll('input, textarea, select').forEach((input) => {
        if (input.matches('[data-requisition-payment-type]')) {
            input.value = 'Cash';
            return;
        }

        if (input.matches('[data-requisition-quantity]')) {
            input.value = '1';
            return;
        }

        if (input.matches('[data-requisition-unit-cost]')) {
            input.value = '0';
            return;
        }

        input.value = '';
    });

    body.appendChild(clone);
    reindexRequisitionRows(wrapper);
    syncRequisitionTotals(wrapper);
};

document.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-requisition-line-add]');

    if (addButton) {
        addRequisitionRow(addButton.closest('[data-requisition-items]'));
        return;
    }

    const removeButton = event.target.closest('[data-requisition-line-remove]');

    if (removeButton) {
        const wrapper = removeButton.closest('[data-requisition-items]');
        const rows = wrapper.querySelectorAll('[data-requisition-line-row]');

        if (rows.length > 1) {
            removeButton.closest('[data-requisition-line-row]').remove();
            reindexRequisitionRows(wrapper);
            syncRequisitionTotals(wrapper);
        }
    }
});

document.addEventListener('input', (event) => {
    const wrapper = event.target.closest('[data-requisition-items]');

    if (wrapper) {
        syncRequisitionTotals(wrapper);
    }
});

document.addEventListener('change', (event) => {
    const wrapper = event.target.closest('[data-requisition-items]');

    if (wrapper) {
        syncRequisitionTotals(wrapper);
    }
});

const reindexLineRows = (wrapper) => {
    wrapper.querySelectorAll('[data-line-item-row]').forEach((row, index) => {
        row.querySelectorAll('[name^="items["]').forEach((input) => {
            input.name = input.name.replace(/items\[\d+]/, `items[${index}]`);
        });
    });
};

const syncLineTotals = (wrapper) => {
    const vatRate = Number(wrapper.dataset.vatRate || 15) / 100;
    let subtotal = 0;
    let vatTotal = 0;
    let total = 0;

    wrapper.querySelectorAll('[data-line-item-row]').forEach((row) => {
        const quantity = Number(row.querySelector('[data-line-quantity]')?.value || 0);
        const unitPrice = Number(row.querySelector('[data-line-unit-price]')?.value || 0);
        const discount = Number(row.querySelector('[data-line-discount]')?.value || 0);
        const taxable = row.querySelector('[data-line-taxable]')?.checked || false;
        const gross = quantity * unitPrice;
        const lineSubtotal = Math.max(0, gross - discount);
        const lineVat = taxable ? lineSubtotal * vatRate : 0;
        const lineTotal = lineSubtotal + lineVat;

        subtotal += lineSubtotal;
        vatTotal += lineVat;
        total += lineTotal;

        row.querySelector('[data-line-total]').textContent = money(lineTotal);
        row.querySelector('[data-line-vat]').textContent = `VAT ${money(lineVat)}`;
    });

    wrapper.querySelector('[data-lines-subtotal]').textContent = money(subtotal);
    wrapper.querySelector('[data-lines-vat]').textContent = money(vatTotal);
    wrapper.querySelector('[data-lines-total]').textContent = money(total);
};

const syncAllLineTotals = () => {
    document.querySelectorAll('[data-line-items]').forEach((wrapper) => {
        reindexLineRows(wrapper);
        syncLineTotals(wrapper);
    });
};

const addLineRow = (wrapper) => {
    const body = wrapper.querySelector('[data-line-items-body]');
    const source = body?.querySelector('[data-line-item-row]');

    if (! body || ! source) {
        return;
    }

    const clone = source.cloneNode(true);
    clone.querySelectorAll('input, textarea, select').forEach((input) => {
        if (input.matches('[type="checkbox"]')) {
            input.checked = true;
            return;
        }

        if (input.matches('[type="hidden"]')) {
            input.value = '0';
            return;
        }

        if (input.matches('[data-line-quantity]')) {
            input.value = '1';
            return;
        }

        if (input.matches('[data-line-unit-price], [data-line-discount]')) {
            input.value = '0';
            return;
        }

        input.value = '';
    });

    body.appendChild(clone);
    reindexLineRows(wrapper);
    syncLineTotals(wrapper);
};

document.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-line-add]');

    if (addButton) {
        addLineRow(addButton.closest('[data-line-items]'));
        return;
    }

    const removeButton = event.target.closest('[data-line-remove]');

    if (removeButton) {
        const wrapper = removeButton.closest('[data-line-items]');
        const rows = wrapper.querySelectorAll('[data-line-item-row]');

        if (rows.length > 1) {
            removeButton.closest('[data-line-item-row]').remove();
            reindexLineRows(wrapper);
            syncLineTotals(wrapper);
        }
    }
});

document.addEventListener('change', (event) => {
    const catalogSelect = event.target.closest('[data-catalog-select]');

    if (catalogSelect) {
        const option = catalogSelect.selectedOptions[0];
        const row = catalogSelect.closest('[data-line-item-row]');

        if (option?.value) {
            row.querySelector('[data-line-description]').value = option.dataset.description || option.textContent.trim();
            row.querySelector('[data-line-unit-price]').value = option.dataset.price || '0';
            row.querySelector('[data-line-taxable]').checked = option.dataset.taxable === '1';
        }
    }

    if (event.target.closest('[data-line-items]')) {
        syncLineTotals(event.target.closest('[data-line-items]'));
    }
});

document.addEventListener('input', (event) => {
    const wrapper = event.target.closest('[data-line-items]');

    if (wrapper) {
        syncLineTotals(wrapper);
    }
});

let draggedLineRow = null;

document.addEventListener('dragstart', (event) => {
    draggedLineRow = event.target.closest('[data-line-item-row]');
});

document.addEventListener('dragover', (event) => {
    const targetRow = event.target.closest('[data-line-item-row]');

    if (! draggedLineRow || ! targetRow || draggedLineRow === targetRow) {
        return;
    }

    event.preventDefault();
    const bounds = targetRow.getBoundingClientRect();
    const after = event.clientY > bounds.top + bounds.height / 2;
    targetRow.parentNode.insertBefore(draggedLineRow, after ? targetRow.nextSibling : targetRow);
});

document.addEventListener('dragend', () => {
    if (draggedLineRow) {
        const wrapper = draggedLineRow.closest('[data-line-items]');
        reindexLineRows(wrapper);
        syncLineTotals(wrapper);
    }

    draggedLineRow = null;
});

document.addEventListener('DOMContentLoaded', () => {
    syncAllRequisitionTotals();
    syncAllLineTotals();
});
