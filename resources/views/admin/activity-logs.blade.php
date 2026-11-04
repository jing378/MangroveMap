@extends('layouts.admin')

@section('title', 'Activity Logs - MangroveMap')

@section('content')
<div id="activity-logs-section">
    <div class="page-title">Activity Logs</div>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Today</div>
            <div class="stat-value green">{{ $totalToday }}</div>
            <div class="stat-change">Activities recorded today</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Last 7 Days</div>
            <div class="stat-value">{{ $totalWeek }}</div>
            <div class="stat-change">Activities this week</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-value">{{ $activeUsersWeek }}</div>
            <div class="stat-change">With activity in the last 7 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rejected / Failed</div>
            <div class="stat-value {{ $failedWeek ? 'red' : '' }}">{{ $failedWeek }}</div>
            <div class="stat-change">In the last 7 days</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-journal-text"></i> All Activity</div>

        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="filter-bar">
            @if($filteredUser)
            <input type="hidden" name="user" value="{{ $filteredUser->id }}">
            @endif
            <input type="search" name="search" class="search-input" placeholder="Search activity, user name or email..." value="{{ $filters['search'] ?? '' }}">
            <select name="module" class="filter-select" aria-label="Module">
                <option value="">All Modules</option>
                @foreach($modules as $module)
                <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                @endforeach
            </select>
            <select name="status" class="filter-select" aria-label="Status">
                <option value="">All Status</option>
                <option value="success" @selected(($filters['status'] ?? '') === 'success')>Success</option>
                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected / Failed</option>
            </select>
            <select name="role" class="filter-select" aria-label="User role">
                <option value="">All Roles</option>
                <option value="end_user" @selected(($filters['role'] ?? '') === 'end_user')>Resident</option>
                <option value="expert" @selected(($filters['role'] ?? '') === 'expert')>Expert</option>
                <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Administrator</option>
            </select>
            <label class="date-filter">From <input type="date" name="from" class="filter-select" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="date-filter">To <input type="date" name="to" class="filter-select" value="{{ $filters['to'] ?? '' }}"></label>
            <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            @if(array_filter($filters))
            <a href="{{ route('admin.activity-logs.index') }}" class="btn-secondary">Clear</a>
            @endif
        </form>

        @if($filteredUser)
        <div class="filter-chip">
            Showing activity by <strong>{{ $filteredUser->name }}</strong>
            <a href="{{ route('admin.activity-logs.index', \Illuminate\Support\Arr::except($filters, 'user')) }}" title="Show all users"><i class="bi bi-x-lg"></i></a>
        </div>
        @endif

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Activity</th>
                        <th>Module</th>
                        <th>Status</th>
                        <th class="sr-only">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activities as $activity)
                    @php($detailUrl = route('admin.activity-logs.show', $activity))
                    <tr class="log-row" data-href="{{ $detailUrl }}">
                        <td class="nowrap">
                            <div>{{ $activity->created_at?->format('M j, Y') ?? '—' }}</div>
                            <div class="muted">{{ $activity->created_at?->format('g:i A') }}</div>
                        </td>
                        <td>
                            <div class="user-name">{{ $activity->user?->name ?? 'Deleted user' }}</div>
                            <div class="muted">{{ $activity->user?->roleLabel() }}</div>
                        </td>
                        <td>{{ $activity->activity }}</td>
                        <td>{{ $activity->module }}</td>
                        <td><span class="badge {{ $activity->status }}">{{ $activity->statusLabel() }}</span></td>
                        <td><a href="{{ $detailUrl }}" class="action-btn" title="View log details"><i class="bi bi-chevron-right"></i></a></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="empty-row">No activity matches these filters.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($activities->hasPages())
        <div class="pagination">
            @if($activities->onFirstPage())
            <span class="pagination-btn disabled">← Previous</span>
            @else
            <a class="pagination-btn" href="{{ $activities->previousPageUrl() }}">← Previous</a>
            @endif
            <div class="pagination-info">Page {{ $activities->currentPage() }} of {{ $activities->lastPage() }} ({{ $activities->total() }} entries)</div>
            @if($activities->hasMorePages())
            <a class="pagination-btn" href="{{ $activities->nextPageUrl() }}">Next →</a>
            @else
            <span class="pagination-btn disabled">Next →</span>
            @endif
        </div>
        @else
        <div class="pagination-info">{{ $activities->total() }} {{ \Illuminate\Support\Str::plural('entry', $activities->total()) }}</div>
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
        border: 1px solid #e0e8e0;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        background: #fff;
        color: #3a5a3a;
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

    .date-filter {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        color: #9ab0a0;
        text-transform: uppercase;
    }

    .date-filter .filter-select {
        min-width: 0;
    }

    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        margin-bottom: 16px;
        border-radius: 999px;
        background: #edf7f2;
        border: 1px solid #b0e0c0;
        font-size: 12px;
        color: #145a33;
    }

    .filter-chip a {
        color: inherit;
    }

    .log-row {
        cursor: pointer;
    }

    .nowrap {
        white-space: nowrap;
    }

    .empty-row {
        text-align: center;
        padding: 32px 16px !important;
        color: #7a9a7a;
    }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
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
        border: 1px solid #e0e8e0;
        background: #fff;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        color: #3a5a3a;
        text-decoration: none;
    }

    a.pagination-btn:hover {
        border-color: #1e9e62;
        background: #edf7f2;
        color: #1e9e62;
    }

    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-info {
        font-size: 12px;
        color: #6a8a6a;
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
<script>
    // Whole rows open the log details; the chevron link keeps it keyboard-accessible.
    document.querySelectorAll('.log-row').forEach(row => {
        row.addEventListener('click', event => {
            if (event.target.closest('a, button')) return;
            window.location.href = row.dataset.href;
        });
    });
</script>
@endsection
