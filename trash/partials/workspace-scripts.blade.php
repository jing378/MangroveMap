<script>
    const APP_PAGE = @json($page ?? 'map');

    // DATA & MAP INITIALIZATION (same as before)
    const saveDelineationUrl = "{{ Auth::user()->isExpert() ? route('expert.delineations.store') : route('delineations.store') }}";
    const deleteDelineationBaseUrl = "{{ url('/delineations') }}";
    const isExpertUser = {{ Auth::check() && Auth::user()->isExpert() ? 'true' : 'false' }};
    const savedDelineations = @json($delineations);
    const approvedDelineations = @json($approvedDelineationsForMap);
    const residentDelineations = @json($residentDelineationsForMap ?? []);
    const focusDelineationId = @json($focusDelineationId ?? null);
    const focusDelineationRecord = @json($focusDelineationRecord ?? null);
    const authUserId = @json(Auth::id());
    const expertDelineationReviewBaseUrl = @json(url('/expert/delineations'));

    function showProcessingModal(title, subtitle) {
      const modal = document.getElementById('processingModal');
      if (!modal) return;
      const titleEl = modal.querySelector('.processing-modal-title');
      const subEl = modal.querySelector('.processing-modal-subtitle');
      if (titleEl) titleEl.textContent = title || 'Processing...';
      if (subEl) subEl.textContent = subtitle || 'Saving your delineation, please wait.';
      modal.classList.add('active');
    }

    function hideProcessingModal() {
      const modal = document.getElementById('processingModal');
      if (!modal) return;
      modal.classList.remove('active');
    }

    function showSuccessModal(message, title = 'Success!') {
      const modal = document.getElementById('successModal');
      if (!modal) return;
      const titleEl = document.getElementById('successModalTitle');
      const msgEl = document.getElementById('successModalMessage');
      if (titleEl) titleEl.textContent = title;
      if (msgEl) msgEl.textContent = message || 'Delineation saved and submitted for expert review.';
      modal.classList.add('active');
    }

    function hideSuccessModal() {
      const modal = document.getElementById('successModal');
      if (!modal) return;
      modal.classList.remove('active');
    }
    function mergeDelineationsById(...lists) {
      const byId = new Map();
      lists.flat().forEach(record => {
        if (record?.id != null) {
          byId.set(record.id, record);
        }
      });
      return [...byId.values()];
    }

    const allDelineations = isExpertUser
      ? mergeDelineationsById(savedDelineations, residentDelineations)
      : mergeDelineationsById(savedDelineations, approvedDelineations);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';


    function extractPoints(coords) {
      if (!coords) return [];
      if (typeof coords[0] === 'number') return [coords];
      if (Array.isArray(coords[0]) && typeof coords[0][0] === 'number') return coords;
      if (Array.isArray(coords[0]) && Array.isArray(coords[0][0])) return coords.flat();
      return [];
    }

    const MAX_FEATURES_PER_DELINEATION = 20;
    const loadedGeometryKeys = new Set();

    function featureGeometryKey(feature) {
      if (!feature?.type || !feature?.coords) return '';
      return `${feature.type}:${JSON.stringify(feature.coords)}`;
    }

    function isValidMapFeature(feature) {
      if (!feature || typeof feature !== 'object') return false;
      if (!['point', 'line', 'area'].includes(feature.type)) return false;
      const coords = feature.coords;
      if (!Array.isArray(coords) || coords.length === 0) return false;
      if (feature.type === 'point') {
        return coords.length >= 2 && typeof coords[0] === 'number';
      }
      return Array.isArray(coords[0]) && typeof coords[0][0] === 'number';
    }

    function featureForPersistence(feature) {
      return {
        type: feature.type,
        coords: feature.coords,
      };
    }

    function getFeatureCenter(feature) {
      const points = extractPoints(feature?.coords);
      if (!points.length) return [10.358, 124.973];
      const total = points.reduce((acc, pt) => {
        acc[0] += Number(pt[0]) || 0;
        acc[1] += Number(pt[1]) || 0;
        return acc;
      }, [0, 0]);
      return [total[0] / points.length, total[1] / points.length];
    }

    function formatFeatureArea(feature) {
      const points = extractPoints(feature?.coords);
      if (!points.length) return 'N/A';
      if (feature?.type === 'point') return '1 pt';
      return `${points.length} pts`;
    }

    function formatDelineationScan(record) {
      if (!record?.created_at) return 'Saved';
      const d = new Date(record.created_at);
      return isNaN(d.getTime()) ? 'Saved' : d.toLocaleDateString();
    }

    function getDelineationStatus(record) {
      if (record.is_rejected) return {
        label: 'Rejected',
        chip: 'cr',
        color: '#d04030',
        sc: 'r'
      };
      if (record.is_approved) return {
        label: 'Approved',
        chip: 'cg',
        color: '#1e9e62',
        sc: 'g'
      };
      return {
        label: 'Pending',
        chip: 'cp',
        color: '#c07818',
        sc: 'p'
      };
    }

    // Residents: sidebar lists approved community zones. Experts: all resident submissions (incl. pending).
    const zoneRecords = Array.isArray(allDelineations)
      ? allDelineations.filter(r => isExpertUser ? !r?.is_rejected : (!!r?.is_approved && !r?.is_rejected))
      : [];

    const zones = zoneRecords.map(record => {
      const feature = Array.isArray(record.features) ? record.features[0] : null;
      const [lat, lng] = getFeatureCenter(feature);
      const review = getDelineationStatus(record);
      return {
        id: record.id,
        name: record.name || `Delineation ${record.id}`,
        lat,
        lng,
        area: formatFeatureArea(feature),
        ndvi: review.label,
        status: review.label,
        genus: (isExpertUser && record.user?.name)
          ? record.user.name
          : (feature?.label || 'User delineation'),
        scan: formatDelineationScan(record),
        sc: review.sc,
        color: review.color,
        chip: review.chip
      };
    });

    const plantSites = [];

    const zoneContainer = document.getElementById('zoneListContainer');
    // Sample multi-year temporal zone
    const tempZoneDiv = document.createElement('div');
    tempZoneDiv.className = `zone-row`;
    tempZoneDiv.id = 'temporalZoneRow';
    tempZoneDiv.setAttribute('onclick', `selectTemporalZone()`);
    tempZoneDiv.innerHTML = `<div class="z-pip" style="background:#1e9e62"></div><div class="z-name">Silago Reforestation</div><div class="z-ha" id="temporalZoneHa">19.4ha</div><span class="z-chip cg">Temporal 🕒</span>`;
    zoneContainer.appendChild(tempZoneDiv);

    zones.forEach((z, i) => {
      const div = document.createElement('div');
      div.className = `zone-row`;
      div.setAttribute('onclick', `flyTo(${i})`);
      div.innerHTML = `<div class="z-pip" style="background:${z.color}"></div><div class="z-name">${z.name}</div><div class="z-ha">${z.area.replace(/[^0-9k]/g, '')}</div><span class="z-chip ${z.chip || 'ca'}">${z.status}</span>`;
      zoneContainer.appendChild(div);
    });

    const tileLayerOpts = {
      maxZoom: 18,
      updateWhenZooming: false,
      updateWhenIdle: true,
      keepBuffer: 2,
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

    const satL = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', tileLayerOpts);
    const osmL = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', tileLayerOpts);
    const topoL = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      ...tileLayerOpts,
      maxZoom: 17,
    });
    const mangroveL = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      ...tileLayerOpts,
      maxZoom: 21,
      maxNativeZoom: 19,
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
      layers: [satL],
    }).setView([10.358, 124.973], 13);

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

    const snapPixelThreshold = 15; // pixels
    let snapMarker = L.circleMarker([0, 0], {
      radius: 8,
      color: '#1e9e62',
      weight: 2,
      fillColor: '#1e9e62',
      fillOpacity: 0.35,
      opacity: 0
    }).addTo(mainMap);

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

    // Keep overlay layers in dedicated groups so redraws are cheap.
    // This avoids expensive mainMap.eachLayer() scans/removals which can get laggy fast.
    const zoneMarkerLayer = L.layerGroup().addTo(mainMap);
    const delineationLayer = L.layerGroup().addTo(mainMap);
    const vertexLayer = L.layerGroup().addTo(mainMap);

    let polys = [];
    zones.forEach((z, i) => {
      const p = L.circleMarker([z.lat, z.lng], {
        radius: 8,
        fillColor: z.color,
        color: "#fff",
        weight: 2,
        opacity: 1,
        fillOpacity: 0.8
      }).addTo(zoneMarkerLayer);

      p.bindPopup(`<div><b>${z.name}</b><br>Area: ${z.area}<br>NDVI: ${z.ndvi}<br>Status: ${z.status}</div>`);
      p.on('click', (e) => {
        if (drawingMode) return;
        if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
        selectZone(i);
      });
      polys.push(p);
    });

    let plantMap = null;
    let pMarkers = [];

    function ensurePlantMap() {
      if (plantMap) return plantMap;
      plantMap = L.map('plantMap', {
        zoomControl: true,
        preferCanvas: true,
        layers: [L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', tileLayerOpts)],
      }).setView([10.358, 124.973], 13);
      zones.forEach(z => {
        L.circleMarker([z.lat, z.lng], {
          radius: 4,
          color: '#1e9e62',
          fillOpacity: 0.3,
          interactive: false,
        }).addTo(plantMap);
      });
      plantSites.forEach((s, i) => {
        const ic = L.divIcon({
          html: `<div style="width:30px;height:30px;border-radius:50%;background:${s.color};border:2px solid white;display:flex;align-items:center;justify-content:center;font-weight:700;color:white;">${i + 1}</div>`,
          iconSize: [30, 30],
          iconAnchor: [15, 15],
        });
        const m = L.marker([s.lat, s.lng], { icon: ic }).addTo(plantMap);
        m.bindPopup(`<b>${s.name}</b><br>Suitability: ${s.score}%<br>Priority: ${s.priority}`);
        pMarkers.push(m);
      });
      return plantMap;
    }

    window.flyTo = (i) => selectZone(i);
    window.showSuitability = () => {
      show('planting');
    };
    window.flyPlant = (i) => {
      const map = ensurePlantMap();
      map.flyTo([plantSites[i].lat, plantSites[i].lng], 11);
      pMarkers[i]?.openPopup();
    };
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

    function collectVertexCoords() {
      const coords = [];
      const add = (item) => {
        if (!item) return;
        if (Array.isArray(item[0])) {
          item.forEach(add);
        } else {
          coords.push(item);
        }
      };
      drawnFeatures.forEach(f => add(f.coords));
      add(currentFeature?.coords);
      return coords;
    }

    function getSnappedLatLng(latlng) {
      const vertices = collectVertexCoords();
      let best = null;
      let bestDist = snapPixelThreshold;

      const mousePixel = mainMap.latLngToContainerPoint(latlng);

      vertices.forEach(pt => {
        const candidate = L.latLng(pt[0], pt[1]);
        const vertexPixel = mainMap.latLngToContainerPoint(candidate);
        const pixelDist = mousePixel.distanceTo(vertexPixel);

        if (pixelDist < bestDist) {
          bestDist = pixelDist;
          best = candidate;
        }
      });
      return best;
    }

    let drawingMode = null;
    let isDrawing = false;
    let currentFeature = null;
    let drawnFeatures = [];
    let featureHistory = [];
    let historyIndex = -1;
    let currentSelectedZoneIndex = -1;
    let selectedDrawnIndex = -1;

    function loadSavedDelineations() {
      if (!Array.isArray(savedDelineations) || savedDelineations.length === 0) {
        return;
      }

      savedDelineations.forEach(record => {
        if (Array.isArray(record.features)) {
          record.features.forEach(feature => {
            drawnFeatures.push({
              ...feature,
              label: record.name,
              notes: record.notes,
              delineation_id: record.id,
              is_approved: !!record.is_approved,
              is_rejected: !!record.is_rejected,
              is_own: true,
            });
          });
        }
      });

      redrawFeatures();
    }

    function pushDelineationFeatures(record, options = {}) {
      if (!Array.isArray(record.features)) return;
      let features = record.features.filter(isValidMapFeature);
      if (features.length > MAX_FEATURES_PER_DELINEATION) {
        console.warn(
          `Delineation #${record.id} has ${features.length} features; using the last ${MAX_FEATURES_PER_DELINEATION}.`
        );
        features = features.slice(-MAX_FEATURES_PER_DELINEATION);
      }
      features.forEach(feature => {
        const key = `${record.id}:${featureGeometryKey(feature)}`;
        if (key && loadedGeometryKeys.has(key)) return;
        if (key) loadedGeometryKeys.add(key);
        drawnFeatures.push({
          type: feature.type,
          coords: feature.coords,
          label: record.name,
          notes: record.notes,
          delineation_id: record.id,
          is_approved: !!record.is_approved,
          is_rejected: !!record.is_rejected,
          rejection_notes: record.rejection_notes || null,
          is_own: !!options.is_own,
          created_by: options.created_by || null,
        });
      });
    }

    function loadDelineationsOnMap() {
      drawnFeatures = [];
      loadedGeometryKeys.clear();
      if (Array.isArray(savedDelineations)) {
        savedDelineations.forEach(record => pushDelineationFeatures(record, {
          is_own: true
        }));
      }
      if (isExpertUser && Array.isArray(residentDelineations)) {
        residentDelineations.forEach(record => {
          pushDelineationFeatures(record, {
            is_own: false,
            created_by: record.user?.name || 'Resident',
          });
        });
      } else if (Array.isArray(approvedDelineations)) {
        approvedDelineations.forEach(record => {
          pushDelineationFeatures(record, {
            is_own: false,
            created_by: record.user ? record.user.name : 'Community',
          });
        });
      }
      redrawFeatures();
    }

    loadDelineationsOnMap();

    function syncTimelineMobilePosition() {
      const timeline = document.getElementById('mapTimelineControl');
      const panel = document.getElementById('mapRightPanel');
      if (!timeline) return;

      if (window.innerWidth <= 780 && panel && panel.classList.contains('open')) {
        if (panel.classList.contains('expanded')) {
          timeline.classList.remove('above-peek');
          timeline.classList.add('hidden-expanded');
        } else {
          timeline.classList.add('above-peek');
          timeline.classList.remove('hidden-expanded');
        }
      } else {
        timeline.classList.remove('above-peek', 'hidden-expanded');
      }
    }
    window.addEventListener('resize', syncTimelineMobilePosition);

    function openRightPanel(titleText) {
      const panel = document.getElementById('mapRightPanel');
      const backdrop = document.getElementById('panelBackdrop');
      const headerTitle = document.getElementById('panelHeaderTitle');
      const headerSub = document.getElementById('panelHeaderSub');

      if (titleText && headerTitle) {
        headerTitle.textContent = titleText;
      }

      if (window.innerWidth <= 780) {
        // Mobile: Open in PEEK state by default — map is NOT covered automatically
        panel.classList.remove('expanded');
        panel.classList.add('open', 'peek');
        if (backdrop) backdrop.classList.remove('show');
        if (headerSub) {
          headerSub.innerHTML = '<span>Adjust upwards for details</span> <i class="bi bi-chevron-up"></i>';
        }
      } else {
        // Desktop: Open side drawer normally
        panel.classList.remove('peek', 'expanded');
        panel.classList.add('open');
      }

      syncTimelineMobilePosition();

      setTimeout(() => {
        if (typeof mainMap !== 'undefined' && mainMap) {
          mainMap.invalidateSize({ pan: false });
        }
      }, 330);
    }

    function expandRightPanel() {
      const panel = document.getElementById('mapRightPanel');
      const backdrop = document.getElementById('panelBackdrop');
      const headerSub = document.getElementById('panelHeaderSub');

      if (!panel || !panel.classList.contains('open')) return;

      panel.classList.remove('peek');
      panel.classList.add('expanded');
      if (backdrop) backdrop.classList.add('show');
      if (headerSub) {
        headerSub.innerHTML = '<span>Swipe down to collapse</span> <i class="bi bi-chevron-down"></i>';
      }

      syncTimelineMobilePosition();

      setTimeout(() => {
        if (typeof mainMap !== 'undefined' && mainMap) {
          mainMap.invalidateSize({ pan: false });
        }
      }, 330);
    }

    function collapseRightPanel() {
      const panel = document.getElementById('mapRightPanel');
      const backdrop = document.getElementById('panelBackdrop');
      const headerSub = document.getElementById('panelHeaderSub');

      if (!panel || !panel.classList.contains('open')) return;

      panel.classList.remove('expanded');
      panel.classList.add('peek');
      if (backdrop) backdrop.classList.remove('show');
      if (headerSub) {
        headerSub.innerHTML = '<span>Adjust upwards for details</span> <i class="bi bi-chevron-up"></i>';
      }

      syncTimelineMobilePosition();

      setTimeout(() => {
        if (typeof mainMap !== 'undefined' && mainMap) {
          mainMap.invalidateSize({ pan: false });
        }
      }, 330);
    }

    window.closeRightPanel = function (event) {
      if (event && typeof event.stopPropagation === 'function') {
        event.stopPropagation();
      }
      const panel = document.getElementById('mapRightPanel');
      const backdrop = document.getElementById('panelBackdrop');
      if (panel) {
        panel.classList.remove('open', 'peek', 'expanded');
      }
      if (backdrop) backdrop.classList.remove('show');

      syncTimelineMobilePosition();

      const infoCard = document.getElementById('delineationInfoCard');
      if (infoCard) infoCard.style.display = 'none';
      selectedDrawnIndex = -1;

      setTimeout(() => {
        if (typeof mainMap !== 'undefined' && mainMap) {
          mainMap.invalidateSize({ pan: false });
        }
      }, 330);
    };

    window.collapseOrClosePanel = function () {
      const panel = document.getElementById('mapRightPanel');
      if (panel && panel.classList.contains('expanded')) {
        collapseRightPanel();
      } else {
        closeRightPanel();
      }
    };

    function initBottomSheetGestures() {
      const panel = document.getElementById('mapRightPanel');
      const header = document.getElementById('panelHeader');
      if (!panel || !header) return;

      let startY = 0;
      let currentY = 0;
      let isTouching = false;
      let startTime = 0;

      // Handle swipe anywhere on the panel during peek state, or on header during expanded state
      panel.addEventListener('touchstart', (e) => {
        if (e.target.closest('.panel-close-btn') || e.target.closest('input') || e.target.closest('textarea')) return;
        // In expanded mode, touches on scroll content should do normal scrolling unless on header
        if (panel.classList.contains('expanded') && !e.target.closest('.panel-header')) return;
        startY = e.touches[0].clientY;
        currentY = startY;
        isTouching = true;
        startTime = Date.now();
      }, { passive: true });

      panel.addEventListener('touchmove', (e) => {
        if (!isTouching) return;
        currentY = e.touches[0].clientY;
      }, { passive: true });

      panel.addEventListener('touchend', (e) => {
        if (!isTouching) return;
        isTouching = false;
        const deltaY = currentY - startY;
        const elapsed = Date.now() - startTime;

        // Swiped UP (adjust upwards)
        if (deltaY < -20 || (deltaY < -8 && elapsed < 250)) {
          expandRightPanel();
        }
        // Swiped DOWN (adjust downwards)
        else if (deltaY > 20 || (deltaY > 8 && elapsed < 250)) {
          if (panel.classList.contains('expanded')) {
            collapseRightPanel();
          } else {
            closeRightPanel();
          }
        }
      }, { passive: true });

      // Tap on header toggles peek <-> expanded
      header.addEventListener('click', (e) => {
        if (e.target.closest('.panel-close-btn')) return;
        if (window.innerWidth <= 780) {
          if (panel.classList.contains('expanded')) {
            collapseRightPanel();
          } else {
            expandRightPanel();
          }
        }
      });

      // Also allow pulling down on the top of scroll container when expanded
      const scrollEl = panel.querySelector('.scroll');
      if (scrollEl) {
        let scrollStartY = 0;
        scrollEl.addEventListener('touchstart', (e) => {
          scrollStartY = e.touches[0].clientY;
        }, { passive: true });

        scrollEl.addEventListener('touchend', (e) => {
          const deltaY = e.changedTouches[0].clientY - scrollStartY;
          if (scrollEl.scrollTop <= 2 && deltaY > 45 && panel.classList.contains('expanded')) {
            collapseRightPanel();
          }
        }, { passive: true });
      }
    }

    initBottomSheetGestures();

    function selectZone(i) {
      isTemporalZoneSelected = false;
      document.getElementById('temporalZoneRow')?.classList.remove('sel');
      document.getElementById('temporalZoneBreakdown')?.style.setProperty('display', 'none');

      currentSelectedZoneIndex = i;
      let z = zones[i];
      document.querySelectorAll('.zone-row').forEach((r, j) => r.classList.toggle('sel', j === i));

      if (isExpertUser && z?.id) {
        const featureIndex = drawnFeatures.findIndex(f => f.delineation_id === z.id);
        if (featureIndex >= 0) {
          selectDrawnFeature(featureIndex);
          return;
        }
      }
      document.getElementById('dName').textContent = z.name;
      document.getElementById('dArea').textContent = z.area;
      document.getElementById('dNDVI').textContent = z.ndvi;
      document.getElementById('dNDVI').className = `d-val ${z.sc}`;
      document.getElementById('dStatus').textContent = z.status;
      document.getElementById('dStatus').className = `d-val ${z.sc}`;
      document.getElementById('dGenus').textContent = z.genus;
      document.getElementById('dScan').textContent = z.scan;

      const removeBtn = document.getElementById('removeDelineationBtn');
      if (removeBtn) {
        removeBtn.style.display = 'none';
      }

      openRightPanel(z.name ? `Zone: ${z.name}` : 'Selected Zone');
      selectedDrawnIndex = -1;
      document.getElementById('delineationInfoCard').style.display = 'none';
      document.getElementById('zoneDetailsContent').style.display = 'block';
      document.getElementById('expertReviewActions')?.style.setProperty('display', 'none');
      document.getElementById('delineationSubmitterRow')?.style.setProperty('display', 'none');
      mainMap.flyTo([z.lat, z.lng], 10, {
        duration: 1
      });
      polys[i].openPopup();
    }

    function selectDrawnFeature(i) {
      const feature = drawnFeatures[i];
      if (!feature) return;
      selectedDrawnIndex = i;

      openRightPanel(feature.label ? `Area: ${feature.label}` : 'Delineated Area');

      document.getElementById('delineationInfoCard').style.display = 'block';
      document.getElementById('zoneDetailsContent').style.display = 'none';

      const removeBtn = document.getElementById('removeDelineationBtn');
      if (removeBtn) {
        removeBtn.style.display = (feature.is_own && !feature.is_approved) ? 'block' : 'none';
      }

      document.getElementById('delineationFeatureType').textContent = feature.type || '-';
      let coordsText = '-';
      if (Array.isArray(feature.coords)) {
        if (feature.type === 'point') {
          coordsText = feature.coords.map(n => Number(n).toFixed(4)).join(', ');
        } else {
          coordsText = feature.coords.slice(0, 3).map(c => Array.isArray(c) ? c.map(n => Number(n).toFixed(4)).join(', ') : c).join(' | ') + (feature.coords.length > 3 ? ' ...' : '');
        }
      }
      document.getElementById('delineationFeatureCoords').textContent = coordsText;
      document.getElementById('delineationFeatureLabel').textContent = feature.label || '-';
      document.getElementById('delineationLabel').value = feature.label || '';
      document.getElementById('delineationNotes').value = feature.notes || '';

      const statusEl = document.getElementById('delineationReviewStatus');
      const rejectionBox = document.getElementById('delineationRejectionBox');
      const rejectionNotes = document.getElementById('delineationRejectionNotes');
      let statusLabel = 'Pending review';
      let statusClass = 'ca';
      if (feature.is_rejected) {
        statusLabel = 'Rejected';
        statusClass = 'cr';
      } else if (feature.is_approved) {
        statusLabel = feature.is_own ? 'Approved' : 'Approved (community)';
        statusClass = 'cg';
      }
      statusEl.textContent = statusLabel;
      statusEl.className = `d-val ${statusClass}`;
      if (feature.is_rejected && feature.rejection_notes) {
        rejectionBox.style.display = 'block';
        rejectionNotes.textContent = feature.rejection_notes;
      } else {
        rejectionBox.style.display = 'none';
        rejectionNotes.textContent = '';
      }

      const submitterRow = document.getElementById('delineationSubmitterRow');
      const submitterEl = document.getElementById('delineationSubmitter');
      if (submitterRow && submitterEl) {
        const submitter = feature.created_by || feature.submitted_by;
        if (isExpertUser && submitter && !feature.is_own) {
          submitterRow.style.display = '';
          submitterEl.textContent = submitter;
        } else {
          submitterRow.style.display = 'none';
          submitterEl.textContent = '-';
        }
      }

      const expertReview = document.getElementById('expertReviewActions');
      if (expertReview) {
        const canReview = isExpertUser && feature.delineation_id && !feature.is_own
          && !feature.is_approved && !feature.is_rejected;
        expertReview.style.display = canReview ? 'block' : 'none';
        expertReview.dataset.delineationId = canReview ? String(feature.delineation_id) : '';
      }

      // Fly/fit to the clicked delineated feature
      if (typeof mainMap !== 'undefined' && mainMap && Array.isArray(feature.coords) && feature.coords.length > 0) {
        if (feature.type === 'point') {
          mainMap.flyTo([feature.coords[0], feature.coords[1]], Math.max(mainMap.getZoom(), 14), { duration: 0.8 });
        } else {
          // For lines and polygons, fit the map to the bounding box of all coordinates
          try {
            const latLngs = feature.coords.map(c => Array.isArray(c) ? L.latLng(c[0], c[1]) : null).filter(Boolean);
            if (latLngs.length > 0) {
              const bounds = L.latLngBounds(latLngs);
              mainMap.flyToBounds(bounds, { padding: [50, 50], maxZoom: 17, duration: 0.8 });
            }
          } catch (err) {
            console.warn('flyToBounds error:', err);
          }
        }
      }
    }

    function validateDelineationMeta() {
      const labelEl = document.getElementById('delineationLabel');
      const notesEl = document.getElementById('delineationNotes');
      const label = labelEl?.value.trim();
      const notes = notesEl?.value.trim();

      const labelValid = Boolean(label);
      const notesValid = Boolean(notes);

      labelEl?.classList.toggle('input-error', !labelValid);
      notesEl?.classList.toggle('input-error', !notesValid);

      if (!labelValid || !notesValid) {
        alert('Please fill in both Name / Label and Notes before saving.');
        if (!labelValid) {
          labelEl?.focus();
        } else {
          notesEl?.focus();
        }
        return null;
      }

      return {
        name: label,
        notes
      };
    }

    function saveDelineationMeta() {
      if (selectedDrawnIndex < 0) return;
      const meta = validateDelineationMeta();
      if (!meta) return;

      const feature = drawnFeatures[selectedDrawnIndex];
      feature.label = meta.name;
      feature.notes = meta.notes;
      alert('Delineation details saved.');
    }

    window.removeCurrentDelineation = async function () {
      if (selectedDrawnIndex < 0) return;

      const feature = drawnFeatures[selectedDrawnIndex];
      const delineationId = feature?.delineation_id;

      // If this feature came from a saved delineation record, deleting it must happen server-side.
      // Otherwise it will reappear on refresh (because it is loaded again from the DB).
      if (delineationId && feature?.is_own && !feature?.is_approved) {
        const ok = confirm('Remove this delineation? This will permanently delete it.');
        if (!ok) return;

        try {
          const response = await fetch(`${deleteDelineationBaseUrl}/${delineationId}`, {
            method: 'DELETE',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
            },
          });

          const payload = await response.json().catch(() => ({}));
          if (!response.ok) {
            throw new Error(payload.message || 'Unable to delete delineation.');
          }

          alert(payload.message || 'Delineation deleted.');
          window.location.reload();
          return;
        } catch (error) {
          console.error('Delete delineation failed:', error);
          alert('Unable to delete delineation. Please try again.');
          return;
        }
      }

      // Unsaved / in-memory only: remove locally.
      drawnFeatures.splice(selectedDrawnIndex, 1);
      selectedDrawnIndex = -1;
      currentFeature = null;
      saveDrawingStateToHistory();
      redrawFeatures();
      document.getElementById('delineationInfoCard').style.display = 'none';
      closeRightPanel();
    };

    function saveDrawingStateToHistory() {
      // Save both completed features and current feature being drawn
      const state = {
        drawnFeatures: JSON.parse(JSON.stringify(drawnFeatures)),
        currentFeature: currentFeature ? JSON.parse(JSON.stringify(currentFeature)) : null
      };
      // Clear redo history and add new state
      featureHistory = featureHistory.slice(0, historyIndex + 1);
      featureHistory.push(state);
      historyIndex = featureHistory.length - 1;
      updateHistoryButtons();
    }

    function updateSaveButtonState() {
      const saveBtn = document.getElementById('saveBtn');
      if (!saveBtn) return;
      const hasUnsaved = Array.isArray(drawnFeatures) && drawnFeatures.some(f => !f.delineation_id);
      saveBtn.disabled = !hasUnsaved;
      saveBtn.style.opacity = !hasUnsaved ? '0.4' : '1';
      saveBtn.style.cursor = !hasUnsaved ? 'not-allowed' : 'pointer';
      saveBtn.classList.toggle('disabled', !hasUnsaved);
    }

    function updateHistoryButtons() {
      const undoBtn = document.getElementById('undoBtn');
      const redoBtn = document.getElementById('redoBtn');

      undoBtn.disabled = historyIndex <= 0;
      redoBtn.disabled = historyIndex >= featureHistory.length - 1;

      undoBtn.style.opacity = undoBtn.disabled ? '0.5' : '1';
      redoBtn.style.opacity = redoBtn.disabled ? '0.5' : '1';
      undoBtn.style.cursor = undoBtn.disabled ? 'not-allowed' : 'pointer';
      redoBtn.style.cursor = redoBtn.disabled ? 'not-allowed' : 'pointer';

      updateSaveButtonState();
    }
    //script for delenation
    window.showEditMode = () => {
      const toolbar = document.getElementById('delineationToolbar');
      const drawTypeSelect = document.getElementById('drawTypeSelect');
      const drawModeButtons = document.querySelectorAll('.draw-btn');
      const isOpening = !toolbar.classList.contains('active');

      toolbar.classList.toggle('active');

      if (isOpening) {
        const leftPanel = document.querySelector('.left-panel');
        if (leftPanel) leftPanel.classList.add('hide');
        if (drawTypeSelect) {
          drawTypeSelect.disabled = false;
          drawTypeSelect.value = '';
          drawingMode = null;
          drawModeButtons.forEach(btn => btn.classList.remove('active'));
        }
        // Initialize history with empty state including the current drawing context
        if (featureHistory.length === 0) {
          featureHistory = [{
            drawnFeatures: JSON.parse(JSON.stringify(drawnFeatures)),
            currentFeature: null
          }];
          historyIndex = 0;
        }

        // Ensure satellite layer is active
        if (curBase !== satL) {
          mainMap.removeLayer(curBase);
          curBase = satL;
          mainMap.addLayer(curBase);
          document.querySelectorAll('.layer-btn, .layer-option').forEach(b => b.classList.remove('active'));
        }

        updateHistoryButtons();
        updateSaveButtonState();
        setTimeout(() => mainMap.invalidateSize(), 50);
        setTimeout(() => mainMap.invalidateSize(), 250);
      } else {
        drawingMode = null;
        const drawTypeSelect = document.getElementById('drawTypeSelect');
        if (drawTypeSelect) drawTypeSelect.disabled = true;
        const leftPanel = document.querySelector('.left-panel');
        if (leftPanel) leftPanel.classList.remove('hide');
        mainMap.dragging.enable();
        setTimeout(() => mainMap.invalidateSize(), 50);
        setTimeout(() => mainMap.invalidateSize(), 250);
      }
    };

    const drawTypeSelect = document.getElementById('drawTypeSelect');
    const drawModeButtons = document.querySelectorAll('.draw-btn');

    drawModeButtons.forEach(btn => {
      btn.addEventListener('click', function () {
        const mode = this.dataset.mode;
        if (!mode) return;
        const isAlreadyActive = this.classList.contains('active');

        if (isAlreadyActive) {
          drawingMode = null;
          if (drawTypeSelect) drawTypeSelect.value = '';
          drawModeButtons.forEach(b => b.classList.remove('active'));
          return;
        }

        drawingMode = mode;
        if (drawTypeSelect) drawTypeSelect.value = mode;
        drawModeButtons.forEach(b => b.classList.toggle('active', b === this));
      });
    });

    drawTypeSelect?.addEventListener('change', function () {
      drawingMode = this.value;
      drawModeButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.mode === drawingMode));
    });

    document.getElementById('undoBtn').addEventListener('click', () => {
      if (historyIndex > 0) {
        historyIndex--;
        const state = featureHistory[historyIndex];
        drawnFeatures = JSON.parse(JSON.stringify(state.drawnFeatures));
        currentFeature = state.currentFeature ? JSON.parse(JSON.stringify(state.currentFeature)) : null;
        redrawFeatures();
        updateHistoryButtons();
      }
    });

    document.getElementById('redoBtn').addEventListener('click', () => {
      if (historyIndex < featureHistory.length - 1) {
        historyIndex++;
        const state = featureHistory[historyIndex];
        drawnFeatures = JSON.parse(JSON.stringify(state.drawnFeatures));
        currentFeature = state.currentFeature ? JSON.parse(JSON.stringify(state.currentFeature)) : null;
        redrawFeatures();
        updateHistoryButtons();
      }
    });

    async function persistDelineation() {
      const hasUnsaved = Array.isArray(drawnFeatures) && drawnFeatures.some(f => !f.delineation_id);
      if (!drawnFeatures.length || !hasUnsaved) {
        alert('No new delineation made to save. Please draw a feature on the map first.');
        return;
      }

      const meta = validateDelineationMeta();
      if (!meta) return;

      const saveBtn = document.getElementById('saveBtn');
      if (saveBtn?.disabled) return;
      if (saveBtn) saveBtn.disabled = true;

      const modalTitle = 'Saving Delineation...';
      const modalSubtitle = isExpertUser ?
        'Saving and publishing delineation to the map, please wait.' :
        'Saving and submitting delineation for expert review, please wait.';

      showProcessingModal(modalTitle, modalSubtitle);

      const newFeatures = drawnFeatures.filter(f => !f.delineation_id && isValidMapFeature(f));
      if (!newFeatures.length) {
        alert('No new delineation made to save. Please draw a feature on the map first.');
        if (saveBtn) saveBtn.disabled = false;
        hideProcessingModal();
        return;
      }

      try {
        const response = await fetch(saveDelineationUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({
            features: newFeatures.map(featureForPersistence),
            name: meta.name,
            notes: meta.notes,
          })
        });

        const payload = await response.json();
        if (!response.ok) {
          throw new Error(payload.message || 'Unable to save delineation.');
        }

        if (payload.delineation) {
          newFeatures.forEach(feature => {
            feature.delineation_id = payload.delineation.id;
            feature.is_approved = !!payload.delineation.is_approved;
            feature.is_rejected = !!payload.delineation.is_rejected;
            feature.label = payload.delineation.name;
            feature.notes = payload.delineation.notes;
          });
          redrawFeatures();
        }

        hideProcessingModal();
        showSuccessModal(payload.message || 'Delineation saved and submitted for expert review.');
      } catch (error) {
        hideProcessingModal();
        console.error('Delineation save failed:', error);
        alert(error.message || 'Unable to save delineation. Please try again.');
      } finally {
        updateSaveButtonState();
      }
    }

    document.getElementById('saveBtn').addEventListener('click', () => {
      persistDelineation();
    });

    const saveDelineationMetaBtn = document.getElementById('saveDelineationMetaBtn');
    if (saveDelineationMetaBtn) {
      saveDelineationMetaBtn.addEventListener('click', () => {
        saveDelineationMeta();
      });
    }

    function applyReviewToDelineationFeatures(delineationId, patch) {
      drawnFeatures.forEach(f => {
        if (f.delineation_id === delineationId) {
          Object.assign(f, patch);
        }
      });
      redrawFeatures();
    }

    async function submitExpertReview(action) {
      const expertReview = document.getElementById('expertReviewActions');
      const delineationId = Number(expertReview?.dataset.delineationId);
      if (!delineationId) return;

      let rejectionNotes = '';
      if (action === 'reject') {
        rejectionNotes = document.getElementById('expertRejectionNotes')?.value.trim() || '';
        if (rejectionNotes.length < 10) {
          alert('Please enter at least 10 characters for rejection notes.');
          return;
        }
      }

      const url = `${expertDelineationReviewBaseUrl}/${delineationId}/${action}`;
      showProcessingModal(
        action === 'approve' ? 'Approving delineation...' : 'Rejecting delineation...',
        'Please wait.'
      );

      try {
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: action === 'reject' ? JSON.stringify({ rejection_notes: rejectionNotes }) : '{}',
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(payload.message || 'Unable to complete review.');
        }

        if (action === 'approve') {
          applyReviewToDelineationFeatures(delineationId, {
            is_approved: true,
            is_rejected: false,
            rejection_notes: null,
          });
        } else {
          applyReviewToDelineationFeatures(delineationId, {
            is_approved: false,
            is_rejected: true,
            rejection_notes: rejectionNotes,
          });
        }

        hideProcessingModal();
        showSuccessModal(payload.message || 'Review saved.');
        if (selectedDrawnIndex >= 0) {
          selectDrawnFeature(selectedDrawnIndex);
        }
      } catch (error) {
        hideProcessingModal();
        alert(error.message || 'Unable to complete review.');
      }
    }

    document.getElementById('expertApproveBtn')?.addEventListener('click', () => submitExpertReview('approve'));
    document.getElementById('expertRejectBtn')?.addEventListener('click', () => submitExpertReview('reject'));

    function redrawFeatures() {
      // Clear only our overlay layers; keep the base tiles + controls intact.
      delineationLayer.clearLayers();
      vertexLayer.clearLayers();

      function getFeatureColor(f) {
        if (f.is_rejected) return '#d04030';
        if (f.is_approved) return '#1e9e62';
        if (!f.is_approved && !f.is_rejected) return '#c07818';
        return '#4ecdc4';
      }

      // Draw completed features
      drawnFeatures.forEach((f, i) => {
        const color = getFeatureColor(f);
        if (f.type === 'point') {
          const marker = L.circleMarker(f.coords, {
            radius: 7,
            color,
            fillColor: color,
            fillOpacity: 0.85,
          }).addTo(delineationLayer);
          marker.on('click', (e) => {
            if (drawingMode) return;
            if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
            selectDrawnFeature(i);
          });
        } else if (f.type === 'line') {
          const line = L.polyline(f.coords, {
            color,
            weight: 3,
          }).addTo(delineationLayer);
          line.on('click', (e) => {
            if (drawingMode) return;
            if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
            selectDrawnFeature(i);
          });
          // Vertex markers are visually nice but expensive; only render them in edit mode.
          if (document.getElementById('delineationToolbar')?.classList.contains('active')) {
            f.coords.forEach(coord => L.circleMarker(coord, {
              radius: 3,
              color,
              weight: 1,
              fillColor: color,
              fillOpacity: 0.9,
              interactive: false,
            }).addTo(vertexLayer));
          }
        } else if (f.type === 'area') {
          const polygon = L.polygon(f.coords, {
            color,
            fillColor: color,
            fillOpacity: 0.45,
            weight: 2,
          }).addTo(delineationLayer);
          polygon.on('click', (e) => {
            if (drawingMode) return;
            if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
            selectDrawnFeature(i);
          });
          if (document.getElementById('delineationToolbar')?.classList.contains('active')) {
            f.coords.forEach(coord => L.circleMarker(coord, {
              radius: 3,
              color,
              weight: 1,
              fillColor: color,
              fillOpacity: 0.9,
              interactive: false,
            }).addTo(vertexLayer));
          }
        }
      });

      // Draw current feature being drawn
      if (currentFeature) {
        if (currentFeature.type === 'point') {
          L.circleMarker(currentFeature.coords, {
            radius: 7,
            color: '#ff6b6b',
            fillColor: '#ff6b6b',
            fillOpacity: 0.6,
            interactive: false,
          }).addTo(delineationLayer);
        } else if (currentFeature.type === 'line') {
          L.polyline(currentFeature.coords, {
            color: '#ff6b6b',
            opacity: 0.7
          }).addTo(delineationLayer);
          currentFeature.coords.forEach(coord => L.circleMarker(coord, {
            radius: 3,
            color: '#ff6b6b',
            weight: 1,
            fillColor: '#ff6b6b',
            fillOpacity: 0.9,
            interactive: false,
          }).addTo(vertexLayer));
        } else if (currentFeature.type === 'area') {
          if (currentFeature.coords.length >= 3) {
            L.polygon(currentFeature.coords, {
              color: '#4ecdc4',
              fillOpacity: 0.3
            }).addTo(delineationLayer);
          } else {
            L.polyline(currentFeature.coords, {
              color: '#4ecdc4',
              opacity: 0.7
            }).addTo(delineationLayer);
          }
          currentFeature.coords.forEach(coord => L.circleMarker(coord, {
            radius: 3,
            color: '#4ecdc4',
            weight: 1,
            fillColor: '#4ecdc4',
            fillOpacity: 0.9,
            interactive: false,
          }).addTo(vertexLayer));
        }
      }

      updateSaveButtonState();
    }

    mainMap.on('click', (e) => {
      if (!drawingMode) return;
      const snappedLatLng = getSnappedLatLng(e.latlng) || e.latlng;
      if (!currentFeature) {
        currentFeature = {
          type: drawingMode,
          coords: []
        };
      }
      if (drawingMode === 'point') {
        currentFeature.coords = [snappedLatLng.lat, snappedLatLng.lng];
        drawnFeatures.push(currentFeature);
        currentFeature = null;
        saveDrawingStateToHistory();
        redrawFeatures();
        selectDrawnFeature(drawnFeatures.length - 1);
      } else if (drawingMode === 'line') {
        currentFeature.coords.push([snappedLatLng.lat, snappedLatLng.lng]);
        // Save state after each point added
        saveDrawingStateToHistory();

        // Check if close to first point to close the delineation
        if (currentFeature.coords.length > 2) {
          let first = currentFeature.coords[0];
          const firstPixel = mainMap.latLngToContainerPoint(L.latLng(first[0], first[1]));
          const currentPixel = mainMap.latLngToContainerPoint(snappedLatLng);
          const pixelDist = firstPixel.distanceTo(currentPixel);

          if (pixelDist < snapPixelThreshold * 2) { // use 2x threshold for closing
            currentFeature.coords[currentFeature.coords.length - 1] = [first[0], first[1]];
            currentFeature.type = 'area';
            drawnFeatures.push(currentFeature);
            currentFeature = null;
            saveDrawingStateToHistory();
            drawingMode = null; // Exit drawing mode
            mainMap.dragging.enable();
            document.querySelectorAll('.draw-btn').forEach(b => b.classList.remove('active'));
            redrawFeatures();
            selectDrawnFeature(drawnFeatures.length - 1);
            return;
          }
        }
      } else if (drawingMode === 'area') {
        currentFeature.coords.push([snappedLatLng.lat, snappedLatLng.lng]);
        // Save state after each point added
        saveDrawingStateToHistory();

        // Check if close to first point to close early
        if (currentFeature.coords.length > 2) {
          let first = currentFeature.coords[0];
          const firstPixel = mainMap.latLngToContainerPoint(L.latLng(first[0], first[1]));
          const currentPixel = mainMap.latLngToContainerPoint(snappedLatLng);
          const pixelDist = firstPixel.distanceTo(currentPixel);

          if (pixelDist < snapPixelThreshold * 2) {
            currentFeature.coords[currentFeature.coords.length - 1] = [first[0], first[1]];
            drawnFeatures.push(currentFeature);
            currentFeature = null;
            saveDrawingStateToHistory();
            drawingMode = null;
            mainMap.dragging.enable();
            document.querySelectorAll('.draw-btn').forEach(b => b.classList.remove('active'));
            redrawFeatures();
            selectDrawnFeature(drawnFeatures.length - 1);
            return;
          }
        }
      }
      redrawFeatures();
    });

    // Throttle snapping updates to one per animation frame (mousemove can fire *a lot*).
    let snapRaf = 0;
    let lastMouseLatLng = null;
    mainMap.on('mousemove', (e) => {
      if (!drawingMode) return;
      lastMouseLatLng = e.latlng;
      if (snapRaf) return;
      snapRaf = requestAnimationFrame(() => {
        snapRaf = 0;
        if (!drawingMode || !lastMouseLatLng) return;
        const snapped = getSnappedLatLng(lastMouseLatLng);
        if (snapped) {
          snapMarker.setLatLng(snapped);
          snapMarker.setStyle({
            opacity: 1,
            fillOpacity: 0.35,
          });
        } else {
          snapMarker.setStyle({
            opacity: 0,
            fillOpacity: 0,
          });
        }
      });
    });

    window.toggleLayerMenu = () => {
      const menu = document.getElementById('layerMenu');
      const toggle = document.getElementById('layerToggle');
      if (!menu || !toggle) return;
      const isOpen = menu.classList.toggle('show');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    document.addEventListener('click', (event) => {
      const menu = document.getElementById('layerMenu');
      const toggle = document.getElementById('layerToggle');
      if (!menu || !toggle) return;
      if (!menu.contains(event.target) && !toggle.contains(event.target)) {
        menu.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    window.show = (id, btn) => {
      document.querySelectorAll('.h-tab').forEach(b => b.classList.remove('active'));
      if (btn && btn.classList) btn.classList.add('active');
      document.querySelectorAll('.view').forEach(v => v.classList.remove('on'));
      document.getElementById('v-' + id).classList.add('on');
      const navDropdown = document.getElementById('navDropdown');
      if (navDropdown) navDropdown.value = id;
      // Keep the URL in sync with the active tab
      const url = new URL(window.location.href);
      url.searchParams.set('tab', id);
      history.replaceState(null, '', url.toString());
      setTimeout(() => {
        if (id === 'map') mainMap.invalidateSize();
        if (id === 'planting') {
          ensurePlantMap().invalidateSize();
        }
      }, 150);
    };

    window.showDelineation = () => {
      window.location.href = @json(route('delineation.index'));
    };

    window.showFromDropdown = (select) => {
      const id = select.value;
      document.querySelectorAll('.view').forEach(v => v.classList.remove('on'));
      document.getElementById('v-' + id).classList.add('on');
      let btns = document.querySelectorAll('.h-tab');
      btns.forEach((b, i) => {
        b.classList.remove('active');
        if ((id === 'map' && i === 0) || (id === 'planting' && i === 1)) b.classList.add('active');
      });
      setTimeout(() => {
        if (id === 'map') mainMap.invalidateSize();
        if (id === 'planting') {
          ensurePlantMap().invalidateSize();
        }
      }, 150);
    };







    window.addEventListener('resize', () => {
      setTimeout(() => {
        mainMap.invalidateSize();
        if (plantMap) plantMap.invalidateSize();
      }, 100);
    });

    function findDelineationRecordById(delineationId) {
      const pools = [focusDelineationRecord, ...savedDelineations, ...residentDelineations, ...approvedDelineations];
      return pools.find(r => r && Number(r.id) === Number(delineationId)) || null;
    }

    function injectFocusDelineationIfNeeded(delineationId) {
      if (!delineationId) return;
      if (drawnFeatures.some(f => Number(f.delineation_id) === Number(delineationId))) return;

      const record = findDelineationRecordById(delineationId);
      if (!record || !Array.isArray(record.features) || !record.features.length) return;

      const isOwn = Number(record.user_id) === Number(authUserId);
      pushDelineationFeatures(record, {
        is_own: isOwn,
        created_by: record.user?.name || (isExpertUser ? 'Resident' : 'Community'),
      });
      redrawFeatures();
    }

    function flyToDelineationRecord(record) {
      if (!record || typeof mainMap === 'undefined' || !mainMap) return;
      const features = Array.isArray(record.features) ? record.features.filter(isValidMapFeature) : [];
      if (!features.length) return;

      const points = [];
      features.forEach(f => extractPoints(f.coords).forEach(pt => points.push(pt)));
      if (!points.length) return;

      if (points.length === 1 || features[0]?.type === 'point') {
        const [lat, lng] = points[0];
        mainMap.flyTo([lat, lng], Math.max(mainMap.getZoom(), 15), { duration: 0.85 });
        return;
      }

      const latLngs = points.map(c => L.latLng(c[0], c[1]));
      mainMap.flyToBounds(L.latLngBounds(latLngs), {
        padding: [60, 60],
        maxZoom: 17,
        duration: 0.85,
      });
    }

    function openDelineationFromQueryParam() {
      const params = new URLSearchParams(window.location.search);
      const delineationId = Number(focusDelineationId || params.get('delineation'));
      if (!delineationId) return;

      if (typeof show === 'function') {
        show('map');
      }

      const finishOpen = () => {
        injectFocusDelineationIfNeeded(delineationId);

        const featureIndex = drawnFeatures.findIndex(f => Number(f.delineation_id) === delineationId);
        if (featureIndex >= 0) {
          selectDrawnFeature(featureIndex);
          return;
        }

        const zoneIndex = zones.findIndex(z => Number(z.id) === delineationId);
        if (zoneIndex >= 0) {
          selectZone(zoneIndex);
          return;
        }

        const record = findDelineationRecordById(delineationId);
        if (record) {
          flyToDelineationRecord(record);
        }
      };

      const run = (attempt = 0) => {
        if (typeof mainMap !== 'undefined' && mainMap) {
          mainMap.invalidateSize({ pan: false });
          finishOpen();
          return;
        }
        if (attempt < 12) {
          setTimeout(() => run(attempt + 1), 100);
        }
      };

      setTimeout(() => run(), 250);
    }

    openDelineationFromQueryParam();
    try {
      const pieCanvas = document.getElementById('pieC');
      if (pieCanvas && typeof Chart !== 'undefined') {
        new Chart(pieCanvas, {
          type: 'doughnut',
          data: {
            labels: ['Rhizophora', 'Avicennia', 'Sonneratia', 'Bruguiera', 'Others'],
            datasets: [{
              data: [34, 22, 18, 14, 12],
              backgroundColor: ['#1e9e62', '#5ab8de', '#f4a840', '#a070e0', '#c0c8b8'],
              borderWidth: 0
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '64%'
          }
        });
      }
    } catch (e) {
      console.warn('pieC chart init error:', e);
    }

    let temporalTrendChart = null;
    try {
      const trendCanvas = document.getElementById('trendC');
      if (trendCanvas && typeof Chart !== 'undefined') {
        temporalTrendChart = new Chart(trendCanvas, {
          type: 'line',
          data: {
            labels: ['2021', '2022', '2023', '2024', '2025', '2026'],
            datasets: [{
              data: [46178, 45900, 46360, 46820, 47100, 47382],
              borderColor: '#1e9e62',
              backgroundColor: 'rgba(30,158,98,.12)',
              fill: true,
              tension: .35,
              pointRadius: [3, 3, 3, 3, 3, 5],
              pointBackgroundColor: ['#1e9e62', '#d04030', '#1e9e62', '#1e9e62', '#1e9e62', '#1e9e62'],
              pointBorderColor: '#fff',
              pointBorderWidth: 2
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false }
            },
            scales: {
              y: {
                ticks: {
                  font: { size: 9 },
                  callback: v => (v / 1000).toFixed(0) + 'k'
                },
                min: 45000
              },
              x: {
                ticks: { font: { size: 9 } }
              }
            }
          }
        });
      }
    } catch (e) {
      console.warn('trendC chart init error:', e);
    }

    try {
      const plantTrendCanvas = document.getElementById('plantTrendC');
      if (plantTrendCanvas && typeof Chart !== 'undefined') {
        new Chart(plantTrendCanvas, {
          type: 'line',
          data: {
            labels: [],
            datasets: [{
              data: [],
              borderColor: '#1e9e62',
              backgroundColor: 'rgba(30,158,98,.1)',
              fill: true
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                ticks: {
                  callback: v => (v / 1000).toFixed(0) + 'k'
                }
              }
            }
          }
        });
      }
    } catch (e) {
      console.warn('plantTrendC chart init error:', e);
    }

    const plantListDiv = document.getElementById('plantSitesList');
    plantSites.forEach((s, i) => {
      const card = document.createElement('div');
      card.className = 'site-card';
      card.setAttribute('onclick', `flyPlant(${i})`);
      card.innerHTML = `<div class="sc-head"><div class="sc-rank ${s.priority === 'Critical' ? 'rg' : (s.priority === 'High' ? 'ra' : 'rb')}">${i + 1}</div><div class="sc-name">${s.name}</div></div><div class="sc-sp">${s.priority} priority zone</div><div class="sc-foot"><div class="sbar"><div class="strack"><div class="sfill" style="width:${s.score}%;background:${s.color}"></div></div><span style="font-size:10px;font-weight:700;color:${s.color}">${s.score}%</span></div><span class="ptag" style="background:${s.color}20;border-color:${s.color};color:${s.color}">${s.priority}</span></div>`;
      plantListDiv.appendChild(card);
    });

    // Clean transitionend handler on right panel: single map resize when sliding animation finishes
    const rightPanelEl = document.getElementById('mapRightPanel');
    if (rightPanelEl) {
      rightPanelEl.addEventListener('transitionend', (e) => {
        if (e.target === rightPanelEl && (e.propertyName === 'width' || e.propertyName === 'transform')) {
          mainMap.invalidateSize({ pan: false });
        }
      });
    }

    // Notification dropdown toggle
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (notificationToggle && notificationDropdown) {
      notificationToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('active');
        if (typeof profileDropdown !== 'undefined' && profileDropdown) {
          profileDropdown.classList.remove('active');
        }
      });

      document.addEventListener('click', function (e) {
        if (!notificationToggle.contains(e.target) && !notificationDropdown.contains(e.target)) {
          notificationDropdown.classList.remove('active');
        }
      });

      notificationDropdown.addEventListener('click', function (e) {
        e.stopPropagation();
      });
    }

    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileToggle && profileDropdown) {
      profileToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('active');
        notificationDropdown?.classList.remove('active');
      });

      document.addEventListener('click', function (e) {
        if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
          profileDropdown.classList.remove('active');
        }
      });

      profileDropdown.addEventListener('click', function (e) {
        e.stopPropagation();
      });
    }

    // =========================================================
    // TEMPORAL MONITORING (CHANGES OVER TIME) TIMELINE CONTROLLER
    // =========================================================
    const temporalData = [
      {
        year: '2021',
        label: '2021 (Baseline)',
        area: 46178,
        areaFormatted: '46,178 ha',
        ndvi: 0.69,
        ndviText: '0.69 (Moderate)',
        changeBadge: 'Baseline (2021)',
        isNegative: false,
        summaryArea: '46,178',
        markerOpacity: 0.75,
        markerScale: 0.92
      },
      {
        year: '2022',
        label: '2022 (Disturbance)',
        area: 45900,
        areaFormatted: '45,900 ha',
        ndvi: 0.67,
        ndviText: '0.67 (Degraded)',
        changeBadge: '-0.6% post-storm',
        isNegative: true,
        summaryArea: '45,900',
        markerOpacity: 0.65,
        markerScale: 0.85
      },
      {
        year: '2023',
        label: '2023 (Early Regrowth)',
        area: 46360,
        areaFormatted: '46,360 ha',
        ndvi: 0.71,
        ndviText: '0.71 (Regrowth)',
        changeBadge: '+1.0% recovery',
        isNegative: false,
        summaryArea: '46,360',
        markerOpacity: 0.8,
        markerScale: 0.95
      },
      {
        year: '2024',
        label: '2024 (Active Regrowth)',
        area: 46820,
        areaFormatted: '46,820 ha',
        ndvi: 0.74,
        ndviText: '0.74 (Healthy)',
        changeBadge: '+2.0% canopy gain',
        isNegative: false,
        summaryArea: '46,820',
        markerOpacity: 0.88,
        markerScale: 1.0
      },
      {
        year: '2025',
        label: '2025 (Dense Canopy)',
        area: 47100,
        areaFormatted: '47,100 ha',
        ndvi: 0.76,
        ndviText: '0.76 (Dense)',
        changeBadge: '+2.6% protection',
        isNegative: false,
        summaryArea: '47,100',
        markerOpacity: 0.92,
        markerScale: 1.05
      },
      {
        year: '2026',
        label: '2026 (Current)',
        area: 47382,
        areaFormatted: '47,382 ha',
        ndvi: 0.78,
        ndviText: '0.78 (Optimal)',
        changeBadge: '+3.2% net recovery',
        isNegative: false,
        summaryArea: '47,382',
        markerOpacity: 1.0,
        markerScale: 1.1
      }
    ];

    let currentTimelineIndex = 5;
    let timelinePlayInterval = null;

    function applyTimelineEpoch(index, animate = false) {
      const item = temporalData[index];
      if (!item) return;

      currentTimelineIndex = index;

      // Update Slider Input
      const slider = document.getElementById('timelineRangeInput');
      if (slider && Number(slider.value) !== index) {
        slider.value = index;
      }

      // Update Marks
      const marks = document.querySelectorAll('#timelineMarks .timeline-mark');
      marks.forEach((m, i) => {
        if (i === index) {
          m.classList.add('active');
        } else {
          m.classList.remove('active');
        }
      });

      // Update Badges & Text
      const badgeEl = document.getElementById('timelineChangeBadge');
      if (badgeEl) {
        badgeEl.textContent = item.changeBadge;
        if (item.isNegative) {
          badgeEl.classList.add('badge-negative');
        } else {
          badgeEl.classList.remove('badge-negative');
        }
      }

      const yearEl = document.getElementById('timelineSelectedYear');
      if (yearEl) yearEl.textContent = item.label;

      const areaEl = document.getElementById('timelineAreaStat');
      if (areaEl) areaEl.textContent = item.areaFormatted;

      const ndviEl = document.getElementById('timelineNdviStat');
      if (ndviEl) ndviEl.textContent = item.ndviText;

      // Sync Left Panel Summary Stat
      const summaryAreaEl = document.getElementById('summaryTotalArea');
      if (summaryAreaEl) {
        summaryAreaEl.textContent = item.summaryArea;
      }

      // Sync Chart Point Highlight if available
      if (temporalTrendChart && temporalTrendChart.data?.datasets?.[0]) {
        const dataset = temporalTrendChart.data.datasets[0];
        dataset.pointRadius = temporalData.map((_, i) => (i === index ? 6 : 2.5));
        temporalTrendChart.update('none');
      }

      // Map Visual Feedback: animate/pulse existing zone markers to reflect temporal state
      if (typeof polys !== 'undefined' && Array.isArray(polys) && polys.length > 0) {
        polys.forEach((p) => {
          if (p && typeof p.setStyle === 'function') {
            const baseRadius = 8;
            p.setStyle({
              fillOpacity: item.markerOpacity * 0.85,
              opacity: item.markerOpacity
            });
            if (typeof p.setRadius === 'function') {
              p.setRadius(Math.round(baseRadius * item.markerScale));
            }
          }
        });
      }

      // Render Sample Temporal Delineation Polygon for this active epoch
      renderSampleTemporalDelineation(index);
    }

    // =========================================================
    // SAMPLE MULTI-YEAR TEMPORAL DELINEATION
    // =========================================================
    const sampleTemporalEpochs = [
      {
        year: '2021',
        label: '2021 Baseline',
        areaFormatted: '12.4 ha',
        ndviText: '0.69 (Moderate)',
        status: 'Baseline Survey',
        notes: 'Initial municipal survey of remnant fringe mangroves prior to restoration.',
        isNegative: false,
        color: '#1e9e62',
        coords: [
          [10.354, 124.968],
          [10.358, 124.972],
          [10.355, 124.976],
          [10.350, 124.972]
        ]
      },
      {
        year: '2022',
        label: '2022 Storm Gap',
        areaFormatted: '11.8 ha (-4.8%)',
        ndviText: '0.67 (Disturbed)',
        status: 'Storm Disturbance',
        notes: 'Severe storm damage defoliated north-eastern seaward fringe; gap created.',
        isNegative: true,
        color: '#c07818',
        coords: [
          [10.353, 124.968],
          [10.356, 124.971],
          [10.353, 124.974],
          [10.350, 124.971]
        ]
      },
      {
        year: '2023',
        label: '2023 Reforestation',
        areaFormatted: '14.1 ha (+13.7%)',
        ndviText: '0.71 (Regrowth)',
        status: 'Community Planting',
        notes: '4,200 Rhizophora & Avicennia propagules planted along tidal flats.',
        isNegative: false,
        color: '#1e9e62',
        coords: [
          [10.353, 124.967],
          [10.359, 124.973],
          [10.357, 124.977],
          [10.349, 124.973]
        ]
      },
      {
        year: '2024',
        label: '2024 Sapling Growth',
        areaFormatted: '16.2 ha (+30.6%)',
        ndviText: '0.74 (Healthy)',
        status: 'Active Expansion',
        notes: '84% survival rate; sapling root networks stabilizing coastal sediment.',
        isNegative: false,
        color: '#1e9e62',
        coords: [
          [10.352, 124.966],
          [10.361, 124.974],
          [10.359, 124.979],
          [10.348, 124.974]
        ]
      },
      {
        year: '2025',
        label: '2025 Canopy Closure',
        areaFormatted: '17.8 ha (+43.5%)',
        ndviText: '0.76 (Dense)',
        status: 'Secondary Forest',
        notes: 'Canopy closure formed across creek estuary; juvenile fish nursery restored.',
        isNegative: false,
        color: '#1e9e62',
        coords: [
          [10.351, 124.965],
          [10.363, 124.975],
          [10.361, 124.981],
          [10.347, 124.975]
        ]
      },
      {
        year: '2026',
        label: '2026 Optimal Canopy',
        areaFormatted: '19.4 ha (+56.4% gain)',
        ndviText: '0.78 (Optimal)',
        status: 'Protected Sanctuary',
        notes: 'Dense, continuous mangrove buffer protecting coastal communities from storm surge.',
        isNegative: false,
        color: '#1e9e62',
        coords: [
          [10.350, 124.964],
          [10.365, 124.976],
          [10.363, 124.983],
          [10.346, 124.976]
        ]
      }
    ];

    let temporalDelineationGroup = null;
    let sampleActivePolygon = null;
    let isTemporalZoneSelected = false;

    function renderSampleTemporalDelineation(epochIndex) {
      if (typeof mainMap === 'undefined' || !mainMap) return;
      if (!temporalDelineationGroup) {
        temporalDelineationGroup = L.layerGroup().addTo(mainMap);
      }
      temporalDelineationGroup.clearLayers();

      const epoch = sampleTemporalEpochs[epochIndex];
      if (!epoch) return;

      // 1. Ghost Boundary for 2021 Baseline
      const baselineCoords = sampleTemporalEpochs[0].coords;
      L.polygon(baselineCoords, {
        color: '#3a7d5a',
        weight: 1.5,
        dashArray: '4, 4',
        fillColor: '#3a7d5a',
        fillOpacity: 0.08,
        interactive: false
      }).addTo(temporalDelineationGroup);

      // 2. Active Year Polygon (expands & adjusts with the epoch)
      sampleActivePolygon = L.polygon(epoch.coords, {
        color: epoch.color,
        weight: 2.5,
        fillColor: epoch.color,
        fillOpacity: epoch.isNegative ? 0.35 : 0.48,
        interactive: true
      }).addTo(temporalDelineationGroup);

      sampleActivePolygon.bindTooltip(
        `<div style="font-family:'Manrope',sans-serif;font-size:11px;">
           <strong style="color:${epoch.color};">${epoch.year} Delineation</strong><br>
           Area: <b>${epoch.areaFormatted}</b><br>
           Health: <b>${epoch.ndviText}</b>
         </div>`,
        { sticky: true }
      );

      sampleActivePolygon.on('click', (e) => {
        if (drawingMode) return;
        if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
        selectTemporalZone();
      });

      // 3. Center indicator badge
      const centerLat = (epoch.coords[0][0] + epoch.coords[2][0]) / 2;
      const centerLng = (epoch.coords[0][1] + epoch.coords[2][1]) / 2;
      const centerIcon = L.divIcon({
        html: `<div style="background:${epoch.color};width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;box-shadow:0 3px 10px rgba(0,0,0,0.3);border:2px solid #fff;cursor:pointer;">
                 <i class="bi bi-clock-history"></i>
               </div>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13]
      });

      const centerMarker = L.marker([centerLat, centerLng], { icon: centerIcon }).addTo(temporalDelineationGroup);
      centerMarker.on('click', (e) => {
        if (drawingMode) return;
        if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
        selectTemporalZone();
      });

      // Update sidebar zone row
      const rowHa = document.getElementById('temporalZoneHa');
      if (rowHa) rowHa.textContent = epoch.areaFormatted.split(' ')[0];

      // Update right panel details if temporal zone is selected
      if (isTemporalZoneSelected) {
        document.getElementById('dArea').textContent = epoch.areaFormatted;
        document.getElementById('dNDVI').textContent = epoch.ndviText;
        document.getElementById('dNDVI').className = `d-val ${epoch.isNegative ? 'r' : 'g'}`;
        document.getElementById('dStatus').textContent = epoch.status;
        document.getElementById('dStatus').className = `d-val ${epoch.isNegative ? 'p' : 'g'}`;
        document.getElementById('dScan').textContent = `${epoch.year} Satellite Epoch`;
        updateTemporalEpochList();
      }
    }

    window.selectTemporalZone = function () {
      isTemporalZoneSelected = true;
      currentSelectedZoneIndex = -1;
      selectedDrawnIndex = -1;

      document.querySelectorAll('.zone-row').forEach(r => r.classList.remove('sel'));
      document.getElementById('temporalZoneRow')?.classList.add('sel');

      const epoch = sampleTemporalEpochs[currentTimelineIndex];
      openRightPanel('Zone: Silago Bay Reforestation');

      document.getElementById('delineationInfoCard').style.display = 'none';
      document.getElementById('zoneDetailsContent').style.display = 'block';

      document.getElementById('dName').textContent = 'Silago Bay Reforestation Site';
      document.getElementById('dArea').textContent = epoch.areaFormatted;
      document.getElementById('dNDVI').textContent = epoch.ndviText;
      document.getElementById('dNDVI').className = `d-val ${epoch.isNegative ? 'r' : 'g'}`;
      document.getElementById('dStatus').textContent = epoch.status;
      document.getElementById('dStatus').className = `d-val ${epoch.isNegative ? 'p' : 'g'}`;
      document.getElementById('dGenus').textContent = 'Rhizophora & Avicennia';
      document.getElementById('dScan').textContent = `${epoch.year} Satellite Epoch`;

      const removeBtn = document.getElementById('removeDelineationBtn');
      if (removeBtn) removeBtn.style.display = 'none';

      const breakdownEl = document.getElementById('temporalZoneBreakdown');
      if (breakdownEl) breakdownEl.style.display = 'block';
      updateTemporalEpochList();

      if (sampleActivePolygon && mainMap) {
        mainMap.flyToBounds(sampleActivePolygon.getBounds(), { padding: [60, 60], maxZoom: 16, duration: 0.8 });
      }
    };

    window.focusTemporalSampleZone = function (e) {
      if (e) e.stopPropagation();
      selectTemporalZone();
    };

    function updateTemporalEpochList() {
      const container = document.getElementById('temporalEpochList');
      if (!container) return;
      container.innerHTML = sampleTemporalEpochs.map((ep, idx) => `
        <div class="temporal-epoch-row ${idx === currentTimelineIndex ? 'active' : ''}" onclick="setTimelineYearIndex(${idx})">
          <div class="epoch-year-badge">${ep.year}</div>
          <div class="epoch-content">
            <div class="epoch-headline">
              <span>${ep.status}</span>
              <span style="color:${ep.color};font-weight:700;">${ep.areaFormatted}</span>
            </div>
            <div class="epoch-sub">${ep.notes}</div>
          </div>
        </div>
      `).join('');
    }

    window.onTimelineSliderChange = function (val) {
      applyTimelineEpoch(parseInt(val, 10));
    };

    window.setTimelineYearIndex = function (index) {
      applyTimelineEpoch(index);
    };

    window.toggleTimelinePlayback = function () {
      const playBtn = document.getElementById('timelinePlayBtn');
      const icon = document.getElementById('timelinePlayIcon');

      if (timelinePlayInterval) {
        // Stop playback
        clearInterval(timelinePlayInterval);
        timelinePlayInterval = null;
        if (playBtn) playBtn.classList.remove('playing');
        if (icon) icon.className = 'bi bi-play-fill';
      } else {
        // Start playback
        if (playBtn) playBtn.classList.add('playing');
        if (icon) icon.className = 'bi bi-pause-fill';

        timelinePlayInterval = setInterval(() => {
          let nextIndex = currentTimelineIndex + 1;
          if (nextIndex >= temporalData.length) {
            nextIndex = 0;
          }
          applyTimelineEpoch(nextIndex, true);
        }, 1600);
      }
    };

    window.toggleTimelineCollapse = function (e) {
      if (e) e.stopPropagation();
      const control = document.getElementById('mapTimelineControl');
      if (control) {
        control.classList.toggle('collapsed');
      }
    };

    window.handleTimelineHeaderClick = function (e) {
      const control = document.getElementById('mapTimelineControl');
      if (control && control.classList.contains('collapsed')) {
        control.classList.remove('collapsed');
      }
    };

    window.toggleTimelineControl = function () {
      const control = document.getElementById('mapTimelineControl');
      const btn = document.getElementById('timelineToggleBtn');
      if (!control) return;

      if (control.style.display === 'none') {
        control.style.display = 'block';
        if (btn) btn.classList.add('active-toggle');
      } else {
        control.style.display = 'none';
        if (btn) btn.classList.remove('active-toggle');
      }
    };

    // Initialize timeline to current epoch
    document.addEventListener('DOMContentLoaded', () => {
      applyTimelineEpoch(5);
    });
    // In case DOM is already ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      applyTimelineEpoch(5);
    }


    if (APP_PAGE === 'delineate') {
      const toolbar = document.getElementById('delineationToolbar');
      if (toolbar && !toolbar.classList.contains('active') && typeof window.showEditMode === 'function') {
        window.showEditMode();
      }
    }
</script>
