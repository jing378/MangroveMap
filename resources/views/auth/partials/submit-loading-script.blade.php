            {{-- Disables an auth form's submit button and shows a spinner while the request is sent, so it can't be
                 submitted twice (e.g. several reset or verification emails). Inlined into a page <script>.
                 Params: formId, buttonId, busyLabel. --}}
            (() => {
                const form = document.getElementById(@json($formId));
                const button = document.getElementById(@json($buttonId));
                if (!form || !button) return;
                const label = button.querySelector('.submit-label');
                const idleLabel = label ? label.textContent : '';
                const setSubmitting = submitting => {
                    button.disabled = submitting;
                    button.classList.toggle('is-loading', submitting);
                    button.setAttribute('aria-busy', submitting ? 'true' : 'false');
                    if (label) label.textContent = submitting ? @json($busyLabel) : idleLabel;
                };
                form.addEventListener('submit', () => setSubmitting(true));
                // Reset if the page is restored from the back/forward cache.
                window.addEventListener('pageshow', event => {
                    if (event.persisted) setSubmitting(false);
                });
            })();
