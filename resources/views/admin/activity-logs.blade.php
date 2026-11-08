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
            <input type="search" name="search" class="search-input" placeholder="Search activity, user name or email..." aria-label="Search activity" value="{{ $filters['search'] ?? '' }}">
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
            <label class="date-filter">
                <span>From</span>
                <input type="date" name="from" class="filter-select" value="{{ $filters['from'] ?? '' }}">
            </label>
            <label class="date-filter">
                <span>To</span>
                <input type="date" name="to" class="filter-select" value="{{ $filters['to'] ?? '' }}">
            </label>
            <div class="filter-actions">
                <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                @if(array_filter($filters))
                <a href="{{ route('admin.activity-logs.index') }}" class="btn-secondary"><i class="bi bi-x-lg"></i> Clear</a>
                @endif
            </div>
        </form>

        @if($filteredUser)
        <div class="filter-chip">
            Showing activity by <strong>{{ $filteredUser->name }}</strong>
            <a href="{{ route('admin.activity-logs.index', \Illuminate\Support\Arr::except($filters, 'user')) }}" title="Show all users"><i class="bi bi-x-lg"></i></a>
        </div>
        @endif

        <div class="table-container log-table-container">
            <table class="table log-table">
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
                        <td class="nowrap cell-time">
                            <div>{{ $activity->created_at?->format('M j, Y') ?? '—' }}</div>
                            <div class="muted">{{ $activity->created_at?->format('g:i A') }}</div>
                        </td>
                        <td class="cell-user">
                            <div class="user-name">{{ $activity->user?->name ?? 'Deleted user' }}</div>
                            <div class="muted">{{ $activity->user?->roleLabel() }}</div>
                        </td>
                        <td class="cell-activity">{{ $activity->activity }}</td>
                        <td class="cell-module"><span class="module-tag">{{ $activity->module }}</span></td>
                        <td class="cell-status"><span class="badge {{ $activity->status }}">{{ $activity->statusLabel() }}</span></td>
                        <td class="cell-open"><a href="{{ $detailUrl }}" class="action-btn" title="View log details" aria-label="View log details"><i class="bi bi-chevron-right"></i></a></td>
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
    /* Filters: search on its own row, then an even grid of dropdowns and dates, then the buttons. */
    .filter-bar {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 10px;
        margin-bottom: 16px;
        align-items: stretch;
    }

    .search-input,
    .filter-select {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
        padding: 10px 14px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        background-color: var(--bg-ffffff, #fff);
        color: var(--fg-3a5a3a, #3a5a3a);
    }

    .filter-bar .search-input {
        grid-column: 1 / -1;
    }

    .filter-select {
        cursor: pointer;
    }

    .search-input:focus,
    .filter-select:focus,
    .date-filter:focus-within {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    /* A date field looks like the dropdowns: one bordered box with a small "From"/"To" label inside. */
    .date-filter {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        padding: 0 0 0 12px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        background: var(--bg-ffffff, #fff);
        cursor: pointer;
    }

    .date-filter span {
        flex: none;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.5px;
        color: #9ab0a0;
        text-transform: uppercase;
    }

    .date-filter .filter-select {
        flex: 1;
        border: none;
        box-shadow: none;
        padding-left: 0;
        background-color: transparent;
    }

    .filter-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        grid-column: -2 / -1;
    }

    .filter-actions .btn-primary,
    .filter-actions .btn-secondary {
        flex: 1;
        justify-content: center;
        white-space: nowrap;
    }

    .log-table .cell-activity {
        color: var(--fg-1a2e1a, #1a2e1a);
        min-width: 220px;
    }

    .module-tag {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 6px;
        background: var(--bg-f5f7f6, #f5f7f6);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        font-size: 11px;
        font-weight: 600;
        color: var(--fg-3a5a3a, #3a5a3a);
        white-space: nowrap;
    }

    .log-table .cell-open .action-btn {
        margin-left: auto;
    }

    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        margin-bottom: 16px;
        border-radius: 999px;
        background: var(--bg-edf7f2, #edf7f2);
        border: 1px solid var(--bd-b0e0c0, #b0e0c0);
        font-size: 12px;
        color: var(--fg-145a33, #145a33);
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
        /* Same idea as the Users page: everything full width, dropdowns two per row. */
        .filter-bar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .filter-actions {
            grid-column: 1 / -1;
        }

        .filter-chip {
            max-width: 100%;
            box-sizing: border-box;
            overflow-wrap: anywhere;
        }

        /* Each log becomes a card: what happened + status, who, then when and where. Tap opens it. */
        .log-table-container {
            overflow-x: visible;
        }

        .log-table thead {
            display: none;
        }

        .log-table,
        .log-table tbody {
            display: block;
            width: 100%;
        }

        .log-table tr.log-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            grid-template-areas:
                "activity activity status"
                "user user open"
                "time module open";
            column-gap: 10px;
            row-gap: 6px;
            align-items: center;
            padding: 12px 14px;
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 12px;
            background: var(--bg-ffffff, #fff);
        }

        .log-table tr.log-row + tr {
            margin-top: 10px;
        }

        .log-table tr.log-row:active {
            background: var(--bg-f5f7f6, #f5f7f6);
        }

        .log-table td {
            display: block;
            padding: 0;
            border: none;
            min-width: 0;
        }

        .log-table .cell-activity {
            grid-area: activity;
            min-width: 0;
            font-weight: 600;
            font-size: 13px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .log-table .cell-status {
            grid-area: status;
            align-self: start;
        }

        .log-table .cell-user {
            grid-area: user;
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0 6px;
            overflow-wrap: anywhere;
        }

        .log-table .cell-user .user-name {
            font-size: 12px;
        }

        .log-table .cell-time {
            grid-area: time;
            display: flex;
            gap: 6px;
            font-size: 11px;
            color: #9ab0a0;
        }

        .log-table .cell-module {
            grid-area: module;
            justify-self: end;
        }

        .log-table .cell-open {
            grid-area: open;
        }

        .log-table .cell-open .action-btn {
            width: 30px;
            height: 30px;
        }

        .log-table td.empty-row {
            border: 1px dashed var(--bd-e0e8e0, #e0e8e0);
            border-radius: 12px;
        }

        .pagination {
            flex-wrap: wrap;
            gap: 10px;
        }

        .pagination-info {
            order: -1;
            flex-basis: 100%;
            text-align: center;
        }

        .pagination-btn {
            flex: 1;
            text-align: center;
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
