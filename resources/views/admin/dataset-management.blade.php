@extends('layouts.admin')

@section('title', 'Dataset Management - MangroveMap')

@section('content')
<div id="dataset-management-section">
    <div class="page-title">Dataset Management</div>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Total Datasets</div>
            <div class="stat-value">{{ number_format($totalDatasets) }}</div>
            <div class="stat-change positive">{{ $newThisMonth }} new this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Coverage</div>
            <div class="stat-value">{{ number_format($totalCoverage, 2) }} km²</div>
            <div class="stat-change">Across all regions</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Avg. Confidence</div>
            <div class="stat-value">{{ $avgConfidence ? number_format($avgConfidence * 100, 1) . '%' : '—' }}</div>
            <div class="stat-change">Model / survey score</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Healthy Stands</div>
            <div class="stat-value green">{{ $healthRate }}%</div>
            <div class="stat-change">Of observation records</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title-with-actions">
            <div><i class="bi bi-folder-check"></i> Dataset Inventory</div>
            <div class="section-actions">
                <button class="btn-primary"><i class="bi bi-upload"></i> Upload Dataset</button>
                <button class="btn-secondary"><i class="bi bi-download"></i> Export All</button>
            </div>
        </div>

        <div class="filter-bar">
            <input type="text" class="search-input" placeholder="Search datasets by name...">
            <select class="filter-select">
                <option value="">All Types</option>
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
                <option value="xls">Excel</option>
                <option value="shapefile">Shapefile</option>
            </select>
            <select class="filter-select">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="processing">Processing</option>
                <option value="archived">Archived</option>
            </select>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dataset Name</th>
                        <th>Type</th>
                        <th>Coverage</th>
                        <th>Confidence</th>
                        <th>Health</th>
                        <th>Observed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($datasets as $dataset)
                    @php
                        $sourceClass = str_contains(strtolower($dataset->data_source ?? ''), 'field') ? 'json' : 'csv';
                        $healthClass = match ($dataset->health_status) {
                            'healthy' => 'success',
                            'recovering' => 'warning',
                            'degraded' => 'danger',
                            default => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td>{{ $dataset->region }} — {{ $dataset->genus?->genus ?? 'Unknown genus' }}</td>
                        <td><span class="type-badge {{ $sourceClass }}">{{ $dataset->data_source ?? 'N/A' }}</span></td>
                        <td>{{ number_format($dataset->coverage_area_km2, 2) }} km²</td>
                        <td>{{ $dataset->confidence_score ? number_format($dataset->confidence_score * 100, 0) . '%' : '—' }}</td>
                        <td><span class="badge {{ $healthClass }}">{{ ucfirst($dataset->health_status ?? 'unknown') }}</span></td>
                        <td>{{ $dataset->observation_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="actions-cell">
                            <button class="action-btn view" title="View"><i class="bi bi-eye"></i></button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">No observation records. Run <code>php artisan db:seed</code>.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($datasets->hasPages())
        <div class="pagination">
            @if($datasets->onFirstPage())
            <span class="pagination-btn disabled">← Previous</span>
            @else
            <a class="pagination-btn" href="{{ $datasets->previousPageUrl() }}">← Previous</a>
            @endif
            <div class="pagination-info">Page {{ $datasets->currentPage() }} of {{ $datasets->lastPage() }} ({{ $datasets->total() }} records)</div>
            @if($datasets->hasMorePages())
            <a class="pagination-btn" href="{{ $datasets->nextPageUrl() }}">Next →</a>
            @else
            <span class="pagination-btn disabled">Next →</span>
            @endif
        </div>
        @endif
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-clock-history"></i> Import History</div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Import ID</th>
                        <th>Dataset</th>
                        <th>Records</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Imported By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($importHistory as $analysis)
                    @php
                        $statusClass = match ($analysis->status) {
                            'completed' => 'success',
                            'pending' => 'warning',
                            'failed' => 'danger',
                            default => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td>#AN-{{ str_pad((string) $analysis->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $analysis->analysis_type)) }}</td>
                        <td>{{ $analysis->species_detected ?? '—' }}</td>
                        <td>—</td>
                        <td><span class="badge {{ $statusClass }}">{{ ucfirst($analysis->status) }}</span></td>
                        <td>{{ $analysis->user?->name ?? 'Unknown' }}</td>
                        <td>{{ $analysis->created_at?->format('M j, g:i A') ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">No analysis activity yet.</td>
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

    .filter-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
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
        transition: all 0.15s;
    }

    .search-input {
        flex: 1;
        min-width: 200px;
    }

    .search-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .filter-select {
        min-width: 140px;
        cursor: pointer;
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

    .type-badge.csv {
        background: #eff6ff;
        color: #1e62d4;
        border-color: #93c5e8;
    }

    .type-badge.json {
        background: #fef3f2;
        color: #b73318;
        border-color: #f8a59a;
    }

    .type-badge.xls {
        background: #f0fdf4;
        color: #166534;
        border-color: #86efac;
    }

    .type-badge.shapefile {
        background: #faf5ff;
        color: #7e22ce;
        border-color: #e9d5ff;
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
        cursor: pointer;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: #3a5a3a;
        transition: all 0.15s;
    }

    .pagination-btn:hover:not(.disabled) {
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

    .btn-secondary {
        padding: 10px 20px;
        background: #fff;
        color: #3a5a3a;
        border: 1.5px solid #e0e8e0;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-secondary:hover {
        border-color: #1e9e62;
        background: #edf7f2;
        color: #1e9e62;
    }

    @media (max-width: 1024px) {
        .grid.cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }

        .section-title-with-actions {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .section-actions {
            width: 100%;
        }

        .section-actions .btn-primary,
        .section-actions .btn-secondary {
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

        .filter-bar {
            flex-direction: column;
        }

        .search-input,
        .filter-select {
            width: 100%;
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

        .pagination {
            flex-direction: column;
            gap: 12px;
        }

        .pagination-btn {
            width: 100%;
        }
    }
</style>
@endsection

@section('scripts')
<script>
    // Search and filter functionality
    document.querySelector('.search-input').addEventListener('keyup', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const tableRows = document.querySelectorAll('.table tbody tr');

        tableRows.forEach(row => {
            const datasetName = row.cells[0].textContent.toLowerCase();
            row.style.display = datasetName.includes(searchTerm) ? '' : 'none';
        });
    });

    // Filter by type
    document.querySelectorAll('.filter-select')[0].addEventListener('change', function(e) {
        console.log('Type filter:', e.target.value);
    });

    // Filter by status
    document.querySelectorAll('.filter-select')[1].addEventListener('change', function(e) {
        console.log('Status filter:', e.target.value);
    });

    // Action buttons
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const action = this.className.split(' ')[1];
            const datasetName = this.closest('tr').cells[0].textContent;
            console.log('Action:', action, 'Dataset:', datasetName);
        });
    });

    // Pagination
    document.querySelectorAll('.pagination-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!this.classList.contains('disabled')) {
                console.log('Page navigation:', this.textContent);
            }
        });
    });
</script>
@endsection