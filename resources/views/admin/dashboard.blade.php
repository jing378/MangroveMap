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

        @include('partials.field-conditions-card')
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
                @if(isset($item['reminder']))
                    {{-- With a reminder: the card is a link plus a "Send reminder" button (a button can't sit inside a link). --}}
                    @php $reminder = $item['reminder']; @endphp
                    <div class="attention-item has-reminder tone-{{ $item['tone'] }}">
                        <a class="attention-link" href="{{ $item['url'] }}">
                            <i class="bi {{ $item['icon'] }} attention-icon" aria-hidden="true"></i>
                            <span class="attention-text">
                                <strong>{{ $item['title'] }}</strong>
                                <span>{{ $item['text'] }}</span>
                            </span>
                            <span class="attention-action">{{ $item['action'] }} <i class="bi bi-arrow-right"></i></span>
                        </a>
                        @if($reminder['recent'])
                            <button type="button" class="reminder-btn" disabled
                                title="Sent by {{ $reminder['recent']->sender?->name ?? 'an admin' }} to {{ $reminder['recent']->recipients }} {{ Str::plural('person', $reminder['recent']->recipients) }}. It can be sent again {{ $reminder['recent']->availableAgainAt()->diffForHumans() }}.">
                                <i class="bi bi-check2" aria-hidden="true"></i> Reminded {{ $reminder['recent']->created_at->diffForHumans(null, true, true) }} ago
                            </button>
                        @else
                            <button type="button" class="reminder-btn" data-reminder
                                data-reminder-kind="{{ $reminder['kind'] }}"
                                data-title="{{ $reminder['title'] }}"
                                data-who="{{ $reminder['who'] }}">
                                <i class="bi bi-alarm" aria-hidden="true"></i> Send reminder
                            </button>
                        @endif
                    </div>
                @else
                <a class="attention-item tone-{{ $item['tone'] }}" href="{{ $item['url'] }}">
                    <i class="bi {{ $item['icon'] }} attention-icon" aria-hidden="true"></i>
                    <span class="attention-text">
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ $item['text'] }}</span>
                    </span>
                    <span class="attention-action">{{ $item['action'] }} <i class="bi bi-arrow-right"></i></span>
                </a>
                @endif
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

    {{-- Overview charts: plain HTML bars (label, bar, value) so every bar is a link to that list in Map Layers,
         the numbers stay readable text (the table view), and dark mode follows the theme like the rest. --}}
    <div class="grid overview-grid">
        {{-- 1. Where the drone surveys are on their way to the map; a stuck stage shows at a glance. --}}
        @php
            $stageMax = max(1, max(array_column($droneStages, 'count')));
            $stageTotal = array_sum(array_column($droneStages, 'count'));
        @endphp
        <div class="section chart-card">
            <div class="section-title"><i class="bi bi-camera"></i> Drone survey pipeline
                <a href="{{ route('admin.map-layers.index', ['tab' => 'imagery']) }}" class="section-link" title="Open Drone Surveys in Map Layers">View <i class="bi bi-arrow-right"></i></a>
            </div>
            <p class="chart-sub">{{ number_format($stageTotal) }} {{ Str::plural('survey', $stageTotal) }} by stage, in pipeline order.</p>
            @if($stageTotal === 0)
                <div class="chart-empty">No drone surveys uploaded yet.</div>
            @else
                <ul class="hbar-list" aria-label="Drone surveys per stage">
                    @foreach($droneStages as $stage)
                        <li>
                            <a class="hbar-row {{ $stage['count'] === 0 ? 'is-zero' : '' }} {{ $stage['key'] === 'failed' ? 'is-critical' : '' }}"
                                href="{{ $stage['url'] }}"
                                title="{{ $stage['label'] }}: {{ number_format($stage['count']) }} {{ Str::plural('survey', $stage['count']) }}. Open in Drone Surveys.">
                                <span class="hbar-label">
                                    @if($stage['key'] === 'failed')<i class="bi bi-exclamation-octagon" aria-hidden="true"></i>@endif
                                    {{ $stage['label'] }}
                                </span>
                                <span class="hbar-track"><span class="hbar-fill" style="width: {{ $stage['count'] ? max(2, round($stage['count'] / $stageMax * 100, 1)) : 0 }}%"></span></span>
                                <span class="hbar-value">{{ number_format($stage['count']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- 2. Review status as one stacked bar, plus how long reviews take: are the experts keeping up? --}}
        <div class="section chart-card">
            <div class="section-title"><i class="bi bi-clipboard-check"></i> Delineation reviews
                <a href="{{ route('admin.map-layers.index') }}" class="section-link" title="Open Delineations in Map Layers">View <i class="bi bi-arrow-right"></i></a>
            </div>
            @if($reviewStatus['total'] === 0)
                <div class="chart-empty">No delineations submitted yet.</div>
            @else
                <div class="review-headline">
                    @if($reviewStatus['average_hours'] !== null)
                        @php
                            $avg = $reviewStatus['average_hours'];
                            $avgText = $avg < 1 ? 'under 1 hour' : ($avg < 48 ? number_format($avg, $avg < 10 ? 1 : 0).' hours' : number_format($avg / 24, 1).' days');
                        @endphp
                        <span class="review-avg">{{ $avgText }}</span>
                        <span class="chart-sub">average time to review, from {{ number_format($reviewStatus['reviewed']) }} {{ Str::plural('review', $reviewStatus['reviewed']) }} in the last {{ $reviewStatus['review_days'] }} days (experts' own areas left out)</span>
                    @else
                        <span class="chart-sub">No resident delineations reviewed in the last {{ $reviewStatus['review_days'] }} days.</span>
                    @endif
                </div>
                <div class="stack-bar" role="img"
                    aria-label="{{ collect($reviewStatus['segments'])->map(fn ($s) => $s['label'].' '.$s['count'])->implode(', ') }}">
                    @foreach($reviewStatus['segments'] as $segment)
                        @if($segment['count'] > 0)
                            <a class="stack-seg seg-{{ $segment['key'] }}" href="{{ $segment['url'] }}"
                                style="flex-grow: {{ $segment['count'] }}"
                                title="{{ $segment['label'] }}: {{ number_format($segment['count']) }} ({{ round($segment['count'] / $reviewStatus['total'] * 100) }}%)"></a>
                        @endif
                    @endforeach
                </div>
                <ul class="stack-legend">
                    @foreach($reviewStatus['segments'] as $segment)
                        <li>
                            <a href="{{ $segment['url'] }}">
                                <span class="legend-swatch seg-{{ $segment['key'] }}" aria-hidden="true"></span>
                                {{ $segment['label'] }}
                                <strong>{{ number_format($segment['count']) }}</strong>
                                <span class="legend-share">{{ round($segment['count'] / $reviewStatus['total'] * 100) }}%</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="chart-sub chart-foot">
                    <i class="bi bi-pencil" aria-hidden="true"></i> {{ number_format($reviewStatus['sources']['manual'] ?? 0) }} hand-drawn
                    · <i class="bi bi-camera" aria-hidden="true"></i> {{ number_format($reviewStatus['sources']['drone'] ?? 0) }} from drone surveys (AI-assisted)
                </p>
            @endif
        </div>

        {{-- 3. Where the mapped mangroves are: approved hectares per municipality. --}}
        @php $haMax = max(0.01, max(array_column($hectaresByMunicipality, 'area_ha') ?: [0])); @endphp
        <div class="section chart-card">
            <div class="section-title"><i class="bi bi-geo-alt"></i> Hectares by municipality</div>
            <p class="chart-sub">Expert-approved mangrove areas, largest first.</p>
            @if($hectaresByMunicipality === [])
                <div class="chart-empty">No approved areas yet.</div>
            @else
                <ul class="hbar-list" aria-label="Approved hectares per municipality">
                    @foreach($hectaresByMunicipality as $row)
                        <li>
                            <{{ $row['url'] ? 'a' : 'div' }} class="hbar-row {{ $row['muted'] ? 'is-muted' : '' }}"
                                @if($row['url']) href="{{ $row['url'] }}" @endif
                                title="{{ $row['name'] }}: {{ number_format($row['area_ha'], 2) }} ha in {{ $row['area_count'] }} {{ Str::plural('area', $row['area_count']) }}">
                                <span class="hbar-label">{{ $row['name'] }}</span>
                                <span class="hbar-track"><span class="hbar-fill" style="width: {{ max(2, round($row['area_ha'] / $haMax * 100, 1)) }}%"></span></span>
                                <span class="hbar-value">{{ number_format($row['area_ha'], 2) }} <small>ha</small></span>
                            </{{ $row['url'] ? 'a' : 'div' }}>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @include('partials.mangrove-comparison', [
        'comparison' => $comparison,
        'cardClass' => 'section',
        'showSurveys' => true,
    ])

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
@include('admin.partials.reminder-dialog')
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
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 28px;
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
        flex: 1 1 auto;
        min-width: 0;
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

    .stat-label > .bi {
        margin-right: 6px;
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

    /* An item with a "Send reminder" button: the link part keeps the usual layout, the button sits at the end. */
    .attention-link {
        display: flex;
        align-items: center;
        gap: 14px;
        flex: 1 1 auto;
        min-width: 0;
        color: inherit;
        text-decoration: none;
    }

    .attention-item.has-reminder .reminder-btn {
        flex: none;
    }

    @media (max-width: 600px) {
        .attention-item.has-reminder {
            flex-wrap: wrap;
        }

        .attention-item.has-reminder .reminder-btn {
            flex: 1 1 100%;
            justify-content: center;
            min-height: 40px;
        }
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

    /* ---- Overview charts (drone pipeline, reviews, hectares by municipality) ---- */
    .grid.overview-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 0;
        align-items: stretch;
    }

    .chart-card {
        display: flex;
        flex-direction: column;
    }

    .chart-card .section-title {
        margin-bottom: 4px;
    }

    .chart-sub {
        margin: 0 0 12px;
        font-size: 12px;
        line-height: 1.45;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .chart-foot {
        margin: auto 0 0;
        padding-top: 12px;
        border-top: 1px solid var(--bd-edf2ed, #edf2ed);
    }

    .chart-empty {
        padding: 18px 0;
        font-size: 13px;
        color: #7a9a7a;
    }

    /* Horizontal bars: label, thin track with the bar, value. Each row links to that list. */
    .hbar-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 2px;
    }

    .hbar-row {
        display: grid;
        /* Wide enough for the longest stage ("Pending expert review") at the three-column width. */
        grid-template-columns: minmax(0, 152px) minmax(36px, 1fr) auto;
        align-items: center;
        gap: 10px;
        padding: 6px 8px;
        margin: 0 -8px;
        border-radius: 8px;
        color: var(--fg-1a2e1a, #1a2e1a);
        text-decoration: none;
        transition: background-color 0.15s ease;
    }

    a.hbar-row:hover,
    a.hbar-row:focus-visible {
        background: var(--bg-f3f8f4, #f3f8f4);
    }

    a.hbar-row:focus-visible {
        outline: 2px solid #1e9e62;
        outline-offset: -2px;
    }

    .hbar-label {
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hbar-label .bi {
        margin-right: 3px;
    }

    .hbar-track {
        height: 10px;
        border-radius: 999px;
        background: var(--bg-edf2ed, #edf2ed);
        overflow: hidden;
    }

    .hbar-fill {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: #1e9e62;
    }

    .hbar-value {
        min-width: 34px;
        text-align: right;
        font-size: 12.5px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .hbar-value small {
        font-size: 10.5px;
        font-weight: 600;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    /* A stage nobody is at: shown (the pipeline keeps its order) but quiet. */
    .hbar-row.is-zero .hbar-label,
    .hbar-row.is-zero .hbar-value {
        color: #9ab0a0;
        font-weight: 500;
    }

    /* Failed is the one stage in a status colour (with its icon), so it stands out from the rest. */
    .hbar-row.is-critical:not(.is-zero) .hbar-label,
    .hbar-row.is-critical:not(.is-zero) .hbar-value {
        color: var(--fg-d04030, #d04030);
    }

    .hbar-row.is-critical .hbar-fill {
        background: #d04030;
    }

    /* "Other" and "Not matched yet": the same scale, a lighter bar. */
    .hbar-row.is-muted .hbar-fill {
        background: var(--bg-9ccfb4, #9ccfb4);
    }

    .hbar-row.is-muted .hbar-label {
        font-weight: 500;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    /* Review status: one stacked bar (2px gaps between segments) and a legend with counts. */
    .review-headline {
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin-bottom: 12px;
    }

    .review-headline .chart-sub {
        margin: 0;
    }

    .review-avg {
        font-size: 24px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        line-height: 1.15;
    }

    .stack-bar {
        display: flex;
        gap: 2px;
        height: 16px;
        border-radius: 999px;
        overflow: hidden;
        background: var(--bg-edf2ed, #edf2ed);
    }

    .stack-seg {
        display: block;
        min-width: 6px;
        height: 100%;
        transition: filter 0.15s ease;
    }

    .stack-seg:hover,
    .stack-seg:focus-visible {
        filter: brightness(1.12);
    }

    /* Review colours, checked for colour-blind separation (deuteranopia included) against the light and the dark
       card: a teal-leaning green so Approved and Rejected stay apart even when nothing is pending between
       them, and a dark-mode amber of its own (the light one is too pale on the dark card). Every segment is also
       named with its count in the legend, so colour is never the only cue. */
    .seg-published {
        background: #0f8f74;
    }

    .seg-pending {
        background: #c98a20;
    }

    html[data-theme="dark"] .seg-pending {
        background: #a8952a;
    }

    .seg-rejected {
        background: #d04030;
    }

    .stack-legend {
        list-style: none;
        margin: 12px 0 0;
        padding: 0;
        display: grid;
        gap: 2px;
    }

    .stack-legend a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 8px;
        margin: 0 -8px;
        border-radius: 8px;
        font-size: 12.5px;
        color: var(--fg-1a2e1a, #1a2e1a);
        text-decoration: none;
    }

    .stack-legend a:hover,
    .stack-legend a:focus-visible {
        background: var(--bg-f3f8f4, #f3f8f4);
    }

    .stack-legend strong {
        margin-left: auto;
        font-variant-numeric: tabular-nums;
    }

    .legend-swatch {
        flex: none;
        width: 10px;
        height: 10px;
        border-radius: 3px;
    }

    .legend-share {
        min-width: 38px;
        text-align: right;
        color: var(--fg-6a8a6a, #6a8a6a);
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 1200px) {
        .grid.overview-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .grid.overview-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }
    }
</style>
@endsection

@section('scripts')
@include('partials.flash-toast')
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
