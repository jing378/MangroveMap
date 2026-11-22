<div class="table-container layer-table-container">
    <table class="table layer-table">
        <thead>
            <tr>
                <th>Layer</th>
                <th>Uploaded by</th>
                <th>Processing</th>
                <th>AI segmentation</th>
                <th>Uploaded</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($layers as $layer)
            @php
            $statusClass = match ($layer->status) {
                'ready' => 'success',
                'failed' => 'rejected',
                default => 'pending',
            };
            $segmentationClass = match ($layer->segmentation_status) {
                'ready' => 'success',
                'failed' => 'rejected',
                'queued', 'processing' => 'pending',
                default => 'secondary',
            };
            // Map tiles once ready, the review image while it awaits its owner's review, else nothing to preview.
            $imagery = \App\Http\Controllers\MapLayerController::imageryPreviewAttributes($layer);
            @endphp
            <tr class="layer-row" data-layer-id="i{{ $layer->id }}">
                <td class="cell-title">
                    <div class="user-name">{{ $layer->name }}</div>
                    <div class="muted">{{ $layer->files_count }} {{ \Illuminate\Support\Str::plural('file', $layer->files_count) }} · {{ \App\Http\Controllers\MapLayerController::formatBytes((int) $layer->files_sum_file_size) }} · {{ $layer->source_type === 'batch' ? 'Survey batch' : 'Single GeoTIFF' }}</div>
                    <div class="muted linked-record">
                        @if($layer->delineation)
                        @php [$linkedLabel, $linkedClass] = \App\Http\Controllers\MapLayerController::delineationStatus($layer->delineation); @endphp
                        <a class="cross-link" href="{{ route('admin.map-layers.index', ['delineation' => $layer->delineation->id]) }}"
                            title="Open the delineation submitted from this imagery"><i class="bi bi-bounding-box"></i> Delineation</a>
                        <span class="badge {{ $linkedClass }}">{{ $linkedLabel }}</span>
                        @else
                        <i class="bi bi-bounding-box"></i> No delineation yet
                        @endif
                    </div>
                </td>
                <td data-label="Uploaded by">
                    <div class="cell-value">
                        <div>{{ $layer->user?->name ?? 'Deleted user' }}</div>
                        <div class="muted">{{ $layer->user?->roleLabel() }}</div>
                    </div>
                </td>
                <td data-label="Processing">
                    <div class="cell-value">
                        <span class="badge {{ $statusClass }}">{{ \App\Http\Controllers\MapLayerController::IMAGERY_STATUSES[$layer->status] ?? ucfirst((string) $layer->status) }}</span>
                        @if($layer->status === 'failed' && $layer->error_message)
                        <div class="muted clamp status-note" title="{{ $layer->error_message }}">{{ $layer->error_message }}</div>
                        @endif
                    </div>
                </td>
                <td data-label="AI segmentation">
                    <div class="cell-value">
                        <span class="badge {{ $segmentationClass }}">{{ $layer->segmentation_status ? ucfirst($layer->segmentation_status) : 'Not run' }}</span>
                        @if($layer->segmentation_status === 'failed' && $layer->segmentation_error)
                        <div class="muted clamp status-note" title="{{ $layer->segmentation_error }}">{{ $layer->segmentation_error }}</div>
                        @endif
                        @php
                        $topGenus = \App\Http\Controllers\MapLayerController::topGenus($layer);
                        $canopies = $layer->classification_stats['canopy_count'] ?? null;
                        @endphp
                        @if($topGenus || $canopies !== null)
                        <div class="muted">
                            {{ $topGenus ? 'Genus: '.$topGenus : '' }}{{ $topGenus && $canopies !== null ? ' · ' : '' }}{{ $canopies !== null ? number_format($canopies).' '.\Illuminate\Support\Str::plural('canopy', $canopies) : '' }}
                        </div>
                        @elseif($layer->classification_status === 'failed')
                        <div class="muted status-note">Genus detection failed</div>
                        @endif
                    </div>
                </td>
                <td data-label="Uploaded" class="nowrap">{{ $layer->created_at?->format('M j, Y') ?? '—' }}</td>
                <td class="cell-actions">
                    <div class="row-actions">
                        {{-- Relative URLs: absolute ones can come out as http:// behind the https tunnel and get blocked. --}}
                        <button type="button" class="action-btn" title="Preview on map" data-preview
                            data-kind="imagery"
                            data-name="{{ $layer->name }}"
                            @foreach($imagery as $attribute => $value) data-{{ $attribute }}="{{ $value }}" @endforeach
                            @if($layerDelineation = $layer->delineation)
                            {{-- The delineation submitted from this imagery, drawn on it (its status colour). --}}
                            data-features='@json(\App\Http\Controllers\EndUserController::sanitizeFeaturesForMap($layerDelineation->features))'
                            data-delineation-status="{{ \App\Http\Controllers\MapLayerController::delineationStatus($layerDelineation)[0] }}"
                            @if($layerDelineation->is_rejected && ! $layerDelineation->is_approved) data-rejected @endif
                            @elseif($layer->segmentation_status === 'ready')
                            data-segmentation-url="{{ route('drone-imagery.segmentation.show', $layer->id, false) }}"
                            @endif
                            @disabled($imagery === [])><i class="bi bi-eye"></i></button>
                        <button type="button" class="action-btn" title="Rename" data-rename-imagery
                            data-url="{{ route('admin.map-layers.imagery.update', $layer) }}"
                            data-name="{{ $layer->name }}"><i class="bi bi-pencil"></i></button>
                        @if($layer->status === 'failed')
                        <form method="POST" action="{{ route('admin.map-layers.imagery.reprocess', $layer) }}"
                            data-confirm="Retry processing?" data-confirm-message="&quot;{{ $layer->name }}&quot; is processed again in the background."
                            data-confirm-label="Retry" data-busy-label="Retrying...">
                            @csrf
                            <button type="submit" class="action-btn" title="Retry processing"><i class="bi bi-arrow-repeat"></i></button>
                        </form>
                        @elseif($layer->status === 'ready' && ! $layer->isSegmenting())
                        <form method="POST" action="{{ route('admin.map-layers.imagery.segment', $layer) }}"
                            data-confirm="{{ $layer->segmentation_status === 'ready' ? 'Run AI segmentation again?' : 'Run AI segmentation?' }}"
                            data-confirm-message="{{ $layer->segmentation_status === 'ready' ? 'The current result is replaced when it finishes.' : 'The AI detects the mangrove areas in the background.' }}"
                            data-confirm-label="Run" data-busy-label="Starting AI...">
                            @csrf
                            <button type="submit" class="action-btn" title="{{ $layer->segmentation_status ? 'Re-run' : 'Run' }} AI segmentation"><i class="bi bi-cpu"></i></button>
                        </form>
                        @endif
                        @unless($layer->isProcessing() || $layer->isSegmenting())
                        <form method="POST" action="{{ route('admin.map-layers.imagery.destroy', $layer) }}"
                            data-confirm="Delete this record?" data-confirm-message="&quot;{{ $layer->name }}&quot; and all of its uploaded TIFFs and tiles are deleted. This cannot be undone.{{ $layer->delineation ? ' Its delineation "'.$layer->delineation->name.'" ('.\App\Http\Controllers\MapLayerController::delineationStatus($layer->delineation)[0].') stays, but will have no drone imagery. Delete it on the Delineations tab if it should go too.' : '' }}"
                            data-confirm-label="Delete" data-confirm-tone="danger" data-busy-label="Deleting...">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                        @endunless
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="empty-row">No drone imagery layers match these filters.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.partials.map-layers-pagination', ['paginator' => $layers, 'noun' => 'layer'])
