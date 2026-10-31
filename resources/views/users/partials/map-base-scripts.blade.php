    {{-- Shared Leaflet base map: tile layers, drone imagery, resize handling, locate control and layer menus.
         Inlined into a page <script> that has already defined `csrfToken`; defines `mainMap`, `curBase` and the base layers. --}}
    const droneImageryIndexUrl = @json(route('drone-imagery.index'));
    const droneImageryUploadUrl = @json(route('drone-imagery.store'));
    const droneImageryStatusUrl = @json(url('/drone-imagery'));

    // Highest zoom the map allows. Base maps and drone imagery are upscaled past their native zoom
    // up to this level so the map never goes blank when zooming in close to a GeoTIFF.
    const MAP_MAX_ZOOM = 22;
    const tileLayerOpts = {
      updateWhenZooming: true,
      updateWhenIdle: false,
      keepBuffer: 3,
      maxZoom: MAP_MAX_ZOOM,
    };
    // Viewing height for mangrove identification. Change this value to adjust later.
    const MANGROVE_VIEW_HEIGHT_FT = 400;
    const FEET_TO_METERS = 0.3048;
    const EARTH_CIRCUMFERENCE_M = 40075016.686;

    function zoomForViewHeightFt(heightFt, lat) {
      const heightM = Math.max(Number(heightFt) * FEET_TO_METERS, 1);
      const latRad = (Number(lat) || 0) * Math.PI / 180;
      return Math.log2((EARTH_CIRCUMFERENCE_M * Math.cos(latRad)) / heightM);
    }

    // Esri imagery returns "Map data not yet available" placeholder tiles at z19 in many rural/coastal
    // areas, so cap its native zoom at 18 and let Leaflet upscale beyond that instead.
    const satL = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      ...tileLayerOpts,
      maxNativeZoom: 18,
    });
    const osmL = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      ...tileLayerOpts,
      maxNativeZoom: 19,
    });
    const topoL = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      ...tileLayerOpts,
      maxNativeZoom: 17,
    });
    const mangroveL = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      ...tileLayerOpts,
      maxNativeZoom: 18,
    });
    const baseLayers = {
      sat: satL,
      osm: osmL,
      topo: topoL,
      mangrove: mangroveL,
    };
    let curBase = satL;
    let mainMap = L.map('mainMap', {
      zoomControl: true,
      preferCanvas: true,
      maxZoom: MAP_MAX_ZOOM,
      layers: [satL],
    }).setView([10.358, 124.973], 13);

    const droneImageryLayersElement = document.getElementById('droneImageryLayers');
    const droneImageryMessageElement = document.getElementById('droneImageryMessage');
    const droneImageryUploadForm = document.getElementById('droneImageryUploadForm');
    const droneImageryUploadProgress = document.getElementById('droneImageryUploadProgress');
    const droneImageryUploadProgressBar = document.getElementById('droneImageryUploadProgressBar');
    const droneImageryUploadProgressFill = droneImageryUploadProgressBar?.querySelector('.drone-imagery-upload-progress-fill');
    const droneImageryUploadPercentage = document.getElementById('droneImageryUploadPercentage');
    const droneImageryTileLayers = new Map();

    function showDroneImageryMessage(message, isError = false) {
      if (!droneImageryMessageElement) return;
      droneImageryMessageElement.textContent = message;
      droneImageryMessageElement.style.color = isError ? '#b42318' : '#4c6b5d';
    }

    function updateDroneImageryUploadProgress(percentage) {
      if (!droneImageryUploadProgress || !droneImageryUploadProgressBar || !droneImageryUploadProgressFill || !droneImageryUploadPercentage) return;
      const progress = Math.max(0, Math.min(100, Math.round(percentage)));
      droneImageryUploadProgress.hidden = false;
      droneImageryUploadProgressBar.setAttribute('aria-valuenow', String(progress));
      droneImageryUploadProgressFill.style.width = `${progress}%`;
      droneImageryUploadPercentage.textContent = `${progress}%`;
    }

    function uploadDroneImagery(formData, onProgress) {
      return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('POST', droneImageryUploadUrl);
        request.withCredentials = true;
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        request.upload.addEventListener('progress', event => {
          if (event.lengthComputable) {
            onProgress((event.loaded / event.total) * 100);
          }
        });
        request.addEventListener('load', () => {
          let payload;
          try {
            payload = JSON.parse(request.responseText);
          } catch {
            reject(new Error('The server returned an invalid response while uploading TIFF files.'));
            return;
          }

          if (request.status < 200 || request.status >= 300) {
            const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            reject(new Error(validationError || payload.message || 'Unable to upload TIFF files.'));
            return;
          }

          resolve(payload);
        });
        request.addEventListener('error', () => reject(new Error('Network error while uploading TIFF files.')));
        request.addEventListener('abort', () => reject(new Error('TIFF file upload was cancelled.')));
        request.send(formData);
      });
    }

    function addDroneImageryTileLayer(layer) {
      if (!layer.tile_url || !layer.bounds) return null;
      if (droneImageryTileLayers.has(layer.id)) return droneImageryTileLayers.get(layer.id);

      const bounds = L.latLngBounds(
        [layer.bounds.south, layer.bounds.west],
        [layer.bounds.north, layer.bounds.east]
      );
      const tileLayer = L.tileLayer(layer.tile_url, {
        opacity: 0.78,
        bounds,
        minNativeZoom: layer.zoom_min,
        maxNativeZoom: layer.zoom_max,
        maxZoom: MAP_MAX_ZOOM,
        updateWhenZooming: true,
        updateWhenIdle: false,
        keepBuffer: 3,
      });
      droneImageryTileLayers.set(layer.id, tileLayer);
      return tileLayer;
    }

    // Move the map onto the imagery at a zoom the GeoTIFF actually has tiles for.
    function zoomToDroneImagery(layer, tileLayer) {
      const bounds = tileLayer.options.bounds;
      if (!bounds || !bounds.isValid()) return;
      const fitZoom = mainMap.getBoundsZoom(bounds, false, L.point(40, 40));
      const targetZoom = Math.min(
        Math.max(fitZoom, Number(layer.zoom_min) || 0),
        Number(layer.zoom_max) || MAP_MAX_ZOOM,
        MAP_MAX_ZOOM
      );
      mainMap.flyTo(bounds.getCenter(), targetZoom, { duration: 0.8 });
    }

    function renderDroneImageryLayers(layers, activateLayerId = null) {
      if (!droneImageryLayersElement) return;
      droneImageryLayersElement.replaceChildren();

      layers.forEach(layer => {
        const row = document.createElement('label');
        row.className = 'drone-imagery-layer';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.disabled = layer.status !== 'ready';

        const info = document.createElement('span');
        info.className = 'drone-imagery-layer-info';
        const name = document.createElement('span');
        name.className = 'drone-imagery-layer-name';
        name.textContent = layer.name;
        const state = document.createElement('span');
        state.className = 'drone-imagery-layer-state';
        state.textContent = layer.status === 'ready'
          ? `${layer.source_type === 'batch' ? 'Mosaic' : 'GeoTIFF'} · zoom ${layer.zoom_min}–${layer.zoom_max}`
          : (layer.error_message || layer.status);
        info.append(name, state);
        row.append(checkbox, info);
        droneImageryLayersElement.appendChild(row);

        if (layer.status !== 'ready') return;

        const tileLayer = addDroneImageryTileLayer(layer);
        if (!tileLayer) return;

        checkbox.checked = mainMap.hasLayer(tileLayer) || Number(activateLayerId) === Number(layer.id);
        checkbox.addEventListener('change', () => {
          if (checkbox.checked) {
            tileLayer.addTo(mainMap);
            zoomToDroneImagery(layer, tileLayer);
          } else {
            mainMap.removeLayer(tileLayer);
          }
        });

        if (Number(activateLayerId) === Number(layer.id)) {
          tileLayer.addTo(mainMap);
          zoomToDroneImagery(layer, tileLayer);
        }
      });
    }

    async function loadDroneImageryLayers(activateLayerId = null) {
      if (!droneImageryLayersElement) return [];
      const response = await fetch(droneImageryIndexUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !Array.isArray(payload.layers)) {
        throw new Error(payload.message || 'Unable to load your drone imagery layers.');
      }
      renderDroneImageryLayers(payload.layers, activateLayerId);
      return payload.layers;
    }

    async function waitForDroneImagery(layerId) {
      for (let attempt = 0; attempt < 1200; attempt += 1) {
        await new Promise(resolve => setTimeout(resolve, 3000));
        const response = await fetch(`${droneImageryStatusUrl}/${encodeURIComponent(layerId)}`, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok) {
          throw new Error(payload.message || 'Unable to check raster processing status.');
        }
        if (payload.status === 'ready') return payload;
        if (payload.status === 'failed') {
          throw new Error(payload.error_message || 'Raster processing failed.');
        }
        showDroneImageryMessage('Upload received. Validating and processing the imagery…');
      }
      throw new Error('Raster processing is taking longer than expected. Check this layer again later.');
    }

    if (droneImageryUploadForm) {
      droneImageryUploadForm.addEventListener('submit', async event => {
        event.preventDefault();
        const fileInput = document.getElementById('droneImageryFiles');
        const submitButton = droneImageryUploadForm.querySelector('button[type="submit"]');
        if (!fileInput?.files?.length) {
          showDroneImageryMessage('Select at least one .tif or .tiff file.', true);
          return;
        }

        const formData = new FormData(droneImageryUploadForm);
        submitButton.disabled = true;
        showDroneImageryMessage('Uploading TIFF files…');
        try {
          const payload = await uploadDroneImagery(formData, updateDroneImageryUploadProgress);

          if (droneImageryUploadProgress) droneImageryUploadProgress.hidden = true;
          showDroneImageryMessage('Upload complete. Waiting for GDAL processing…');
          await waitForDroneImagery(payload.id);
          const layers = await loadDroneImageryLayers(payload.id);
          const readyLayer = layers.find(layer => Number(layer.id) === Number(payload.id));
          showDroneImageryMessage(readyLayer ? `"${readyLayer.name}" is ready and displayed on the map.` : 'Imagery is ready.');
          droneImageryUploadForm.reset();
        } catch (error) {
          if (droneImageryUploadProgress) droneImageryUploadProgress.hidden = true;
          showDroneImageryMessage(error.message || 'Drone imagery upload failed.', true);
          try {
            await loadDroneImageryLayers();
          } catch (loadError) {
            console.error('Unable to refresh drone imagery layer list.', loadError);
          }
        } finally {
          submitButton.disabled = false;
        }
      });
    }

    loadDroneImageryLayers().catch(error => {
      showDroneImageryMessage(error.message || 'Unable to load drone imagery layers.', true);
      console.error('Unable to load drone imagery layers.', error);
    });

    let mainMapResizeTimer = null;
    let mainMapLastSize = { w: 0, h: 0 };
    let mainMapIsDragging = false;
    mainMap.on('movestart', () => { mainMapIsDragging = true; });
    mainMap.on('moveend', () => { mainMapIsDragging = false; });

    if (typeof ResizeObserver !== 'undefined') {
      const mapContainer = document.getElementById('mainMap');
      if (mapContainer) {
        new ResizeObserver((entries) => {
          const entry = entries[0];
          if (!entry) return;
          const { width, height } = entry.contentRect;
          if (Math.abs(width - mainMapLastSize.w) < 1 && Math.abs(height - mainMapLastSize.h) < 1) {
            return;
          }
          mainMapLastSize = { w: width, h: height };
          if (mainMapIsDragging) return;
          // Debounce to prevent layout thrashing and stutter during CSS panel transitions
          clearTimeout(mainMapResizeTimer);
          mainMapResizeTimer = setTimeout(() => {
            if (!mainMapIsDragging) {
              mainMap.invalidateSize({ pan: false });
            }
          }, 200);
        }).observe(mapContainer);
      }
    }

    let locationMarker = null;
    let locationCircle = null;

    function showMyLocation() {
      mainMap.locate({
        setView: true,
        maxZoom: 15,
        watch: false
      });
    }

    mainMap.on('locationfound', (e) => {
      if (locationMarker) mainMap.removeLayer(locationMarker);
      if (locationCircle) mainMap.removeLayer(locationCircle);
      locationMarker = L.marker(e.latlng).addTo(mainMap).bindPopup('You are here').openPopup();
      locationCircle = L.circle(e.latlng, {
        radius: e.accuracy || 50,
        color: '#1e9e62',
        fillColor: '#1e9e62',
        fillOpacity: 0.15
      }).addTo(mainMap);
    });

    mainMap.on('locationerror', () => {
      alert('Unable to determine your location.');
    });

    const zoomControlContainer = mainMap.zoomControl.getContainer();
    if (zoomControlContainer) {
      const locateBtn = L.DomUtil.create('a', 'leaflet-control-locate-button', zoomControlContainer);
      locateBtn.href = '#';
      locateBtn.title = 'Show my location';
      locateBtn.innerHTML = '<i class="bi bi-geo-alt-fill"></i>';
      L.DomEvent.on(locateBtn, 'click', L.DomEvent.stopPropagation)
        .on(locateBtn, 'click', L.DomEvent.preventDefault)
        .on(locateBtn, 'click', showMyLocation);
    }

    window.setBase = (t, btn) => {
      document.querySelectorAll('.layer-btn, .layer-option').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      document.getElementById('layerMenu')?.classList.remove('show');
      document.getElementById('layerToggle')?.setAttribute('aria-expanded', 'false');
      mainMap.removeLayer(curBase);
      curBase = baseLayers[t] || satL;
      mainMap.addLayer(curBase);
      if (t === 'mangrove') {
        const targetZoom = zoomForViewHeightFt(MANGROVE_VIEW_HEIGHT_FT, mainMap.getCenter().lat);
        mainMap.flyTo(mainMap.getCenter(), targetZoom, { duration: 0.7 });
      }
    };

    window.toggleLayerMenu = () => {
      const menu = document.getElementById('layerMenu');
      const toggle = document.getElementById('layerToggle');
      if (!menu || !toggle) return;
      const isOpen = menu.classList.toggle('show');
      if (isOpen) {
        document.getElementById('droneImageryMenu')?.classList.remove('show');
        document.getElementById('droneImageryToggle')?.setAttribute('aria-expanded', 'false');
      }
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    window.toggleDroneImageryMenu = () => {
      const menu = document.getElementById('droneImageryMenu');
      const toggle = document.getElementById('droneImageryToggle');
      if (!menu || !toggle) return;
      const isOpen = menu.classList.toggle('show');
      if (isOpen) {
        document.getElementById('layerMenu')?.classList.remove('show');
        document.getElementById('layerToggle')?.setAttribute('aria-expanded', 'false');
      }
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    document.addEventListener('click', (event) => {
      [
        ['layerMenu', 'layerToggle'],
        ['droneImageryMenu', 'droneImageryToggle'],
      ].forEach(([menuId, toggleId]) => {
        const menu = document.getElementById(menuId);
        const toggle = document.getElementById(toggleId);
        if (menu && toggle && !menu.contains(event.target) && !toggle.contains(event.target)) {
          menu.classList.remove('show');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    });
