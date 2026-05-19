@extends('layouts.admin')

@section('title', 'Admin Dashboard - MangroveMap')

@section('content')
<div id="dashboard-section">
    <div class="page-title">Dashboard</div>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value green">248</div>
            <div class="stat-change positive">↑ 12% this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Mapped Zones</div>
            <div class="stat-value">1,247</div>
            <div class="stat-change positive">↑ 84 new this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Genera Classified</div>
            <div class="stat-value">45,392</div>
            <div class="stat-change positive">↑ 5,200 this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">System Health</div>
            <div class="stat-value green">99.8%</div>
            <div class="stat-change">Uptime (7 days)</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-graph-up"></i> Recent User Activity</div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Zone</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Dr. Maria Santos</td>
                        <td>Classified 45 genera</td>
                        <td>Sogod Bay North</td>
                        <td><span class="badge success">Completed</span></td>
                        <td>2 hours ago</td>
                    </tr>
                    <tr>
                        <td>Juan Dela Cruz</td>
                        <td>Mapped new zone</td>
                        <td>Limon Bay Zone</td>
                        <td><span class="badge success">Completed</span></td>
                        <td>4 hours ago</td>
                    </tr>
                    <tr>
                        <td>Rina Gonzales</td>
                        <td>Updated zone data</td>
                        <td>Saint Bernard Wetland</td>
                        <td><span class="badge warning">In Progress</span></td>
                        <td>6 hours ago</td>
                    </tr>
                    <tr>
                        <td>Carlos Mendoza</td>
                        <td>Generated report</td>
                        <td>Southern Coastal Belt</td>
                        <td><span class="badge success">Completed</span></td>
                        <td>8 hours ago</td>
                    </tr>
                    <tr>
                        <td>Ana Rivera</td>
                        <td>Classification failed</td>
                        <td>Hinunangan Margin</td>
                        <td><span class="badge danger">Failed</span></td>
                        <td>10 hours ago</td>
                    </tr>
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

    .badge.warning {
        background: #fdf5e8;
        color: #c07818;
        border: 1px solid #e8cc98;
    }

    .badge.danger {
        background: #fdf0ee;
        color: #d04030;
        border: 1px solid #e8b8b0;
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
                display: false
            }
        },
        animation: {
            duration: 600
        }
    };

    new Chart(document.getElementById('growthChart'), {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                    label: 'New Zones',
                    data: [12, 28, 45, 84],
                    borderColor: '#1e9e62',
                    backgroundColor: 'rgba(30,158,98,.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                },
                {
                    label: 'Genera Classified',
                    data: [800, 1200, 3400, 5200],
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