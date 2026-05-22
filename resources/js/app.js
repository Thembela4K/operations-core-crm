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
