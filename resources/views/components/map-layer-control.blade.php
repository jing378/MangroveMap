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
      <form id="droneImageryUploadForm">
        <label for="droneImageryName">Layer name</label>
        <input id="droneImageryName" name="name" type="text" maxlength="120" placeholder="Survey name">
        <label for="droneImageryFiles">GeoTIFF files</label>
        <input id="droneImageryFiles" name="files[]" type="file" accept=".tif,.tiff,image/tiff" multiple required>
        <button type="submit" class="drone-imagery-upload-button">Upload and process</button>
      </form>
      <div id="droneImageryUploadProgress" class="drone-imagery-upload-progress" hidden>
        <div class="drone-imagery-upload-progress-label">
          <span>Uploading TIFF files</span>
          <span id="droneImageryUploadPercentage">0%</span>
        </div>
        <div id="droneImageryUploadProgressBar" class="drone-imagery-upload-progress-track"
          role="progressbar" aria-label="TIFF upload progress" aria-valuemin="0" aria-valuemax="100"
          aria-valuenow="0">
          <div class="drone-imagery-upload-progress-fill"></div>
        </div>
      </div>
      <div id="droneImageryMessage" class="drone-imagery-message" role="status" aria-live="polite"></div>
      <div id="droneImageryLayers" class="drone-imagery-layers" aria-label="Your drone imagery layers"></div>
    </section>
  </div>
</div>
