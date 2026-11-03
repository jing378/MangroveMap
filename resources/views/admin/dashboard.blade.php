@extends('layouts.admin')

@section('title', 'Admin Dashboard - MangroveMap')

@section('content')
<div id="dashboard-section">
    <div class="page-title">Welcome back, {{ Auth::user()->name }}</div>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value green">{{ number_format($totalUsers) }}</div>
            <div class="stat-change positive">{{ $newUsersThisMonth }} new this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Observation Records</div>
            <div class="stat-value">{{ number_format($mappedZones) }}</div>
            <div class="stat-change positive">{{ $newZonesThisMonth }} new this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Completed Analyses</div>
            <div class="stat-value">{{ number_format($generaClassified) }}</div>
            <div class="stat-change positive">Demo &amp; field data</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Analysis Success Rate</div>
            <div class="stat-value green">{{ $systemHealth }}%</div>
            <div class="stat-change">Completed vs. total runs</div>
        </div>
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
                        <td>{{ $contributor->user ? ucwords(str_replace('_', ' ', $contributor->user->role)) : '—' }}</td>
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
                        <td colspan="5">No activity recorded yet. Run <code>php artisan db:seed</code> to load demo data.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-arrow-up-right-circle"></i> Monthly Growth Trend</div>
        <div class="chart-container">
            <canvas id="growthChart"></canvas>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .page-title {
        font-size: 28px;
        font-weight: 800;
        color: #1a2e1a;
        margin-bottom: 24px;
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
        background: #fff;
        border: 1px solid #e0e8e0;
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
        color: #1a2e1a;
        margin-bottom: 8px;
    }

    .stat-value.green {
        color: #1e9e62;
    }

    .stat-change {
        font-size: 12px;
        font-weight: 600;
        color: #7a9a7a;
    }

    .stat-change.positive {
        color: #1e9e62;
    }

    .section {
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        margin-bottom: 28px;
    }

    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1a2e1a;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-link {
        margin-left: auto;
        font-size: 12px;
        font-weight: 600;
        color: #1e9e62;
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

    .contributor-metric {
        background: #f5f7f6;
        border: 1px solid #e0e8e0;
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
        background: #f5f7f6;
        padding: 12px 16px;
        text-align: left;
        font-weight: 700;
        color: #9ab0a0;
        border-bottom: 1px solid #e0e8e0;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f0f4f0;
        color: #3a5a3a;
    }

    .table tr:hover {
        background: #f5f7f6;
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
    }

    .badge.success {
        background: #edf7f2;
        color: #1e9e62;
        border: 1px solid #b0e0c0;
    }

    .badge.pending {
        background: #fdf5e8;
        color: #c07818;
        border: 1px solid #e8cc98;
    }

    .badge.rejected {
        background: #fdf0ee;
        color: #d04030;
        border: 1px solid #e8b8b0;
    }

    .badge.secondary {
        background: #f0f4f0;
        color: #6a8a6a;
        border: 1px solid #d4e0d4;
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
        .page-title {
            font-size: 22px;
            margin-bottom: 16px;
        }

        .grid.cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid {
            gap: 12px;
        }

        .contributor-summary {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .section {
            padding: 16px;
            margin-bottom: 16px;
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
            labels: @json($growthChartLabels),
            datasets: [{
                    label: 'New observation records',
                    data: @json($growthChartZones),
                    borderColor: '#1e9e62',
                    backgroundColor: 'rgba(30,158,98,.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                },
                {
                    label: 'Analyses run',
                    data: @json($growthChartAnalyses),
                    borderColor: '#5ab8de',
                    backgroundColor: 'rgba(90,184,222,.1)',
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
                        color: '#9ab0a0'
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
