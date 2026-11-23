@extends('layouts.admin')

@section('title', 'Map Layers - MangroveMap')

@section('content')
<div id="map-layers-section">
    <div class="page-title">Map Layers</div>
    <p class="page-sub">Delineations, hand-drawn or from a drone survey, and the drone surveys uploaded for them. Export either list as CSV, or the delineations as GeoJSON for QGIS.</p>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Published Delineations</div>
            <div class="stat-value green">{{ $counts['published'] }}</div>
            <div class="stat-change">Visible on the shared map</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending Review</div>
            <div class="stat-value">{{ $counts['pending'] }}</div>
            <div class="stat-change">Awaiting expert review</div>
        </div>
        <a class="stat-card" href="{{ route('admin.map-layers.index', ['tab' => 'imagery', 'status' => 'waiting_owner']) }}">
            <div class="stat-label">Waiting for Owner</div>
            <div class="stat-value {{ $counts['staleDrafts'] ? 'amber' : '' }}">{{ $counts['waitingOwner'] }}</div>
            <div class="stat-change">
                {{ $counts['staleDrafts']
                    ? $counts['staleDrafts'].' over '.\App\Models\DroneImageryLayer::STALE_DRAFT_DAYS.' days · AI drafts not confirmed'
                    : 'AI drafts the owner has not confirmed' }}
            </div>
        </a>
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

    {{-- Swapped in place (no page reload) by map-layers-scripts when switching tabs, filtering or paging. --}}
    <div class="section layer-list" id="layerList">
        <nav class="tabs" aria-label="Layer types">
            <a href="{{ route('admin.map-layers.index') }}" class="tab {{ $tab === 'delineations' ? 'active' : '' }}" @if($tab === 'delineations') aria-current="page" @endif>
                <i class="bi bi-bounding-box"></i> Delineations
            </a>
            <a href="{{ route('admin.map-layers.index', ['tab' => 'imagery']) }}" class="tab {{ $tab === 'imagery' ? 'active' : '' }}" @if($tab === 'imagery') aria-current="page" @endif>
                <i class="bi bi-camera"></i> Drone Surveys
            </a>
        </nav>

        <div class="layer-toolbar">
        <form method="GET" action="{{ route('admin.map-layers.index') }}" class="filter-bar">
            @if($tab === 'imagery')
            <input type="hidden" name="tab" value="imagery">
            @endif
            <input type="search" name="search" class="search-input" aria-label="Search"
                placeholder="{{ $tab === 'delineations' ? 'Search by name, place or user...' : 'Search by layer or user name...' }}" value="{{ $filters['search'] ?? '' }}">
            <select name="status" class="filter-select" aria-label="Status">
                <option value="">All Status</option>
                @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if($tab === 'delineations')
            <select name="source" class="filter-select" aria-label="Source">
                <option value="">All Sources</option>
                @foreach(\App\Models\Delineation::SOURCES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @endif
            <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            @if(array_filter($filters))
            <a href="{{ route('admin.map-layers.index', $tab === 'imagery' ? ['tab' => 'imagery'] : []) }}" class="btn-secondary">Clear</a>
            @endif
        </form>

        {{-- Exports take every row matching the search and status, not only this page. --}}
        <div class="export-actions">
            <a class="btn-secondary" href="{{ route('admin.map-layers.export', array_filter(['tab' => $tab === 'imagery' ? 'imagery' : null] + $filters)) }}"
                download data-no-progress title="Every row that matches the search and status, not only this page">
                <i class="bi bi-filetype-csv"></i> Export CSV
            </a>
            @if($tab === 'delineations')
            <a class="btn-secondary" href="{{ route('admin.map-layers.export', ['format' => 'geojson'] + array_filter($filters)) }}"
                download data-no-progress title="Polygons in WGS 84 (EPSG:4326), one feature per drawn area. Without a status filter: published ones only.">
                <i class="bi bi-globe2"></i> GeoJSON{{ empty($filters['status']) ? ' (published)' : '' }}
            </a>
            @endif
        </div>
        </div>

        @if($tab === 'imagery')
        @include('admin.partials.map-layers-imagery')
        @else
        @include('admin.partials.map-layers-delineations')
        @endif
    </div>

    {{-- Opened by the bells in the rows (pending delineations, surveys waiting for their owner). --}}
    @include('admin.partials.reminder-dialog')
</div>
@endsection

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@include('admin.partials.activity-log-styles')
@include('admin.partials.map-layers-styles')
@endsection

@section('scripts')
@include('partials.flash-toast', ['withValidationErrors' => true])
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@include('admin.partials.map-layers-scripts')
<script>
    // Row actions (delete, reprocess, ...) reload this page; keep the reader where they were instead of
    // jumping back to the top. Tabs, filters and pages swap the list without a reload (map-layers-scripts);
    // they are only saved here when that falls back to a normal load. Restored only coming from this page.
    (function () {
        const KEY = 'mapLayers.scroll';
        const page = location.pathname;
        // Desktop scrolls the layout's .content box; phones scroll the window.
        const box = document.querySelector('.content');

        function save() {
            try {
                sessionStorage.setItem(KEY, JSON.stringify({
                    page, at: Date.now(), box: box ? box.scrollTop : 0, win: window.scrollY,
                }));
            } catch (error) {}
        }

        // On window (bubbling), so it runs after the in-place navigation, which marks the clicks it handles.
        window.addEventListener('click', event => {
            if (event.defaultPrevented) return;
            const link = event.target.closest('#map-layers-section a[href]');
            if (link && new URL(link.href, location.href).pathname === page) save();
        });
        // Capture: forms with a confirm dialog stop the event later and submit again after confirming.
        // Only POST forms (row actions): the GET filter is swapped in place.
        document.getElementById('map-layers-section').addEventListener('submit', event => {
            if ((event.target.method || 'get').toLowerCase() === 'post') save();
        }, true);

        let saved = null;
        try {
            saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
            sessionStorage.removeItem(KEY);
        } catch (error) {}
        const fromHere = document.referrer && new URL(document.referrer).pathname === page;
        if (saved && saved.page === page && fromHere && Date.now() - saved.at < 30000) {
            if (box) box.scrollTop = saved.box;
            window.scrollTo(0, saved.win);
        }
    })();
</script>
@endsection
