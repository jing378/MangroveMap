@extends('layouts.enduser')

@section('title', 'Notifications - MangroveMap')

@section('styles')
<style>
    .notifications-page {
        max-width: 800px;
    }

    .notifications-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .notifications-header h1 {
        font-size: 24px;
        font-weight: 800;
        color: #1a2e1a;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notifications-header h1 i {
        color: #1e9e62;
    }

    .mark-all-read-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid #c8e6d4;
        border-radius: 8px;
        background: #fff;
        color: #1e9e62;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.15s;
    }

    .mark-all-read-btn:hover {
        background: #edf7f2;
        border-color: #1e9e62;
    }

    .unread-banner {
        background: #edf7f2;
        border: 1px solid #c8e6d4;
        color: #1a5c3a;
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .notification-card {
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        transition: all 0.15s;
    }

    .notification-card.unread {
        border-left: 4px solid #1e9e62;
        background: #f8fcfa;
    }

    .notification-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #edf7f2;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 18px;
    }

    .notification-card-body {
        flex: 1;
        min-width: 0;
    }

    .notification-card-title {
        font-size: 15px;
        font-weight: 700;
        color: #1a2e1a;
        margin-bottom: 4px;
    }

    .notification-card.unread .notification-card-title {
        color: #0d4a2d;
    }

    .notification-card-message {
        font-size: 13px;
        color: #6a8a6a;
        line-height: 1.5;
        margin-bottom: 8px;
    }

    .notification-card-meta {
        font-size: 11px;
        color: #a8bfa8;
    }

    .notification-card-actions {
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex-shrink: 0;
    }

    .btn-mark-read {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        border: 1px solid #c8e6d4;
        border-radius: 6px;
        background: #fff;
        color: #1e9e62;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        white-space: nowrap;
        transition: all 0.15s;
    }

    .btn-mark-read:hover {
        background: #1e9e62;
        color: #fff;
        border-color: #1e9e62;
    }

    .btn-delete {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        border: 1px solid #f0d0d0;
        border-radius: 6px;
        background: #fff;
        color: #d04030;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        white-space: nowrap;
        transition: all 0.15s;
    }

    .btn-delete:hover {
        background: #fef2f2;
        border-color: #d04030;
    }

    .notification-action-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        padding: 8px 14px;
        background: #1e9e62;
        color: #fff;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.15s;
    }

    .notification-action-link:hover {
        background: #178a54;
        color: #fff;
    }

    .empty-state {
        text-align: center;
        padding: 48px 24px;
        color: #a8bfa8;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 12px;
        display: block;
        color: #d4e0d4;
    }

    .pagination-wrap {
        margin-top: 24px;
    }
</style>
@endsection

@section('content')
<div class="notifications-page">
    <div class="notifications-header">
        <h1><i class="bi bi-bell"></i> Notifications</h1>
        @if($unreadCount > 0)
        <form action="{{ route('notifications.mark-all-as-read') }}" method="POST" class="mark-all-read-form" data-ajax-mark-all>
            @csrf
            <button type="submit" class="mark-all-read-btn">
                <i class="bi bi-check-all"></i> Mark all as read
            </button>
        </form>
        @endif
    </div>

    @if(session('success'))
    <div class="unread-banner" style="background:#edf7f2;border-color:#c8e6d4;color:#1e9e62;">
        <i class="bi bi-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    @if($unreadCount > 0)
    <div class="unread-banner" id="unreadBanner">
        <i class="bi bi-info-circle"></i>
        You have <strong id="unreadCountText">{{ $unreadCount }}</strong> unread notification(s)
    </div>
    @endif

    @if($notifications->count() > 0)
    <div id="notificationsList">
        @foreach($notifications as $notification)
        @php
        $icon = $notification->data['icon'] ?? 'bi-info-circle';
        $type = $notification->data['type'] ?? 'notification';
        $colorMap = [
            'new_user' => '#3b82f6',
            'new_analysis' => '#10b981',
            'analysis_failed' => '#ef4444',
            'system_error' => '#dc2626',
            'system_alert' => '#f59e0b',
            'data_update' => '#06b6d4',
            'user_action' => '#8b5cf6',
            'analysis_completed' => '#10b981',
            'delineation_approved' => '#10b981',
            'delineation_rejected' => '#ef4444',
        ];
        $iconColor = $colorMap[$type] ?? '#1e9e62';
        @endphp
        <div class="notification-card {{ !$notification->read_at ? 'unread' : '' }}" data-notification-id="{{ $notification->id }}">
            <div class="notification-card-icon">
                <i class="bi {{ $icon }}" style="color: {{ $iconColor }}"></i>
            </div>
            <div class="notification-card-body">
                <h3 class="notification-card-title">{{ $notification->data['title'] ?? 'Notification' }}</h3>
                <p class="notification-card-message">{{ $notification->data['message'] ?? '' }}</p>
                <p class="notification-card-meta">{{ $notification->created_at->diffForHumans() }}</p>
                @if(!empty($notification->data['actionUrl']))
                <a href="{{ $notification->data['actionUrl'] }}" class="notification-action-link">
                    <i class="bi bi-arrow-right"></i> {{ $notification->data['actionLabel'] ?? 'View details' }}
                </a>
                @endif
            </div>
            <div class="notification-card-actions">
                @if(!$notification->read_at)
                <form action="{{ route('notifications.mark-as-read', $notification->id) }}" method="POST" class="mark-read-form" data-ajax-mark-read>
                    @csrf
                    @method('PUT')
                    <button type="submit" class="btn-mark-read" title="Mark as read">
                        <i class="bi bi-check2"></i> Mark as read
                    </button>
                </form>
                @endif
                <form action="{{ route('notifications.destroy', $notification->id) }}" method="POST" onsubmit="return confirm('Delete this notification?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-delete">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <div class="pagination-wrap">
        {{ $notifications->links() }}
    </div>
    @else
    <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p>No notifications yet</p>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function updateUnreadUi(count) {
        const badge = document.querySelector('.notification-badge');
        const headerUnread = document.querySelector('.dropdown-header-email');
        const banner = document.getElementById('unreadBanner');
        const countText = document.getElementById('unreadCountText');
        const markAllForm = document.querySelector('[data-ajax-mark-all]');

        if (countText) countText.textContent = count;
        if (headerUnread) headerUnread.textContent = count + ' unread';
        if (badge) {
            if (count > 0) badge.textContent = count;
            else badge.remove();
        }
        if (count === 0) {
            banner?.remove();
            markAllForm?.remove();
            document.querySelectorAll('[data-ajax-mark-read]').forEach(f => f.remove());
        }
    }

    async function ajaxSubmit(form, onSuccess) {
        const res = await fetch(form.action, {
            method: form.querySelector('[name="_method"]')?.value || form.method,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!res.ok) return;
        const data = await res.json();
        onSuccess(data);
    }

    document.querySelectorAll('[data-ajax-mark-read]').forEach(form => {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            await ajaxSubmit(form, (data) => {
                const card = form.closest('[data-notification-id]');
                card?.classList.remove('unread');
                form.remove();
                updateUnreadUi(data.unreadCount ?? 0);
            });
        });
    });

    const markAllForm = document.querySelector('[data-ajax-mark-all]');
    if (markAllForm) {
        markAllForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            await ajaxSubmit(markAllForm, () => {
                document.querySelectorAll('.notification-card.unread').forEach(card => {
                    card.classList.remove('unread');
                    card.querySelector('[data-ajax-mark-read]')?.remove();
                });
                updateUnreadUi(0);
            });
        });
    }
})();
</script>
@endsection
