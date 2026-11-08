{{-- "Install App" item at the bottom of the sidebar, just above the version (resident, expert and admin layouts).
     Hidden until the device can install the app, and never shown inside the installed app:
     - Chrome / Edge / Android: the browser's own install prompt (beforeinstallprompt).
     - iPhone / iPad: Apple has no install prompt, so it explains "Share → Add to Home Screen".
     Browsers only allow installing over HTTPS (or localhost). --}}
<div class="sidebar-section sidebar-install-section" id="installAppSection" hidden>
    <button type="button" class="sidebar-item sidebar-install" id="installAppBtn">
        <i class="bi bi-download" aria-hidden="true"></i>
        <span class="sidebar-install-text">
            <strong>Install App</strong>
            <small>Open MangroveMap from your home screen</small>
        </span>
    </button>
</div>

<dialog class="app-confirm-dialog install-help-dialog" id="installHelpDialog" aria-labelledby="installHelpTitle">
    <div class="app-confirm-card">
        <div class="app-confirm-icon" aria-hidden="true"><i class="bi bi-phone"></i></div>
        <h2 class="app-confirm-title" id="installHelpTitle">Install on your iPhone or iPad</h2>
        <ol class="install-help-steps">
            <li>Tap the <strong>Share</strong> button <i class="bi bi-box-arrow-up" aria-label="Share icon"></i> in Safari's toolbar.</li>
            <li>Scroll down and tap <strong>Add to Home Screen</strong> <i class="bi bi-plus-square" aria-hidden="true"></i>.</li>
            <li>Tap <strong>Add</strong>. MangroveMap appears on your home screen like an app.</li>
        </ol>
        <div class="app-confirm-actions install-help-actions">
            <button type="button" class="app-confirm-btn is-primary" data-install-help-close>Got it</button>
        </div>
    </div>
</dialog>

<style>
    /* Sits just above the version line at the bottom of the sidebar: while it is shown it takes the
       version's margin-top: auto, so the card, version and profile stay together at the bottom. */
    .sidebar-install-section {
        margin-top: auto;
        margin-bottom: 0;
        padding-top: 12px;
    }

    /* Card, version and profile packed closely together at the bottom. "~", not "+": this partial's
       dialog, style and script elements sit between the card and the version line. */
    .sidebar-install-section:not([hidden]) ~ .sidebar-version {
        margin-top: 0;
        padding-top: 4px;
        padding-bottom: 4px;
    }

    .sidebar-install-section[hidden] {
        display: none !important;
    }

    .sidebar-item.sidebar-install {
        width: calc(100% - 16px);
        border: 1px solid var(--bd-b0e0c0, #b0e0c0);
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-176d46, #176d46);
        font: inherit;
        text-align: left;
        cursor: pointer;
    }

    .sidebar-item.sidebar-install:hover {
        background: var(--bg-e0f2e8, #e0f2e8);
        color: var(--fg-125a39, #125a39);
    }

    .sidebar-item.sidebar-install:focus-visible {
        outline: 3px solid rgba(30, 158, 98, 0.35);
        outline-offset: 2px;
    }

    .sidebar-install-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.3;
    }

    .sidebar-install-text strong {
        font-size: 13px;
        font-weight: 700;
    }

    .sidebar-install-text small {
        font-size: 11px;
        font-weight: 500;
        opacity: 0.85;
    }

    .install-help-steps {
        margin: 10px 0 0;
        padding-left: 20px;
        text-align: left;
        color: var(--fg-3a5a3a, #3a5a3a);
        font-size: 13px;
        line-height: 1.55;
    }

    .install-help-steps li + li {
        margin-top: 6px;
    }

    .install-help-actions {
        grid-template-columns: 1fr;
    }

    .install-help-dialog::backdrop {
        background: rgba(15, 23, 42, .55);
    }
</style>

<script>
    (() => {
        const section = document.getElementById('installAppSection');
        const button = document.getElementById('installAppBtn');
        const help = document.getElementById('installHelpDialog');
        if (!section || !button) return;

        const isInstalled = () => window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: window-controls-overlay)').matches
            || window.navigator.standalone === true;
        // iPadOS reports itself as a Mac, so also check for a touch screen.
        const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        let installPrompt = null;
        const show = visible => { section.hidden = !visible; };

        if ('serviceWorker' in navigator && window.isSecureContext) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => { /* the app still works without it */ });
            });
        }

        if (isInstalled()) return; // already running as the installed app

        window.addEventListener('beforeinstallprompt', event => {
            event.preventDefault(); // offer it from the sidebar instead of the browser's mini bar
            installPrompt = event;
            show(true);
        });

        window.addEventListener('appinstalled', () => {
            installPrompt = null;
            show(false);
            window.AppActions?.notify('MangroveMap was installed on this device.');
        });

        // iPhone / iPad: no install prompt exists, so the item opens the steps instead.
        if (isIos && window.isSecureContext) show(true);

        button.addEventListener('click', async () => {
            if (installPrompt) {
                const promptEvent = installPrompt;
                installPrompt = null;
                promptEvent.prompt();
                const { outcome } = await promptEvent.userChoice;
                // A dismissed prompt cannot be shown again on this page; the browser offers it again later.
                if (outcome !== 'accepted') show(false);
                return;
            }
            if (isIos && help) {
                help.showModal();
                requestAnimationFrame(() => help.classList.add('is-open'));
            }
        });

        help?.addEventListener('click', event => {
            if (event.target === help || event.target.closest('[data-install-help-close]')) {
                help.classList.remove('is-open');
                help.close();
            }
        });
    })();
</script>
