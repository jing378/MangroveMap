<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Delineation</th>
                <th>Owner</th>
                <th>Status</th>
                <th>Areas</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($delineations as $delineation)
            @php
            $mapFeatures = \App\Http\Controllers\EndUserController::sanitizeFeaturesForMap($delineation->features);
            [$statusLabel, $statusClass] = match (true) {
                $delineation->isHiddenFromMap() => ['Hidden from map', 'secondary'],
                $delineation->is_approved => ['Published', 'success'],
                $delineation->is_rejected => ['Rejected', 'rejected'],
                default => ['Pending review', 'pending'],
            };
            @endphp
            <tr class="layer-row" data-layer-id="d{{ $delineation->id }}">
                <td>
                    <div class="user-name">{{ $delineation->name }}</div>
                    @if($delineation->notes)
                    <div class="muted clamp">{{ $delineation->notes }}</div>
                    @endif
                </td>
                <td>
                    <div>{{ $delineation->user?->name ?? 'Deleted user' }}</div>
                    <div class="muted">{{ $delineation->user?->roleLabel() }}</div>
                </td>
                <td>
                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    @if($delineation->isHiddenFromMap())
                    <div class="muted" title="{{ $delineation->hidden_reason }}">
                        by {{ $delineation->hiddenBy?->name ?? 'an admin' }}, {{ $delineation->hidden_at->diffForHumans() }}
                    </div>
                    @elseif($delineation->is_approved && $delineation->approvedBy)
                    <div class="muted">by {{ $delineation->approvedBy->name }}</div>
                    @endif
                </td>
                <td>{{ count($mapFeatures) }}</td>
                <td class="nowrap">{{ $delineation->updated_at?->format('M j, Y') ?? '—' }}</td>
                <td>
                    <div class="row-actions">
                        <button type="button" class="action-btn" title="Preview on map" data-preview
                            data-kind="delineation"
                            data-name="{{ $delineation->name }}"
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
