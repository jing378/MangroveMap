@extends('layouts.enduser')

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

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid #e0e8e0;
        border-radius: 8px;
        background: #fff;
        color: #4b5f56;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        text-decoration: none;
        transition: all 0.15s;
    }

    .back-btn:hover {
        background: #f8fcfa;
        border-color: #1e9e62;
        color: #1e9e62;
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

    .notification-card-detail {
        font-size: 12px;
        color: #4b5f56;
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
        border: 1px solid #c8e6d4;
        background: #fff;
        color: #1e9e62;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        font-family: inherit;
        text-decoration: none;
        transition: all 0.15s;
    }

    .btn-view-delineation:hover {
        background: #edf7f2;
        border-color: #1e9e62;
        color: #178a54;
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
        background: #fff;
        color: #d04030;
        border: 1px solid #f0d0d0;
    }

    .btn-reject:hover {
        background: #fef2f2;
    }

    .notification-reject-form {
        display: none;
        background: #fff5f5;
        padding: 12px;
        border: 1px solid #f7d6d6;
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
        border: 1px solid #e2e8f0;
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

    /* Processing Modal */
    .processing-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 99999;
        opacity: 0;
        transition: opacity 0.25s ease;
    }

    .processing-modal-backdrop.active {
        display: flex;
        opacity: 1;
    }

    .processing-modal-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 32px 28px;
        text-align: center;
        max-width: 320px;
        width: 90%;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.05);
        transform: scale(0.95);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .processing-modal-backdrop.active .processing-modal-card {
        transform: scale(1);
    }

    .processing-spinner {
        width: 44px;
        height: 44px;
        margin: 0 auto 16px;
        border: 3.5px solid #edf2f7;
        border-top-color: #d04030;
        border-radius: 50%;
        animation: processingSpin 0.75s linear infinite;
    }

    @keyframes processingSpin {
        to {
            transform: rotate(360deg);
        }
    }

    .processing-modal-title {
        font-size: 16px;
        font-weight: 700;
        color: #1a2e1a;
        margin-bottom: 6px;
    }

    .processing-modal-subtitle {
        font-size: 13px;
        color: #64748b;
        margin: 0;
        line-height: 1.4;
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
        <a href="{{ route('dashboard') }}" class="back-btn">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if(session('success'))
    <div class="unread-banner" style="background:#edf7f2;border-color:#c8e6d4;color:#1e9e62;margin-bottom:16px;">
        <i class="bi bi-check-circle"></i> {{ session('success') }}
    </div>
    @endif

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
        ];
        $delineationId = $notification->data['delineation_id'] ?? null;
        $viewUrl = $notification->data['url'] ?? null;
        if (! $viewUrl && $delineationId) {
        $viewUrl = Auth::user()->isExpert()
        ? route('expert.dashboard', ['delineation' => $delineationId])
        : route('dashboard', ['delineation' => $delineationId]);
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
                @if($delineationId && $viewUrl)
                <a href="{{ $viewUrl }}" class="btn-view-delineation">
                    <i class="bi bi-map"></i> View delineation
                </a>
                @endif
                @if(Auth::user()->isExpert() && ($notification->data['type'] ?? '') === 'delineation_submitted' && !empty($notification->data['delineation_id']))
                <form action="{{ route('expert.delineations.approve', $notification->data['delineation_id']) }}" method="POST" data-approve-form>
                    @csrf
                    <button type="submit" class="btn-approve">
                        <i class="bi bi-check2"></i> Approve
                    </button>
                </form>
                <button type="button" class="btn-reject" data-reject-toggle="{{ $notification->id }}">
                    <i class="bi bi-x-lg"></i> Reject
                </button>
                <form action="{{ route('expert.delineations.reject', $notification->data['delineation_id']) }}" method="POST" class="notification-reject-form" id="notification-reject-form-{{ $notification->id }}" data-reject-form>
                    @csrf
                    <label for="rejection_notes_{{ $notification->id }}">Rejection notes</label>
                    <textarea id="rejection_notes_{{ $notification->id }}" name="rejection_notes" placeholder="Enter rejection notes (min 10 characters)" required minlength="10" maxlength="2000"></textarea>
                    <button type="submit" class="btn-reject">
                        <i class="bi bi-send"></i> Submit rejection
                    </button>
                </form>
                @endif
                @if(!$notification->read_at)
                <form action="{{ route('notifications.mark-as-read', $notification->id) }}" method="POST" class="mark-read-form" data-ajax-mark-read>
                    @csrf
                    @method('PUT')
                    <button type="submit" class="btn-mark-read" title="Mark as read">
                        <i class="bi bi-check2"></i> Mark as read
                    </button>
                </form>
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

    <!-- Processing Modal to prevent multiple clicks -->
    <div id="processingModal" class="processing-modal-backdrop" role="dialog" aria-modal="true" aria-label="Processing request">
        <div class="processing-modal-card">
            <div class="processing-spinner"></div>
            <h3 class="processing-modal-title">Processing...</h3>
            <p class="processing-modal-subtitle">Submitting your rejection notes, please wait.</p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        function showProcessingModal(title = 'Processing...', subtitle = 'Submitting rejection notes, please wait.') {
            const modal = document.getElementById('processingModal');
            if (!modal) return;
            const titleEl = modal.querySelector('.processing-modal-title');
            const subEl = modal.querySelector('.processing-modal-subtitle');
            if (titleEl) titleEl.textContent = title;
            if (subEl) subEl.textContent = subtitle;
            modal.classList.add('active');
        }

        function hideProcessingModal() {
            const modal = document.getElementById('processingModal');
            if (!modal) return;
            modal.classList.remove('active');
        }

        function updateUnreadUi(count) {
            const badge = document.querySelector('.notification-badge');
            const headerUnread = document.querySelector('.notification-dropdown .dropdown-header-email');
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

        function showSuccessMessage(msg) {
            const banner = document.createElement('div');
            banner.className = 'unread-banner';
            banner.style.cssText = 'background:#edf7f2;border-color:#c8e6d4;color:#1e9e62;margin-bottom:16px;';
            banner.innerHTML = `<i class="bi bi-check-circle"></i> ${msg}`;

            const container = document.querySelector('.notifications-page');
            const header = container?.querySelector('.notifications-header');
            if (header?.nextElementSibling) {
                header.nextElementSibling.insertAdjacentElement('beforebegin', banner);
            }

            setTimeout(() => banner.remove(), 4000);
        }

        async function ajaxSubmit(form, onSuccess) {
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

            try {
                const res = await fetch(form.action, options);
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    let errMsg = data.message || 'Error performing action';
                    if (data.errors) {
                        const firstKey = Object.keys(data.errors)[0];
                        if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey].length > 0) {
                            errMsg = data.errors[firstKey][0];
                        }
                    }
                    alert(errMsg);
                    return;
                }
                onSuccess(data);
            } catch (err) {
                console.error('AJAX error:', err);
                alert('Network error. Please try again.');
            }
        }

        document.querySelectorAll('[data-ajax-mark-read]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                await ajaxSubmit(form, (data) => {
                    const card = form.closest('[data-notification-id]');
                    card?.classList.remove('unread');
                    form.remove();
                    updateUnreadUi(data.unreadCount ?? 0);
                    showSuccessMessage('Marked as read');
                });
            });
        });

        const markAllForm = document.querySelector('[data-ajax-mark-all]');
        if (markAllForm) {
            markAllForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                await ajaxSubmit(markAllForm, () => {
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        card.querySelector('[data-ajax-mark-read]')?.remove();
                    });
                    updateUnreadUi(0);
                    showSuccessMessage('All notifications marked as read');
                });
            });
        }

        // AJAX handler for approve button
        document.querySelectorAll('[data-approve-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn?.disabled) return;
                if (submitBtn) submitBtn.disabled = true;

                showProcessingModal('Approving Delineation...', 'Approving and publishing delineation, please wait.');

                try {
                    await ajaxSubmit(form, (data) => {
                        const card = form.closest('[data-notification-id]');
                        card?.remove();
                        showSuccessMessage(data.message || 'Delineation approved successfully');
                        updateUnreadUi(data.unreadCount ?? 0);
                    });
                } finally {
                    hideProcessingModal();
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        });

        // AJAX handler for reject form submission
        document.querySelectorAll('[data-reject-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                const textarea = form.querySelector('textarea[name="rejection_notes"]');
                const notes = textarea?.value?.trim() || '';

                if (notes.length < 10) {
                    alert('Please enter at least 10 characters for rejection notes.');
                    textarea?.focus();
                    return;
                }

                if (submitBtn?.disabled) return;
                if (submitBtn) submitBtn.disabled = true;

                showProcessingModal('Processing Rejection...', 'Submitting your rejection notes, please wait.');

                try {
                    await ajaxSubmit(form, (data) => {
                        const card = form.closest('[data-notification-id]');
                        card?.remove();
                        showSuccessMessage(data.message || 'Delineation rejected successfully');
                        updateUnreadUi(data.unreadCount ?? 0);
                    });
                } finally {
                    hideProcessingModal();
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        });

        // AJAX handler for delete button
        document.querySelectorAll('[data-delete-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                if (!confirm('Delete this notification?')) return;
                await ajaxSubmit(form, (data) => {
                    const card = form.closest('[data-notification-id]');
                    card?.remove();
                    showSuccessMessage('Notification deleted');
                    updateUnreadUi(data.unreadCount ?? 0);
                });
            });
        });

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