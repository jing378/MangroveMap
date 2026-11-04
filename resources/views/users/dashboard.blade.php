@extends('layouts.enduser')

@section('title', 'Dashboard - MangroveMap')

@section('styles')
<style>
    .dash-container {
        max-width: 1300px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 28px;
    }

    /* Hero greeting */
    .dash-hero {
        background: linear-gradient(135deg, #1b382b 0%, #165337 100%);
        border-radius: 16px;
        padding: 32px 36px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
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

    .hero-content h1 {
        font-size: 26px;
        font-weight: 800;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }

    .hero-content p {
        font-size: 14px;
        color: #b8d9c6;
        max-width: 600px;
        line-height: 1.5;
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

    /* KPI Grid */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
        gap: 16px;
    }

    .kpi-card {
        min-width: 0;
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: var(--bg-f5f7f6, #f5f7f6);
        color: var(--fg-1e9e62, #1e9e62);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .kpi-info {
        flex: 1;
        min-width: 0;
    }

    .kpi-val {
        font-size: 22px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        line-height: 1.2;
    }

    .kpi-label {
        font-size: 12px;
        color: #7a9a7a;
        margin-top: 4px;
        font-weight: 500;
    }

    .kpi-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 12px;
        margin-top: 6px;
    }

    .kpi-badge.g { background: var(--bg-edf7f2, #edf7f2); color: var(--fg-1e9e62, #1e9e62); }
    .kpi-badge.a { background: var(--bg-fef7ed, #fef7ed); color: var(--fg-c07818, #c07818); }

    /* Visual Charts Section */
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(420px, 100%), 1fr));
        gap: 20px;
    }

    .chart-card {
        min-width: 0;
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 24px;
    }

    .chart-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .chart-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .chart-canvas-wrap {
        position: relative;
        height: 240px;
        width: 100%;
    }

    /* Submissions Table */
    .table-card {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 24px;
    }

    .table-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .table-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .custom-table th {
        text-align: left;
        padding: 12px 14px;
        color: #7a9a7a;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
    }

    .custom-table td {
        padding: 14px;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .custom-table tr:hover td {
        background: var(--bg-fbfdfb, #fbfdfb);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .status-badge.approved {
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
    }

    .status-badge.pending {
        background: var(--bg-fef7ed, #fef7ed);
        color: var(--fg-c07818, #c07818);
    }

    .status-badge.rejected {
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
    }

    .btn-table-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: var(--fg-1e9e62, #1e9e62);
        background: var(--bg-edf7f2, #edf7f2);
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .btn-table-action:hover {
        background: #1e9e62;
        color: #fff;
    }

    /* Top Contributors */
    .contributor-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .contributor-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 10px 12px;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        font-size: 13px;
        border-radius: 8px;
    }

    .contributor-row:last-child {
        border-bottom: none;
    }

    .contributor-row.is-me {
        background: var(--bg-edf7f2, #edf7f2);
    }

    .contributor-rank {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--bg-f5f7f6, #f5f7f6);
        color: var(--fg-1e9e62, #1e9e62);
        font-weight: 800;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .contributor-row:first-child .contributor-rank {
        background: #1e9e62;
        color: #fff;
    }

    .contributor-name {
        flex: 1;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .contributor-you {
        margin-left: 6px;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        background: #1e9e62;
        color: #fff;
    }

    .contributor-role {
        font-size: 11px;
        color: #7a9a7a;
        font-weight: 600;
    }

    .contributor-count {
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-1e9e62, #1e9e62);
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        .dash-container {
            gap: 18px;
        }

        .dash-hero {
            padding: 22px 20px;
        }

        .hero-content h1 {
            font-size: 21px;
            overflow-wrap: anywhere;
        }

        .hero-content p {
            font-size: 13px;
        }

        .contributor-role {
            display: none;
        }

        /* KPI cards: compact 2-column tiles, icon above text */
        .kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .kpi-card {
            flex-direction: column;
            gap: 10px;
            padding: 14px;
        }

        .kpi-card:last-child:nth-child(odd) {
            grid-column: 1 / -1;
        }

        .kpi-icon {
            width: 36px;
            height: 36px;
            font-size: 17px;
        }

        .kpi-val {
            font-size: 19px;
            overflow-wrap: anywhere;
        }

        .kpi-badge {
            white-space: normal;
            line-height: 1.35;
        }

        .charts-grid {
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .chart-card,
        .table-card {
            padding: 16px;
        }

        .chart-header,
        .table-header {
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .chart-canvas-wrap {
            height: 220px;
        }

        .contributor-row {
            gap: 10px;
            padding: 10px 8px;
        }
    }

    @media (max-width: 360px) {
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="dash-container">
    <!-- Hero Banner -->
    <div class="dash-hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="bi bi-shield-check"></i>
                <span>{{ Auth::user()->isExpert() ? 'Expert Reviewer' : 'Resident Mapper' }}</span>
            </div>
            <h1>Welcome back, {{ Auth::user()->name }}</h1>
            <p>Monitor local mangrove health, delineate geospatial forest boundaries, and perform high-precision canopy segmentations with our AI models.</p>
        </div>
    </div>

    @php
        $myDelineations = collect($delineations ?? []);
        $recentDelineations = collect($dashboardDelineations ?? $delineations ?? []);
        $totalDels = $myDelineations->count();
        $approvedDels = $myDelineations->filter(fn($d) => !empty($d['is_approved']))->count();
        $pendingDels = $myDelineations->filter(fn($d) => empty($d['is_approved']) && empty($d['is_rejected']))->count();
    @endphp

    <!-- KPI Statistics Grid -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-pie-chart-fill"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ number_format($totalCoverage ?? 47382, 0) }} ha</div>
                <div class="kpi-label">Total Monitored Area</div>
                <span class="kpi-badge g">+3.2% recovery</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-flower1"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ $genusCount ?? 4 }}</div>
                <div class="kpi-label">Mangrove Genera Found</div>
                <span class="kpi-badge g">Stable Diversity</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-vector-pen"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ $totalDels }}</div>
                <div class="kpi-label">Your Delineations</div>
                <span class="kpi-badge {{ $approvedDels > 0 ? 'g' : 'a' }}">{{ $approvedDels }} Approved · {{ $pendingDels }} Pending</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-cpu-fill"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ $totalAnalyses ?? 0 }}</div>
                <div class="kpi-label">AI Analyses Run</div>
                <span class="kpi-badge g">{{ $completedAnalyses ?? 0 }} Completed</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ number_format($communityContributors ?? 0) }}</div>
                <div class="kpi-label">Community Contributors</div>
                @if(!empty($myContributorRank))
                    <span class="kpi-badge g">You're #{{ $myContributorRank }} of {{ $communityContributors }}</span>
                @else
                    <span class="kpi-badge a">Get a delineation approved to join</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title"><i class="bi bi-pie-chart me-2"></i> Genus Distribution</div>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="genusPieChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title"><i class="bi bi-graph-up me-2"></i> Coverage Trend (ha)</div>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="coverageTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Contributors Leaderboard -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title"><i class="bi bi-trophy me-2"></i> Top Contributors</div>
            <span class="kpi-badge g">{{ $activeContributorsThisMonth ?? 0 }} active this month</span>
        </div>

        @if(collect($topContributors ?? [])->isEmpty())
            <div style="text-align: center; padding: 24px 20px; color: #7a9a7a; font-size: 14px;">
                No approved delineations yet. Be the first contributor!
            </div>
        @else
            <ol class="contributor-list">
                @foreach($topContributors as $index => $contributor)
                    @php $isMe = $contributor->user_id === auth()->id(); @endphp
                    <li class="contributor-row {{ $isMe ? 'is-me' : '' }}">
                        <span class="contributor-rank">{{ $index + 1 }}</span>
                        <span class="contributor-name">
                            {{ $contributor->user?->name ?? 'Unknown' }}
                            @if($isMe)<span class="contributor-you">You</span>@endif
                        </span>
                        <span class="contributor-role">{{ $contributor->user?->roleLabel() ?? 'Resident' }}</span>
                        <span class="contributor-count">{{ $contributor->approved_count }} approved</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <!-- Recent Submissions Table -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title"><i class="bi bi-clock-history me-2"></i> Recent Delineations</div>
            <a href="{{ auth()->user()->isExpert() ? route('expert.delineate') : route('delineate') }}" class="btn-table-action">
                <i class="bi bi-plus-lg"></i>
                <span>New Delineation</span>
            </a>
        </div>

        @if($recentDelineations->isEmpty())
            <div style="text-align: center; padding: 40px 20px; color: #7a9a7a;">
                <i class="bi bi-bounding-box" style="font-size: 36px; display: block; margin-bottom: 10px; color: #b8d9c6;"></i>
                <p style="margin: 0; font-size: 14px;">{{ auth()->user()->isExpert() ? 'No resident delineations are available to review yet.' : "You haven't submitted any zone delineations yet." }}</p>
                <a href="{{ auth()->user()->isExpert() ? route('expert.delineate') : route('delineate') }}" style="display: inline-block; margin-top: 12px; color: var(--fg-1e9e62, #1e9e62); font-weight: 700; text-decoration: none;">
                    Start drawing your first boundary &rarr;
                </a>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Zone Name / Notes</th>
                            <th>Features</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentDelineations->take(8) as $d)
                            @php
                                $isApp = !empty($d['is_approved']);
                                $isRej = !empty($d['is_rejected']);
                                $featureCount = count($d['features'] ?? []);
                                $dateStr = !empty($d['created_at']) ? \Carbon\Carbon::parse($d['created_at'])->format('M d, Y') : '—';
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--fg-1a2e1a, #1a2e1a);">{{ $d['name'] ?? 'Unnamed zone' }}</div>
                                    @if(!empty($d['notes']))
                                        <div style="font-size: 11px; color: #7a9a7a; margin-top: 2px;">{{ Str::limit($d['notes'], 60) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: var(--fg-4a6a4a, #4a6a4a); font-weight: 600;">
                                        <i class="bi bi-layers me-1"></i> {{ $featureCount }} {{ Str::plural('geometry', $featureCount) }}
                                    </span>
                                </td>
                                <td>
                                    @if($isApp)
                                        <span class="status-badge approved"><i class="bi bi-check-circle-fill"></i> Approved</span>
                                    @elseif($isRej)
                                        <span class="status-badge rejected"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                                    @else
                                        <span class="status-badge pending"><i class="bi bi-hourglass-split"></i> Under Review</span>
                                    @endif
                                </td>
                                <td style="color: #7a9a7a; font-size: 12px;">{{ $dateStr }}</td>
                                <td style="text-align: right;">
                                    <a href="{{ (auth()->user()->isExpert() ? route('expert.map') : route('map')) }}?delineation={{ $d['id'] }}" class="btn-table-action" title="View on map">
                                        <i class="bi bi-eye"></i> View on Map
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Genus Distribution Doughnut
        const genusCtx = document.getElementById('genusPieChart');
        if (genusCtx) {
            const rawLabels = @json($genusLabels ?? []);
            const rawSeries = @json($genusSeries ?? []);
            
            const labels = rawLabels.length > 0 ? rawLabels : ['Rhizophora', 'Avicennia', 'Sonneratia', 'Bruguiera'];
            const data = rawSeries.length > 0 ? rawSeries : [48, 28, 14, 10];

            new Chart(genusCtx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: ['#1e9e62', '#16a34a', '#5ab8de', '#c07818', '#e6a23c', '#909399'],
                        borderWidth: 2,
                        // Matches the card background in either theme (re-read on chart.update()).
                        borderColor: () => (window.MangroveTheme?.get() === 'dark' ? '#141d18' : '#fff')
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { family: 'Manrope', size: 11, weight: 600 }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        // Coverage Trend Line
        const trendCtx = document.getElementById('coverageTrendChart');
        if (trendCtx) {
            const rawYears = @json($trendYears ?? []);
            const rawValues = @json($trendValues ?? []);

            const years = rawYears.length > 0 ? rawYears : ['2021', '2022', '2023', '2024', '2025', '2026'];
            const values = rawValues.length > 0 ? rawValues : [42100, 43400, 44200, 45800, 46900, 47382];

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: years,
                    datasets: [{
                        label: 'Total Mangrove Area (ha)',
                        data: values,
                        borderColor: '#1e9e62',
                        backgroundColor: 'rgba(30, 158, 98, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#1e9e62',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: () => (window.MangroveTheme?.get() === 'dark' ? 'rgba(255, 255, 255, 0.07)' : '#f0f4f0') },
                            ticks: { font: { family: 'Manrope', size: 11 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Manrope', size: 11 } }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
