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
            // The drone layer's stage (as on the imagery tab), shown only when it says more than this row's own
            // status (e.g. Finalizing, Failed): Published / Pending / Rejected just repeat it. The layer belongs to
            // this delineation and its owner, so reuse those instead of loading them again per row.
            $droneStage = null;
            if ($droneLayer = $delineation->droneImageryLayer) {
                $droneLayer->setRelation('delineation', $delineation)->setRelation('user', $delineation->user);
                $droneStage = $droneLayer->stage();
                if (in_array($droneStage, ['published', 'pending_expert', 'rejected'], true)) {
                    $droneStage = null;
                }
            }
            @endphp
            {{-- Clicking the row shows it on the preview map (map-layers-scripts). --}}
            <tr class="layer-row" data-layer-id="d{{ $delineation->id }}"
                @if($mapFeatures !== [])
                data-preview tabindex="0" title="Show on the preview map"
                data-kind="delineation"
                data-name="{{ $delineation->name }}"
                @if($delineation->is_rejected && ! $delineation->is_approved) data-rejected @endif
                @foreach($imagery as $attribute => $value) data-{{ $attribute }}="{{ $value }}" @endforeach
                data-features='@json($mapFeatures)'
                @endif>
                <td class="cell-title">
                    <div class="user-name">{{ $delineation->name }}</div>
                    @if($delineation->notes)
                    <div class="muted clamp" title="{{ $delineation->notes }}">{{ $delineation->notes }}</div>
                    @endif
                    {{-- Where it came from: traced by hand, or a drone survey's AI areas the owner corrected and confirmed. --}}
                    <div class="muted source-line">
                        @if($delineation->isFromDrone())
                        @if($delineation->droneImageryLayer)
                        <a class="source-tag drone" href="{{ route('admin.map-layers.index', ['tab' => 'imagery', 'layer' => $delineation->drone_imagery_layer_id]) }}"
                            title="Open the drone imagery this was drawn from"><i class="bi bi-camera"></i> {{ $delineation->sourceLabel() }}</a>
                        @if($droneStage)
                        <span class="badge {{ \App\Http\Controllers\MapLayerController::STAGE_BADGES[$droneStage] }}"
                            title="Stage of the drone imagery">{{ \App\Models\DroneImageryLayer::STAGES[$droneStage] }}</span>
                        @endif
                        @else
                        <span class="source-tag drone"><i class="bi bi-camera"></i> {{ $delineation->sourceLabel() }}</span>
                        <span title="The drone imagery was deleted; this delineation was kept">· imagery deleted</span>
                        @endif
                        @else
                        <span class="source-tag"><i class="bi bi-pencil"></i> {{ $delineation->sourceLabel() }}</span>
                        @endif
                        · Updated {{ $delineation->updated_at?->format('M j, Y') ?? '—' }}
                    </div>
                </td>
                <td data-label="Owner" class="cell-owner">
                    <div class="cell-value">
                        <div>{{ $delineation->user?->name ?? 'Deleted user' }}</div>
                        <div class="muted">{{ $delineation->user?->roleLabel() }}</div>
                    </div>
                </td>
                <td data-label="Status" class="cell-status">
                    <div class="cell-value">
                        <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        @if($delineation->is_approved && $delineation->approvedBy)
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
                <td data-label="Location" class="cell-meta">
                    <div class="cell-value">
                        <div>{{ $facts['municipality'] ?? 'Not matched yet' }}</div>
                        @if($facts['barangays'])
                        <div class="muted clamp" title="{{ implode(', ', $facts['barangays']) }}">{{ implode(', ', $facts['barangays']) }}</div>
                        @endif
                    </div>
                </td>
                <td data-label="Area" class="cell-area">
                    <div class="cell-value">
                        <div class="nowrap">{{ number_format($facts['hectares'], 2) }} ha <span class="muted">· {{ $areaCount }} {{ \Illuminate\Support\Str::plural('area', $areaCount) }}</span></div>
                        @if($facts['genera'])
                        <div class="muted">{{ implode(', ', $facts['genera']) }}</div>
                        @endif
                    </div>
                </td>
                <td class="cell-actions">
                    <div class="row-actions">
                        @if(! $delineation->is_approved && ! $delineation->is_rejected)
                        {{-- Pending: remind the experts to review this one. --}}
                        @include('admin.partials.reminder-bell', [
                            'kind' => \App\Models\AdminReminder::DELINEATION,
                            'target' => $delineation->id,
                            'title' => 'Remind the experts to review this',
                            'who' => 'Every expert is reminded to review "'.$delineation->name.'", waiting since '.$delineation->created_at?->diffForHumans().'.',
                            'recent' => $reminders[$delineation->id] ?? null,
                        ])
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
