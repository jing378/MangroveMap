<div class="table-container layer-table-container">
    <table class="table layer-table">
        <thead>
            <tr>
                <th>Delineation</th>
                <th>Owner</th>
                <th>Status</th>
                <th>Location</th>
                <th>Area</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($delineations as $delineation)
            @php
            $mapFeatures = \App\Http\Controllers\EndUserController::sanitizeFeaturesForMap($delineation->features);
            [$statusLabel, $statusClass] = \App\Http\Controllers\MapLayerController::delineationStatus($delineation);
            $facts = \App\Http\Controllers\MapLayerController::delineationFacts($delineation);
            $areaCount = count(array_filter($mapFeatures, fn ($feature) => $feature['type'] === 'area'));
            // Drawn from drone imagery: the preview shows that imagery under the outline (tiles, or the review image).
            $imagery = \App\Http\Controllers\MapLayerController::imageryPreviewAttributes($delineation->droneImageryLayer);
            @endphp
            <tr class="layer-row" data-layer-id="d{{ $delineation->id }}">
                <td class="cell-title">
                    <div class="user-name">{{ $delineation->name }}</div>
                    @if($delineation->notes)
                    <div class="muted clamp" title="{{ $delineation->notes }}">{{ $delineation->notes }}</div>
                    @endif
                    <div class="muted">
                        Updated {{ $delineation->updated_at?->format('M j, Y') ?? '—' }}
                        @if($delineation->droneImageryLayer)
                        · <a class="cross-link" href="{{ route('admin.map-layers.index', ['tab' => 'imagery', 'layer' => $delineation->drone_imagery_layer_id]) }}"
                            title="Open the drone imagery this was drawn from"><i class="bi bi-camera"></i> Drone imagery</a>
                        @endif
                    </div>
                </td>
                <td data-label="Owner">
                    <div class="cell-value">
                        <div>{{ $delineation->user?->name ?? 'Deleted user' }}</div>
                        <div class="muted">{{ $delineation->user?->roleLabel() }}</div>
                    </div>
                </td>
                <td data-label="Status">
                    <div class="cell-value">
                        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        @if($delineation->isHiddenFromMap())
                        <div class="muted">
                            by {{ $delineation->hiddenBy?->name ?? 'an admin' }}, {{ $delineation->hidden_at?->diffForHumans() }}
                        </div>
                        @if($delineation->hidden_reason)
                        <div class="muted status-note">Reason: {{ $delineation->hidden_reason }}</div>
                        @endif
                        @elseif($delineation->is_approved && $delineation->approvedBy)
                        <div class="muted">by {{ $delineation->approvedBy->name }}</div>
                        @elseif($removableAt = $delineation->rejectedRemovableAt())
                        {{-- Kept while the owner may still revise it (REJECTED_REMOVAL_GRACE_DAYS), then removable. --}}
                        @if($removableAt->isFuture())
                        @php $daysLeft = max(1, (int) ceil(now()->diffInHours($removableAt) / 24)); @endphp
                        <div class="muted" title="The owner can revise and resubmit it until {{ $removableAt->timezone(config('app.timezone'))->format('M j, Y g:i A') }}">
                            {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left to revise
                        </div>
                        @else
                        <div class="muted status-removable" title="The revision week is over, so it can be deleted">Revision period over · removable</div>
                        @endif
                        @endif
                    </div>
                </td>
                <td data-label="Location">
                    <div class="cell-value">
                        <div>{{ $facts['municipality'] ?? 'Not matched yet' }}</div>
                        @if($facts['barangays'])
                        <div class="muted clamp" title="{{ implode(', ', $facts['barangays']) }}">{{ implode(', ', $facts['barangays']) }}</div>
                        @endif
                    </div>
                </td>
                <td data-label="Area">
                    <div class="cell-value">
                        <div class="nowrap">{{ number_format($facts['hectares'], 2) }} ha <span class="muted">· {{ $areaCount }} {{ \Illuminate\Support\Str::plural('area', $areaCount) }}</span></div>
                        @if($facts['genera'])
                        <div class="muted">{{ implode(', ', $facts['genera']) }}</div>
                        @endif
                    </div>
                </td>
                <td class="cell-actions">
                    <div class="row-actions">
                        <button type="button" class="action-btn" title="Preview on map" data-preview
                            data-kind="delineation"
                            data-name="{{ $delineation->name }}"
                            @if($delineation->is_rejected && ! $delineation->is_approved) data-rejected @endif
                            @foreach($imagery as $attribute => $value) data-{{ $attribute }}="{{ $value }}" @endforeach
                            data-features='@json($mapFeatures)'
                            @disabled($mapFeatures === [])><i class="bi bi-eye"></i></button>
                        <button type="button" class="action-btn" title="Edit name and notes" data-edit-delineation
                            data-url="{{ route('admin.map-layers.delineations.update', $delineation) }}"
                            data-name="{{ $delineation->name }}"
                            data-notes="{{ $delineation->notes }}"><i class="bi bi-pencil"></i></button>
                        @if($delineation->isHiddenFromMap())
                        <form method="POST" action="{{ route('admin.map-layers.delineations.restore', $delineation) }}"
                            data-confirm="Restore this delineation?" data-confirm-message="&quot;{{ $delineation->name }}&quot; appears on the public and shared maps again, and the owner is notified."
                            data-confirm-label="Restore" data-busy-label="Restoring...">
                            @csrf
                            <button type="submit" class="action-btn" title="Restore to map"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </form>
                        @elseif($delineation->is_approved)
                        <button type="button" class="action-btn" title="Hide from map" data-hide-delineation
                            data-url="{{ route('admin.map-layers.delineations.hide', $delineation) }}"
                            data-name="{{ $delineation->name }}"><i class="bi bi-eye-slash"></i></button>
                        @endif
                        <form method="POST" action="{{ route('admin.map-layers.delineations.destroy', $delineation) }}"
                            data-confirm="Delete this record?" data-confirm-message="&quot;{{ $delineation->name }}&quot; is permanently deleted with its yearly observations and revisions, and the owner is notified. This cannot be undone."
                            data-confirm-label="Delete" data-confirm-tone="danger" data-busy-label="Deleting...">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="empty-row">No delineations match these filters.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.partials.map-layers-pagination', ['paginator' => $delineations, 'noun' => 'delineation'])
