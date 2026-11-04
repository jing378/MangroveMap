@php
$currentUser = auth()->user();
$layoutToUse = 'layouts.enduser';
if ($currentUser && $currentUser->role === 'admin') {
    $layoutToUse = 'layouts.admin';
}
@endphp

@extends($layoutToUse)

@section('title', 'Notifications - MangroveMap')

@section('styles')
<style>
    .notifications-page {
        max-width: 800px;
        margin: 0 auto;
    }

    .main-content {
        flex: 1;
    }

    .content {
        padding: 28px 32px;
    }

    @media (max-width: 768px) {
        .content {
            padding: 20px 16px;
        }
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
        color: var(--fg-1a2e1a, #1a2e1a);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notifications-header h1 i {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .mark-all-read-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid var(--bd-c8e6d4, #c8e6d4);
        border-radius: 8px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1e9e62, #1e9e62);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.15s;
    }

    .mark-all-read-btn:hover {
        background: var(--bg-edf7f2, #edf7f2);
        border-color: #1e9e62;
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-4b5f56, #4b5f56);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        text-decoration: none;
        transition: all 0.15s;
    }

    .back-btn:hover {
        background: var(--bg-f8fcfa, #f8fcfa);
        border-color: #1e9e62;
        color: var(--fg-1e9e62, #1e9e62);
    }

    .unread-banner {
        background: var(--bg-edf7f2, #edf7f2);
        border: 1px solid var(--bd-c8e6d4, #c8e6d4);
        color: var(--fg-1a5c3a, #1a5c3a);
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .notification-card {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
        background: var(--bg-f8fcfa, #f8fcfa);
    }

    .notification-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--bg-edf7f2, #edf7f2);
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
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 4px;
    }

    .notification-card.unread .notification-card-title {
        color: var(--fg-0d4a2d, #0d4a2d);
    }

    .notification-card-message {
        font-size: 13px;
        color: var(--fg-6a8a6a, #6a8a6a);
        line-height: 1.5;
        margin-bottom: 8px;
    }

    .notification-card-detail {
        font-size: 12px;
        color: var(--fg-4b5f56, #4b5f56);
        margin: 2px 0 6px;
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
        border: 1px solid var(--bd-c8e6d4, #c8e6d4);
        border-radius: 6px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1e9e62, #1e9e62);
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
        border: 1px solid var(--bd-f0d0d0, #f0d0d0);
        border-radius: 6px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-d04030, #d04030);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        white-space: nowrap;
        transition: all 0.15s;
    }

    .btn-delete:hover {
        background: var(--bg-fef2f2, #fef2f2);
        border-color: #d04030;
    }

    .notification-card-actions {
        display: grid;
        gap: 10px;
        margin-top: 14px;
    }

    .notification-card-actions form {
        margin: 0;
    }

    .btn-view-delineation {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid var(--bd-c8e6d4, #c8e6d4);
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1e9e62, #1e9e62);
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        font-family: inherit;
        text-decoration: none;
        transition: all 0.15s;
    }

    .btn-view-delineation:hover {
        background: var(--bg-edf7f2, #edf7f2);
        border-color: #1e9e62;
        color: var(--fg-178a54, #178a54);
    }

    .btn-approve,
    .btn-reject,
    .btn-mark-read,
    .btn-delete {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
        padding: 10px 14px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        font-family: inherit;
        transition: all 0.15s;
    }

    .btn-approve {
        background: #1e9e62;
        color: #fff;
    }

    .btn-approve:hover {
        background: #178a54;
    }

    .btn-reject {
        background: var(--bg-ffffff, #fff);
        color: var(--fg-d04030, #d04030);
        border: 1px solid var(--bd-f0d0d0, #f0d0d0);
    }

    .btn-reject:hover {
        background: var(--bg-fef2f2, #fef2f2);
    }

    .notification-reject-form {
        display: none;
        background: var(--bg-fff5f5, #fff5f5);
        padding: 12px;
        border: 1px solid var(--bd-f7d6d6, #f7d6d6);
        border-radius: 10px;
    }

    .notification-reject-form.open {
        display: block;
    }

    .notification-reject-form textarea {
        width: 100%;
        min-height: 80px;
        margin-bottom: 10px;
        padding: 10px;
        border: 1px solid var(--bd-e2e8f0, #e2e8f0);
        border-radius: 8px;
        resize: vertical;
        font-family: inherit;
        font-size: 13px;
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

    @if($unreadCount > 0)
    <div class="unread-banner" id="unreadBanner">
        <i class="bi bi-info-circle"></i>
        <span>You have <strong id="unreadCountText">{{ $unreadCount }}</strong> unread notification{{ $unreadCount > 1 ? 's' : '' }}</span>
    </div>
    @endif

    @if($notifications->count() > 0)
    <div class="notifications-list">
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
        'delineation_submitted' => '#c07818',
        'expert_application' => '#c07818',
        'expert_application_approved' => '#10b981',
        'expert_application_rejected' => '#ef4444',
        ];
        $delineationId = $notification->data['delineation_id'] ?? null;
        $viewUrl = $notification->data['url'] ?? $notification->data['actionUrl'] ?? null;
        if ($delineationId) {
        $viewUrl = Auth::user()->isExpert()
        ? route('expert.map', ['delineation' => $delineationId])
        : route('map', ['delineation' => $delineationId]);
        }
        $iconColor = $colorMap[$type] ?? '#1e9e62';
        @endphp
        <div class="notification-card {{ !$notification->read_at ? 'unread' : '' }}" data-notification-id="{{ $notification->id }}">
            <div class="notification-card-icon">
                <i class="bi {{ $icon }}" style="color: {{ $iconColor }}"></i>
            </div>
            <div class="notification-card-body">
                <h3 class="notification-card-title">{{ $notification->data['title'] ?? 'Notification' }}</h3>
                <p class="notification-card-message">{{ $notification->data['message'] ?? '' }}</p>
                @if(!empty($notification->data['delineation_name']))
                <p class="notification-card-detail"><strong>Delineation:</strong> {{ $notification->data['delineation_name'] }}</p>
                @endif
                @if(!empty($notification->data['submitted_by']))
                <p class="notification-card-detail"><strong>Submitted by:</strong> {{ $notification->data['submitted_by'] }}</p>
                @endif
                <p class="notification-card-meta">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
            <div class="notification-card-actions">
                @if($viewUrl)
                {{-- Delineation notifications open the map; others (e.g. expert applications) use their own link. --}}
                <a href="{{ $viewUrl }}" class="btn-view-delineation" @if(!$notification->read_at) data-mark-read-url="{{ route('notifications.mark-as-read', $notification->id) }}" @endif>
                    @if($delineationId)
                    <i class="bi bi-map"></i> View
                    @else
                    <i class="bi bi-box-arrow-up-right"></i> {{ $notification->data['actionLabel'] ?? 'View' }}
                    @endif
                </a>
                @endif
                <form action="{{ route('notifications.destroy', $notification->id) }}" method="POST" data-delete-form>
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
    (function() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        function updateUnreadUi(count) {
            const badge = document.querySelector('.notification-badge');
            const sidebarLink = document.getElementById('sidebarNotificationsLink');
            let sidebarBadge = sidebarLink?.querySelector('.sidebar-notification-badge');
            const headerUnread = document.querySelector('.notification-dropdown .dropdown-header-email');
            const banner = document.getElementById('unreadBanner');
            const countText = document.getElementById('unreadCountText');
            const markAllForm = document.querySelector('[data-ajax-mark-all]');

            if (countText) countText.textContent = count;
            if (headerUnread) headerUnread.textContent = count + ' unread';
            sidebarLink?.classList.toggle('has-unread', count > 0);
            if (sidebarLink && count > 0) {
                if (!sidebarBadge) {
                    sidebarBadge = document.createElement('span');
                    sidebarBadge.className = 'sidebar-notification-badge';
                    sidebarLink.querySelector('.sidebar-notification-icon')?.appendChild(sidebarBadge);
                }
                if (sidebarBadge) {
                    sidebarBadge.textContent = count;
                    sidebarBadge.setAttribute('aria-label', count + ' unread notifications');
                }
            } else {
                sidebarBadge?.remove();
            }
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


        // Sends the form with fetch; resolves with the JSON body, or throws with the server's message.
        async function ajaxRequest(form) {
            const method = (form.querySelector('[name="_method"]')?.value || form.method || 'POST').toUpperCase();
            const options = {
                method: method,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            };

            if (method !== 'GET' && method !== 'HEAD') {
                options.body = new FormData(form);
            }

            let res;
            try {
                res = await fetch(form.action, options);
            } catch (err) {
                console.error('AJAX error:', err);
                throw new Error('Network error. Please try again.');
            }
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                let errMsg = data.message || 'Error performing action';
                if (data.errors) {
                    const firstKey = Object.keys(data.errors)[0];
                    if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey].length > 0) {
                        errMsg = data.errors[firstKey][0];
                    }
                }
                throw new Error(errMsg);
            }
            return data;
        }

        // Confirmation → Processing → Success or Error (partials.action-feedback).
        // Success uses the same toast as the profile page.
        async function ajaxAction(form, { confirm = null, busyLabel, lock = [], successMessage }, onSuccess) {
            const outcome = await window.AppActions.run({
                confirm,
                button: form.querySelector('button[type="submit"]'),
                busyLabel,
                lock,
                task: () => ajaxRequest(form),
                successMessage: data => data?.message || successMessage,
            });
            if (outcome.status === 'done') onSuccess(outcome.result);
        }

        // Every action button of the notification card, so a second action cannot start while one runs.
        const cardButtons = form => [...(form.closest('[data-notification-id]')?.querySelectorAll('button') || [])];

        document.querySelectorAll('[data-ajax-mark-read]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                await ajaxAction(form, { busyLabel: 'Marking as read...', successMessage: 'Notification marked as read' }, (data) => {
                    const card = form.closest('[data-notification-id]');
                    card?.classList.remove('unread');
                    form.remove();
                    updateUnreadUi(data.unreadCount ?? 0);
                });
            });
        });

        document.querySelectorAll('.btn-view-delineation[data-mark-read-url]').forEach(link => {
            link.addEventListener('click', async function(e) {
                if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                    return;
                }

                e.preventDefault();
                const markReadUrl = link.dataset.markReadUrl;
                const targetUrl = link.href;

                try {
                    const res = await fetch(markReadUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new URLSearchParams({
                            _method: 'PUT'
                        })
                    });

                    if (res.ok) {
                        const data = await res.json().catch(() => ({}));
                        const card = link.closest('[data-notification-id]');
                        card?.classList.remove('unread');
                        const unreadForm = card?.querySelector('[data-ajax-mark-read]');
                        unreadForm?.remove();
                        updateUnreadUi(data.unreadCount ?? 0);
                    }
                } catch (error) {
                    console.warn('Could not mark notification as read before navigation.', error);
                } finally {
                    window.location.href = targetUrl;
                }
            });
        });

        const markAllForm = document.querySelector('[data-ajax-mark-all]');
        if (markAllForm) {
            markAllForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                await ajaxAction(markAllForm, {
                    confirm: { title: 'Mark all as read?', message: 'Every unread notification is marked as read.', confirmLabel: 'Mark all' },
                    busyLabel: 'Marking all as read...',
                    successMessage: 'All notifications marked as read',
                }, () => {
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        card.querySelector('[data-ajax-mark-read]')?.remove();
                    });
                    updateUnreadUi(0);
                });
            });
        }

        // AJAX handler for approve button
        document.querySelectorAll('[data-approve-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                await ajaxAction(form, {
                    confirm: { title: 'Approve this delineation?', message: 'It is published on the map and the resident is notified.', confirmLabel: 'Approve' },
                    busyLabel: 'Approving...',
                    lock: cardButtons(form),
                    successMessage: 'Delineation approved successfully',
                }, (data) => {
                    const card = form.closest('[data-notification-id]');
                    card?.remove();
                    updateUnreadUi(data.unreadCount ?? 0);
                });
            });
        });

        // AJAX handler for reject form submission
        document.querySelectorAll('[data-reject-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const textarea = form.querySelector('textarea[name="rejection_notes"]');
                const notes = textarea?.value?.trim() || '';

                if (notes.length < 10) {
                    window.AppActions.notify('Please enter at least 10 characters for rejection notes.', 'error');
                    textarea?.focus();
                    return;
                }

                await ajaxAction(form, {
                    confirm: { title: 'Reject this delineation?', message: 'The resident is notified with your notes and can revise it.', confirmLabel: 'Reject', tone: 'danger' },
                    busyLabel: 'Rejecting...',
                    lock: [...cardButtons(form), textarea],
                    successMessage: 'Delineation rejected successfully',
                }, (data) => {
                    const card = form.closest('[data-notification-id]');
                    card?.remove();
                    updateUnreadUi(data.unreadCount ?? 0);
                });
            });
        });

        // AJAX handler for delete button
        document.querySelectorAll('[data-delete-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                await ajaxAction(form, {
                    confirm: { title: 'Delete this notification?', message: 'It is removed from your notifications.', confirmLabel: 'Delete', tone: 'danger' },
                    busyLabel: 'Deleting...',
                    lock: cardButtons(form),
                    successMessage: 'Notification deleted',
                }, (data) => {
                    const card = form.closest('[data-notification-id]');
                    card?.remove();
                    updateUnreadUi(data.unreadCount ?? 0);
                });
            });
        });

        // Results of full-page submits use the same success toast as the profile page.
        @if (session('success'))
        window.AppActions?.notify(@json(session('success')));
        @endif

        document.querySelectorAll('[data-reject-toggle]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-reject-toggle');
                const form = document.getElementById('notification-reject-form-' + id);
                form?.classList.toggle('open');
                if (form?.classList.contains('open')) {
                    form.querySelector('textarea')?.focus();
                }
            });
        });
    })();
</script>
@endsection