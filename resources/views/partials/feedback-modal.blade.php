{{-- "Send Feedback" pop-up, opened by any [data-feedback-open] button (the sidebar item).
     Included in the enduser and admin layouts; posts to feedback.store with fetch() so the user
     keeps whatever they were doing on the page (e.g. a half-drawn delineation). --}}
<style>
    /* The sidebar item is a <button>: match the <a> sidebar items. */
    button.sidebar-item {
        width: calc(100% - 16px);
        border-top: 0;
        border-right: 0;
        border-bottom: 0;
        background: transparent;
        font-family: inherit;
        text-align: left;
        cursor: pointer;
    }

    .feedback-dialog {
        /* Explicit centering: Tailwind's preflight (admin layout) zeroes the native margin:auto. */
        position: fixed;
        inset: 0;
        margin: auto;
        height: fit-content;
        max-height: calc(100% - 32px);
        width: calc(100% - 32px);
        max-width: 460px;
        padding: 0;
        border: 0;
        border-radius: 16px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1a2e1a, #1a2e1a);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, .15), 0 10px 10px -5px rgba(0, 0, 0, .05);
        font-family: 'Manrope', sans-serif;
        overflow: auto;
    }

    .feedback-dialog::backdrop {
        background: rgba(15, 23, 42, .55);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    .feedback-form {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 22px 22px 20px;
    }

    .feedback-head {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .feedback-head-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
        font-size: 18px;
    }

    .feedback-title {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
    }

    .feedback-subtitle {
        margin: 2px 0 0;
        color: var(--fg-64748b, #64748b);
        font-size: 12px;
        line-height: 1.45;
    }

    .feedback-close {
        margin-left: auto;
        width: 30px;
        height: 30px;
        flex: 0 0 30px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--fg-64748b, #64748b);
        cursor: pointer;
    }

    .feedback-close:hover {
        background: var(--bg-f1f5f2, #f1f5f2);
    }

    .feedback-label {
        display: block;
        margin-bottom: 6px;
        font-size: 12px;
        font-weight: 700;
    }

    .feedback-optional {
        color: #7a9a7a;
        font-weight: 500;
    }

    .feedback-types {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }

    .feedback-type input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .feedback-type span {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 10px;
        color: var(--fg-3a5a3a, #3a5a3a);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .feedback-type span:hover {
        border-color: #1e9e62;
    }

    .feedback-type input:checked + span {
        border-color: #1e9e62;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-145a33, #145a33);
    }

    .feedback-type input:focus-visible + span {
        outline: 3px solid rgba(30, 158, 98, .35);
        outline-offset: 1px;
    }

    .feedback-textarea,
    .feedback-file {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 10px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1a2e1a, #1a2e1a);
        font: inherit;
        font-size: 13px;
    }

    .feedback-textarea {
        min-height: 120px;
        resize: vertical;
        line-height: 1.5;
    }

    .feedback-textarea:focus,
    .feedback-file:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, .12);
    }

    .feedback-file {
        padding: 8px;
        font-size: 12px;
    }

    .feedback-hint {
        margin-top: 4px;
        color: #7a9a7a;
        font-size: 11px;
    }

    .feedback-error {
        margin-top: 4px;
        color: var(--fg-c0392b, #c0392b);
        font-size: 11px;
        font-weight: 600;
    }

    .feedback-error:empty {
        display: none;
    }

    .feedback-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 4px;
    }

    .feedback-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 40px;
        padding: 9px 14px;
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 10px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1a2e1a, #1a2e1a);
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .feedback-btn:hover {
        border-color: #1e9e62;
        color: var(--fg-1e9e62, #1e9e62);
    }

    .feedback-btn.is-primary {
        border-color: #1e9e62;
        background: #1e9e62;
        color: #fff;
    }

    .feedback-btn.is-primary:hover {
        background: #16824f;
        color: #fff;
    }
</style>

<dialog class="feedback-dialog" id="feedbackDialog" aria-labelledby="feedbackTitle">
    <form class="feedback-form" id="feedbackForm" novalidate>
        <div class="feedback-head">
            <div class="feedback-head-icon" aria-hidden="true"><i class="bi bi-chat-left-text"></i></div>
            <div>
                <h2 class="feedback-title" id="feedbackTitle">Send Feedback</h2>
                <p class="feedback-subtitle">Found a problem or have an idea? Tell us. The admins will review it.</p>
            </div>
            <button type="button" class="feedback-close" data-feedback-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>

        <fieldset style="border:0;padding:0;margin:0;">
            <legend class="feedback-label">What kind of feedback?</legend>
            <div class="feedback-types">
                @foreach ([
                    'bug' => ['bi-bug', 'Bug / Problem'],
                    'suggestion' => ['bi-lightbulb', 'Suggestion'],
                    'question' => ['bi-question-circle', 'Question'],
                    'other' => ['bi-three-dots', 'Other'],
                ] as $value => [$icon, $label])
                    <label class="feedback-type">
                        <input type="radio" name="type" value="{{ $value }}" @checked($loop->first)>
                        <span><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <div class="feedback-error" data-error-for="type"></div>
        </fieldset>

        <div>
            <label class="feedback-label" for="feedbackMessage">Your feedback</label>
            <textarea class="feedback-textarea" id="feedbackMessage" name="message" maxlength="5000" required
                placeholder="Describe what happened or what you'd like to see. If it's a problem, include what you clicked."></textarea>
            <div class="feedback-error" data-error-for="message"></div>
        </div>

        <div>
            <label class="feedback-label" for="feedbackScreenshot">Screenshot <span class="feedback-optional">(optional)</span></label>
            <input class="feedback-file" type="file" id="feedbackScreenshot" name="screenshot" accept="image/png,image/jpeg,image/webp">
            <div class="feedback-hint">JPG, PNG or WebP, up to 5 MB. The page you're on is attached automatically.</div>
            <div class="feedback-error" data-error-for="screenshot"></div>
        </div>

        <div class="feedback-actions">
            <button type="button" class="feedback-btn" data-feedback-close>Cancel</button>
            <button type="submit" class="feedback-btn is-primary"><i class="bi bi-send"></i> Send</button>
        </div>
    </form>
</dialog>

<script>
    (() => {
        const dialog = document.getElementById('feedbackDialog');
        const form = document.getElementById('feedbackForm');
        if (!dialog || !form) return;

        const submitButton = form.querySelector('[type="submit"]');
        const clearErrors = () => form.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; });

        const open = () => {
            document.querySelector('.sidebar')?.classList.remove('active'); // mobile slide-out menu
            clearErrors();
            dialog.showModal();
            form.querySelector('#feedbackMessage').focus();
        };
        const close = () => dialog.close();

        document.addEventListener('click', event => {
            if (event.target.closest('[data-feedback-open]')) {
                event.preventDefault();
                open();
            } else if (event.target.closest('[data-feedback-close]')) {
                close();
            }
        });
        // Click on the backdrop closes it.
        dialog.addEventListener('click', event => {
            if (event.target === dialog) close();
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            clearErrors();

            const message = form.message.value.trim();
            if (message.length < 5) {
                form.querySelector('[data-error-for="message"]').textContent = 'Please describe your feedback (at least 5 characters).';
                form.message.focus();
                return;
            }

            const data = new FormData(form);
            data.set('page_url', window.location.href);

            const outcome = await AppActions.run({
                button: submitButton,
                busyLabel: 'Sending...',
                lock: [...form.querySelectorAll('[data-feedback-close]')],
                errorMessage: error => error.message || 'Your feedback could not be sent. Please try again.',
                task: async () => {
                    const response = await fetch(@json(route('feedback.store')), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: data,
                    });
                    const body = await response.json().catch(() => ({}));
                    if (response.status === 422 && body.errors) {
                        Object.entries(body.errors).forEach(([field, messages]) => {
                            const target = form.querySelector(`[data-error-for="${field}"]`);
                            if (target) target.textContent = messages[0];
                        });
                        throw new Error('Please check the form and try again.');
                    }
                    if (response.status === 429) throw new Error('You are sending feedback too quickly. Please wait a minute.');
                    if (!response.ok) throw new Error(body.message || 'Your feedback could not be sent. Please try again.');
                    return body;
                },
                successMessage: body => body.message || 'Thank you! Your feedback was sent.',
            });

            if (outcome.status === 'done') {
                form.reset();
                close();
            }
        });
    })();
</script>
