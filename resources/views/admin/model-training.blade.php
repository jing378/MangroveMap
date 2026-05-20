@extends('layouts.admin')

@section('title', 'AI Model Training - MangroveMap')

@section('content')
<div id="model-training-section">
    <div class="page-title">AI Model Training</div>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Active Models</div>
            <div class="stat-value">{{ $activeModels }}</div>
            <div class="stat-change positive">Deployed for inference</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Avg. Accuracy</div>
            <div class="stat-value green">{{ $avgAccuracy }}%</div>
            <div class="stat-change positive">Completed models</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Training Now</div>
            <div class="stat-value">{{ $trainingModels }}</div>
            <div class="stat-change">In progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">In Queue</div>
            <div class="stat-value">{{ $queuedModels }}</div>
            <div class="stat-change">Waiting to train</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title-with-actions">
            <div><i class="bi bi-robot"></i> Model Training Queue</div>
            <div class="section-actions">
                <button class="btn-primary"><i class="bi bi-plus-lg"></i> Train New Model</button>
            </div>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Model Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Accuracy</th>
                        <th>Started</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($models as $model)
                    @php
                        $progress = match ($model->status) {
                            'completed' => 100,
                            'training' => 68,
                            'failed' => 23,
                            default => 0,
                        };
                        $statusClass = match ($model->status) {
                            'completed' => 'success',
                            'training' => 'warning',
                            'failed' => 'danger',
                            default => 'secondary',
                        };
                        $accuracy = $model->accuracy ? round($model->accuracy * 100, 1) : null;
                        $accClass = $accuracy === null ? 'pending' : ($accuracy >= 90 ? 'excellent' : ($accuracy >= 85 ? 'good' : 'fair'));
                        $typeClass = match ($model->model_type) {
                            'classification' => 'cnn',
                            'segmentation' => 'transformer',
                            'change_detection' => 'lstm',
                            default => 'ensemble',
                        };
                    @endphp
                    <tr>
                        <td>{{ $model->name }}</td>
                        <td><span class="type-badge {{ $typeClass }}">{{ ucwords(str_replace('_', ' ', $model->model_type)) }}</span></td>
                        <td><span class="badge {{ $statusClass }}">{{ ucfirst($model->status) }}</span></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: {{ $progress }}%"></div>
                            </div>
                        </td>
                        <td>
                            @if($accuracy !== null)
                            <span class="accuracy-badge {{ $accClass }}">{{ $accuracy }}%</span>
                            @else
                            <span class="accuracy-badge pending">Pending</span>
                            @endif
                        </td>
                        <td>{{ $model->training_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="actions-cell">
                            @if($model->is_active)
                            <span class="badge success">Active</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">No models. Run <code>php artisan db:seed</code>.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid cols-2">
        <div class="section">
            <div class="section-title"><i class="bi bi-graph-up"></i> Training Performance</div>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <div class="section">
            <div class="section-title"><i class="bi bi-gear"></i> Model Parameters</div>
            <div class="params-list">
                <div class="param-item">
                    <div class="param-label">Learning Rate</div>
                    <div class="param-value">0.001</div>
                </div>
                <div class="param-item">
                    <div class="param-label">Batch Size</div>
                    <div class="param-value">32</div>
                </div>
                <div class="param-item">
                    <div class="param-label">Epochs</div>
                    <div class="param-value">100</div>
                </div>
                <div class="param-item">
                    <div class="param-label">Validation Split</div>
                    <div class="param-value">0.2</div>
                </div>
                <div class="param-item">
                    <div class="param-label">Optimizer</div>
                    <div class="param-value">Adam</div>
                </div>
                <div class="param-item">
                    <div class="param-label">GPU Memory</div>
                    <div class="param-value">8GB VRAM</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-clock-history"></i> Training History</div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Model Name</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Final Accuracy</th>
                        <th>Training Time</th>
                        <th>Trained By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($completedModels as $model)
                    @php
                        $accuracy = $model->accuracy ? round($model->accuracy * 100, 1) : 0;
                        $accClass = $accuracy >= 90 ? 'excellent' : 'good';
                    @endphp
                    <tr>
                        <td>{{ $model->name }}</td>
                        <td>v{{ $model->version }}</td>
                        <td><span class="badge success">Completed</span></td>
                        <td><span class="accuracy-badge {{ $accClass }}">{{ $accuracy }}%</span></td>
                        <td>{{ number_format($model->dataset_size) }} samples</td>
                        <td>System</td>
                        <td>{{ $model->training_date?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">No completed training runs yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
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

    .grid.cols-2 {
        grid-template-columns: repeat(2, 1fr);
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

    .section-title-with-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        font-size: 16px;
        font-weight: 700;
        color: #1a2e1a;
    }

    .section-actions {
        display: flex;
        gap: 12px;
    }

    .table-container {
        overflow-x: auto;
        margin-bottom: 20px;
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

    .badge.secondary {
        background: #f0f4f0;
        color: #6a8a6a;
        border: 1px solid #d4e0d4;
    }

    .type-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        border: 1px solid;
    }

    .type-badge.cnn {
        background: #eff6ff;
        color: #1e62d4;
        border-color: #93c5e8;
    }

    .type-badge.lstm {
        background: #fef3f2;
        color: #b73318;
        border-color: #f8a59a;
    }

    .type-badge.transformer {
        background: #f0fdf4;
        color: #166534;
        border-color: #86efac;
    }

    .type-badge.ensemble {
        background: #faf5ff;
        color: #7e22ce;
        border-color: #e9d5ff;
    }

    .progress-bar {
        width: 100%;
        height: 6px;
        background: #e0e8e0;
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #1e9e62, #16a34a);
        transition: width 0.3s ease;
    }

    .accuracy-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        border: 1px solid;
    }

    .accuracy-badge.excellent {
        background: #edf7f2;
        color: #1e9e62;
        border-color: #b0e0c0;
    }

    .accuracy-badge.good {
        background: #fef3f2;
        color: #b73318;
        border-color: #f8a59a;
    }

    .accuracy-badge.fair {
        background: #fdf5e8;
        color: #c07818;
        border-color: #e8cc98;
    }

    .accuracy-badge.poor {
        background: #fdf0ee;
        color: #d04030;
        border-color: #e8b8b0;
    }

    .accuracy-badge.pending {
        background: #f0f4f0;
        color: #6a8a6a;
        border-color: #d4e0d4;
    }

    .actions-cell {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border: 1px solid #e0e8e0;
        background: #fff;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
    }

    .action-btn:hover {
        border-color: #1e9e62;
        background: #edf7f2;
    }

    .action-btn.delete:hover {
        border-color: #d04030;
        background: #fdf0ee;
    }

    .action-btn.deploy:hover {
        border-color: #1e62d4;
        background: #eff6ff;
    }

    .params-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .param-item {
        padding: 12px;
        background: #f5f7f6;
        border-radius: 8px;
        border: 1px solid #e0e8e0;
    }

    .param-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #9ab0a0;
        margin-bottom: 6px;
    }

    .param-value {
        font-size: 16px;
        font-weight: 700;
        color: #1a2e1a;
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin-top: 16px;
    }

    .btn-primary {
        padding: 10px 20px;
        background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(30, 158, 98, 0.3);
    }

    @media (max-width: 1024px) {
        .grid.cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid.cols-2 {
            grid-template-columns: 1fr;
        }

        .section-title-with-actions {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .section-actions {
            width: 100%;
        }

        .section-actions .btn-primary {
            width: 100%;
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

        .table {
            font-size: 11px;
        }

        .table th,
        .table td {
            padding: 10px 12px;
        }

        .actions-cell {
            gap: 4px;
        }

        .action-btn {
            width: 28px;
            height: 28px;
            font-size: 12px;
        }

        .params-list {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
    const performanceChartOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        animation: {
            duration: 600
        }
    };

    // Performance Chart
    new Chart(document.getElementById('performanceChart'), {
        type: 'line',
        data: {
            labels: @json($accuracyChartLabels),
            datasets: [{
                    label: 'Model Accuracy (%)',
                    data: @json($accuracyChartValues),
                    borderColor: '#1e9e62',
                    backgroundColor: 'rgba(30,158,98,.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                },
                {
                    label: 'Training Loss',
                    data: [0.45, 0.38, 0.28, 0.18],
                    borderColor: '#d04030',
                    backgroundColor: 'rgba(208,64,48,.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                }
            ]
        },
        options: {
            ...performanceChartOpts,
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

    // Action buttons
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const action = this.className.split(' ')[1];
            const modelName = this.closest('tr').cells[0].textContent;
            console.log('Action:', action, 'Model:', modelName);
        });
    });
</script>
@endsection