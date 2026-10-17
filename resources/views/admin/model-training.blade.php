@extends('layouts.admin')

@section('title', 'AI Model Management - MangroveMap')

@section('content')
<div id="model-training-section">
    <div class="page-title">AI Model Management</div>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Registry Records</div>
            <div class="stat-value">{{ $models->count() }}</div>
            <div class="stat-change">Database entries</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Available Checkpoints</div>
            <div class="stat-value green">{{ $availableModelCount }}</div>
            <div class="stat-change">Model files found on disk</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ready for Image Analysis</div>
            <div class="stat-value">{{ $inferenceReady ? 1 : 0 }}</div>
            <div class="stat-change">A usable model file is {{ $inferenceReady ? 'available' : 'missing' }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">With Accuracy Data</div>
            <div class="stat-value">{{ $modelsWithAccuracy }}</div>
            <div class="stat-change">Records include accuracy</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title-with-actions">
            <div><i class="bi bi-robot"></i> Model Registry</div>
            <div class="section-actions">
                <button class="btn-primary" id="openModelUpload" type="button">
                    <i class="bi bi-upload"></i> Upload Model
                </button>
            </div>
        </div>

        @if(session('status'))
        <div class="upload-status" role="status">{{ session('status') }}</div>
        @endif

        <dialog class="model-upload-dialog" id="modelUploadDialog" aria-labelledby="modelUploadTitle">
            <div class="dialog-heading">
                <div>
                    <h2 id="modelUploadTitle">Upload Model</h2>
                    <p>Upload a compatible PyTorch checkpoint and activate it for image analysis.</p>
                </div>
                <button class="dialog-close" type="button" data-close-upload aria-label="Close upload dialog">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.models.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="model-upload-meta">
                    <label class="upload-field">
                        Model Name
                        <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                        @error('name')<span class="upload-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="upload-field">
                        Version
                        <input type="text" name="version" value="{{ old('version', '1.0.0') }}" maxlength="50" required>
                        @error('version')<span class="upload-error">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label class="upload-field">
                    Model Type
                    <span class="model-type-select">
                        <select id="modelTypeSelect" name="model_type" required>
                            <option value="segmentation" @selected(old('model_type', 'segmentation') === 'segmentation')>Mangrove Segmentation</option>
                            <option value="classification" @selected(old('model_type') === 'classification')>Mangrove Classification (Coming Soon)</option>
                        </select>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </span>
                </label>
                <div class="upload-field">
                    <span>PyTorch Checkpoint (.pth, max 512 MB)</span>
                    <div class="model-file-picker">
                        <input id="modelCheckpointInput" type="file" name="model_file" accept=".pth" required>
                        <label class="choose-file-button" for="modelCheckpointInput"><i class="bi bi-folder2-open"></i> Choose a file</label>
                        <span id="selectedModelFile" aria-live="polite">No file selected</span>
                    </div>
                    @error('model_file')<span class="upload-error">{{ $message }}</span>@enderror
                </div>
                <p class="upload-help" id="segmentationUploadHelp">Requires U-Net with a ResNet34 encoder, 3 input channels, and 2 output classes.</p>
                <p class="upload-notice" id="classificationUploadNotice" hidden>Classification checkpoint upload is not available yet. The classifier needs its own architecture and prediction pipeline.</p>
                <div class="dialog-actions">
                    <button class="btn-secondary" type="button" data-close-upload>Cancel</button>
                    <button class="btn-primary" id="uploadModelSubmit" type="submit"><i class="bi bi-upload"></i> Upload &amp; Activate</button>
                </div>
            </form>
        </dialog>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Model Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Checkpoint</th>
                        <th>Accuracy</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($models as $model)
                    @php
                        $statusClass = $model->artifact_available ? match ($model->status) {
                            'completed' => 'success',
                            'training' => 'warning',
                            'failed' => 'danger',
                            default => 'secondary',
                        } : 'danger';
                        $accuracy = $model->artifact_available && $model->accuracy !== null ? round($model->accuracy * 100, 1) : null;
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
                        <td><span class="badge {{ $statusClass }}">{{ $model->artifact_available ? ucfirst($model->status) : 'Unavailable' }}</span></td>
                        <td>
                            @if($model->artifact_available)
                            <span class="badge success">Available</span>
                            @else
                            <span class="badge danger">Missing</span>
                            @endif
                        </td>
                        <td>
                            @if($accuracy !== null)
                            <span class="accuracy-badge {{ $accClass }}">{{ $accuracy }}%</span>
                            @else
                            <span class="accuracy-badge pending">Not available</span>
                            @endif
                        </td>
                        <td>{{ $model->created_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="actions-cell">
                            @if($model->is_active && $model->artifact_available && $model->model_type === 'segmentation')
                            <span class="badge success">Active</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">No model records are registered.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-graph-up"></i> Completed Model Accuracy</div>
        <div class="chart-container">
            @if(count($accuracyChartValues))
            <canvas id="performanceChart"></canvas>
            @else
            <p class="chart-empty">No measured accuracy is available for checkpoints currently on disk.</p>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-clock-history"></i> Recently Completed Models</div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Model Name</th>
                        <th>Version</th>
                        <th>Accuracy</th>
                        <th>Dataset Size</th>
                        <th>Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($completedModels as $model)
                    @php
                        $accuracy = $model->accuracy !== null ? round($model->accuracy * 100, 1) : null;
                        $accClass = $accuracy === null ? 'pending' : ($accuracy >= 90 ? 'excellent' : 'good');
                    @endphp
                    <tr>
                        <td>{{ $model->name }}</td>
                        <td>v{{ $model->version }}</td>
                        <td>
                            @if($accuracy !== null)
                            <span class="accuracy-badge {{ $accClass }}">{{ $accuracy }}%</span>
                            @else
                            <span class="accuracy-badge pending">Not provided</span>
                            @endif
                        </td>
                        <td>{{ $model->dataset_size !== null ? number_format($model->dataset_size).' samples' : '—' }}</td>
                        <td>{{ $model->created_at?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">No completed model records yet.</td>
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

    .chart-empty {
        display: grid;
        min-height: 100%;
        place-items: center;
        color: #6a8a6a;
        font-size: 13px;
        text-align: center;
    }

    .model-upload-dialog {
        position: fixed;
        top: 50%;
        left: 50%;
        width: min(520px, calc(100vw - 32px));
        max-height: calc(100dvh - 32px);
        margin: 0;
        overflow-y: auto;
        padding: 24px;
        border: 1px solid #d4e0d4;
        border-radius: 10px;
        color: #1a2e1a;
        box-shadow: 0 20px 60px rgba(15, 35, 25, 0.25);
        transform: translate(-50%, -50%);
    }

    .model-upload-dialog::backdrop {
        background: rgba(15, 35, 25, 0.58);
    }

    .dialog-heading,
    .dialog-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .dialog-heading {
        margin-bottom: 20px;
    }

    .dialog-heading h2 {
        margin: 0 0 6px;
        font-size: 18px;
        color: #1a2e1a;
    }

    .dialog-heading p,
    .upload-help {
        margin: 0;
        color: #6a8a6a;
        font-size: 12px;
        line-height: 1.5;
    }

    .upload-notice {
        margin: 0;
        padding: 10px 12px;
        border: 1px solid #e8cc98;
        border-radius: 6px;
        background: #fdf5e8;
        color: #925a12;
        font-size: 12px;
        line-height: 1.5;
    }

    .upload-notice[hidden] {
        display: none;
    }

    .dialog-close {
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        border: 1px solid #d4e0d4;
        border-radius: 6px;
        background: #fff;
        color: #3a5a3a;
        font-size: 22px;
        cursor: pointer;
    }

    .upload-field {
        display: grid;
        gap: 7px;
        margin-bottom: 16px;
        color: #3a5a3a;
        font-size: 12px;
        font-weight: 700;
    }

    .model-upload-meta {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(110px, 0.45fr);
        gap: 14px;
    }

    .upload-field input:not([type="file"]),
    .upload-field select {
        width: 100%;
        min-width: 0;
        min-height: 42px;
        padding: 9px 10px;
        border: 1px solid #d4e0d4;
        border-radius: 6px;
        background-color: #fff;
        color: #1a2e1a;
        font: inherit;
    }

    .upload-field input:focus,
    .upload-field select:focus {
        border-color: #1e9e62;
        outline: 3px solid rgba(30, 158, 98, 0.16);
    }

    .upload-field select {
        appearance: none;
        padding-right: 38px;
        cursor: pointer;
    }

    .model-type-select {
        position: relative;
        display: block;
    }

    .model-type-select i {
        position: absolute;
        top: 50%;
        right: 14px;
        color: #6a8a6a;
        font-size: 12px;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .model-file-picker {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        padding: 8px;
        border: 1px dashed #9ab0a0;
        border-radius: 6px;
        background: #f5f7f6;
    }

    .model-file-picker input[type="file"] {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .choose-file-button {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 7px;
        min-height: 36px;
        padding: 8px 12px;
        border: 1px solid #168250;
        border-radius: 5px;
        background: #1e9e62;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .choose-file-button:hover {
        background: #168250;
    }

    .model-file-picker input:focus-visible + .choose-file-button {
        outline: 3px solid rgba(30, 158, 98, 0.28);
        outline-offset: 2px;
    }

    #selectedModelFile {
        min-width: 0;
        overflow: hidden;
        color: #6a8a6a;
        font-size: 11px;
        font-weight: 500;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .upload-error {
        color: #b73318;
        font-size: 11px;
        font-weight: 600;
    }

    .upload-status {
        margin-bottom: 16px;
        padding: 10px 12px;
        border: 1px solid #b0e0c0;
        border-radius: 6px;
        background: #edf7f2;
        color: #176d46;
        font-size: 12px;
        font-weight: 600;
    }

    .dialog-actions {
        justify-content: flex-end;
        margin-top: 22px;
    }

    .btn-secondary {
        min-height: 40px;
        padding: 9px 16px;
        border: 1px solid #d4e0d4;
        border-radius: 6px;
        background: #fff;
        color: #3a5a3a;
        font: inherit;
        cursor: pointer;
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

    .btn-primary:disabled {
        background: #9ab0a0;
        box-shadow: none;
        cursor: not-allowed;
        transform: none;
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
        .model-upload-meta {
            grid-template-columns: 1fr;
            gap: 0;
        }

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
    const modelUploadDialog = document.getElementById('modelUploadDialog');
    const openModelUpload = document.getElementById('openModelUpload');
    const modelCheckpointInput = document.getElementById('modelCheckpointInput');
    const selectedModelFile = document.getElementById('selectedModelFile');
    const modelTypeSelect = document.getElementById('modelTypeSelect');
    const uploadModelSubmit = document.getElementById('uploadModelSubmit');
    const segmentationUploadHelp = document.getElementById('segmentationUploadHelp');
    const classificationUploadNotice = document.getElementById('classificationUploadNotice');

    function updateModelUploadType() {
        const isClassification = modelTypeSelect.value === 'classification';
        uploadModelSubmit.disabled = isClassification;
        segmentationUploadHelp.hidden = isClassification;
        classificationUploadNotice.hidden = !isClassification;
    }

    modelCheckpointInput.addEventListener('change', () => {
        selectedModelFile.textContent = modelCheckpointInput.files[0]?.name ?? 'No file selected';
    });
    modelTypeSelect.addEventListener('change', updateModelUploadType);
    updateModelUploadType();

    openModelUpload.addEventListener('click', () => modelUploadDialog.showModal());
    modelUploadDialog.querySelectorAll('[data-close-upload]').forEach(button => {
        button.addEventListener('click', () => modelUploadDialog.close());
    });
    modelUploadDialog.addEventListener('click', event => {
        if (event.target === modelUploadDialog) modelUploadDialog.close();
    });

    @if($errors->any())
    modelUploadDialog.showModal();
    @endif

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
    const performanceCanvas = document.getElementById('performanceChart');
    if (performanceCanvas) new Chart(performanceCanvas, {
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