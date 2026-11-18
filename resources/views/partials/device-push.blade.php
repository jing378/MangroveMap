{{--
    Device notifications (Web Push) for the current browser: a card at the top of the Notifications page.
    Server side: PushSubscriptionController + App\Notifications\Channels\DevicePushChannel.
--}}
@php
$pushUser = auth()->user();
$pushConfigured = \App\Notifications\Channels\DevicePushChannel::configured();
@endphp
@if($pushUser)
<section class="push-card is-checking" id="device-notifications" aria-labelledby="pushStatusTitle"
    data-configured="{{ $pushConfigured ? '1' : '0' }}"
    data-public-key="{{ $pushConfigured ? config('webpush.vapid.public_key') : '' }}"
    data-endpoints='@json($pushUser->pushSubscriptions()->pluck('endpoint'))'
    data-user-id="{{ $pushUser->getAuthIdentifier() }}"
    data-store-url="{{ route('push-subscriptions.store') }}"
    data-destroy-url="{{ route('push-subscriptions.destroy') }}">
    <div class="push-card-icon" aria-hidden="true"><i class="bi bi-phone-vibrate"></i></div>
    <div class="push-card-text" role="status" aria-live="polite">
        <strong id="pushStatusTitle">Get these on your phone or computer</strong>
        <span id="pushStatusText">Checking this device...</span>
    </div>
    <div class="push-card-actions">
        <button type="button" class="push-btn is-primary" id="pushToggle" hidden>
            <i class="bi bi-bell"></i> Turn on
        </button>
    </div>
</section>

@push('styles')
<style>
    .push-card {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        margin-bottom: 20px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 12px;
        background: var(--bg-ffffff, #ffffff);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .push-card-icon {
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
    }

    .push-card-text {
        flex: 1 1 auto;
        min-width: 0;
        font-size: 13px;
        line-height: 1.45;
    }

    .push-card-text strong {
        display: block;
        font-size: 14px;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .push-card-text span {
        display: block;
        color: var(--fg-6b8a78, #6b8a78);
    }

    .push-card-text span:empty {
        display: none;
    }

    .push-card-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        flex: 0 0 auto;
    }

    .push-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid var(--bd-c8e6d4, #c8e6d4);
        border-radius: 8px;
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-1e9e62, #1e9e62);
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.15s;
    }

    .push-btn:hover {
        background: var(--bg-edf7f2, #edf7f2);
        border-color: #1e9e62;
    }

    .push-btn.is-primary {
        background: #1e9e62;
        border-color: #1e9e62;
        color: #fff;
    }

    .push-btn.is-primary:hover {
        background: #16a34a;
        border-color: #16a34a;
    }

    .push-btn[hidden] {
        display: none;
    }

    /* On: a quieter one-line card so it doesn't push the list down. */
    .push-card.is-on {
        padding: 10px 14px;
        background: var(--bg-f5f7f6, #f5f7f6);
        box-shadow: none;
    }

    .push-card.is-on .push-card-icon {
        flex-basis: 32px;
        width: 32px;
        height: 32px;
        font-size: 16px;
    }

    .push-card.is-on .push-card-text strong {
        font-size: 13px;
        font-weight: 700;
    }

    .push-card.is-on .push-btn {
        padding: 6px 12px;
        font-size: 12px;
    }

    .push-card.is-blocked {
        background: var(--bg-fdf5e8, #fdf5e8);
        border-color: var(--bd-e8cc98, #e8cc98);
    }

    .push-card.is-blocked .push-card-icon {
        background: var(--bg-f8e6c4, #f8e6c4);
        color: var(--fg-925a12, #925a12);
    }

    .push-card.is-blocked .push-card-text span {
        color: var(--fg-925a12, #925a12);
    }

    @media (max-width: 600px) {
        .push-card {
            flex-wrap: wrap;
            align-items: flex-start;
        }

        .push-card-text {
            flex-basis: calc(100% - 54px);
        }

        .push-card-actions {
            width: 100%;
        }

        .push-card-actions .push-btn {
            flex: 1 1 auto;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Device notifications (Web Push): turn them on/off for this browser.
    (function () {
        const card = document.getElementById('device-notifications');
        if (!card) return;

        const statusTitle = document.getElementById('pushStatusTitle');
        const statusText = document.getElementById('pushStatusText');
        const icon = card.querySelector('.push-card-icon .bi');
        const toggle = document.getElementById('pushToggle');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const publicKey = card.dataset.publicKey;
        let serverEndpoints = [];
        try { serverEndpoints = JSON.parse(card.dataset.endpoints || '[]'); } catch (e) { /* none */ }

        // Remembered per account in this browser, so logging in again turns them back on by itself
        // (partials/device-push-restore). Turning them off here forgets it.
        const WANTED_KEY = 'mangrovemap.push.wanted';
        const userId = card.dataset.userId;
        function rememberChoice(on) {
            try {
                const wanted = JSON.parse(localStorage.getItem(WANTED_KEY) || '{}') || {};
                if (on) wanted[userId] = 1; else delete wanted[userId];
                localStorage.setItem(WANTED_KEY, JSON.stringify(wanted));
            } catch (e) { /* storage unavailable: they just need turning on again after logging in */ }
        }

        const icons = { 'is-on': 'bi-bell-fill', 'is-off': 'bi-phone-vibrate', 'is-blocked': 'bi-exclamation-triangle', 'is-checking': 'bi-phone-vibrate' };
        const blockedHelp = 'Allow notifications for this site in your browser\'s site settings, then reload this page.';

        function setStatus(state, title, text = '') {
            card.className = 'push-card ' + state;
            icon.className = 'bi ' + (icons[state] || 'bi-bell');
            statusTitle.textContent = title;
            statusText.textContent = text;
        }

        function setButtons(subscribed) {
            toggle.hidden = false;
            toggle.dataset.on = subscribed ? '1' : '0';
            toggle.className = 'push-btn' + (subscribed ? '' : ' is-primary');
            toggle.innerHTML = subscribed
                ? '<i class="bi bi-bell-slash"></i> Turn off'
                : '<i class="bi bi-bell"></i> Turn on';
        }

        function showOn() {
            setStatus('is-on', 'Notifications are on for this device');
            setButtons(true);
        }

        function showOff() {
            setStatus('is-off', 'Get these on your phone or computer',
                'A pop-up for each new notification, even when MangroveMap is closed.');
            setButtons(false);
        }

        function showBlocked(title, text) {
            setStatus('is-blocked', title, text);
            toggle.hidden = true;
        }

        function keyToBytes(base64) {
            const padded = (base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(padded);
            return Uint8Array.from(raw, ch => ch.charCodeAt(0));
        }

        async function send(url, method, body) {
            const response = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: body ? JSON.stringify(body) : undefined,
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'The server could not save this. Please try again.');
            }
            return response.json().catch(() => ({}));
        }

        async function registration() {
            await navigator.serviceWorker.register('/sw.js');
            return navigator.serviceWorker.ready;
        }

        const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isInstalled = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        async function init() {
            if (card.dataset.configured !== '1') {
                return showBlocked('Phone notifications are not available yet', 'They are not set up on this server.');
            }
            if (!window.isSecureContext) {
                return showBlocked('Phone notifications need the secure address', 'Open MangroveMap through its https:// address to turn them on.');
            }
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                return isIos && !isInstalled
                    ? showBlocked('Add MangroveMap to your Home Screen first', 'On iPhone or iPad, tap Share, then "Add to Home Screen". Open MangroveMap from the new icon and turn notifications on here.')
                    : showBlocked('This browser cannot show phone notifications', 'Try Chrome, Edge, Firefox or Samsung Internet.');
            }
            if (Notification.permission === 'denied') {
                return showBlocked('Notifications are blocked for this site', blockedHelp);
            }

            try {
                const reg = await registration();
                const subscription = await reg.pushManager.getSubscription();
                if (subscription && Notification.permission === 'granted' && serverEndpoints.includes(subscription.endpoint)) {
                    rememberChoice(true); // also for those who turned them on before this was remembered
                    showOn();
                } else {
                    showOff();
                }
            } catch (e) {
                showOff();
            }
        }

        async function subscribeOnBrowser(reg) {
            const options = { userVisibleOnly: true, applicationServerKey: keyToBytes(publicKey) };
            let subscription = await reg.pushManager.getSubscription();
            if (!subscription) {
                subscription = await reg.pushManager.subscribe(options);
            } else {
                // Re-subscribe if the server's key changed since this browser subscribed.
                try {
                    subscription = await reg.pushManager.subscribe(options);
                } catch (e) {
                    await subscription.unsubscribe();
                    subscription = await reg.pushManager.subscribe(options);
                }
            }
            return subscription;
        }

        async function turnOn() {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                if (permission === 'denied') {
                    showBlocked('Notifications are blocked for this site', blockedHelp);
                }
                throw new Error('Notifications were not allowed.');
            }

            const reg = await registration();
            let subscription;
            try {
                subscription = await subscribeOnBrowser(reg);
            } catch (e) {
                // Chromium's "Registration failed - push service error": the browser could not reach its push
                // service (Brave has it off by default; some networks, VPNs and ad-blocking DNS block it).
                if (e && (e.name === 'AbortError' || /push service/i.test(e.message || ''))) {
                    const brave = navigator.brave && typeof navigator.brave.isBrave === 'function';
                    throw new Error(brave
                        ? 'Brave blocks this by default. Open brave://settings/privacy, turn on "Use Google services for push messaging", restart Brave and try again.'
                        : 'Your browser could not reach its notification service. Try another network (some Wi-Fi, VPNs and ad blockers block it) or another browser such as Chrome.');
                }
                throw e;
            }

            const encodings = PushManager.supportedContentEncodings || ['aesgcm'];
            const json = subscription.toJSON();
            await send(card.dataset.storeUrl, 'POST', {
                endpoint: json.endpoint,
                keys: json.keys,
                contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
            });
            serverEndpoints.push(json.endpoint);
            rememberChoice(true);
        }

        async function turnOff() {
            const reg = await registration();
            const subscription = await reg.pushManager.getSubscription();
            if (subscription) {
                await send(card.dataset.destroyUrl, 'DELETE', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
                serverEndpoints = serverEndpoints.filter(endpoint => endpoint !== subscription.endpoint);
            }
            rememberChoice(false);
        }

        toggle.addEventListener('click', async () => {
            const turningOn = toggle.dataset.on !== '1';
            let render = null;
            AppActions.setBusy(toggle, turningOn ? 'Turning on...' : 'Turning off...');
            try {
                await (turningOn ? turnOn() : turnOff());
                render = turningOn ? showOn : showOff;
                AppActions.notify(turningOn ? 'Notifications turned on for this device.' : 'Notifications turned off for this device.', 'success');
            } catch (e) {
                AppActions.notify(e.message || 'Something went wrong. Please try again.', 'error');
            } finally {
                // clearBusy restores the old label, so the new one is drawn after it.
                AppActions.clearBusy(toggle);
                if (render) render();
            }
        });

        // Turned back on by itself after logging in (partials/device-push-restore).
        window.addEventListener('device-push-restored', event => {
            if (event.detail?.endpoint) serverEndpoints.push(event.detail.endpoint);
            showOn();
        });

        init();
    })();
</script>
@endpush
@endif
