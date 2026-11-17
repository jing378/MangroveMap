{{--
    Live notifications: while a page is open and visible, asks notifications.poll every 20 s.
    - keeps the sidebar bell badge current (the mobile menu dot follows it, see partials/mobile-menu-badge);
    - pops up new notifications with AppActions.notify (title, message and a View link);
    - on the Notifications page, shows a "N new notifications · Show" bar instead (opens page 1 again).
    Pauses while the tab is hidden and checks right away when it comes back. Stops if the session ends.
    Included in the enduser and admin layouts, after partials/action-feedback.
--}}
@auth
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const pollUrl = @json(route('notifications.poll'));
        const notificationsUrl = @json(route('notifications.index', [], false));
        const intervalMs = 20000;
        let since = @json(now()->toIso8601String());
        let timer = null;
        let inFlight = false;
        let stopped = false;
        let lastPoll = Date.now();
        let failures = 0;
        const seen = new Set();
        const onNotificationsPage = !!document.querySelector('.notifications-page');

        const safeUrl = url => (typeof url === 'string' && url.startsWith('/') && !url.startsWith('//')) ? url : notificationsUrl;

        function setUnread(count) {
            const link = document.getElementById('sidebarNotificationsLink');
            if (link) {
                let badge = link.querySelector('.sidebar-notification-badge');
                link.classList.toggle('has-unread', count > 0);
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'sidebar-notification-badge';
                        link.querySelector('.sidebar-notification-icon')?.appendChild(badge);
                    }
                    if (badge && badge.textContent.trim() !== String(count)) {
                        badge.textContent = count;
                        badge.setAttribute('aria-label', count + ' unread notifications');
                    }
                } else {
                    badge?.remove();
                }
            }
            const pageCount = document.getElementById('unreadCountText');
            if (pageCount && count > 0) pageCount.textContent = count;
        }

        function showNewBar(added) {
            let bar = document.getElementById('liveNotificationsBar');
            if (!bar) {
                bar = document.createElement('button');
                bar.type = 'button';
                bar.id = 'liveNotificationsBar';
                bar.className = 'live-notifications-bar';
                bar.dataset.count = '0';
                bar.addEventListener('click', () => { window.location.href = notificationsUrl; }); // page 1, where the new ones are
                const anchor = document.querySelector('.notifications-page .notifications-header');
                anchor ? anchor.after(bar) : document.querySelector('.notifications-page')?.prepend(bar);
            }
            const total = Number(bar.dataset.count) + added;
            bar.dataset.count = String(total);
            bar.innerHTML = '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i> '
                + total + ' new notification' + (total === 1 ? '' : 's') + ' <span>Show</span>';
        }

        function announce(fresh) {
            if (onNotificationsPage) {
                showNewBar(fresh.length);
                return;
            }
            if (!window.AppActions) return;
            fresh.slice(0, 3).forEach(item => {
                AppActions.notify(item.message || item.title, 'success', {
                    icon: item.icon,
                    title: item.title,
                    action: { label: 'View', href: safeUrl(item.url) },
                    timeout: 10000,
                });
            });
            if (fresh.length > 3) {
                AppActions.notify('and ' + (fresh.length - 3) + ' more', 'success', {
                    icon: 'bi-bell-fill',
                    action: { label: 'Open notifications', href: notificationsUrl },
                    timeout: 10000,
                });
            }
        }

        function schedule(ms) {
            clearTimeout(timer);
            if (!stopped && !document.hidden) timer = setTimeout(poll, ms);
        }

        async function poll() {
            if (inFlight || stopped) return;
            inFlight = true;
            lastPoll = Date.now();
            try {
                const response = await fetch(pollUrl + '?since=' + encodeURIComponent(since), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if ([401, 403, 409, 419].includes(response.status) || response.redirected) {
                    stopped = true; // logged out or session expired: the next page load shows the login
                    return;
                }
                if (!response.ok) throw new Error('poll failed');
                const data = await response.json();
                failures = 0;
                since = data.now || since;
                setUnread(Number(data.unread) || 0);
                const fresh = (data.fresh || []).filter(item => !item.read && !seen.has(item.id));
                fresh.forEach(item => seen.add(item.id));
                if (fresh.length) announce(fresh);
            } catch (e) {
                failures++; // offline or server busy: back off up to 2 minutes
            } finally {
                inFlight = false;
                schedule(Math.min(intervalMs * Math.max(1, 2 ** failures), 120000));
            }
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearTimeout(timer);
            } else if (Date.now() - lastPoll > 5000) {
                poll();
            } else {
                schedule(intervalMs);
            }
        });

        schedule(intervalMs);
    });
</script>
<style>
    .live-notifications-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        margin: 0 0 16px;
        padding: 10px 14px;
        border: 1px solid #1e9e62;
        border-radius: 10px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-14653f, #14653f);
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s;
    }

    .live-notifications-bar:hover {
        background: var(--bg-dff1e7, #dff1e7);
    }

    .live-notifications-bar span {
        text-decoration: underline;
    }
</style>
@endauth
