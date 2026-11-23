{{--
  "Send reminder" dialog for the admin dashboard and Map Layers (AdminReminderController, AdminReminderSender).
  Any element with data-reminder opens it:
    data-reminder-kind="pending_reviews|stale_drafts|delineation|drone_layer"  data-reminder-target="<id>" (one record only)
    data-title="Remind the experts"  data-who="Every expert (3) gets …"  (shown in the dialog)
  Clicks are delegated on document, so buttons in lists swapped in place (Map Layers) keep working.
--}}
<dialog class="reminder-dialog" id="reminderDialog" aria-labelledby="reminderDialogTitle">
    <form method="POST" action="{{ route('admin.reminders.store') }}" data-busy-label="Sending...">
        @csrf
        <input type="hidden" name="kind" id="reminderKind">
        <input type="hidden" name="target_id" id="reminderTarget">
        <div class="reminder-head">
            <span class="reminder-icon" aria-hidden="true"><i class="bi bi-alarm"></i></span>
            <div>
                <h3 id="reminderDialogTitle">Send reminder</h3>
                <p id="reminderWho"></p>
            </div>
        </div>
        <label for="reminderNote">Note <span class="reminder-optional">(optional, added to the reminder)</span></label>
        <textarea id="reminderNote" name="note" rows="3" maxlength="500" placeholder="e.g. Please finish these before Friday's report."></textarea>
        <p class="reminder-help"><i class="bi bi-info-circle" aria-hidden="true"></i> Sent to their notifications, by email, and to their phone or computer if they turned that on. The same reminder can be sent once a day.</p>
        <div class="reminder-actions">
            <button type="button" class="reminder-cancel" data-close-reminder>Cancel</button>
            <button type="submit" class="reminder-send"><i class="bi bi-send" aria-hidden="true"></i> Send reminder</button>
        </div>
    </form>
</dialog>

<style>
    .reminder-dialog {
        /* Centred: the admin layout's "* { margin: 0 }" reset would pin a <dialog> to the top-left corner. */
        margin: auto;
        width: min(440px, calc(100vw - 32px));
        padding: 0;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-1a2e1a, #1a2e1a);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    }

    .reminder-dialog::backdrop {
        background: rgba(12, 24, 16, 0.45);
    }

    .reminder-dialog form {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 20px;
        font-family: 'Manrope', sans-serif;
    }

    .reminder-head {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 6px;
    }

    .reminder-icon {
        flex: none;
        display: grid;
        place-items: center;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
        font-size: 18px;
    }

    .reminder-head h3 {
        margin: 0 0 3px;
        font-size: 16px;
        font-weight: 800;
    }

    .reminder-head p {
        margin: 0;
        font-size: 12.5px;
        line-height: 1.45;
        color: var(--fg-4a6a4a, #4a6a4a);
    }

    .reminder-dialog label {
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-3a5a3a, #3a5a3a);
    }

    .reminder-optional {
        font-weight: 500;
        color: #7a9a7a;
    }

    .reminder-dialog textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 11px;
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 8px;
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-1a2e1a, #1a2e1a);
        font: inherit;
        font-size: 13px;
        resize: vertical;
    }

    .reminder-dialog textarea:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.12);
    }

    .reminder-help {
        margin: 2px 0 6px;
        font-size: 11.5px;
        line-height: 1.45;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .reminder-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .reminder-actions button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 16px;
        border-radius: 8px;
        font: inherit;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
    }

    .reminder-cancel {
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-3a5a3a, #3a5a3a);
    }

    .reminder-cancel:hover {
        background: var(--bg-f5f7f6, #f5f7f6);
    }

    .reminder-send {
        border: 1px solid #1e9e62;
        background: #1e9e62;
        color: #fff;
    }

    .reminder-send:hover {
        background: #17834f;
    }

    /* The buttons that open it: "Send reminder" on the dashboard, a bell in Map Layers rows. */
    .reminder-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        border: 1px solid var(--bd-b0e0c0, #b0e0c0);
        border-radius: 8px;
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-1e9e62, #1e9e62);
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        cursor: pointer;
    }

    .reminder-btn:hover:not(:disabled) {
        border-color: #1e9e62;
        background: var(--bg-edf7f2, #edf7f2);
    }

    .reminder-btn:disabled {
        cursor: not-allowed;
        color: #7a9a7a;
        border-color: var(--bd-e0e8e0, #e0e8e0);
        font-weight: 600;
    }

    @media (max-width: 600px) {
        .reminder-actions button {
            flex: 1 1 0;
            justify-content: center;
            min-height: 42px;
        }

        .reminder-dialog {
            margin: auto 0 0;
            width: 100%;
            max-width: 100%;
            border-radius: 16px 16px 0 0;
        }
    }
</style>

<script>
    (function () {
        const dialog = document.getElementById('reminderDialog');
        if (!dialog) return;

        document.addEventListener('click', event => {
            const opener = event.target.closest('[data-reminder]');
            if (!opener || opener.disabled) return;
            event.preventDefault();
            event.stopPropagation();
            document.getElementById('reminderKind').value = opener.dataset.reminderKind || '';
            document.getElementById('reminderTarget').value = opener.dataset.reminderTarget || '';
            document.getElementById('reminderDialogTitle').textContent = opener.dataset.title || 'Send reminder';
            document.getElementById('reminderWho').textContent = opener.dataset.who || '';
            document.getElementById('reminderNote').value = '';
            dialog.showModal();
            document.getElementById('reminderNote').focus();
        });

        dialog.querySelector('[data-close-reminder]').addEventListener('click', () => dialog.close());
        // A click on the dimmed area outside closes it.
        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });
    })();
</script>
