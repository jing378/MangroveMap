    {{-- Shared Leaflet base map: tile layers, drone imagery, resize handling, locate control and layer menus.
         Inlined into a page <script> that has already defined `csrfToken`; defines `mainMap`, `curBase` and the base layers. --}}
    const droneImageryIndexUrl = @json(route('drone-imagery.index'));
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

    // On phones with a fractional devicePixelRatio, Leaflet tiles render with hairline seams between them.
    // Grow each tile by 1px so neighbours overlap. Patched once on the prototype so every map on the page gets it.
    // leaflet.css sets `mix-blend-mode: plus-lighter` on tiles, which would add the overlapping rows together
    // and draw brighter lines, so tiles are switched back to normal blending.
    if (!L.GridLayer.prototype._seamFixApplied) {
      const tileSeamStyle = document.createElement('style');
      tileSeamStyle.textContent = '.leaflet-container img.leaflet-tile { mix-blend-mode: normal !important; }';
      document.head.appendChild(tileSeamStyle);

      const originalInitTile = L.GridLayer.prototype._initTile;
      L.GridLayer.include({
        _seamFixApplied: true,
        _initTile(tile) {
          originalInitTile.call(this, tile);
          const tileSize = this.getTileSize();
          tile.style.width = `${tileSize.x + 1}px`;
          tile.style.height = `${tileSize.y + 1}px`;
        },
      });
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
    const droneImageryTileLayers = new Map();

    function showDroneImageryMessage(message, isError = false) {
      if (!droneImageryMessageElement) return;
      droneImageryMessageElement.textContent = message;
      droneImageryMessageElement.style.color = isError ? '#b42318' : '#4c6b5d';
    }

    function addDroneImageryTileLayer(layer) {
      if (!layer.tile_url || !layer.bounds) return null;
      // Re-confirming edits rebuilds the tiles (and may crop or move the extent), so key on the build time.
      const version = layer.processed_at || '';
      const opacity = Number(layer.display_opacity) || 0.78;
      const existing = droneImageryTileLayers.get(layer.id);
      if (existing && existing.options.droneVersion === version) {
        existing.setOpacity(opacity);
        return existing;
      }
      const wasShown = existing ? mainMap.hasLayer(existing) : false;
      if (existing && wasShown) mainMap.removeLayer(existing);

      const bounds = L.latLngBounds(
        [layer.bounds.south, layer.bounds.west],
        [layer.bounds.north, layer.bounds.east]
      );
      // Drone tiles come through Laravel (auth-checked), so avoid the request burst of loading
      // every intermediate zoom level during the zoom animation; load once the zoom settles.
      const tileLayer = L.tileLayer(`${layer.tile_url}${layer.tile_url.includes('?') ? '&' : '?'}v=${encodeURIComponent(version)}`, {
        droneVersion: version,
        opacity,
        bounds,
        minNativeZoom: layer.zoom_min,
        maxNativeZoom: layer.zoom_max,
        maxZoom: MAP_MAX_ZOOM,
        updateWhenZooming: false,
        updateWhenIdle: true,
        keepBuffer: 2,
        zIndex: 5,
      });
      droneImageryTileLayers.set(layer.id, tileLayer);
      if (wasShown) tileLayer.addTo(mainMap);
      return tileLayer;
    }

    // AI mangrove extent (SegmentDroneImagery output) per drone layer.
    const droneSegmentationOverlays = new Map(); // layer id -> { key, group }
    const droneSegmentationCache = new Map(); // `${id}:${segmented_at}` -> payload
    let droneSegmentationPollTimer = null;

    // layer.auto_show: published imagery (an expert's, or a resident's whose delineation an expert approved;
    // the delineation itself is drawn with the other delineations), and for experts, resident imagery under
    // review. Turned on once per page load, so unticking it is not undone by the status poll.
    const droneAutoShownTiles = new Set();

    function isDroneSegmenting(layer) {
      return ['queued', 'processing'].includes(layer.segmentation_status);
    }

    // Upload → prepare preview (ProcessDroneImagery) → AI detection (SegmentDroneImagery) → owner review
    // in the editor → apply edits & tile (FinalizeDroneImagery; AI re-runs on the edited raster) → ready.
    const DRONE_PIPELINE_STEPS = ['Upload', 'Prepare', 'AI detection', 'Review', 'Tile', 'Ready'];
    const droneProgressChip = document.getElementById('droneImageryProgressChip');
    let droneOwnedLayers = [];
    let pendingDroneActivationId = null; // freshly uploaded or confirmed layer to show once it is ready

    // Server-side work in progress (polled). awaiting_review is not here: it waits for the owner.
    function dronePipelineStage(layer) {
      if (layer.status === 'queued') return { step: 1, label: 'Waiting to prepare the preview…' };
      if (layer.status === 'processing') return { step: 1, label: 'Stitching imagery and preparing the preview…' };
      if (layer.status === 'awaiting_review' && layer.segmentation_status === 'queued') return { step: 2, label: 'Waiting for AI mangrove detection…' };
      if (layer.status === 'awaiting_review' && layer.segmentation_status === 'processing') return { step: 2, label: 'Detecting mangroves with AI for your review…' };
      if (layer.status === 'finalizing') return { step: 4, label: 'Applying your edits & tiling…' };
      if (layer.status === 'ready' && layer.segmentation_status === 'queued') return { step: 5, label: 'Waiting for AI mangrove detection…' };
      if (layer.status === 'ready' && layer.segmentation_status === 'processing') return { step: 5, label: 'Detecting mangroves with AI…' };
      return null;
    }

    function buildDroneReviewLink(layer) {
      if (!layer.review_url) return null;
      const link = document.createElement('a');
      link.href = layer.review_url;
      link.className = `drone-imagery-ai-button${layer.status === 'ready' ? '' : ' primary'}`;
      link.textContent = layer.status === 'awaiting_review' ? 'Review & add to map'
        : (layer.status === 'failed' ? 'Edit & retry' : 'Edit imagery');
      link.title = 'Remove non-mangrove areas, adjust opacity and position';
      return link;
    }

    function buildDronePipelineTracker(stage) {
      const tracker = document.createElement('span');
      tracker.className = 'drone-pipeline';
      const steps = document.createElement('span');
      steps.className = 'drone-pipeline-steps';
      DRONE_PIPELINE_STEPS.forEach((title, index) => {
        const step = document.createElement('span');
        step.className = `drone-pipeline-step${index < stage.step ? ' done' : ''}${index === stage.step ? ' active' : ''}`;
        step.textContent = title;
        steps.appendChild(step);
      });
      const track = document.createElement('span');
      track.className = 'drone-progress-chip-track is-indeterminate';
      track.setAttribute('role', 'progressbar');
      track.setAttribute('aria-label', stage.label);
      const fill = document.createElement('span');
      fill.className = 'drone-progress-chip-fill';
      track.appendChild(fill);
      const label = document.createElement('span');
      label.className = 'drone-imagery-layer-state';
      label.textContent = stage.label;
      tracker.append(steps, track, label);
      return tracker;
    }

    function updateDroneProgressChip() {
      if (!droneProgressChip) return;
      const label = droneProgressChip.querySelector('.drone-progress-chip-label');
      const track = droneProgressChip.querySelector('.drone-progress-chip-track');
      const fill = droneProgressChip.querySelector('.drone-progress-chip-fill');

      const pending = droneOwnedLayers
        .map(layer => ({ layer, stage: dronePipelineStage(layer) }))
        .filter(item => item.stage);
      if (!pending.length) {
        droneProgressChip.hidden = true;
        return;
      }

      const [first] = pending;
      label.textContent = `${first.layer.name}: ${first.stage.label}${pending.length > 1 ? ` (+${pending.length - 1} more)` : ''}`;
      track.classList.add('is-indeterminate');
      track.removeAttribute('aria-valuenow');
      fill.style.width = '';
      droneProgressChip.hidden = false;
    }

    if (droneProgressChip) {
      const openDronePanel = event => {
        event.stopPropagation();
        if (!document.getElementById('droneImageryMenu')?.classList.contains('show')) {
          window.toggleDroneImageryMenu?.();
        }
      };
      droneProgressChip.addEventListener('click', openDronePanel);
      droneProgressChip.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openDronePanel(event);
        }
      });
    }

    function droneSegmentationSummary(layer) {
      switch (layer.segmentation_status) {
        case 'failed': return `AI segmentation failed: ${layer.segmentation_error || 'unknown error'}`;
        case 'ready': {
          const stats = layer.segmentation_stats || {};
          const area = Number(stats.mangrove_area_ha || 0).toFixed(2);
          const coverage = Number(stats.mangrove_coverage_percent || 0).toFixed(1);
          return stats.edited
            ? `Mangrove areas (corrected by the uploader): ${area} ha`
            : `AI extent: ${area} ha mangrove (${coverage}% of surveyed area)`;
        }
        default: return 'AI segmentation not run yet';
      }
    }

    async function fetchDroneSegmentation(layer) {
      const key = `${layer.id}:${layer.segmented_at}`;
      if (droneSegmentationCache.has(key)) return droneSegmentationCache.get(key);
      const response = await fetch(layer.segmentation_url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Unable to load the AI mangrove extent.');
      droneSegmentationCache.set(key, payload);
      return payload;
    }

    function removeDroneSegmentationOverlay(layerId) {
      const overlay = droneSegmentationOverlays.get(layerId);
      if (overlay && mainMap.hasLayer(overlay.group)) mainMap.removeLayer(overlay.group);
      droneSegmentationOverlays.delete(layerId);
    }

    async function showDroneSegmentationOverlay(layer) {
      const key = `${layer.id}:${layer.segmented_at}`;
      let overlay = droneSegmentationOverlays.get(layer.id);
      if (!overlay || overlay.key !== key) {
        removeDroneSegmentationOverlay(layer.id);
        const payload = await fetchDroneSegmentation(layer);
        const group = L.featureGroup((payload.features || []).map(feature => L.polygon(feature.coords, {
          color: '#c6ff00',
          weight: 2,
          dashArray: '6 4',
          fillColor: '#1e9e62',
          fillOpacity: 0.2,
          interactive: false,
        })));
        overlay = { key, group };
        droneSegmentationOverlays.set(layer.id, overlay);
      }
      overlay.group.addTo(mainMap);
      return overlay.group;
    }

    async function requestDroneSegmentation(layer) {
      const response = await fetch(`${droneImageryStatusUrl}/${encodeURIComponent(layer.id)}/segmentation`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        credentials: 'same-origin',
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Unable to start AI segmentation.');
      return payload;
    }

    function buildDroneSegmentationControls(layer) {
      const box = document.createElement('span');
      box.className = 'drone-imagery-ai';
      const stage = dronePipelineStage(layer);
      if (stage) {
        box.appendChild(buildDronePipelineTracker(stage));
      } else {
        const summary = document.createElement('span');
        summary.className = 'drone-imagery-layer-state';
        summary.textContent = droneSegmentationSummary(layer);
        box.appendChild(summary);
      }

      const actions = document.createElement('span');
      actions.className = 'drone-imagery-ai-actions';

      if (layer.segmentation_status === 'ready') {
        const toggleLabel = document.createElement('label');
        toggleLabel.className = 'drone-imagery-ai-toggle';
        const toggle = document.createElement('input');
        toggle.type = 'checkbox';
        const overlay = droneSegmentationOverlays.get(layer.id);
        toggle.checked = Boolean(overlay && overlay.key === `${layer.id}:${layer.segmented_at}` && mainMap.hasLayer(overlay.group));
        toggle.addEventListener('change', async () => {
          if (!toggle.checked) {
            const current = droneSegmentationOverlays.get(layer.id);
            if (current) mainMap.removeLayer(current.group);
            return;
          }
          try {
            const group = await showDroneSegmentationOverlay(layer);
            if (group.getLayers().length) mainMap.flyToBounds(group.getBounds(), { padding: [40, 40], duration: 0.8 });
          } catch (error) {
            toggle.checked = false;
            showDroneImageryMessage(error.message, true);
          }
        });
        toggleLabel.append(toggle, document.createTextNode(' Show AI extent'));
        actions.appendChild(toggleLabel);
        if (typeof window.useAiExtentAsDelineation === 'function') {
          const useButton = document.createElement('button');
          useButton.type = 'button';
          useButton.className = 'drone-imagery-ai-button primary';
          useButton.textContent = 'Use as delineation';
          useButton.title = 'Open the AI-detected areas in the Delineate tab to edit and submit';
          useButton.addEventListener('click', async () => {
            // Drawing tools live on the Delineate page; go there and let it load the draft.
            const delineateUrl = window.aiExtentDelineateUrl?.(layer.id);
            if (delineateUrl) {
              window.location.href = delineateUrl;
              return;
            }
            useButton.disabled = true;
            try {
              const payload = await fetchDroneSegmentation(layer);
              if (!payload.features?.length) {
                showDroneImageryMessage('The AI did not detect any mangrove areas large enough to delineate.', true);
                return;
              }
              const loaded = window.useAiExtentAsDelineation(payload, layer);
              if (loaded) {
                const tileLayer = droneImageryTileLayers.get(layer.id);
                if (tileLayer && !mainMap.hasLayer(tileLayer)) tileLayer.addTo(mainMap);
                const skipped = payload.features.length - loaded;
                showDroneImageryMessage(
                  `Loaded ${loaded} AI area${loaded === 1 ? '' : 's'} as a draft${skipped > 0 ? ` (${skipped} smaller skipped)` : ''}. Edit the shapes, select the genus present and save.`
                );
                document.getElementById('droneImageryMenu')?.classList.remove('show');
              }
            } catch (error) {
              showDroneImageryMessage(error.message, true);
            } finally {
              useButton.disabled = false;
            }
          });
          actions.appendChild(useButton);
        }
      }

      if (layer.is_owner && !isDroneSegmenting(layer)) {
        const runButton = document.createElement('button');
        runButton.type = 'button';
        runButton.className = 'drone-imagery-ai-button';
        runButton.textContent = layer.segmentation_status === 'ready' ? 'Re-run AI'
          : (layer.segmentation_status === 'failed' ? 'Retry AI' : 'Run AI segmentation');
        runButton.addEventListener('click', async () => {
          const rerun = layer.segmentation_status === 'ready';
          const outcome = await window.AppActions.run({
            confirm: {
              title: rerun ? 'Run AI segmentation again?' : 'Run AI segmentation?',
              message: rerun ? 'The current result is replaced when it finishes.' : `The AI detects the mangrove areas on "${layer.name}" in the background.`,
              confirmLabel: 'Run',
            },
            button: runButton,
            busyLabel: 'Starting AI...',
            errorMessage: false,
            task: () => requestDroneSegmentation(layer),
          });
          if (outcome.status === 'done') {
            removeDroneSegmentationOverlay(layer.id);
            showDroneImageryMessage(`AI segmentation queued for "${layer.name}".`);
            await loadDroneImageryLayers();
          } else if (outcome.status === 'failed') {
            showDroneImageryMessage(outcome.error.message, true);
          }
        });
        actions.appendChild(runButton);
      }

      if (actions.childElementCount) box.appendChild(actions);
      return box;
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
        const row = document.createElement('div');
        row.className = 'drone-imagery-layer';
        const checkbox = document.createElement('input');
        checkbox.setAttribute('aria-label', `Show ${layer.name}`);
        checkbox.type = 'checkbox';
        checkbox.disabled = layer.status !== 'ready';

        const info = document.createElement('span');
        info.className = 'drone-imagery-layer-info';
        const name = document.createElement('span');
        name.className = 'drone-imagery-layer-name';
        name.textContent = layer.name;
        const state = document.createElement('span');
        state.className = 'drone-imagery-layer-state';
        const kind = layer.source_type === 'batch' ? 'Mosaic' : 'GeoTIFF';
        if (layer.status === 'ready') {
          state.textContent = `${kind} · zoom ${layer.zoom_min}–${layer.zoom_max}${layer.has_pending_edits ? ' · saved edits not applied yet' : ''}`;
        } else if (layer.status === 'failed') {
          state.textContent = `Processing failed: ${layer.error_message || 'unknown error'}`;
        } else if (layer.status === 'awaiting_review') {
          state.textContent = isDroneSegmenting(layer)
            ? `${kind} · the AI is detecting mangroves so you can review the result.`
            : `${kind} · ready for review. Remove non-mangrove areas, then add it to the map.`;
        } else {
          state.textContent = `${kind} · processing on the server`;
        }
        const uploader = document.createElement('span');
        uploader.className = 'drone-imagery-layer-state';
        uploader.textContent = `Uploaded by ${layer.uploaded_by || 'Unknown user'}`;
        info.append(name, state, uploader);
        if (layer.status === 'ready') {
          info.appendChild(buildDroneSegmentationControls(layer));
        } else {
          const stage = dronePipelineStage(layer);
          if (stage) info.appendChild(buildDronePipelineTracker(stage));
        }
        const reviewLink = buildDroneReviewLink(layer);
        if (reviewLink) {
          const reviewActions = document.createElement('span');
          reviewActions.className = 'drone-imagery-ai-actions';
          reviewActions.appendChild(reviewLink);
          info.appendChild(reviewActions);
        }
        if (layer.segmentation_status !== 'ready') {
          removeDroneSegmentationOverlay(layer.id);
        }
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'drone-imagery-layer-remove';
        removeButton.textContent = layer.is_owner ? 'Delete' : 'Remove';
        removeButton.setAttribute(
          'aria-label',
          layer.is_owner ? `Delete ${layer.name}` : `Remove ${layer.name} from your map`
        );
        removeButton.disabled = layer.is_owner && (['queued', 'processing', 'finalizing'].includes(layer.status) || isDroneSegmenting(layer));
        removeButton.title = removeButton.disabled
          ? 'Wait until processing finishes before deleting this layer.'
          : (layer.is_owner ? 'Delete this TIFF layer and its files' : 'Hide this shared layer from your map');
        removeButton.addEventListener('click', async () => {
          // Confirmation → Processing → Success or Error (partials.action-feedback); messages use the panel's line.
          const outcome = await window.AppActions.run({
            confirm: layer.is_owner
              ? { title: 'Delete this record?', message: `"${layer.name}" and its TIFF files are permanently deleted. This cannot be undone.`, confirmLabel: 'Delete', tone: 'danger' }
              : { title: 'Remove this item?', message: `"${layer.name}" is hidden from your map. The owner keeps it.`, confirmLabel: 'Remove', tone: 'danger' },
            button: removeButton,
            busyLabel: layer.is_owner ? 'Deleting...' : 'Removing...',
            errorMessage: false,
            task: async () => {
              const response = await fetch(`${droneImageryStatusUrl}/${encodeURIComponent(layer.id)}`, {
                method: 'DELETE',
                headers: {
                  Accept: 'application/json',
                  'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
              });
              const payload = await response.json().catch(() => ({}));
              if (!response.ok) {
                throw new Error(payload.message || 'Unable to remove this TIFF layer.');
              }

              const activeTileLayer = droneImageryTileLayers.get(layer.id);
              if (activeTileLayer && mainMap.hasLayer(activeTileLayer)) mainMap.removeLayer(activeTileLayer);
              droneImageryTileLayers.delete(layer.id);
              removeDroneSegmentationOverlay(layer.id);
              await loadDroneImageryLayers();
            },
          });
          if (outcome.status === 'done') {
            showDroneImageryMessage(layer.is_owner ? 'TIFF layer deleted.' : 'Shared TIFF layer removed from your map.');
          } else if (outcome.status === 'failed') {
            showDroneImageryMessage(outcome.error.message || 'Unable to remove this TIFF layer.', true);
          }
        });
        row.append(checkbox, info, removeButton);
        droneImageryLayersElement.appendChild(row);

        if (layer.status !== 'ready') return;

        const tileLayer = addDroneImageryTileLayer(layer);
        if (!tileLayer) return;

        if (layer.auto_show && !droneAutoShownTiles.has(layer.id)) {
          droneAutoShownTiles.add(layer.id);
          if (!mainMap.hasLayer(tileLayer)) tileLayer.addTo(mainMap);
        }

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

      // Show a freshly uploaded layer on the map as soon as its tiles are ready.
      let activateId = activateLayerId;
      if (pendingDroneActivationId !== null) {
        const target = payload.layers.find(layer => Number(layer.id) === Number(pendingDroneActivationId));
        if (!target) {
          pendingDroneActivationId = null;
        } else if (target.status === 'ready') {
          pendingDroneActivationId = null;
          activateId = target.id;
          showDroneImageryMessage(`"${target.name}" is ready and displayed on the map.${isDroneSegmenting(target) ? ' AI mangrove segmentation is running in the background.' : ''}`);
        } else if (target.status === 'awaiting_review' && !isDroneSegmenting(target)) {
          pendingDroneActivationId = null;
          showDroneImageryMessage(`"${target.name}" is ready for review. Use "Review & add to map" to remove non-mangrove areas before it is placed on the map.`);
        } else if (target.status === 'failed') {
          pendingDroneActivationId = null;
          showDroneImageryMessage(target.error_message || 'Raster processing failed.', true);
        }
      }

      renderDroneImageryLayers(payload.layers, activateId);
      droneOwnedLayers = payload.layers.filter(layer => layer.is_owner);
      updateDroneProgressChip();
      scheduleDroneImageryPoll(payload.layers);
      return payload.layers;
    }

    // Refresh the layer list while any imagery processing or AI segmentation is still queued or running.
    function scheduleDroneImageryPoll(layers) {
      clearTimeout(droneSegmentationPollTimer);
      droneSegmentationPollTimer = null;
      if (!layers.some(dronePipelineStage)) return;
      const tiling = layers.some(layer => ['queued', 'processing', 'finalizing'].includes(layer.status));
      droneSegmentationPollTimer = setTimeout(() => {
        loadDroneImageryLayers().catch(error => console.error('Unable to refresh drone imagery status.', error));
      }, tiling ? 4000 : 8000);
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
      }
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    // The drone panel has no map button (the sidebar's Drone Upload tab replaced it); it is opened by
    // the progress chip and by "View on map" (?drone=upload) from the Drone Upload page.
    window.toggleDroneImageryMenu = () => {
      const menu = document.getElementById('droneImageryMenu');
      if (!menu) return;
      const isOpen = menu.classList.toggle('show');
      if (isOpen) {
        document.getElementById('layerMenu')?.classList.remove('show');
        document.getElementById('layerToggle')?.setAttribute('aria-expanded', 'false');
      }
    };

    // "View on map" on the Drone Upload page links here with ?drone=upload to open the drone imagery panel
    // straight away (&drone_layer=<id> also shows that layer once it is ready). The params are dropped
    // afterwards so a reload doesn't reopen the panel.
    {
      const pageUrl = new URL(window.location.href);
      if (pageUrl.searchParams.get('drone') === 'upload') {
        const layerId = Number(pageUrl.searchParams.get('drone_layer'));
        if (layerId > 0) pendingDroneActivationId = layerId;
        pageUrl.searchParams.delete('drone');
        pageUrl.searchParams.delete('drone_layer');
        window.history.replaceState(window.history.state, '', pageUrl);
        if (!document.getElementById('droneImageryMenu')?.classList.contains('show')) {
          window.toggleDroneImageryMenu();
        }
      }
    }

    document.addEventListener('click', (event) => {
      const layerMenu = document.getElementById('layerMenu');
      const layerToggle = document.getElementById('layerToggle');
      if (layerMenu && layerToggle && !layerMenu.contains(event.target) && !layerToggle.contains(event.target)) {
        layerMenu.classList.remove('show');
        layerToggle.setAttribute('aria-expanded', 'false');
      }
      // The progress chip stops propagation when it opens the panel, so it never reaches this.
      const droneMenu = document.getElementById('droneImageryMenu');
      if (droneMenu && !droneMenu.contains(event.target)) {
        droneMenu.classList.remove('show');
      }
    });
