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
            <div class="stat-label">Models In Use</div>
            <div class="stat-value {{ $inferenceReady && $genusReady ? 'green' : '' }}">{{ (int) $inferenceReady + (int) $genusReady }} <span class="stat-sub">/ 2</span></div>
            <div class="stat-change">
                <span class="ready-flag {{ $inferenceReady ? 'on' : 'off' }}"><i class="bi {{ $inferenceReady ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i> Segmentation</span>
                <span class="ready-flag {{ $genusReady ? 'on' : 'off' }}"><i class="bi {{ $genusReady ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i> Genus</span>
            </div>
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

        <dialog class="model-upload-dialog" id="modelUploadDialog" aria-labelledby="modelUploadTitle">
            <div class="dialog-heading">
                <div>
                    <h2 id="modelUploadTitle">Upload Model</h2>
                    <p>Upload a compatible PyTorch checkpoint and activate it for image analysis.</p>
                </div>
                <button class="dialog-close" type="button" data-close-upload aria-label="Close upload dialog" title="Close">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.models.upload') }}" enctype="multipart/form-data" id="modelUploadForm"
                data-confirm="Upload and activate this model?"
                data-confirm-message="The checkpoint is validated first; if it passes, it replaces the active segmentation model."
                data-confirm-label="Upload" data-busy-label="Validating & uploading...">
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
                            <option value="classification" @selected(old('model_type') === 'classification')>Mangrove Genus Classification</option>
                        </select>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </span>
                </label>
                <div class="upload-field">
                    <span id="modelCheckpointLabel">PyTorch Checkpoint (.pth, max 512 MB)</span>
                    <div class="model-file-picker">
                        <input id="modelCheckpointInput" type="file" name="model_file" accept=".pth" required>
                        <label class="choose-file-button" for="modelCheckpointInput"><i class="bi bi-folder2-open"></i> Choose a file</label>
                        <span id="selectedModelFile" aria-live="polite">No file selected</span>
                    </div>
                    @error('model_file')<span class="upload-error">{{ $message }}</span>@enderror
                </div>
                <fieldset class="performance-fields">
                    <legend>Performance from training <span class="label-optional">(optional)</span></legend>
                    <div class="performance-grid">
                        <label class="upload-field">
                            Accuracy (%)
                            <input type="number" name="accuracy" value="{{ old('accuracy') }}" min="0" max="100" step="0.1" inputmode="decimal" placeholder="e.g. 92.4">
                            @error('accuracy')<span class="upload-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="upload-field">
                            Metric
                            <input type="text" name="accuracy_metric" value="{{ old('accuracy_metric') }}" maxlength="40" list="accuracyMetricOptions" placeholder="e.g. IoU" data-metric-input>
                            @error('accuracy_metric')<span class="upload-error">{{ $message }}</span>@enderror
                        </label>
                        <label class="upload-field">
                            Dataset size
                            <input type="number" name="dataset_size" value="{{ old('dataset_size') }}" min="1" step="1" inputmode="numeric" placeholder="Training samples">
                            @error('dataset_size')<span class="upload-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <p class="upload-help" id="performanceHelpSegmentation">A U-Net <code>.pth</code> stores no results, so copy the validation IoU (or Dice / F1) from your training notebook. For Dataset size, use the Train image count of the Roboflow dataset version you trained on.</p>
                    <p class="upload-help" id="performanceHelpGenus" hidden>Leave these empty: a YOLO <code>.pt</code> trained with Ultralytics (e.g. on a Roboflow export in Colab) stores its validation results, and they are filled in automatically. Fill them only to use different figures, such as Roboflow's own test-set results.</p>
                </fieldset>
                <datalist id="accuracyMetricOptions">
                    @foreach(\App\Models\AIModel::ACCURACY_METRICS as $metric)
                    <option value="{{ $metric }}"></option>
                    @endforeach
                </datalist>
                <p class="upload-help" id="segmentationUploadHelp">Requires U-Net with a ResNet34 encoder, 3 input channels, and 2 output classes.</p>
                <p class="upload-help" id="classificationUploadNotice" hidden>Requires an Ultralytics YOLO segmentation model (.pt) whose classes are mangrove genera (e.g. best.pt from Roboflow/Colab training). On drone imagery it works inside the U-Net's mangrove extent: it suggests the genus and pinpoints each canopy on the map; owners still confirm the genus.</p>
                <div class="dialog-actions">
                    <button class="btn-secondary" type="button" data-close-upload>Cancel</button>
                    <button class="btn-primary" id="uploadModelSubmit" type="submit"><i class="bi bi-upload"></i> Upload &amp; Activate</button>
                </div>
            </form>
        </dialog>

        @php
            $metricsErrors = $errors->getBag('metrics');
        @endphp
        <dialog class="model-upload-dialog" id="modelMetricsDialog" aria-labelledby="modelMetricsTitle">
            <div class="dialog-heading">
                <div>
                    <h2 id="modelMetricsTitle">Model performance</h2>
                    <p id="modelMetricsName"></p>
                </div>
                <button class="dialog-close" type="button" data-close-metrics aria-label="Close" title="Close">&times;</button>
            </div>

            <form method="POST" id="modelMetricsForm" action=""
                data-confirm="Save performance figures?"
                data-confirm-message="They are shown in the registry and the accuracy chart."
                data-confirm-label="Save" data-busy-label="Saving...">
                @csrf
                @method('PUT')
                <div class="performance-grid">
                    <label class="upload-field">
                        Accuracy (%)
                        <input type="number" name="accuracy" id="metricsAccuracy" min="0" max="100" step="0.1" inputmode="decimal" placeholder="e.g. 92.4">
                        @if($metricsErrors->has('accuracy'))<span class="upload-error">{{ $metricsErrors->first('accuracy') }}</span>@endif
                    </label>
                    <label class="upload-field">
                        Metric
                        <input type="text" name="accuracy_metric" id="metricsMetric" maxlength="40" list="accuracyMetricOptions" placeholder="e.g. IoU">
                        @if($metricsErrors->has('accuracy_metric'))<span class="upload-error">{{ $metricsErrors->first('accuracy_metric') }}</span>@endif
                    </label>
                    <label class="upload-field">
                        Dataset size
                        <input type="number" name="dataset_size" id="metricsDatasetSize" min="1" step="1" inputmode="numeric" placeholder="Training samples">
                        @if($metricsErrors->has('dataset_size'))<span class="upload-error">{{ $metricsErrors->first('dataset_size') }}</span>@endif
                    </label>
                </div>
                <p class="upload-help">Leave Accuracy empty to remove it. Use the figure from your validation or test set, not the training set.</p>
                <div class="dialog-actions">
                    <button class="btn-secondary" type="button" data-close-metrics>Cancel</button>
                    <button class="btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save</button>
                </div>
            </form>
        </dialog>

        <div class="table-container registry-table-container">
            <table class="table registry-table">
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
                    <tr class="registry-row">
                        <td class="cell-title">
                            <div class="model-name">{{ $model->name }}</div>
                            @if($model->description)
                            <div class="model-description">{{ $model->description }}</div>
                            @endif
                        </td>
                        <td data-label="Type"><span class="type-badge {{ $typeClass }}">{{ ucwords(str_replace('_', ' ', $model->model_type)) }}</span></td>
                        <td data-label="Status"><span class="badge {{ $statusClass }}">{{ $model->artifact_available ? ucfirst($model->status) : 'Unavailable' }}</span></td>
                        <td data-label="Checkpoint">
                            @if($model->artifact_available)
                            <span class="badge success">Available</span>
                            @else
                            <span class="badge danger">Missing</span>
                            @endif
                        </td>
                        <td data-label="Accuracy">
                            <div class="cell-value">
                            @if($accuracy !== null)
                            <span class="accuracy-badge {{ $accClass }}">{{ $accuracy }}%</span>
                            @if($model->accuracy_metric)<span class="metric-note">{{ $model->accuracy_metric }}</span>@endif
                            @else
                            <span class="accuracy-badge pending">Not entered</span>
                            @endif
                            @if($model->dataset_size)
                            <div class="metric-note">{{ number_format($model->dataset_size) }} samples</div>
                            @endif
                            </div>
                        </td>
                        <td data-label="Date added">{{ $model->created_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="actions-cell">
                            @php
                                $canActivate = $model->artifact_available
                                    && in_array(strtolower(pathinfo((string) $model->artifact_path, PATHINFO_EXTENSION)), \App\Models\AIModel::runnableExtensions($model->model_type), true);
                            @endphp
                            <div class="row-actions">
                            @if($model->artifact_available)
                            <button type="button" class="btn-activate btn-metrics" data-edit-metrics
                                data-url="{{ route('admin.models.metrics', $model) }}"
                                data-model-id="{{ $model->id }}"
                                data-name="{{ $model->name }}"
                                data-accuracy="{{ $model->accuracyPercent() }}"
                                data-metric="{{ $model->accuracy_metric }}"
                                data-dataset-size="{{ $model->dataset_size }}"
                                title="Add or edit accuracy and dataset size"><i class="bi bi-graph-up"></i> {{ $model->accuracy === null ? 'Add accuracy' : 'Edit accuracy' }}</button>
                            @if($model->model_type === 'classification' && strtolower(pathinfo((string) $model->artifact_path, PATHINFO_EXTENSION)) === 'pt')
                            <form method="POST" action="{{ route('admin.models.metrics.checkpoint', $model) }}"
                                data-confirm="Read the results from the model file?"
                                data-confirm-message="Fills in the validation mAP50, precision and recall saved in {{ $model->name }} at the end of training. Any accuracy entered by hand is replaced."
                                data-confirm-label="Read" data-busy-label="Reading...">
                                @csrf
                                <button type="submit" class="btn-activate btn-metrics" title="Use the validation results stored in the YOLO file"><i class="bi bi-file-earmark-bar-graph"></i> Read from file</button>
                            </form>
                            @endif
                            @endif
                            @if($model->is_active && $canActivate)
                            <span class="badge success"><i class="bi bi-check2"></i> Active</span>
                            @elseif($canActivate)
                            <form method="POST" action="{{ route('admin.models.activate', $model) }}"
                                data-confirm="Activate {{ $model->name }}?"
                                data-confirm-message="It replaces the active {{ $model->model_type === 'classification' ? 'genus suggestion' : 'segmentation' }} model for new AI runs."
                                data-confirm-label="Activate" data-busy-label="Activating...">
                                @csrf
                                <button type="submit" class="btn-activate"><i class="bi bi-power"></i> Activate</button>
                            </form>
                            @elseif(! $model->artifact_available)
                            <span class="muted-note">—</span>
                            @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="registry-empty">
                        <td colspan="7">No model records are registered.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-graph-up"></i> Completed Model Accuracy</div>
        <p class="chart-note">Accuracy you entered for each model, from oldest to newest. Segmentation and genus models are measured differently, so compare a bar with others of the same color.</p>
        <div class="chart-container">
            @if(count($accuracyChartValues))
            <canvas id="performanceChart" role="img" aria-label="Accuracy of each model"></canvas>
            @else
            <p class="chart-empty">No accuracy entered yet. Use “Add accuracy” in the Model Registry above (or fill it in when uploading) to see your models compared here.</p>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-clock-history"></i> Recently Completed Models</div>
        <div class="table-container registry-table-container">
            <table class="table registry-table">
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
                        <td data-label="Accuracy">
                            <div class="cell-value">
                            @if($accuracy !== null)
                            <span class="accuracy-badge {{ $accClass }}">{{ $accuracy }}%</span>
                            @if($model->accuracy_metric)<span class="metric-note">{{ $model->accuracy_metric }}</span>@endif
                            @else
                            <span class="accuracy-badge pending">Not entered</span>
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
        color: var(--fg-1a2e1a, #1a2e1a);
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

    .section-title-with-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        font-size: 16px;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
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

    .badge.warning {
        background: var(--bg-fdf5e8, #fdf5e8);
        color: var(--fg-c07818, #c07818);
        border: 1px solid var(--bd-e8cc98, #e8cc98);
    }

    .badge.danger {
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
        border: 1px solid var(--bd-e8b8b0, #e8b8b0);
    }

    .badge.secondary {
        background: var(--bg-f0f4f0, #f0f4f0);
        color: var(--fg-6a8a6a, #6a8a6a);
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
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
        background: var(--bg-eff6ff, #eff6ff);
        color: var(--fg-1e62d4, #1e62d4);
        border-color: var(--bd-93c5e8, #93c5e8);
    }

    .type-badge.lstm {
        background: var(--bg-fef3f2, #fef3f2);
        color: var(--fg-b73318, #b73318);
        border-color: var(--bd-f8a59a, #f8a59a);
    }

    .type-badge.transformer {
        background: var(--bg-f0fdf4, #f0fdf4);
        color: var(--fg-166534, #166534);
        border-color: var(--bd-86efac, #86efac);
    }

    .type-badge.ensemble {
        background: var(--bg-faf5ff, #faf5ff);
        color: var(--fg-7e22ce, #7e22ce);
        border-color: var(--bd-e9d5ff, #e9d5ff);
    }

    .progress-bar {
        width: 100%;
        height: 6px;
        background: var(--bg-e0e8e0, #e0e8e0);
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
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
        border-color: var(--bd-b0e0c0, #b0e0c0);
    }

    .accuracy-badge.good {
        background: var(--bg-fef3f2, #fef3f2);
        color: var(--fg-b73318, #b73318);
        border-color: var(--bd-f8a59a, #f8a59a);
    }

    .accuracy-badge.fair {
        background: var(--bg-fdf5e8, #fdf5e8);
        color: var(--fg-c07818, #c07818);
        border-color: var(--bd-e8cc98, #e8cc98);
    }

    .accuracy-badge.poor {
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
        border-color: var(--bd-e8b8b0, #e8b8b0);
    }

    .accuracy-badge.pending {
        background: var(--bg-f0f4f0, #f0f4f0);
        color: var(--fg-6a8a6a, #6a8a6a);
        border-color: var(--bd-d4e0d4, #d4e0d4);
    }

    /* Stays a real table cell (display:flex on a <td> breaks its height and row border). */
    .table td.actions-cell {
        vertical-align: middle;
        width: 1%;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        background: var(--bg-ffffff, #fff);
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
        background: var(--bg-edf7f2, #edf7f2);
    }

    .action-btn.delete {
        color: var(--fg-d04030, #d04030);
        border-color: var(--bd-e8b8b0, #e8b8b0);
    }

    .action-btn.delete:hover {
        border-color: #d04030;
        background: var(--bg-fdf0ee, #fdf0ee);
    }

    .action-btn.deploy:hover {
        border-color: #1e62d4;
        background: var(--bg-eff6ff, #eff6ff);
    }

    .params-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .param-item {
        padding: 12px;
        background: var(--bg-f5f7f6, #f5f7f6);
        border-radius: 8px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
        color: var(--fg-1a2e1a, #1a2e1a);
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
        color: var(--fg-6a8a6a, #6a8a6a);
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
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 10px;
        /* Without a background the browser's own dialog color shows (white even in dark mode). */
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1a2e1a, #1a2e1a);
        box-shadow: 0 20px 60px rgba(15, 35, 25, 0.25);
        transform: translate(-50%, -50%);
    }

    .stat-sub {
        font-size: 18px;
        color: #9ab0a0;
    }

    .ready-flag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-right: 10px;
    }

    .ready-flag.on {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .ready-flag.off {
        color: var(--fg-d04030, #d04030);
    }


    .actions-cell form {
        margin: 0;
    }

    .btn-activate {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border: 1px solid var(--bd-b0e0c0, #b0e0c0);
        border-radius: 6px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1e9e62, #1e9e62);
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
    }

    .btn-activate:hover {
        border-color: #1e9e62;
        background: var(--bg-edf7f2, #edf7f2);
    }

    .muted-note {
        color: #9ab0a0;
    }

    .model-name {
        font-weight: 600;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .model-description {
        margin-top: 3px;
        max-width: 360px;
        font-size: 11px;
        line-height: 1.45;
        color: #9ab0a0;
    }

    .upload-help code {
        font-size: 11px;
        padding: 0 3px;
        border-radius: 3px;
        background: var(--bg-eef3ee, #eef3ee);
    }

    /* One column of equal-width buttons, so rows with 1, 2 or 3 actions line up. */
    .row-actions {
        display: grid;
        grid-template-columns: minmax(130px, max-content);
        gap: 6px;
    }

    .row-actions form,
    .row-actions .btn-activate {
        width: 100%;
    }

    .row-actions .badge {
        justify-self: start;
    }

    .btn-metrics {
        color: var(--fg-3a5a3a, #3a5a3a);
        border-color: var(--bd-d4e0d4, #d4e0d4);
    }

    .metric-note {
        margin-left: 4px;
        font-size: 10.5px;
        font-weight: 600;
        color: #9ab0a0;
    }

    div.metric-note {
        margin: 3px 0 0;
    }

    .performance-fields {
        margin: 0 0 16px;
        padding: 12px 14px 4px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        background: var(--bg-fbfcfb, #fbfcfb);
        min-width: 0;
    }

    .performance-fields legend {
        padding: 0 6px;
        margin-left: -6px;
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-3a5a3a, #3a5a3a);
    }

    .performance-fields .upload-help {
        margin-bottom: 10px;
    }

    .label-optional {
        font-weight: 500;
        color: #9ab0a0;
    }

    .performance-grid {
        display: grid;
        grid-template-columns: minmax(0, 0.8fr) minmax(0, 1fr) minmax(0, 1fr);
        gap: 0 12px;
    }

    .performance-grid .upload-field {
        margin-bottom: 12px;
    }

    .chart-note {
        margin: -8px 0 0;
        font-size: 12px;
        line-height: 1.5;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    @media (max-width: 560px) {
        .performance-grid {
            grid-template-columns: 1fr;
        }
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
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .dialog-heading p,
    .upload-help {
        margin: 0;
        color: var(--fg-6a8a6a, #6a8a6a);
        font-size: 12px;
        line-height: 1.5;
    }

    .upload-notice {
        margin: 0;
        padding: 10px 12px;
        border: 1px solid var(--bd-e8cc98, #e8cc98);
        border-radius: 6px;
        background: var(--bg-fdf5e8, #fdf5e8);
        color: var(--fg-925a12, #925a12);
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
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 6px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-3a5a3a, #3a5a3a);
        font-size: 22px;
        cursor: pointer;
    }

    .upload-field {
        display: grid;
        gap: 7px;
        margin-bottom: 16px;
        color: var(--fg-3a5a3a, #3a5a3a);
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
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 6px;
        background-color: var(--bg-ffffff, #fff);
        color: var(--fg-1a2e1a, #1a2e1a);
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
        color: var(--fg-6a8a6a, #6a8a6a);
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
        background: var(--bg-f5f7f6, #f5f7f6);
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
        color: var(--fg-6a8a6a, #6a8a6a);
        font-size: 11px;
        font-weight: 500;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .upload-error {
        color: var(--fg-b73318, #b73318);
        font-size: 11px;
        font-weight: 600;
    }


    .dialog-actions {
        justify-content: flex-end;
        margin-top: 22px;
    }

    .btn-secondary {
        min-height: 40px;
        padding: 9px 16px;
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 6px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-3a5a3a, #3a5a3a);
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

        .row-actions {
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

        /* Model Registry: one card per model (like Map Layers): name on top, labelled details,
           actions at the bottom, instead of a 7-column table that scrolls sideways. */
        .registry-table-container {
            overflow-x: visible;
        }

        .registry-table thead {
            display: none;
        }

        .registry-table,
        .registry-table tbody,
        .registry-table tr,
        .registry-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }

        .registry-table tbody tr.registry-row {
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 12px;
            padding: 12px 14px;
            background: var(--bg-ffffff, #fff);
        }

        .registry-table tbody tr + tr {
            margin-top: 10px;
        }

        .registry-table tbody tr:hover {
            background: var(--bg-ffffff, #fff);
        }

        .registry-table td {
            padding: 0;
            border-bottom: none;
            font-size: 12px;
        }

        .registry-table td.cell-title {
            padding-bottom: 10px;
            overflow-wrap: anywhere;
        }

        .registry-table .model-name {
            font-size: 14px;
        }

        .registry-table .model-description {
            max-width: none;
        }

        .registry-table td[data-label] {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-top: 1px solid var(--bd-f0f4f0, #f0f4f0);
            text-align: right;
        }

        .registry-table td[data-label]::before {
            content: attr(data-label);
            flex: none;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #9ab0a0;
            text-align: left;
        }

        .registry-table .cell-value {
            min-width: 0;
        }

        .registry-table .cell-value .metric-note {
            margin-left: 0;
        }

        .registry-table td.actions-cell {
            width: 100%;
            padding-top: 10px;
            border-top: 1px solid var(--bd-f0f4f0, #f0f4f0);
        }

        /* Actions side by side, full width, wrapping when they don't fit. */
        .registry-table .row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .registry-table .row-actions form,
        .registry-table .row-actions > .btn-activate {
            flex: 1 1 140px;
            width: auto;
        }

        .registry-table .row-actions .btn-activate {
            justify-content: center;
            min-height: 38px;
        }

        .registry-table .row-actions > .badge {
            align-self: center;
        }

        .registry-table tr.registry-empty td {
            padding: 16px 0;
            text-align: center;
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

    const modelCheckpointLabel = document.getElementById('modelCheckpointLabel');
    const modelUploadForm = document.getElementById('modelUploadForm');

    function updateModelUploadType() {
        const isClassification = modelTypeSelect.value === 'classification';
        const extension = isClassification ? '.pt' : '.pth';
        segmentationUploadHelp.hidden = isClassification;
        classificationUploadNotice.hidden = !isClassification;
        document.getElementById('performanceHelpSegmentation').hidden = isClassification;
        document.getElementById('performanceHelpGenus').hidden = !isClassification;
        modelCheckpointLabel.textContent = isClassification
            ? 'YOLO Model (.pt, max 512 MB)'
            : 'PyTorch Checkpoint (.pth, max 512 MB)';
        // The confirmation names the model this upload actually replaces.
        modelUploadForm.dataset.confirmMessage = isClassification
            ? 'The model is validated first; if it passes, it replaces the active genus suggestion model.'
            : 'The checkpoint is validated first; if it passes, it replaces the active segmentation model.';
        if (modelCheckpointInput.accept !== extension) {
            modelCheckpointInput.accept = extension;
            modelCheckpointInput.value = '';
            selectedModelFile.textContent = 'No file selected';
        }
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

    @if($errors->getBag('default')->any())
    modelUploadDialog.showModal();
    @endif

    // ---- Edit a model's performance figures ----
    const metricsDialog = document.getElementById('modelMetricsDialog');
    const metricsForm = document.getElementById('modelMetricsForm');
    const metricsFields = {
        accuracy: document.getElementById('metricsAccuracy'),
        metric: document.getElementById('metricsMetric'),
        datasetSize: document.getElementById('metricsDatasetSize'),
    };

    function openMetricsDialog(button, values = null) {
        metricsForm.action = button.dataset.url;
        document.getElementById('modelMetricsName').textContent = button.dataset.name;
        const source = values || {
            accuracy: button.dataset.accuracy,
            metric: button.dataset.metric,
            datasetSize: button.dataset.datasetSize,
        };
        metricsFields.accuracy.value = source.accuracy ?? '';
        metricsFields.metric.value = source.metric ?? '';
        metricsFields.datasetSize.value = source.datasetSize ?? '';
        metricsDialog.showModal();
    }

    document.querySelectorAll('[data-edit-metrics]').forEach(button => {
        button.addEventListener('click', () => {
            // Fresh values each time; errors from an earlier save belong to that attempt only.
            metricsDialog.querySelectorAll('.upload-error').forEach(el => el.remove());
            openMetricsDialog(button);
        });
    });
    metricsDialog.querySelectorAll('[data-close-metrics]').forEach(button => {
        button.addEventListener('click', () => metricsDialog.close());
    });
    metricsDialog.addEventListener('click', event => {
        if (event.target === metricsDialog) metricsDialog.close();
    });

    @if($errors->getBag('metrics')->any() && old('metrics_model_id'))
    // Saving failed: reopen the same model's dialog with what was typed, next to the errors.
    (() => {
        const button = document.querySelector('[data-edit-metrics][data-model-id="{{ (int) old('metrics_model_id') }}"]');
        if (button) openMetricsDialog(button, @json(['accuracy' => old('accuracy'), 'metric' => old('accuracy_metric'), 'datasetSize' => old('dataset_size')]));
    })();
    @endif

    // ---- Accuracy chart: one bar per model, colored by model type ----
    const performanceCanvas = document.getElementById('performanceChart');
    if (performanceCanvas) {
        const typeColors = {
            segmentation: { fill: 'rgba(30,158,98,.75)', line: '#1e9e62', label: 'Segmentation (U-Net)' },
            classification: { fill: 'rgba(90,184,222,.75)', line: '#3a9cc4', label: 'Genus (YOLO)' },
        };
        const types = @json($accuracyChartTypes);
        const metrics = @json($accuracyChartMetrics);
        const colorFor = type => typeColors[type] || { fill: 'rgba(154,176,160,.75)', line: '#9ab0a0', label: type };
        const isDark = document.documentElement.dataset.theme === 'dark';
        const tickColor = isDark ? '#a8bfae' : '#7a9a7a';
        const gridColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';

        new Chart(performanceCanvas, {
            type: 'bar',
            data: {
                labels: @json($accuracyChartLabels),
                datasets: [{
                    label: 'Accuracy (%)',
                    data: @json($accuracyChartValues),
                    backgroundColor: types.map(type => colorFor(type).fill),
                    borderColor: types.map(type => colorFor(type).line),
                    borderWidth: 1.5,
                    borderRadius: 6,
                    maxBarThickness: 56,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600 },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: tickColor,
                            // One legend entry per model type shown, instead of a single "Accuracy" entry.
                            generateLabels: () => [...new Set(types)].map(type => ({
                                text: colorFor(type).label,
                                fillStyle: colorFor(type).fill,
                                strokeStyle: colorFor(type).line,
                                lineWidth: 1.5,
                                fontColor: tickColor,
                            })),
                        },
                        onClick: () => {},
                    },
                    tooltip: {
                        callbacks: {
                            label: context => `${context.parsed.y}% ${metrics[context.dataIndex]}`,
                            afterLabel: context => colorFor(types[context.dataIndex]).label,
                        },
                    },
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: { color: tickColor, callback: value => value + '%' },
                        grid: { color: gridColor },
                    },
                    x: {
                        ticks: { color: tickColor, maxRotation: 30, autoSkip: false },
                        grid: { display: false },
                    },
                },
            },
        });
    }
</script>
{{-- Results of Upload / Activate / Save accuracy / Read from file use the shared toast, like the other admin pages. --}}
@include('partials.flash-toast')
@endsection