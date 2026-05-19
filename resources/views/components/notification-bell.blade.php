<!-- Notification Bell Dropdown Component -->
<div class="notification-bell-wrapper">
    <button class="notification-bell-btn" id="notificationToggle" title="Notifications">
        <i class="bi bi-bell"></i>
        @if(Auth::check() && Auth::user()->unreadNotifications->count() > 0)
        <span class="notification-badge">{{ Auth::user()->unreadNotifications->count() }}</span>
        @endif
    </button>

    <div class="notification-dropdown" id="notificationDropdown">
        @if(Auth::check())
        <div class="dropdown-header">
            <div class="dropdown-header-text">
                <div class="dropdown-header-name">Notifications</div>
                <div class="dropdown-header-email">{{ Auth::user()->unreadNotifications->count() }} unread</div>
            </div>
        </div>

        <div class="dropdown-menu notification-menu">
            @php
            $recentNotifications = Auth::user()->notifications()->limit(5)->get();
            @endphp

            @if(Auth::user()->unreadNotifications->count() > 0)
            <form action="{{ route('notifications.mark-all-as-read') }}" method="POST" class="mark-all-read-form mark-all-read-form--header" data-ajax-mark-all>
                @csrf
                <button type="submit" class="mark-all-read-btn" title="Mark all as read">
                    <i class="bi bi-check-all"></i> Mark all read
                </button>
            </form>
            @endif

            @if($recentNotifications->count() > 0)
            @foreach($recentNotifications as $notification)
            <div class="notification-item {{ !$notification->read_at ? 'unread' : '' }}">
                <div class="notification-icon">
                    <i class="bi {{ $notification->data['icon'] ?? 'bi-info-circle' }}"></i>
                </div>
                <div class="notification-content">
                    <p class="notification-title">{{ $notification->data['title'] ?? 'Notification' }}</p>
                    <p class="notification-message">{{ $notification->data['message'] ?? '' }}</p>
                    <p class="notification-time">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                @if(!$notification->read_at)
                <form action="{{ route('notifications.mark-as-read', $notification->id) }}" method="POST" class="notification-mark-read-form" data-ajax-mark-read>
                    @csrf
                    @method('PUT')
                    <button type="submit" class="notification-mark-read-btn" title="Mark as read">
                        <i class="bi bi-check2"></i>
                        <span class="mark-read-label">Read</span>
                    </button>
                </form>
                @endif
            </div>
            @endforeach
            @else
            <div class="empty-notifications">
                <i class="bi bi-inbox"></i>
                <p>No notifications yet</p>
            </div>
            @endif
        </div>

        <div class="dropdown-divider"></div>

        <div class="dropdown-footer">
            <a href="{{ route('notifications.index') }}" class="view-all-link">
                <i class="bi bi-arrow-right"></i> View All Notifications
            </a>
        </div>
        @endif
    </div>
</div>

<style>
    .notification-bell-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        height: 100%;
    }

    .notification-bell-btn {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: transparent;
        color: #6a8a6a;
        font-size: 18px;
        transition: all 0.15s;
        border: none;
        cursor: pointer;
        font-family: 'Manrope', system-ui, -apple-system, Segoe UI, sans-serif;
    }

    .notification-bell-btn:hover {
        background: #f5f7f6;
        color: #1e9e62;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        background: #ef4444;
        color: white;
        font-size: 11px;
        font-weight: 700;
        border-radius: 10px;
        border: 2px solid white;
    }

    .notification-dropdown {
        position: absolute;
        top: 68px;
        /* Adjusted to match profile dropdown spacing */
        right: 0;
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 10px;
        min-width: 340px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        display: none;
        z-index: 999;
        overflow: hidden;
    }

    .notification-dropdown.active {
        display: block;
    }

    .notification-menu {
        max-height: 360px;
        overflow-y: auto;
        padding: 0;
    }

    .notification-menu::-webkit-scrollbar {
        width: 6px;
    }

    .notification-menu::-webkit-scrollbar-track {
        background: transparent;
    }

    .notification-menu::-webkit-scrollbar-thumb {
        background: #d4e0d4;
        border-radius: 3px;
    }

    .notification-menu::-webkit-scrollbar-thumb:hover {
        background: #b8d4b8;
    }

    .notification-item {
        display: flex;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid #f0f4f0;
        transition: all 0.15s;
        position: relative;
        background: transparent;
    }

    .notification-item:hover {
        background: #f5f7f6;
    }

    .notification-item.unread {
        background: #edf7f2;
    }

    .notification-item.unread:hover {
        background: #e0f2e8;
    }

    .notification-icon {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        background: #edf7f2;
        color: #1e9e62;
        flex-shrink: 0;
        font-size: 14px;
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-title {
        font-size: 12px;
        font-weight: 600;
        color: #1a2e1a;
        margin: 0 0 4px 0;
        line-height: 1.4;
    }

    .notification-message {
        font-size: 11px;
        color: #7a9a7a;
        margin: 0 0 4px 0;
        line-height: 1.4;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notification-time {
        font-size: 10px;
        color: #a8bfa8;
        margin: 0;
    }

    .mark-all-read-form--header {
        padding: 0 16px 10px;
        border-bottom: 1px solid #f0f4f0;
    }

    .mark-all-read-form {
        margin: 0;
    }

    .mark-all-read-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border: 1px solid #c8e6d4;
        border-radius: 6px;
        background: #fff;
        color: #1e9e62;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.15s;
    }

    .mark-all-read-btn:hover {
        background: #edf7f2;
        border-color: #1e9e62;
    }

    .notification-mark-read-form {
        flex-shrink: 0;
        margin: 0;
    }

    .notification-mark-read-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
        height: 28px;
        padding: 0 8px;
        border: 1px solid #c8e6d4;
        border-radius: 6px;
        background: #fff;
        color: #1e9e62;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        font-family: inherit;
        white-space: nowrap;
    }

    .mark-read-label {
        line-height: 1;
    }

    .notification-mark-read-btn:hover {
        background: #1e9e62;
        color: #fff;
        border-color: #1e9e62;
    }

    .empty-notifications {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
        color: #a8bfa8;
        font-size: 12px;
    }

    .empty-notifications i {
        font-size: 28px;
        margin-bottom: 8px;
        color: #d4e0d4;
    }

    .dropdown-footer {
        padding: 12px 16px;
        background: #f5f7f6;
    }

    .view-all-link {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #1e9e62;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s;
    }

    .view-all-link:hover {
        color: #16a34a;
        gap: 10px;
    }

    .dropdown-divider {
        height: 1px;
        background: #e0e8e0;
        margin: 0;
    }

    @media (max-width: 768px) {
        .notification-dropdown {
            top: 64px;
            /* Adjusted to match profile dropdown on mobile */
            min-width: 300px;
            max-height: 60vh;
        }

        .notification-menu {
            max-height: calc(60vh - 140px);
        }

        .notification-item {
            padding: 10px 12px;
            gap: 10px;
        }

        .notification-icon {
            width: 28px;
            height: 28px;
            font-size: 12px;
        }

        .notification-title {
            font-size: 11px;
        }

        .notification-message {
            font-size: 10px;
        }

        .notification-time {
            font-size: 9px;
        }

        .view-all-link {
            font-size: 11px;
        }
    }

    @media (max-width: 480px) {
        .notification-dropdown {
            min-width: 280px;
            max-height: 50vh;
            right: -10px;
        }

        .notification-menu {
            max-height: calc(50vh - 120px);
        }

        .notification-item {
            padding: 8px 10px;
            gap: 8px;
        }

        .notification-icon {
            width: 24px;
            height: 24px;
            font-size: 11px;
        }

        .notification-title {
            font-size: 10px;
        }

        .notification-message {
            font-size: 9px;
        }

        .notification-time {
            font-size: 8px;
        }

        .empty-notifications {
            padding: 20px 10px;
        }

        .empty-notifications i {
            font-size: 24px;
            margin-bottom: 6px;
        }

        .empty-notifications p {
            font-size: 11px;
        }

        .dropdown-footer {
            padding: 8px 10px;
        }

        .view-all-link {
            font-size: 10px;
        }

        .mark-read-label {
            display: none;
        }
    }
</style>

<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) return;

    function updateBellUnread(count) {
        const badge = document.querySelector('.notification-badge');
        const headerUnread = document.querySelector('.dropdown-header-email');

        if (headerUnread) {
            headerUnread.textContent = count + ' unread';
        }

        if (count > 0) {
            if (badge) {
                badge.textContent = count;
            } else {
                const btn = document.getElementById('notificationToggle');
                if (btn) {
                    const span = document.createElement('span');
                    span.className = 'notification-badge';
                    span.textContent = count;
                    btn.appendChild(span);
                }
            }
        } else {
            badge?.remove();
            document.querySelector('.mark-all-read-form--header')?.remove();
        }
    }

    async function ajaxSubmit(form) {
        const res = await fetch(form.action, {
            method: form.querySelector('[name="_method"]')?.value || form.method,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!res.ok) return null;
        return res.json();
    }

    document.querySelectorAll('[data-ajax-mark-read]').forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const data = await ajaxSubmit(form);
            if (!data) return;

            const item = form.closest('.notification-item');
            item?.classList.remove('unread');
            form.remove();
            updateBellUnread(data.unreadCount ?? 0);
        });
    });

    document.querySelectorAll('[data-ajax-mark-all]').forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const data = await ajaxSubmit(form);
            if (!data) return;

            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.querySelector('[data-ajax-mark-read]')?.remove();
            });
            updateBellUnread(0);
        });
    });
})();
</script>