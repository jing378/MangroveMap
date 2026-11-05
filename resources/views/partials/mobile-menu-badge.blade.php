{{-- Unread-notification dot on the mobile hamburger (#mobileMenuBtn), where the sidebar badge is hidden
     until the menu is opened. Include it inside the button. It is a plain red dot (no number); the count is only
     in the labels for screen readers. It starts from the server count and then follows the sidebar badge
     (#sidebarNotificationsLink), which the notification pages update when items are read.
     Styles and script are inline: the layouts print their style stack before this part of the body renders. --}}
@php($mobileMenuUnread = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0)
<span class="mobile-menu-badge" id="mobileMenuBadge" role="img" @if ($mobileMenuUnread === 0) hidden @endif
    aria-label="{{ $mobileMenuUnread }} unread notifications"></span>
<style>
    #mobileMenuBtn {
        position: relative;
    }

    .mobile-menu-badge {
        position: absolute;
        top: 3px;
        right: 3px;
        width: 11px;
        height: 11px;
        border: 2px solid var(--bd-ffffff, #fff);
        border-radius: 50%;
        background: #ef4444;
        pointer-events: none;
    }

    .mobile-menu-badge[hidden] {
        display: none;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const badge = document.getElementById('mobileMenuBadge');
        const button = document.getElementById('mobileMenuBtn');
        const sidebarLink = document.getElementById('sidebarNotificationsLink');
        if (!badge || !sidebarLink) return;

        const baseLabel = button?.getAttribute('aria-label') || 'Open navigation menu';
        const sync = () => {
            const count = Number(sidebarLink.querySelector('.sidebar-notification-badge')?.textContent.trim()) || 0;
            badge.hidden = count === 0;
            badge.setAttribute('aria-label', `${count} unread notifications`);
            button?.setAttribute('aria-label', count ? `${baseLabel} (${count} unread notifications)` : baseLabel);
        };

        sync();
        new MutationObserver(sync).observe(sidebarLink, { childList: true, subtree: true, characterData: true });
    });
</script>
