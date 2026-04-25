<dialog id="echo-ui-modal" class="echo-ui-modal">
    <div class="echo-ui-modal__panel">
        <div id="echo-ui-modal-icon" class="echo-ui-modal__icon" aria-hidden="true">!</div>

        <div class="echo-ui-modal__copy">
            <h2 id="echo-ui-modal-heading" class="echo-ui-modal__heading">Notice</h2>
            <p id="echo-ui-modal-message" class="echo-ui-modal__message"></p>
        </div>

        <div class="echo-ui-modal__actions">
            <button
                id="echo-ui-modal-cancel"
                type="button"
                class="fi-btn fi-btn-color-gray fi-color-custom fi-size-md fi-btn-size-md"
                hidden
            >
                Cancel
            </button>
            <button
                id="echo-ui-modal-confirm"
                type="button"
                class="fi-btn fi-color-custom fi-size-md fi-btn-size-md"
            >
                Close
            </button>
        </div>
    </div>
</dialog>

<script>
document.addEventListener('livewire:init', () => {
    const refreshSidebar = () => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        Livewire.dispatch('refresh-sidebar');
    };

    refreshSidebar();

    window.setInterval(refreshSidebar, 5000);

    document.addEventListener('visibilitychange', refreshSidebar);
    window.addEventListener('focus', refreshSidebar);
});

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('echo-ui-modal');
    const icon = document.getElementById('echo-ui-modal-icon');
    const heading = document.getElementById('echo-ui-modal-heading');
    const message = document.getElementById('echo-ui-modal-message');
    const cancelButton = document.getElementById('echo-ui-modal-cancel');
    const confirmButton = document.getElementById('echo-ui-modal-confirm');

    if (! dialog || ! icon || ! heading || ! message || ! cancelButton || ! confirmButton) {
        return;
    }

    const toneClasses = [
        'echo-ui-modal--danger',
        'echo-ui-modal--success',
        'echo-ui-modal--info',
        'echo-ui-modal--warning',
    ];

    const toneButtonClasses = [
        'fi-btn-color-danger',
        'fi-btn-color-success',
        'fi-btn-color-info',
        'fi-btn-color-warning',
        'fi-btn-color-primary',
    ];

    let activeResolver = null;

    const settleModal = (result) => {
        const resolver = activeResolver;

        activeResolver = null;

        if (dialog.open) {
            dialog.close();
        }

        if (resolver) {
            resolver(result);
        }
    };

    const openModal = (detail = {}) => {
        const tone = detail.tone || 'primary';
        const iconText = detail.icon || (tone === 'success' ? '+' : tone === 'info' ? 'i' : tone === 'warning' ? '!' : '!');
        const buttonLabel = detail.buttonLabel || detail.confirmLabel || 'Close';
        const cancelLabel = detail.cancelLabel || 'Cancel';
        const isConfirm = Boolean(detail.confirm);

        heading.textContent = detail.heading || 'Notice';
        message.innerHTML = detail.messageHtml || '';

        if (! detail.messageHtml) {
            message.textContent = detail.message || '';
        }

        icon.textContent = iconText;
        confirmButton.textContent = buttonLabel;
        cancelButton.textContent = cancelLabel;
        cancelButton.hidden = ! isConfirm;

        dialog.classList.remove(...toneClasses);

        if (tone !== 'primary') {
            dialog.classList.add(`echo-ui-modal--${tone}`);
        }

        confirmButton.classList.remove(...toneButtonClasses);
        confirmButton.classList.add(tone === 'danger' ? 'fi-btn-color-danger' : 'fi-btn-color-primary');

        if (! dialog.open) {
            dialog.showModal();
        }
    };

    const confirmModal = (detail = {}) => new Promise((resolve) => {
        activeResolver = resolve;

        openModal({
            ...detail,
            confirm: true,
            buttonLabel: detail.confirmLabel || 'Confirm',
        });
    });

    window.EchoUiModal = {
        open: openModal,
        confirm: confirmModal,
        close: () => settleModal(false),
    };

    window.addEventListener('echo-modal:open', (event) => openModal(event.detail || {}));
    window.addEventListener('echo-modal:confirm', async (event) => {
        await confirmModal(event.detail || {});
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            settleModal(false);
        }
    });

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        settleModal(false);
    });

    dialog.addEventListener('close', () => {
        if (activeResolver) {
            settleModal(false);
        }
    });

    cancelButton.addEventListener('click', () => settleModal(false));
    confirmButton.addEventListener('click', () => settleModal(true));

    const startUpTimeTimers = new WeakMap();

    const formatUtcNow = () => {
        const now = new Date();

        return `${String(now.getUTCHours()).padStart(2, '0')}${String(now.getUTCMinutes()).padStart(2, '0')}`;
    };

    const getLivewireComponent = (element) => {
        const componentRoot = element.closest('[wire\\:id]');
        const componentId = componentRoot?.getAttribute('wire:id');

        return componentId ? Livewire.find(componentId) : null;
    };

    const clearTimer = (input) => {
        const timer = startUpTimeTimers.get(input);

        if (timer) {
            window.clearTimeout(timer);
            startUpTimeTimers.delete(input);
        }
    };

    const isValidUtcTime = (value) => {
        if (! /^\d{4}$/.test(value)) {
            return false;
        }

        const hours = Number.parseInt(value.slice(0, 2), 10);
        const minutes = Number.parseInt(value.slice(2, 4), 10);

        return hours <= 23 && minutes <= 59;
    };

    const scheduleStartUpConfirmation = (input) => {
        clearTimer(input);

        const value = input.value.trim();

        if (! /^\d{4}$/.test(value) || input.dataset.echoConfirmedValue === value) {
            return;
        }

        const timer = window.setTimeout(async () => {
            if (! document.body.contains(input)) {
                return;
            }

            if (input.value.trim() !== value || input.dataset.echoConfirmedValue === value) {
                return;
            }

            const callsign = input.dataset.callsign || 'this flight';

            if (! isValidUtcTime(value)) {
                window.EchoUiModal.open({
                    heading: 'Invalid UTC Time',
                    message: 'The Start Up Time must be a valid UTC time in 4-digit HHMM format between 0000 and 2359.',
                    tone: 'danger',
                    buttonLabel: 'Cancel',
                });

                input.focus();
                input.select();

                return;
            }

            const confirmed = await window.EchoUiModal.confirm({
                heading: 'Confirm Start Up Time',
                messageHtml: `Is <strong>${value}Z</strong> the correct Start Up Time for <strong>${callsign}</strong>?`,
                tone: 'primary',
                confirmLabel: 'Yes',
                cancelLabel: 'Cancel',
            });

            if (confirmed) {
                input.dataset.echoConfirmedValue = value;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.blur();
            } else {
                input.focus();
                input.select();
            }
        }, 3000);

        startUpTimeTimers.set(input, timer);
    };

    document.addEventListener('input', (event) => {
        const input = event.target.closest('.echo-ready-start-input[data-confirm-startup-time]');

        if (! input) {
            return;
        }

        if (input.dataset.echoConfirmedValue !== input.value.trim()) {
            delete input.dataset.echoConfirmedValue;
        }

        scheduleStartUpConfirmation(input);
    });

    document.addEventListener('change', (event) => {
        const input = event.target.closest('.echo-ready-start-input[data-confirm-startup-time]');

        if (! input) {
            return;
        }

        const value = input.value.trim();

        if (value === '' || input.dataset.echoConfirmedValue === value || ! /^\d{4}$/.test(value)) {
            return;
        }

        event.stopImmediatePropagation();
        event.stopPropagation();
        input.focus();
        scheduleStartUpConfirmation(input);
    }, true);

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('.echo-ready-start-now-trigger[data-record-id]');

        if (! trigger) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const livewire = getLivewireComponent(trigger);

        if (! livewire) {
            return;
        }

        const callsign = trigger.dataset.callsign || 'this flight';
        const utcNow = formatUtcNow();
        const confirmed = await window.EchoUiModal.confirm({
            heading: 'Confirm Start Up Time',
            messageHtml: `Is <strong>${utcNow}Z</strong> the correct Start Up Time for <strong>${callsign}</strong>?`,
            tone: 'primary',
            confirmLabel: 'Yes',
            cancelLabel: 'Cancel',
        });

        if (! confirmed) {
            return;
        }

        await livewire.call('confirmStartUpNow', trigger.dataset.recordId);
    });
});
</script>
