@extends('layouts.admin')

@section('title', 'Admin Dashboard - MangroveMap')

@section('content')
<div id="dashboard-section">
    <!-- Hero Banner (same as the resident/expert dashboard) -->
    <div class="dash-hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="bi bi-shield-lock"></i>
                <span>Administrator</span>
            </div>
            <h1>Welcome back, {{ Auth::user()->name }}</h1>
            <p>Manage user accounts, oversee mangrove delineations and drone imagery, and keep the AI models running.</p>
        </div>
    </div>

    <div class="grid cols-4">
        <a class="stat-card" href="{{ route('admin.users') }}">
            <div class="stat-label"><i class="bi bi-people"></i> Users</div>
            <div class="stat-value green">{{ number_format($totalUsers) }}</div>
            <div class="stat-change positive">{{ number_format($newUsersThisMonth) }} new this month · {{ number_format($expertCount) }} {{ Str::plural('expert', $expertCount) }}</div>
        </a>
        <a class="stat-card" href="{{ route('admin.map-layers.index', ['status' => 'published']) }}">
            <div class="stat-label"><i class="bi bi-tree"></i> Mangroves mapped</div>
            <div class="stat-value">{{ number_format($approvedHectares, $approvedHectares < 100 ? 2 : 1) }} <span class="stat-unit">ha</span></div>
            <div class="stat-change">{{ number_format($approvedAreas) }} approved {{ Str::plural('area', $approvedAreas) }}</div>
        </a>
        <a class="stat-card" href="{{ route('admin.map-layers.index', ['status' => 'pending']) }}">
            <div class="stat-label"><i class="bi bi-hourglass-split"></i> Waiting for experts</div>
            <div class="stat-value {{ $pendingDelineations > 0 ? 'amber' : '' }}">{{ number_format($pendingDelineations) }}</div>
            <div class="stat-change">
                @if($oldestPendingAt)
                    Oldest submitted {{ $oldestPendingAt->diffForHumans() }}
                @else
                    Nothing to review
                @endif
            </div>
        </a>
        <a class="stat-card" href="{{ route('admin.map-layers.index', ['tab' => 'imagery']) }}">
            <div class="stat-label"><i class="bi bi-camera"></i> Drone imagery</div>
            <div class="stat-value">{{ number_format($droneTotal) }}</div>
            <div class="stat-change {{ $droneFailed > 0 ? 'negative' : '' }}">
                @php
                    $droneParts = array_filter([
                        $droneProcessing ? number_format($droneProcessing).' processing' : null,
                        $droneAwaitingOwner ? number_format($droneAwaitingOwner).' awaiting owner' : null,
                        $droneFailed ? number_format($droneFailed).' failed' : null,
                    ]);
                @endphp
                {{ $droneParts ? implode(' · ', $droneParts) : 'All layers processed' }}
            </div>
        </a>
    </div>

    <div class="grid attention-grid">
        <div class="section attention">
            <div class="section-title"><i class="bi bi-bell"></i> Needs attention
                @if($attention)
                    <span class="badge pending">{{ count($attention) }}</span>
                @endif
            </div>
            @forelse($attention as $item)
                <a class="attention-item tone-{{ $item['tone'] }}" href="{{ $item['url'] }}">
                    <i class="bi {{ $item['icon'] }} attention-icon" aria-hidden="true"></i>
                    <span class="attention-text">
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ $item['text'] }}</span>
                    </span>
                    <span class="attention-action">{{ $item['action'] }} <i class="bi bi-arrow-right"></i></span>
                </a>
            @empty
                <div class="attention-empty">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    <span>All clear. No failed jobs, waiting applications, overdue reviews or new feedback.</span>
                </div>
            @endforelse
        </div>

        <div class="section">
            <div class="section-title"><i class="bi bi-cpu"></i> AI models &amp; storage
                <a href="{{ route('admin.models') }}" class="section-link">Manage <i class="bi bi-arrow-right"></i></a>
            </div>
            @foreach($aiModels as $model)
                <div class="model-row">
                    <span class="model-dot {{ $model['ready'] ? 'ok' : 'missing' }}" aria-hidden="true"></span>
                    <span class="model-text">
                        <span class="model-kind">{{ ucfirst($model['label']) }}</span>
                        <strong>{{ $model['name'] ?? 'Not installed' }}</strong>
                        <span class="cell-sub">{{ $model['detail'] }}</span>
                    </span>
                </div>
            @endforeach
            <div class="model-row">
                <i class="bi bi-hdd storage-icon" aria-hidden="true"></i>
                <span class="model-text">
                    <span class="model-kind">Uploaded files</span>
                    <span>Drone imagery: <strong>{{ \App\Http\Controllers\MapLayerController::formatBytes($storage['droneBytes']) }}</strong></span>
                    <span>Tile Explorer: <strong>{{ \App\Http\Controllers\MapLayerController::formatBytes($storage['tileBytes']) }}</strong>
                        <span class="cell-sub">({{ number_format($storage['tileSources']) }} {{ Str::plural('upload', $storage['tileSources']) }})</span></span>
                </span>
            </div>
        </div>
    </div>

    <div class="section" id="today-users">
        <div class="section-title"><i class="bi bi-calendar-check"></i> Today
            <span class="section-note">{{ now($todayTimezone)->format('F j, Y') }}</span>
            <a href="{{ route('admin.users') }}" class="section-link">All users <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="contributor-summary today-summary">
            <div class="contributor-metric">
                <div class="stat-value green">{{ number_format($newUsersToday) }}</div>
                <div class="stat-label">New sign-ups today</div>
            </div>
            <div class="contributor-metric">
                <div class="stat-value">{{ number_format($loggedInToday) }}</div>
                <div class="stat-label">Users who logged in today</div>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Today</th>
                        <th>Email</th>
                        <th>Signed up</th>
                        <th>Last login</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($todayUsers as $todayUser)
                    <tr>
                        <td>
                            <a href="{{ route('admin.users.show', $todayUser) }}" class="activity-link">{{ $todayUser->name }}</a>
                            <div class="cell-sub">{{ $todayUser->email }}</div>
                        </td>
                        <td>{{ $todayUser->roleLabel() }}</td>
                        <td>
                            @if($todayUser->signed_up_today)
                                <span class="badge success">New sign-up</span>
                            @else
                                <span class="badge secondary">Logged in</span>
                            @endif
                        </td>
                        <td>
                            @if($todayUser->email_verified_at)
                                <span class="badge success">Verified</span>
                            @else
                                <span class="badge pending">Not verified</span>
                            @endif
                        </td>
                        <td>{{ $todayUser->created_at->timezone($todayTimezone)->format($todayUser->signed_up_today ? 'g:i A' : 'M j, Y') }}</td>
                        <td>{{ $todayUser->last_login_at?->timezone($todayTimezone)->format($todayUser->last_login_at->timezone($todayTimezone)->isToday() ? 'g:i A' : 'M j, Y') ?? 'Not yet' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">No sign-ups or logins yet today.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($todayUsers->count() >= 25)
            <p class="section-footnote">Showing the first {{ $todayUsers->count() }}. See All users for the rest.</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-people-fill"></i> Delineation Contributors</div>
        <div class="contributor-summary">
            <div class="contributor-metric">
                <div class="stat-value green">{{ number_format($totalContributors) }}</div>
                <div class="stat-label">Total Contributors</div>
            </div>
            <div class="contributor-metric">
                <div class="stat-value">{{ number_format($approvedContributors) }}</div>
                <div class="stat-label">With Approved Work</div>
            </div>
            <div class="contributor-metric">
                <div class="stat-value">{{ number_format($activeContributorsThisMonth) }}</div>
                <div class="stat-label">Active This Month</div>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Top Contributors</th>
                        <th>Role</th>
                        <th>Delineations</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Last Submission</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topContributors as $contributor)
                    <tr>
                        <td>{{ $contributor->user?->name ?? 'Unknown' }}</td>
                        <td>{{ $contributor->user?->roleLabel() ?? '—' }}</td>
                        <td>{{ $contributor->total_count }}</td>
                        <td><span class="badge success">{{ (int) $contributor->approved_count }}</span></td>
                        <td><span class="badge pending">{{ (int) $contributor->pending_count }}</span></td>
                        <td>{{ $contributor->last_submitted_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">No delineations submitted yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-graph-up"></i> Recent User Activity
            <a href="{{ route('admin.activity-logs.index') }}" class="section-link">View all logs <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Activity</th>
                        <th>Module</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentActivities as $activity)
                    <tr>
                        <td>{{ $activity->user?->name ?? 'Unknown' }}</td>
                        <td><a href="{{ route('admin.activity-logs.show', $activity) }}" class="activity-link">{{ $activity->activity }}</a></td>
                        <td>{{ $activity->module }}</td>
                        <td><span class="badge {{ $activity->status }}">{{ ucfirst($activity->status) }}</span></td>
                        <td>{{ $activity->created_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">No activity recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-arrow-up-right-circle"></i> Weekly Growth (last 4 weeks)</div>
        <div class="chart-container">
            <canvas id="growthChart"></canvas>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    /* Hero greeting: dark green in both themes, matching the resident/expert dashboard */
    .dash-hero {
        background: linear-gradient(135deg, #1b382b 0%, #165337 100%);
        border-radius: 16px;
        padding: 32px 36px;
        margin-bottom: 28px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(22, 83, 55, 0.15);
    }

    .dash-hero::after {
        content: "";
        position: absolute;
        right: -40px;
        bottom: -40px;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(30, 158, 98, 0.25) 0%, transparent 70%);
        pointer-events: none;
    }

    .hero-content {
        position: relative;
        z-index: 1;
    }

    .hero-content h1 {
        font-size: 26px;
        font-weight: 800;
        margin: 0 0 6px;
        letter-spacing: -0.5px;
        color: #fff;
    }

    .hero-content p {
        font-size: 14px;
        color: #b8d9c6;
        max-width: 600px;
        line-height: 1.5;
        margin: 0;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 12px;
        color: #a3e6be;
    }

    @media (max-width: 768px) {
        .dash-hero {
            padding: 22px 20px;
            margin-bottom: 18px;
        }

        .hero-content h1 {
            font-size: 21px;
            overflow-wrap: anywhere;
        }

        .hero-content p {
            font-size: 13px;
        }
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }

    .grid.cols-4 {
        grid-template-columns: repeat(4, 1fr);
    }

    .stat-card {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        transition: all 0.15s;
    }

    .stat-card:hover {
        border-color: #1e9e62;
        box-shadow: 0 4px 16px rgba(30, 158, 98, 0.1);
    }

    .stat-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #9ab0a0;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 8px;
    }

    .stat-value.green {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .stat-change {
        font-size: 12px;
        font-weight: 600;
        color: #7a9a7a;
    }

    .stat-change.positive {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .section {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        margin-bottom: 28px;
    }

    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-link {
        margin-left: auto;
        font-size: 12px;
        font-weight: 600;
        color: var(--fg-1e9e62, #1e9e62);
        text-decoration: none;
    }

    .section-link:hover,
    .activity-link:hover {
        text-decoration: underline;
    }

    .activity-link {
        color: inherit;
        text-decoration: none;
    }

    .contributor-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    .today-summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .section-note {
        margin-left: 6px;
        font-size: 12px;
        font-weight: 500;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .cell-sub {
        font-size: 11px;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .section-footnote {
        margin: 10px 0 0;
        font-size: 12px;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .contributor-metric {
        background: var(--bg-f5f7f6, #f5f7f6);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 10px;
        padding: 14px 16px;
    }

    .contributor-metric .stat-value {
        font-size: 24px;
        margin-bottom: 2px;
    }

    .contributor-metric .stat-label {
        margin-bottom: 0;
    }

    .table-container {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .table th {
        background: var(--bg-f5f7f6, #f5f7f6);
        padding: 12px 16px;
        text-align: left;
        font-weight: 700;
        color: #9ab0a0;
        border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        color: var(--fg-3a5a3a, #3a5a3a);
    }

    .table tr:hover {
        background: var(--bg-f5f7f6, #f5f7f6);
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
    }

    .badge.success {
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
        border: 1px solid var(--bd-b0e0c0, #b0e0c0);
    }

    .badge.pending {
        background: var(--bg-fdf5e8, #fdf5e8);
        color: var(--fg-c07818, #c07818);
        border: 1px solid var(--bd-e8cc98, #e8cc98);
    }

    .badge.rejected {
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
        border: 1px solid var(--bd-e8b8b0, #e8b8b0);
    }

    .badge.secondary {
        background: var(--bg-f0f4f0, #f0f4f0);
        color: var(--fg-6a8a6a, #6a8a6a);
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
    }

    a.stat-card {
        display: block;
        color: inherit;
        text-decoration: none;
    }

    .stat-label i {
        margin-right: 4px;
    }

    .stat-unit {
        font-size: 16px;
        font-weight: 700;
        color: #7a9a7a;
    }

    .stat-value.amber {
        color: var(--fg-c07818, #c07818);
    }

    .stat-change.negative {
        color: var(--fg-d04030, #d04030);
    }

    .grid.attention-grid {
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        gap: 20px;
        margin-bottom: 0;
        align-items: start;
    }

    .attention-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-left-width: 4px;
        border-radius: 10px;
        margin-bottom: 10px;
        color: inherit;
        text-decoration: none;
        transition: background 0.15s;
    }

    .attention-item:last-child {
        margin-bottom: 0;
    }

    .attention-item:hover {
        background: var(--bg-f5f7f6, #f5f7f6);
    }

    .attention-item.tone-danger {
        border-left-color: #d04030;
    }

    .attention-item.tone-warning {
        border-left-color: #c07818;
    }

    .attention-item.tone-info {
        border-left-color: #1e9e62;
    }

    .attention-icon {
        font-size: 18px;
        flex: 0 0 auto;
    }

    .tone-danger .attention-icon {
        color: var(--fg-d04030, #d04030);
    }

    .tone-warning .attention-icon {
        color: var(--fg-c07818, #c07818);
    }

    .tone-info .attention-icon {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .attention-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
        flex: 1 1 auto;
        font-size: 12px;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .attention-text strong {
        font-size: 13px;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .attention-action {
        flex: 0 0 auto;
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-1e9e62, #1e9e62);
        white-space: nowrap;
    }

    .attention-empty {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px;
        border-radius: 10px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-2f5a46, #2f5a46);
        font-size: 13px;
    }

    .attention-empty i {
        color: var(--fg-1e9e62, #1e9e62);
        font-size: 18px;
    }

    .model-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
    }

    .model-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .model-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-top: 5px;
        flex: 0 0 auto;
    }

    .storage-icon {
        font-size: 13px;
        color: #9ab0a0;
        margin-top: 1px;
        flex: 0 0 10px;
    }

    .model-dot.ok {
        background: #16a34a;
    }

    .model-dot.missing {
        background: #d04030;
    }

    .model-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
        font-size: 13px;
        color: var(--fg-1a2e1a, #1a2e1a);
        overflow-wrap: anywhere;
    }

    .model-kind {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #9ab0a0;
    }

    @media (max-width: 1024px) {
        .grid.attention-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }
    }

    @media (max-width: 768px) {
        .attention-item {
            flex-wrap: wrap;
            gap: 8px 12px;
        }

        .attention-action {
            margin-left: 32px;
        }
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin-top: 16px;
    }

    @media (max-width: 1024px) {
        .grid.cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }

        .notifications-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .grid.cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid {
            gap: 12px;
        }

        /* The counts stay side by side on phones: short numbers, so they fit and the tables come sooner. */
        .contributor-summary,
        .today-summary {
            gap: 8px;
        }

        .contributor-metric {
            padding: 10px 10px;
            min-width: 0;
        }

        .contributor-metric .stat-value {
            font-size: 20px;
        }

        .contributor-metric .stat-label {
            font-size: 9.5px;
            letter-spacing: 0.3px;
            overflow-wrap: anywhere;
        }

        .section {
            padding: 16px;
            margin-bottom: 16px;
        }

        .stat-card {
            padding: 14px;
            min-width: 0;
        }

        .stat-value {
            font-size: 24px;
            margin-bottom: 4px;
            overflow-wrap: anywhere;
        }

        .stat-label {
            font-size: 10px;
            margin-bottom: 6px;
        }

        .stat-change {
            font-size: 11px;
        }

        .section-title {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 360px) {
        .grid.cols-4 {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
    const chartOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true
            }
        },
        animation: {
            duration: 600
        }
    };

    new Chart(document.getElementById('growthChart'), {
        type: 'line',
        data: {
            labels: @json($growthChart['labels']),
            datasets: [{
                    label: 'New users',
                    data: @json($growthChart['users']),
                    borderColor: '#1e9e62',
                    backgroundColor: 'rgba(30,158,98,.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                },
                {
                    label: 'Delineations submitted',
                    data: @json($growthChart['delineations']),
                    borderColor: '#5ab8de',
                    backgroundColor: 'rgba(90,184,222,.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                },
                {
                    label: 'Drone uploads',
                    data: @json($growthChart['drone']),
                    borderColor: '#d9a23a',
                    backgroundColor: 'rgba(217,162,58,.08)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                }
            ]
        },
        options: {
            ...chartOpts,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#9ab0a0',
                        precision: 0
                    }
                },
                x: {
                    ticks: {
                        color: '#9ab0a0'
                    }
                }
            }
        }
    });
</script>
@endsection
