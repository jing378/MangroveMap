<div class="table-container layer-table-container">
    <table class="table layer-table">
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
                <td class="cell-title">
                    <div class="user-name">{{ $delineation->name }}</div>
                    @if($delineation->notes)
                    <div class="muted clamp" title="{{ $delineation->notes }}">{{ $delineation->notes }}</div>
                    @endif
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
                <td data-label="Areas">{{ count($mapFeatures) }}</td>
                <td data-label="Updated" class="nowrap">{{ $delineation->updated_at?->format('M j, Y') ?? '—' }}</td>
                <td class="cell-actions">
                    <div class="row-actions">
                        <button type="button" class="action-btn" title="Preview on map" data-preview
                            data-kind="delineation"
                            data-name="{{ $delineation->name }}"
                            @if($delineation->is_rejected && ! $delineation->is_approved) data-rejected @endif
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
