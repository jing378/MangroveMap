{{-- Base layer + drone imagery controls for the shared Leaflet map (see users.partials.map-base-scripts). --}}
<div class="map-layer-control">
  <button id="layerToggle" type="button" onclick="toggleLayerMenu()" aria-expanded="false"
    aria-controls="layerMenu" title="Choose map layer" aria-label="Choose map layer">
    <i class="bi bi-layers-fill"></i>
  </button>
  <button id="droneImageryToggle" type="button" onclick="toggleDroneImageryMenu()" aria-expanded="false"
    aria-controls="droneImageryMenu" title="Drone imagery" aria-label="Drone imagery">
    <i class="bi bi-globe2"></i>
  </button>
  {{ $slot }}
  <div id="layerMenu" class="layer-menu" aria-label="Base layer options">
    <button class="layer-option active" onclick="setBase('sat', this)">Satellite</button>
    <button class="layer-option" onclick="setBase('osm', this)">Street</button>
    <button class="layer-option" onclick="setBase('topo', this)">Topo</button>
    <button class="layer-option" onclick="setBase('mangrove', this)"
      title="High-resolution view for identifying mangrove areas">Mangrove 400-ft View</button>
  </div>
  <div id="droneImageryMenu" class="layer-menu drone-imagery-menu" aria-label="Drone imagery uploads and layers">
    <section class="drone-imagery-panel" aria-labelledby="droneImageryTitle">
      <h3 id="droneImageryTitle">Drone imagery</h3>
      {{-- Uploading and reviewing live on the Drone Upload page; this panel shows layers on the map. --}}
      <a href="{{ route('drone-imagery.page') }}" class="drone-imagery-upload-button">
        <i class="bi bi-cloud-arrow-up"></i> Upload &amp; manage drone imagery
      </a>
      <div id="droneImageryMessage" class="drone-imagery-message" role="status" aria-live="polite"></div>
      <div id="droneImageryLayers" class="drone-imagery-layers" aria-label="Available drone imagery layers"></div>
    </section>
  </div>
  {{-- Upload/processing progress that stays visible while the drone imagery panel is closed. --}}
  <div id="droneImageryProgressChip" class="drone-progress-chip" role="status" aria-live="polite" tabindex="0"
    title="Show drone imagery progress" hidden>
    <i class="bi bi-cloud-arrow-up"></i>
    <span class="drone-progress-chip-body">
      <span class="drone-progress-chip-label">Processing drone imagery…</span>
      <span class="drone-progress-chip-track" role="progressbar" aria-label="Drone imagery progress"
        aria-valuemin="0" aria-valuemax="100">
        <span class="drone-progress-chip-fill"></span>
      </span>
    </span>
  </div>
</div>
