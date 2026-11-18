{{-- Device notifications come back after logging in again. Logging out removes this browser's subscription
     (AppServiceProvider, so the next person on a shared device doesn't get them), but the browser remembers per
     account that the user turned them on (localStorage "mangrovemap.push.wanted", set by partials/device-push).
     When that user is back and the browser still allows notifications, this quietly subscribes again: no prompt,
     no tap. Only rendered while this session has no subscription yet (session push_endpoint). --}}
@php
    $restoreUser = auth()->user();
    $restorePush = $restoreUser
        && ! session()->has('push_endpoint')
        && \App\Notifications\Channels\DevicePushChannel::configured();
@endphp
@if($restorePush)
<script>
    (() => {
        const userId = @json((string) $restoreUser->getAuthIdentifier());
        let wanted = {};
        try { wanted = JSON.parse(localStorage.getItem('mangrovemap.push.wanted') || '{}') || {}; } catch (e) { return; }
        if (!wanted[userId]) return;
        if (!window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window)
            || !('Notification' in window) || Notification.permission !== 'granted') return;

        const publicKey = @json(config('webpush.vapid.public_key'));
        const storeUrl = @json(route('push-subscriptions.store'));
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const keyToBytes = base64 => {
            const padded = (base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
            return Uint8Array.from(atob(padded), ch => ch.charCodeAt(0));
        };

        (async () => {
            try {
                await navigator.serviceWorker.register('/sw.js');
                const reg = await navigator.serviceWorker.ready;
                // Permission is already granted, so subscribing shows no prompt.
                const subscription = await reg.pushManager.getSubscription()
                    || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(publicKey) });
                const json = subscription.toJSON();
                const encodings = PushManager.supportedContentEncodings || ['aesgcm'];
                const response = await fetch(storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        endpoint: json.endpoint,
                        keys: json.keys,
                        contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
                    }),
                });
                if (response.ok) {
                    // The Notifications page card (partials/device-push) switches to "on".
                    window.dispatchEvent(new CustomEvent('device-push-restored', { detail: { endpoint: json.endpoint } }));
                }
            } catch (e) {
                // Not restored this time (offline, push service unreachable): the card still offers "Turn on".
            }
        })();
    })();
</script>
@endif
