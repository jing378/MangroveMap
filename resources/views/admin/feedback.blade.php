@extends('layouts.admin')

@section('title', 'Feedback - MangroveMap')

@section('content')
<div id="feedback-section">
    <div class="page-title">Feedback</div>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">New</div>
            <div class="stat-value {{ ($counts['new'] ?? 0) ? 'green' : '' }}">{{ $counts['new'] ?? 0 }}</div>
            <div class="stat-change">Waiting for review</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Reviewed</div>
            <div class="stat-value">{{ $counts['reviewed'] ?? 0 }}</div>
            <div class="stat-change">Seen, not resolved yet</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Resolved</div>
            <div class="stat-value">{{ $counts['resolved'] ?? 0 }}</div>
            <div class="stat-change">Fixed or answered</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Last 7 Days</div>
            <div class="stat-value">{{ $totalWeek }}</div>
            <div class="stat-change">Feedback received this week</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-chat-left-text"></i> All Feedback</div>

        <form method="GET" action="{{ route('admin.feedback.index') }}" class="filter-bar">
            <input type="search" name="search" class="search-input" placeholder="Search message, user name or email..." value="{{ $filters['search'] ?? '' }}">
            <select name="type" class="filter-select" aria-label="Type">
                <option value="">All Types</option>
                @foreach(\App\Models\Feedback::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="filter-select" aria-label="Status">
                <option value="">All Status</option>
                @foreach(\App\Models\Feedback::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            @if(array_filter($filters))
            <a href="{{ route('admin.feedback.index') }}" class="btn-secondary">Clear</a>
            @endif
        </form>

        <div class="feedback-list">
            @forelse($feedback as $item)
            <article class="feedback-item {{ $item->status === 'new' ? 'is-new' : '' }}">
                <div class="feedback-item-head">
                    <div>
                        <div class="user-name">{{ $item->user?->name ?? 'Deleted user' }}</div>
                        <div class="muted">
                            {{ $item->user?->roleLabel() }}@if($item->user) · {{ $item->user->email }}@endif
                        </div>
                    </div>
                    <div class="feedback-item-meta">
                        <span class="badge type-{{ $item->type }}">{{ $item->typeLabel() }}</span>
                        <span class="badge {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                        <span class="muted" title="{{ $item->created_at?->format('M j, Y g:i A') }}">{{ $item->created_at?->diffForHumans() }}</span>
                    </div>
                </div>

                <p class="feedback-item-message">{{ $item->message }}</p>

                <div class="feedback-item-foot">
                    <div class="feedback-item-links">
                        @if($item->page_url)
                        <a href="{{ url($item->page_url) }}" target="_blank" rel="noopener" class="feedback-link"><i class="bi bi-link-45deg"></i> {{ \Illuminate\Support\Str::limit($item->page_url, 60) }}</a>
                        @endif
                        @if($item->screenshot_path)
                        <a href="{{ route('admin.feedback.screenshot', $item) }}" target="_blank" rel="noopener" class="feedback-link"><i class="bi bi-image"></i> View screenshot</a>
                        @endif
                    </div>
                    <div class="feedback-item-actions">
                        <form method="POST" action="{{ route('admin.feedback.update', $item) }}" class="feedback-status-form">
                            @csrf
                            @method('PUT')
                            <select name="status" class="filter-select" aria-label="Change status" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                                @foreach(\App\Models\Feedback::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected($item->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                        <form method="POST" action="{{ route('admin.feedback.destroy', $item) }}" data-confirm="Delete this feedback?" data-confirm-message="This cannot be undone." data-confirm-label="Delete" data-confirm-tone="danger" data-confirm-icon="bi-trash" data-busy-label="Deleting...">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn danger" title="Delete feedback" aria-label="Delete feedback"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </article>
            @empty
            <div class="empty-row">No feedback matches these filters.</div>
            @endforelse
        </div>

        @if($feedback->hasPages())
        <div class="pagination">
            @if($feedback->onFirstPage())
            <span class="pagination-btn disabled">← Previous</span>
            @else
            <a class="pagination-btn" href="{{ $feedback->previousPageUrl() }}">← Previous</a>
            @endif
            <div class="pagination-info">Page {{ $feedback->currentPage() }} of {{ $feedback->lastPage() }} ({{ $feedback->total() }} entries)</div>
            @if($feedback->hasMorePages())
            <a class="pagination-btn" href="{{ $feedback->nextPageUrl() }}">Next →</a>
            @else
            <span class="pagination-btn disabled">Next →</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection

@section('styles')
@include('admin.partials.activity-log-styles')
<style>
    .filter-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-input,
    .filter-select {
        padding: 10px 14px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-3a5a3a, #3a5a3a);
    }

    .search-input {
        flex: 1;
        min-width: 220px;
    }

    .filter-select {
        min-width: 130px;
        cursor: pointer;
    }

    .search-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .feedback-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .feedback-item {
        padding: 16px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-left: 3px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 10px;
        background: var(--bg-ffffff, #fff);
    }

    .feedback-item.is-new {
        border-left-color: #c07818;
        background: var(--bg-fffcf5, #fffcf5);
    }

    .feedback-item-head,
    .feedback-item-foot {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .feedback-item-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .badge.type-bug {
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
        border-color: var(--bd-e8b8b0, #e8b8b0);
    }

    .badge.type-suggestion {
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
        border-color: var(--bd-b0e0c0, #b0e0c0);
    }

    .badge.type-question {
        background: var(--bg-eef4fd, #eef4fd);
        color: var(--fg-2563eb, #2563eb);
        border-color: var(--bd-bfd3f5, #bfd3f5);
    }

    .feedback-item-message {
        margin: 12px 0;
        color: var(--fg-1a2e1a, #1a2e1a);
        font-size: 13px;
        line-height: 1.55;
        white-space: pre-line;
        overflow-wrap: anywhere;
    }

    .feedback-item-foot {
        align-items: center;
    }

    .feedback-item-links {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
    }

    .feedback-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--fg-1e9e62, #1e9e62);
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        overflow-wrap: anywhere;
    }

    .feedback-link:hover {
        text-decoration: underline;
    }

    .feedback-item-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    .feedback-item-actions .filter-select {
        min-width: 120px;
        padding: 7px 10px;
    }

    .action-btn {
        cursor: pointer;
        font-size: 14px;
    }

    .action-btn.danger {
        color: var(--fg-d04030, #d04030);
    }

    .action-btn.danger:hover {
        border-color: #d04030;
        background: var(--bg-fdf0ee, #fdf0ee);
    }

    .empty-row {
        text-align: center;
        padding: 32px 16px;
        color: #7a9a7a;
        font-size: 13px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 16px;
        margin-top: 20px;
    }

    .pagination-btn {
        padding: 8px 16px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        background: var(--bg-ffffff, #fff);
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        color: var(--fg-3a5a3a, #3a5a3a);
        text-decoration: none;
    }

    a.pagination-btn:hover {
        border-color: #1e9e62;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
    }

    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-info {
        font-size: 12px;
        color: var(--fg-6a8a6a, #6a8a6a);
        font-weight: 500;
    }

    @media (max-width: 768px) {
        .filter-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .pagination {
            flex-direction: column;
            gap: 12px;
        }
    }
</style>
@endsection

@section('scripts')
@include('partials.flash-toast')
@endsection
