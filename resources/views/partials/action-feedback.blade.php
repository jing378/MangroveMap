{{-- Universal action feedback: Confirmation → Processing → Success or Error. Included in every layout.

     JavaScript (window.AppActions):
       await AppActions.confirm({ title, message, confirmLabel, tone: 'primary' | 'danger' })  → true / false
       AppActions.setBusy(button, 'Saving...') / AppActions.clearBusy(button)
       AppActions.notify('Saved.', 'success' | 'error')
       await AppActions.run({ confirm, button, busyLabel, lock, task, successMessage, errorMessage })
         → { status: 'done', result } | { status: 'cancelled' } | { status: 'failed', error } | { status: 'busy' }
         Asks first (confirm), shows the busy label until task() settles, then shows the success or error
         message and restores the buttons so the user can retry.

     Regular (page-reload) forms opt in with attributes, no script needed:
       <form data-confirm="Delete this user?" data-confirm-message="…" data-confirm-label="Delete"
             data-confirm-tone="danger" data-confirm-icon="bi-trash" data-busy-label="Deleting...">
     The server's flash message reports the result after the reload. Forms that submit with fetch()
     call AppActions.run() from their own submit handler instead. --}}
<style>
    /* A native modal <dialog>: it opens in the top layer, so it also sits above other open <dialog>s
       (e.g. the admin Map Layers and Model Training dialogs). */
    .app-confirm-dialog {
        /* Explicit centering: CSS resets such as Tailwind's preflight (admin layout) zero the native margin:auto. */
        position: fixed;
        inset: 0;
        margin: auto;
        height: fit-content;
        max-height: calc(100% - 32px);
        width: calc(100% - 32px);
        max-width: 360px;
        padding: 0;
        border: 0;
        background: transparent;
        overflow: visible;
        opacity: 0;
        transition: opacity .2s ease;
    }

    .app-confirm-dialog.is-open {
        opacity: 1;
    }

    .app-confirm-dialog::backdrop {
        background: rgba(15, 23, 42, .55);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    .app-confirm-card {
        width: 100%;
        padding: 28px 24px 22px;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, .15), 0 10px 10px -5px rgba(0, 0, 0, .05);
        text-align: center;
        font-family: 'Manrope', sans-serif;
        transform: scale(.95);
        transition: transform .25s cubic-bezier(.16, 1, .3, 1);
    }

    .app-confirm-dialog.is-open .app-confirm-card {
        transform: scale(1);
    }

    .app-confirm-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 52px;
        height: 52px;
        margin: 0 auto 14px;
        border: 2px solid #c8e6d4;
        border-radius: 50%;
        background: #edf7f2;
        color: #1e9e62;
        font-size: 24px;
    }

    .app-confirm-card.is-danger .app-confirm-icon {
        border-color: #f2c2bd;
        background: #fff1f0;
        color: #c0392b;
    }

    .app-confirm-title {
        margin: 0 0 6px;
        color: #1a2e1a;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .app-confirm-message {
        margin: 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .app-confirm-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 20px;
    }

    .app-confirm-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 40px;
        padding: 9px 14px;
        border: 1px solid #d4e0d4;
        border-radius: 10px;
        background: #fff;
        color: #1a2e1a;
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .app-confirm-btn:hover {
        border-color: #1e9e62;
        color: #1e9e62;
    }

    .app-confirm-btn.is-primary {
        border-color: #1e9e62;
        background: #1e9e62;
        color: #fff;
    }

    .app-confirm-btn.is-primary:hover {
        background: #16824f;
        color: #fff;
    }

    .app-confirm-card.is-danger .app-confirm-btn.is-primary {
        border-color: #d04030;
        background: #d04030;
    }

    .app-confirm-card.is-danger .app-confirm-btn.is-primary:hover {
        background: #b42318;
    }

    .app-confirm-btn:focus-visible {
        outline: 3px solid rgba(30, 158, 98, .35);
        outline-offset: 2px;
    }

    /* Busy buttons: spinner follows the text color, so it works on green and light buttons alike. */
    .app-btn-spinner {
        display: inline-block;
        flex: none;
        width: 14px;
        height: 14px;
        margin-right: 6px;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        vertical-align: -2px;
        animation: app-btn-spin .7s linear infinite;
    }

    .app-btn-spinner.is-solo {
        margin-right: 0;
    }

    [data-loading="true"] {
        cursor: wait !important;
    }

    @keyframes app-btn-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Shown as a popover where supported, so messages also appear above open modal dialogs. */
    .app-toast-stack {
        position: fixed;
        inset: 76px 16px auto auto;
        z-index: 100001;
        display: grid;
        gap: 10px;
        width: min(360px, calc(100vw - 32px));
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
        overflow: visible;
        pointer-events: none;
    }

    .app-toast {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 12px 12px 14px;
        border: 1px solid #c8e6d4;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(0, 0, 0, .14);
        color: #1a2e1a;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        line-height: 1.45;
        pointer-events: auto;
        opacity: 0;
        transform: translateY(-6px);
        transition: opacity .2s ease, transform .2s ease;
    }

    .app-toast.is-shown {
        opacity: 1;
        transform: none;
    }

    .app-toast > i {
        flex: none;
        margin-top: 1px;
        color: #1e9e62;
        font-size: 17px;
    }

    .app-toast.is-error {
        border-color: #f2c2bd;
    }

    .app-toast.is-error > i {
        color: #c0392b;
    }

    .app-toast-text {
        flex: 1;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .app-toast-close {
        flex: none;
        width: 24px;
        height: 24px;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: #64748b;
        cursor: pointer;
    }

    .app-toast-close:hover {
        background: #f1f5f2;
    }

    @media (max-width: 768px) {
        .app-toast-stack {
            inset: 64px auto auto 50%;
            transform: translateX(-50%);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .app-confirm-dialog,
        .app-confirm-card,
        .app-toast {
            transition: none;
        }
    }
</style>
<script>
    (() => {
        if (window.AppActions) return;

        const escapeHtml = value => String(value ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

        // ---- Confirmation dialog ---------------------------------------------------------------
        let openDialog = null;

        function confirmAction(options = {}) {
            if (typeof options === 'string') options = { title: options };
            const {
                title = 'Confirm?',
                message = '',
                confirmLabel = 'Confirm',
                cancelLabel = 'Cancel',
                tone = 'primary',
                icon = tone === 'danger' ? 'bi-exclamation-triangle' : 'bi-question-lg',
            } = options;
            openDialog?.close(false);

            return new Promise(resolve => {
                const previousFocus = document.activeElement;
                const dialog = document.createElement('dialog');
                dialog.className = 'app-confirm-dialog';
                dialog.setAttribute('role', 'alertdialog');
                dialog.setAttribute('aria-labelledby', 'appConfirmTitle');
                if (message) dialog.setAttribute('aria-describedby', 'appConfirmMessage');
                dialog.innerHTML = `
                    <div class="app-confirm-card${tone === 'danger' ? ' is-danger' : ''}">
                        <div class="app-confirm-icon" aria-hidden="true"><i class="bi ${escapeHtml(icon)}"></i></div>
                        <h2 class="app-confirm-title" id="appConfirmTitle">${escapeHtml(title)}</h2>
                        ${message ? `<p class="app-confirm-message" id="appConfirmMessage">${escapeHtml(message)}</p>` : ''}
                        <div class="app-confirm-actions">
                            <button type="button" class="app-confirm-btn" data-answer="no">${escapeHtml(cancelLabel)}</button>
                            <button type="button" class="app-confirm-btn is-primary" data-answer="yes">${escapeHtml(confirmLabel)}</button>
                        </div>
                    </div>`;

                const buttons = [...dialog.querySelectorAll('[data-answer]')];
                const close = answer => {
                    if (openDialog?.dialog !== dialog) return;
                    openDialog = null;
                    dialog.classList.remove('is-open');
                    if (dialog.open) dialog.close();
                    setTimeout(() => dialog.remove(), 200);
                    if (previousFocus instanceof HTMLElement && document.contains(previousFocus)) previousFocus.focus({ preventScroll: true });
                    resolve(answer);
                };

                // Escape fires "cancel".
                dialog.addEventListener('cancel', event => {
                    event.preventDefault();
                    close(false);
                });
                dialog.addEventListener('click', event => {
                    if (event.target === dialog) {
                        close(false); // click on the backdrop
                        return;
                    }
                    const answer = event.target.closest('[data-answer]')?.dataset.answer;
                    if (answer) close(answer === 'yes');
                });
                document.body.appendChild(dialog);
                openDialog = { dialog, close };
                dialog.showModal();
                requestAnimationFrame(() => dialog.classList.add('is-open'));
                // Destructive actions start on Cancel so Enter does not delete by accident.
                buttons[tone === 'danger' ? 0 : 1].focus();
            });
        }

        // ---- Busy buttons ----------------------------------------------------------------------
        // Icon-only buttons (no visible text) keep their size: spinner only, the label goes to the tooltip.
        const isIconOnly = button => !button.textContent.trim();

        function setBusy(button, label = 'Processing...') {
            if (!(button instanceof HTMLElement) || button.dataset.loading === 'true') return;
            button.dataset.loading = 'true';
            button.dataset.idleHtml = button.innerHTML;
            button.dataset.idleDisabled = button.disabled ? 'true' : 'false';
            if (isIconOnly(button)) {
                button.dataset.idleTitle = button.getAttribute('title') ?? '';
                button.innerHTML = '<span class="app-btn-spinner is-solo" aria-hidden="true"></span>';
                button.setAttribute('title', label);
                button.setAttribute('aria-label', label);
            } else {
                button.innerHTML = `<span class="app-btn-spinner" aria-hidden="true"></span>${escapeHtml(label)}`;
            }
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        function clearBusy(button) {
            if (!(button instanceof HTMLElement) || button.dataset.loading !== 'true') return;
            button.innerHTML = button.dataset.idleHtml ?? button.innerHTML;
            button.disabled = button.dataset.idleDisabled === 'true';
            button.setAttribute('aria-busy', 'false');
            if ('idleTitle' in button.dataset) {
                if (button.dataset.idleTitle) button.setAttribute('title', button.dataset.idleTitle);
                else button.removeAttribute('title');
                button.removeAttribute('aria-label');
                delete button.dataset.idleTitle;
            }
            delete button.dataset.loading;
            delete button.dataset.idleHtml;
            delete button.dataset.idleDisabled;
        }

        // Other controls that must not be used while the request runs; returns a function that restores them.
        function lockControls(controls) {
            const locked = [...new Set((controls || []).filter(Boolean))].filter(control => !control.disabled);
            locked.forEach(control => { control.disabled = true; });
            return () => locked.forEach(control => { control.disabled = false; });
        }

        // ---- Success / error messages ----------------------------------------------------------
        let toastStack = null;
        const supportsPopover = typeof HTMLElement.prototype.showPopover === 'function';

        function notify(message, type = 'success', { timeout } = {}) {
            if (!message) return;
            if (!toastStack || !document.body.contains(toastStack)) {
                toastStack = document.createElement('div');
                toastStack.className = 'app-toast-stack';
                toastStack.setAttribute('aria-live', 'polite');
                if (supportsPopover) toastStack.setAttribute('popover', 'manual');
                document.body.appendChild(toastStack);
            }
            // Re-show on every message so the stack is above any modal dialog opened since.
            if (supportsPopover) {
                if (toastStack.matches(':popover-open')) toastStack.hidePopover();
                toastStack.showPopover();
            }
            const isError = type === 'error';
            const toast = document.createElement('div');
            toast.className = `app-toast${isError ? ' is-error' : ''}`;
            toast.setAttribute('role', isError ? 'alert' : 'status');
            toast.innerHTML = `<i class="bi ${isError ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'}" aria-hidden="true"></i>
                <div class="app-toast-text">${escapeHtml(message)}</div>
                <button type="button" class="app-toast-close" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>`;
            const dismiss = () => {
                toast.classList.remove('is-shown');
                setTimeout(() => {
                    toast.remove();
                    if (supportsPopover && !toastStack.childElementCount && toastStack.matches(':popover-open')) toastStack.hidePopover();
                }, 200);
            };
            toast.querySelector('.app-toast-close').addEventListener('click', dismiss);
            toastStack.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('is-shown'));
            setTimeout(dismiss, timeout ?? (isError ? 8000 : 4500));
        }

        // ---- Confirmation → Processing → Success or Error ----------------------------------------
        async function run({
            confirm = null,
            button = null,
            busyLabel = 'Processing...',
            lock = [],
            task,
            successMessage = null,
            errorMessage = 'Something went wrong. Please try again.',
        } = {}) {
            if (button?.dataset.loading === 'true') return { status: 'busy' };
            if (confirm && !(await confirmAction(confirm))) return { status: 'cancelled' };

            setBusy(button, busyLabel);
            const unlock = lockControls(lock);
            try {
                const result = await task();
                unlock();
                clearBusy(button);
                const text = typeof successMessage === 'function' ? successMessage(result) : successMessage;
                if (text) notify(text, 'success');
                return { status: 'done', result };
            } catch (error) {
                unlock();
                clearBusy(button);
                if (errorMessage !== false) {
                    const text = typeof errorMessage === 'function' ? errorMessage(error) : (error?.message || errorMessage);
                    notify(text, 'error');
                }
                return { status: 'failed', error };
            }
        }

        // ---- Regular forms: data-confirm / data-busy-label ---------------------------------------
        document.addEventListener('submit', async event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
            // An empty data-confirm means "no question this time" (e.g. a first AI run); the busy state still applies.
            const asks = Boolean(form.dataset.confirm);
            if (!asks && !form.hasAttribute('data-busy-label')) return;
            if (form.dataset.actionConfirmed === 'true') {
                // Second pass from requestSubmit below: let the browser submit it.
                delete form.dataset.actionConfirmed;
                return;
            }
            event.preventDefault();
            if (form.dataset.actionPending === 'true') return;

            const submitter = event.submitter || form.querySelector('[type="submit"]');
            if (asks) {
                form.dataset.actionPending = 'true';
                const ok = await confirmAction({
                    title: form.dataset.confirm,
                    message: form.dataset.confirmMessage || '',
                    confirmLabel: form.dataset.confirmLabel || 'Confirm',
                    tone: form.dataset.confirmTone || 'primary',
                    ...(form.dataset.confirmIcon ? { icon: form.dataset.confirmIcon } : {}),
                });
                delete form.dataset.actionPending;
                if (!ok) return;
            }

            form.dataset.actionConfirmed = 'true';
            // Submit first (the submitter's name/value is only sent while it is enabled), then show the busy state.
            if (submitter && typeof form.requestSubmit === 'function') form.requestSubmit(submitter);
            else form.submit();
            if (form.dataset.actionConfirmed === 'true') delete form.dataset.actionConfirmed;
            setBusy(submitter, form.dataset.busyLabel || 'Processing...');
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(other => {
                if (other === submitter || other.disabled) return;
                other.disabled = true;
                other.dataset.actionLocked = 'true';
            });
        });

        // Back/forward cache restores the page as it was left: un-stick buttons that were busy.
        window.addEventListener('pageshow', event => {
            if (!event.persisted) return;
            document.querySelectorAll('[data-loading="true"]').forEach(clearBusy);
            document.querySelectorAll('[data-action-locked="true"]').forEach(button => {
                button.disabled = false;
                delete button.dataset.actionLocked;
            });
        });

        window.AppActions = { confirm: confirmAction, setBusy, clearBusy, notify, run, lockControls, escapeHtml };
    })();
</script>
