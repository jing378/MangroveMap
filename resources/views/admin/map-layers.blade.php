@extends('layouts.admin')

@section('title', 'Map Layers - MangroveMap')

@section('content')
<div id="map-layers-section">
    <div class="page-title">Map Layers</div>

    @if(session('success'))
    <div class="page-alert page-alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="page-alert page-alert-error" role="alert">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="page-alert page-alert-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Published Delineations</div>
            <div class="stat-value green">{{ $counts['published'] }}</div>
            <div class="stat-change">Visible on the shared map</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Hidden / Pending</div>
            <div class="stat-value">{{ $counts['hidden'] }} <span class="stat-sub">/ {{ $counts['pending'] }}</span></div>
            <div class="stat-change">Hidden by admins / awaiting expert review</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Drone Imagery Layers</div>
            <div class="stat-value">{{ $counts['imagery'] }}</div>
            <div class="stat-change">{{ $counts['imageryReady'] }} ready to view</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Imagery Issues</div>
            <div class="stat-value {{ $counts['imageryFailed'] ? 'red' : '' }}">{{ $counts['imageryFailed'] }}</div>
            <div class="stat-change">Failed processing or AI segmentation</div>
        </div>
    </div>

    <div class="section preview-section">
        <div class="section-title"><i class="bi bi-map"></i> Preview <span id="previewLabel" class="preview-label">Select a layer below to preview it</span></div>
        <div id="layerPreviewMap" class="preview-map" aria-label="Layer preview map"></div>
    </div>

    <div class="section">
        <nav class="tabs" aria-label="Layer types">
            <a href="{{ route('admin.map-layers.index') }}" class="tab {{ $tab === 'delineations' ? 'active' : '' }}" @if($tab === 'delineations') aria-current="page" @endif>
                <i class="bi bi-bounding-box"></i> Mangrove Delineations
            </a>
            <a href="{{ route('admin.map-layers.index', ['tab' => 'imagery']) }}" class="tab {{ $tab === 'imagery' ? 'active' : '' }}" @if($tab === 'imagery') aria-current="page" @endif>
                <i class="bi bi-camera"></i> Drone Imagery
            </a>
        </nav>

        <form method="GET" action="{{ route('admin.map-layers.index') }}" class="filter-bar">
            @if($tab === 'imagery')
            <input type="hidden" name="tab" value="imagery">
            @endif
            <input type="search" name="search" class="search-input" placeholder="Search by layer or user name..." value="{{ $filters['search'] ?? '' }}">
            <select name="status" class="filter-select" aria-label="Status">
                <option value="">All Status</option>
                @if($tab === 'imagery')
                @foreach(['ready' => 'Ready', 'awaiting_review' => 'Awaiting owner review', 'finalizing' => 'Finalizing', 'processing' => 'Processing', 'queued' => 'Queued', 'failed' => 'Failed'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
                @else
                @foreach(['published' => 'Published', 'hidden' => 'Hidden from map', 'pending' => 'Pending review', 'rejected' => 'Rejected'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
                @endif
            </select>
            <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            @if(array_filter($filters))
            <a href="{{ route('admin.map-layers.index', $tab === 'imagery' ? ['tab' => 'imagery'] : []) }}" class="btn-secondary">Clear</a>
            @endif
        </form>

        @if($tab === 'delineations')
        @include('admin.partials.map-layers-delineations')
        @else
        @include('admin.partials.map-layers-imagery')
        @endif
    </div>

    <dialog id="editDelineationDialog" class="layer-dialog" aria-labelledby="editDelineationTitle">
        <form method="POST" id="editDelineationForm" data-confirm="Save changes?"
            data-confirm-message="The name and notes are updated; the previous details are kept as a revision."
            data-confirm-label="Save" data-busy-label="Saving...">
            @csrf
            @method('PUT')
            <h3 id="editDelineationTitle">Edit delineation details</h3>
            <p class="dialog-note">The boundary is not changed here; a revision of the previous details is kept.</p>
            <label for="editDelineationName">Name</label>
            <input id="editDelineationName" name="name" type="text" maxlength="255" required>
            <label for="editDelineationNotes">Notes</label>
            <textarea id="editDelineationNotes" name="notes" rows="4" maxlength="5000"></textarea>
            <div class="dialog-actions">
                <button type="button" class="btn-secondary" data-close-dialog>Cancel</button>
                <button type="submit" class="btn-primary">Save changes</button>
            </div>
        </form>
    </dialog>

    <dialog id="hideDelineationDialog" class="layer-dialog" aria-labelledby="hideDelineationTitle">
        <form method="POST" id="hideDelineationForm" data-confirm="Hide this delineation?"
            data-confirm-message="It disappears from the public and shared maps until restored, and the owner is notified."
            data-confirm-label="Hide" data-confirm-tone="danger" data-busy-label="Hiding...">
            @csrf
            <h3 id="hideDelineationTitle">Hide from map</h3>
            <p class="dialog-note">
                <strong id="hideDelineationName"></strong> will disappear from the public and shared maps until restored.
                The owner is notified.
            </p>
            <label for="hideDelineationReason">Reason (optional, shared with the owner)</label>
            <textarea id="hideDelineationReason" name="reason" rows="3" maxlength="500"></textarea>
            <div class="dialog-actions">
                <button type="button" class="btn-secondary" data-close-dialog>Cancel</button>
                <button type="submit" class="btn-danger">Hide from map</button>
            </div>
        </form>
    </dialog>

    <dialog id="renameImageryDialog" class="layer-dialog" aria-labelledby="renameImageryTitle">
        <form method="POST" id="renameImageryForm" data-confirm="Save changes?"
            data-confirm-message="The imagery layer is renamed." data-confirm-label="Save" data-busy-label="Saving...">
            @csrf
            @method('PUT')
            <h3 id="renameImageryTitle">Rename imagery layer</h3>
            <label for="renameImageryName">Layer name</label>
            <input id="renameImageryName" name="name" type="text" maxlength="120" required>
            <div class="dialog-actions">
                <button type="button" class="btn-secondary" data-close-dialog>Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </dialog>
</div>
@endsection

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@include('admin.partials.activity-log-styles')
@include('admin.partials.map-layers-styles')
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@include('admin.partials.map-layers-scripts')
@endsection
