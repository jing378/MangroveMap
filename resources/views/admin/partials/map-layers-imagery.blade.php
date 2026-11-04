<div class="table-container">
    <table class="table">
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
            $isReady = $layer->status === 'ready' && $layer->bounds;
            @endphp
            <tr class="layer-row" data-layer-id="i{{ $layer->id }}">
                <td>
                    <div class="user-name">{{ $layer->name }}</div>
                    <div class="muted">{{ $layer->files_count }} {{ \Illuminate\Support\Str::plural('file', $layer->files_count) }} · {{ $layer->source_type === 'batch' ? 'Survey batch' : 'Single GeoTIFF' }}</div>
                </td>
                <td>
                    <div>{{ $layer->user?->name ?? 'Deleted user' }}</div>
                    <div class="muted">{{ $layer->user?->roleLabel() }}</div>
                </td>
                <td>
                    <span class="badge {{ $statusClass }}">{{ $layer->status === 'awaiting_review' ? 'Awaiting owner review' : ucfirst($layer->status) }}</span>
                    @if($layer->status === 'failed' && $layer->error_message)
                    <div class="muted clamp" title="{{ $layer->error_message }}">{{ $layer->error_message }}</div>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $segmentationClass }}">{{ $layer->segmentation_status ? ucfirst($layer->segmentation_status) : 'Not run' }}</span>
                    @if($layer->segmentation_status === 'failed' && $layer->segmentation_error)
                    <div class="muted clamp" title="{{ $layer->segmentation_error }}">{{ $layer->segmentation_error }}</div>
                    @endif
                </td>
                <td class="nowrap">{{ $layer->created_at?->format('M j, Y') ?? '—' }}</td>
                <td>
                    <div class="row-actions">
                        <button type="button" class="action-btn" title="Preview on map" data-preview
                            data-kind="imagery"
                            data-name="{{ $layer->name }}"
                            @if($isReady)
                            data-tile-url="{{ url("/drone-imagery/{$layer->id}/tiles") }}/{z}/{x}/{y}"
                            data-bounds='@json($layer->bounds)'
                            data-zoom-min="{{ $layer->zoom_min }}"
                            data-zoom-max="{{ $layer->zoom_max }}"
                            @endif
                            @if($layer->segmentation_status === 'ready')
                            data-segmentation-url="{{ route('drone-imagery.segmentation.show', $layer->id) }}"
                            @endif
                            @disabled(! $isReady)><i class="bi bi-eye"></i></button>
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
                            data-confirm="Delete this record?" data-confirm-message="&quot;{{ $layer->name }}&quot; and all of its uploaded TIFFs and tiles are deleted. This cannot be undone."
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
