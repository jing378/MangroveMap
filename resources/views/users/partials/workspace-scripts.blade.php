<script>
    const APP_PAGE = @json($page ?? 'map');

    // DATA & MAP INITIALIZATION (same as before)
    const saveDelineationUrl = "{{ Auth::user()->isExpert() ? route('expert.delineations.store') : route('delineations.store') }}";
    const reviseDelineationUrlTemplate = @json(route('delineations.revise', ['delineation' => '__DELINEATION_ID__']));
    const updateBoundaryUrlTemplate = @json(route('delineations.boundary.update', ['delineation' => '__DELINEATION_ID__']));
    const resolveDelineationLocationUrl = @json(route('delineations.location-preview'));
    const deleteDelineationBaseUrl = "{{ url('/delineations') }}";
    const annualObservationBaseUrl = @json(url('/delineations'));
    const isExpertUser = {{ Auth::check() && Auth::user()->isExpert() ? 'true' : 'false' }};
    const savedDelineations = @json($delineations);
    const approvedDelineations = @json($approvedDelineationsForMap);
    const residentDelineations = @json($residentDelineationsForMap ?? []);
    const focusDelineationId = @json($focusDelineationId ?? null);
    const focusDelineationRecord = @json($focusDelineationRecord ?? null);
    const authUserId = @json(Auth::id());
    const authUserName = @json(Auth::user()?->name ?? '');
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
        genera: Array.isArray(feature.genera) ? feature.genera : (currentSelectedGenera || []),
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

    let activeLocationPreviewToken = 0;

    async function updateFeatureLocationDisplay(feature, featureIndex = selectedDrawnIndex) {
      const locationEl = document.getElementById('delineationFeatureLocation') || document.getElementById('delineationFeatureLabel');
      if (!locationEl) return;

      const token = ++activeLocationPreviewToken;
      if (feature.location_status === 'matched' && feature.location) {
        locationEl.textContent = feature.location;
        return;
      }

      locationEl.textContent = 'Locating barangay...';

      try {
        const response = await fetch(resolveDelineationLocationUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({
            feature: {
              type: feature.type,
              coords: feature.coords,
            },
          }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.feature) {
          throw new Error(payload.message || 'Unable to preview location.');
        }

        feature.location = payload.feature.location || null;
        feature.location_status = payload.feature.location_status || 'outside_boundaries';
        feature.location_details = payload.feature.location_details || null;

        if (token !== activeLocationPreviewToken || drawnFeatures[featureIndex] !== feature) return;

        if (feature.location_status === 'matched' && feature.location) {
          locationEl.textContent = feature.location;
          if (!feature.delineation_id) {
            feature.label = feature.location;
          }
        } else if (feature.location_status === 'pending') {
          locationEl.textContent = 'Location pending boundary import';
        } else {
          locationEl.textContent = 'Outside loaded barangay boundaries';
        }
      } catch (error) {
        if (token === activeLocationPreviewToken) {
          locationEl.textContent = 'Location preview unavailable';
        }
      }
    }

    let currentSelectedGenera = [];

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    // Local/common names for Philippine mangrove genera
    const genusLocalNames = {
      'Rhizophora': 'Bakawan',
      'Avicennia': 'Bungalon',
      'Sonneratia': 'Pagatpat',
      'Bruguiera': 'Miapi',
      'Ceriops': 'Tungog',
      'Xylocarpus': 'Tabigi',
      'Nypa': 'Nipa/Sasa',
    };

    function getGenusDisplayLabel(genus) {
      const local = genusLocalNames[genus];
      return local ? `${genus} <span class="genus-local-name">(${local})</span>` : escapeHtml(genus);
    }

    function getGenusPlainLabel(genus) {
      const local = genusLocalNames[genus];
      return local ? `${genus} (${local})` : genus;
    }

    function renderDelineationGenera(genera) {
      currentSelectedGenera = Array.isArray(genera) ? [...genera] : [];
      const displayEl = document.getElementById('delineationFeatureGenera');
      if (displayEl) {
        if (!currentSelectedGenera.length) {
          displayEl.innerHTML = '<span style="color:#9ab0a0;font-weight:400;font-style:italic;">None recorded</span>';
        } else {
          displayEl.innerHTML = currentSelectedGenera
            .map(g => `<span class="genus-tag">${getGenusDisplayLabel(g)}</span>`)
            .join(' ');
        }
      }

      // Update quick selection buttons
      document.querySelectorAll('#genusQuickSelector .genus-pill-btn').forEach(btn => {
        const genus = btn.dataset.genus;
        const isActive = currentSelectedGenera.includes(genus);
        btn.classList.toggle('active', isActive);
        btn.innerHTML = `<i class="bi ${isActive ? 'bi-check2' : 'bi-plus'}"></i> ${getGenusDisplayLabel(genus)}`;
      });

      // Update selected tags with remove button
      const container = document.getElementById('delineationSelectedGeneraContainer');
      if (container) {
        container.innerHTML = currentSelectedGenera.map(genus => `
          <span class="genus-tag">
            ${getGenusDisplayLabel(genus)}
            <span class="genus-tag-remove" onclick="removeGenusSelection('${escapeHtml(genus)}')" title="Remove">&times;</span>
          </span>
        `).join('');
      }
    }

    window.toggleGenusSelection = function (genus) {
      if (!genus) return;
      const idx = currentSelectedGenera.indexOf(genus);
      if (idx >= 0) {
        currentSelectedGenera.splice(idx, 1);
      } else {
        currentSelectedGenera.push(genus);
      }
      if (selectedDrawnIndex >= 0 && drawnFeatures[selectedDrawnIndex]) {
        drawnFeatures[selectedDrawnIndex].genera = [...currentSelectedGenera];
      }
      renderDelineationGenera(currentSelectedGenera);
    };

    window.removeGenusSelection = function (genus) {
      window.toggleGenusSelection(genus);
    };

    window.addCustomGenusFromInput = function () {
      const input = document.getElementById('delineationCustomGenusInput');
      if (!input) return;
      const raw = input.value.trim();
      if (!raw) return;
      const formatted = raw.charAt(0).toUpperCase() + raw.slice(1);
      if (!currentSelectedGenera.includes(formatted)) {
        currentSelectedGenera.push(formatted);
        if (selectedDrawnIndex >= 0 && drawnFeatures[selectedDrawnIndex]) {
          drawnFeatures[selectedDrawnIndex].genera = [...currentSelectedGenera];
        }
        renderDelineationGenera(currentSelectedGenera);
      }
      input.value = '';
    };

    document.getElementById('delineationCustomGenusInput')?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        window.addCustomGenusFromInput();
      }
    });

    function formatFeatureArea(feature) {
      const points = extractPoints(feature?.coords);
      if (!points.length) return 'N/A';
      if (feature?.type === 'point') return '1 pt';
      return `${points.length} pts`;
    }

    function calculateFeatureAreaHectares(feature) {
      if (feature?.type !== 'area' || !Array.isArray(feature.coords) || feature.coords.length < 3) {
        return null;
      }

      const earthRadiusMeters = 6371008.8;
      const points = feature.coords.map(([lat, lng]) => [
        Number(lat) * Math.PI / 180,
        Number(lng) * Math.PI / 180,
      ]);
      let area = 0;

      for (let i = 0; i < points.length; i++) {
        const [lat1, lng1] = points[i];
        const [lat2, lng2] = points[(i + 1) % points.length];
        area += (lng2 - lng1) * (2 + Math.sin(lat1) + Math.sin(lat2));
      }

      return Math.abs(area * earthRadiusMeters ** 2 / 2) / 10000;
    }

    function formatFeatureAreaHectares(feature) {
      const areaHectares = calculateFeatureAreaHectares(feature);
      return areaHectares === null ? 'N/A' : `${areaHectares.toFixed(2)} ha`;
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
        feature,
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
    zones.forEach((z, i) => {
      const div = document.createElement('div');
      div.className = `zone-row`;
      div.setAttribute('onclick', `flyTo(${i})`);
      div.innerHTML = `<div class="z-pip" style="background:${z.color}"></div><div class="z-name">${z.name}</div><div class="z-ha">${z.area.replace(/[^0-9k]/g, '')}</div><span class="z-chip ${z.chip || 'ca'}">${z.status}</span>`;
      zoneContainer.appendChild(div);
    });

    const quickJumpInput = document.getElementById('quickJumpInput');
    const quickJumpResults = document.getElementById('quickJumpResults');

    function renderQuickJumpResults(query = '') {
      if (!quickJumpResults) return;
      const normalizedQuery = query.trim().toLocaleLowerCase();
      const matches = zones
        .map((zone, index) => ({ zone, index }))
        .filter(({ zone }) => !normalizedQuery || `${zone.name} ${zone.genus} ${zone.status}`.toLocaleLowerCase().includes(normalizedQuery));

      quickJumpResults.replaceChildren();
      if (matches.length === 0) {
        const emptyMessage = document.createElement('div');
        emptyMessage.className = 'quick-jump-empty';
        emptyMessage.textContent = zones.length ? 'No matching saved areas' : 'No saved areas available';
        quickJumpResults.appendChild(emptyMessage);
      } else {
        matches.slice(0, 8).forEach(({ zone, index }) => {
          const result = document.createElement('button');
          result.type = 'button';
          result.className = 'quick-jump-result';

          const heading = document.createElement('span');
          heading.className = 'quick-jump-result-heading';
          const locationIcon = document.createElement('i');
          locationIcon.className = 'bi bi-geo-alt-fill';
          locationIcon.setAttribute('aria-hidden', 'true');
          const name = document.createElement('span');
          name.className = 'quick-jump-result-name';
          name.textContent = zone.name;
          heading.append(locationIcon, name);
          const meta = document.createElement('span');
          meta.className = 'quick-jump-result-meta';
          meta.textContent = `Mangrove Area: ${formatFeatureAreaHectares(zone.feature)}`;

          result.append(heading, meta);
          result.addEventListener('click', () => {
            selectZone(index);
            quickJumpInput.value = zone.name;
            quickJumpResults.hidden = true;
            quickJumpInput.setAttribute('aria-expanded', 'false');
          });
          quickJumpResults.appendChild(result);
        });
      }
      quickJumpResults.hidden = false;
      quickJumpInput?.setAttribute('aria-expanded', 'true');
    }

    if (quickJumpInput && quickJumpResults) {
      quickJumpInput.placeholder = zones.length
        ? 'Search Mangrove areas'
        : 'No saved areas available';
      quickJumpInput.disabled = zones.length === 0;
      quickJumpInput.addEventListener('focus', () => renderQuickJumpResults(quickJumpInput.value));
      quickJumpInput.addEventListener('input', () => renderQuickJumpResults(quickJumpInput.value));
      quickJumpInput.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
          quickJumpResults.hidden = true;
          quickJumpInput.setAttribute('aria-expanded', 'false');
        }
      });
      document.addEventListener('click', event => {
        if (!event.target.closest('#mapQuickJump')) {
          quickJumpResults.hidden = true;
          quickJumpInput.setAttribute('aria-expanded', 'false');
        }
      });
    }

    @include('users.partials.map-base-scripts')
    const snapPixelThreshold = 15; // pixels
    let snapMarker = L.circleMarker([0, 0], {
      radius: 8,
      color: '#1e9e62',
      weight: 2,
      fillColor: '#1e9e62',
      fillOpacity: 0.35,
      opacity: 0
    }).addTo(mainMap);

    // Keep overlay layers in dedicated groups so redraws are cheap.
    // This avoids expensive mainMap.eachLayer() scans/removals which can get laggy fast.
    const zoneMarkerLayer = L.layerGroup().addTo(mainMap);
    const delineationLayer = L.layerGroup().addTo(mainMap);
    const vertexLayer = L.layerGroup().addTo(mainMap);

    let polys = [];
    zones.forEach((z, i) => {
      const locationIcon = L.divIcon({
        className: '',
        html: `<span style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;color:#fff;background:${z.color};border:2px solid #fff;border-radius:50% 50% 50% 0;box-shadow:0 2px 8px rgba(0,0,0,.35);transform:rotate(-45deg);font-size:16px;"><i class="bi bi-geo-alt-fill" aria-hidden="true" style="transform:rotate(45deg);"></i></span>`,
        iconSize: [30, 30],
        iconAnchor: [15, 29],
      });
      const p = L.marker([z.lat, z.lng], { icon: locationIcon }).addTo(zoneMarkerLayer);

      p.bindPopup(`<div><b>${z.name}</b><br>Area: ${z.area}<br>NDVI: ${z.ndvi}<br>Status: ${z.status}</div>`);
      p.bindTooltip(`${z.name} · Mangrove location`, { direction: 'top', offset: [0, -20] });
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
    const lineOnlyDelineation = document.body.dataset.page === 'delineate';
    let isDrawing = false;
    let currentFeature = null;
    let drawnFeatures = [];
    let featureHistory = [];
    let historyIndex = -1;
    let currentSelectedZoneIndex = -1;
    let selectedDrawnIndex = -1;
    let rejectedRevisionIndex = -1;
    let rejectedRevisionFeatureIndexes = [];
    let rejectedRevisionOriginalFeatures = [];
    let isEditingRejectedRevision = false;
    let rejectedRevisionControlStates = new Map();
    // Editing the boundary of an existing saved delineation (updates the same record in place).
    let boundaryEdit = null; // { delineationId, featureIndexes, originalFeatures }
    let selectedEditVertex = null; // { featureIndex, vertexIndex }
    let editVertexHandles = []; // [{ featureIndex, vertexIndex, handle }] for the vertices currently rendered

    function isGeometryEditLocked() {
      return isEditingRejectedRevision || boundaryEdit !== null;
    }

    function loadSavedDelineations() {
      if (!Array.isArray(savedDelineations) || savedDelineations.length === 0) {
        return;
      }

      savedDelineations.forEach(record => {
        if (Array.isArray(record.features)) {
          record.features.forEach(feature => {
            drawnFeatures.push({
              ...feature,
              location: feature.location || null,
              location_status: feature.location_status || null,
              location_details: feature.location_details || null,
              genera: Array.isArray(feature.genera) ? feature.genera : [],
              label: record.name,
              notes: record.notes,
              planting_recommendation: record.planting_recommendation || null,
              approved_by_name: record.approved_by_name || null,
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
          location: feature.location || null,
          location_status: feature.location_status || null,
          location_details: feature.location_details || null,
          genera: Array.isArray(feature.genera) ? feature.genera : [],
          label: record.name,
          notes: record.notes,
          planting_recommendation: record.planting_recommendation || null,
          approved_by_name: record.approved_by_name || null,
          delineation_id: record.id,
          is_approved: !!record.is_approved,
          is_rejected: !!record.is_rejected,
          rejection_notes: record.rejection_notes || null,
          last_revised_by_name: record.last_revised_by_name || null,
          last_revised_at: record.last_revised_at || null,
          is_own: !!options.is_own,
          created_by: options.created_by || record.user?.name || authUserName || 'Unknown user',
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

    function openRightPanel() {
      const panel = document.getElementById('mapRightPanel');
      const backdrop = document.getElementById('panelBackdrop');
      const headerSub = document.getElementById('panelHeaderSub');

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
      if (rejectedRevisionIndex >= 0) {
        cancelRejectedDelineationRevision(false);
      }
      if (boundaryEdit) {
        if (!confirmDiscardBoundaryEdit()) return;
        cancelBoundaryEdit(false);
      }
      const panel = document.getElementById('mapRightPanel');
      const backdrop = document.getElementById('panelBackdrop');
      if (panel) {
        panel.classList.remove('open', 'peek', 'expanded');
      }
      if (backdrop) backdrop.classList.remove('show');

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
      if (rejectedRevisionIndex >= 0) {
        cancelRejectedDelineationRevision(false);
      }
      if (boundaryEdit) {
        if (!confirmDiscardBoundaryEdit()) return;
        cancelBoundaryEdit(false);
      }
      document.getElementById('boundaryEditActions')?.style.setProperty('display', 'none');
      currentSelectedZoneIndex = i;
      let z = zones[i];
      if (quickJumpInput && z) quickJumpInput.value = z.name;
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
      loadAnnualObservationCharts(z.id);

      const removeBtn = document.getElementById('removeDelineationBtn');
      if (removeBtn) {
        removeBtn.style.display = 'none';
      }

      openRightPanel();
      selectedDrawnIndex = -1;
      document.getElementById('delineationInfoCard').style.display = 'none';
      document.getElementById('zoneDetailsContent').style.display = 'block';
      document.getElementById('expertReviewActions')?.style.setProperty('display', 'none');
      resetExpertRejectionPrompt();
      document.getElementById('delineationSubmitterRow')?.style.setProperty('display', 'none');
      const feature = z.feature;
      if (feature?.type === 'point' && Array.isArray(feature.coords)) {
        mainMap.flyTo([z.lat, z.lng], Math.max(mainMap.getZoom(), 16), { duration: 0.8 });
      } else {
        const points = extractPoints(feature?.coords)
          .filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1]));
        if (points.length > 0) {
          const bounds = L.latLngBounds(points.map(point => L.latLng(point[0], point[1])));
          mainMap.flyToBounds(bounds, { padding: [50, 50], maxZoom: 17, duration: 0.8 });
        } else {
          mainMap.flyTo([z.lat, z.lng], Math.max(mainMap.getZoom(), 14), { duration: 0.8 });
        }
      }
      polys[i].openPopup();
    }

    function selectDrawnFeature(i) {
      if (rejectedRevisionIndex >= 0 && i !== rejectedRevisionIndex) {
        const revisionFeature = drawnFeatures[rejectedRevisionIndex];
        const nextFeature = drawnFeatures[i];
        if (!nextFeature || Number(nextFeature.delineation_id) !== Number(revisionFeature?.delineation_id)) {
          cancelRejectedDelineationRevision(false);
        }
      }
      if (boundaryEdit && Number(drawnFeatures[i]?.delineation_id) !== Number(boundaryEdit.delineationId)) {
        if (!confirmDiscardBoundaryEdit()) return;
        cancelBoundaryEdit(false);
      }
      const feature = drawnFeatures[i];
      if (!feature) return;
      selectedDrawnIndex = i;
      resetExpertRejectionPrompt();

      openRightPanel();
      loadAnnualObservationCharts(feature.delineation_id);

      document.getElementById('delineationInfoCard').style.display = 'block';
      document.getElementById('zoneDetailsContent').style.display = 'none';

      const showResidentEditors = isExpertUser || !feature.is_approved;
      const genusEditor = document.getElementById('delineationGenusEditor');
      const generaRow = document.querySelector('.d-row-genera');
      const notesEditor = document.getElementById('delineationNotesEditor');
      if (genusEditor) genusEditor.style.display = showResidentEditors ? '' : 'none';
      if (generaRow) generaRow.style.display = feature.delineation_id ? '' : 'none';
      if (notesEditor) notesEditor.style.display = showResidentEditors ? '' : 'none';

      const removeBtn = document.getElementById('removeDelineationBtn');
      if (removeBtn) {
        removeBtn.style.display = (feature.is_own && !feature.is_approved && !feature.is_editing_revision && !feature.is_editing_boundary) ? 'block' : 'none';
      }
      updateBoundaryEditActions(feature);
      const lastEditedRow = document.getElementById('delineationLastEditedRow');
      if (lastEditedRow) {
        const editedBy = feature.last_revised_by_name;
        lastEditedRow.style.display = feature.delineation_id && editedBy ? '' : 'none';
        const editedAt = feature.last_revised_at ? new Date(feature.last_revised_at) : null;
        document.getElementById('delineationLastEditedBy').textContent = editedBy
          ? (editedAt && !isNaN(editedAt) ? `${editedBy} (${editedAt.toLocaleDateString()})` : editedBy)
          : '-';
      }
      const revisionActions = document.getElementById('rejectedRevisionActions');
      if (revisionActions) {
        const canRevise = feature.is_own && feature.is_rejected && !feature.is_approved;
        revisionActions.style.display = canRevise ? 'block' : 'none';
        document.getElementById('startRejectedRevisionBtn').style.display = canRevise && !feature.is_editing_revision ? 'block' : 'none';
        document.getElementById('saveRejectedRevisionBtn').style.display = feature.is_editing_revision ? 'block' : 'none';
        document.getElementById('cancelRejectedRevisionBtn').style.display = feature.is_editing_revision ? 'block' : 'none';
      }

      document.getElementById('delineationFeatureType').textContent = feature.type || '-';
      document.getElementById('delineationFeatureArea').textContent = formatFeatureAreaHectares(feature);
      const locationEl = document.getElementById('delineationFeatureLocation');
      if (locationEl) {
        updateFeatureLocationDisplay(feature, i);
      }
      document.getElementById('delineationNotes').value = feature.notes || '';

      // Mangrove Genus Present
      currentSelectedGenera = Array.isArray(feature.genera) ? [...feature.genera] : [];
      renderDelineationGenera(currentSelectedGenera);

      const createdByEl = document.getElementById('delineationCreatedBy');
      const statusEl = document.getElementById('delineationReviewStatus');
      const rejectionBox = document.getElementById('delineationRejectionBox');
      const rejectionNotes = document.getElementById('delineationRejectionNotes');

      const delineatedBy = feature.created_by || feature.submitted_by || feature.user?.name || authUserName || 'Unknown user';
      if (createdByEl) {
        createdByEl.textContent = delineatedBy;
      }

      let statusLabel = 'Pending review';
      let statusClass = 'ca';
      if (feature.is_rejected) {
        statusLabel = 'Rejected';
        statusClass = 'cr';
      } else if (feature.is_approved) {
        const approverName = feature.approved_by_name || feature.approved_by || feature.created_by || feature.submitted_by;
        statusLabel = feature.is_own ? 'Approved' : (approverName ? `Approved (${approverName})` : 'Approved');
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

    function finishRejectedDelineationRevision() {
      rejectedRevisionIndex = -1;
      rejectedRevisionFeatureIndexes = [];
      rejectedRevisionOriginalFeatures = [];
      isEditingRejectedRevision = false;
      rejectedRevisionControlStates.forEach((wasDisabled, control) => {
        control.disabled = wasDisabled;
      });
      rejectedRevisionControlStates.clear();
      document.getElementById('rejectedRevisionActions')?.style.setProperty('display', 'none');
    }

    function setRejectedRevisionControlsLocked(locked) {
      const controls = document.querySelectorAll('#drawTypeSelect, .draw-btn, #undoBtn, #redoBtn, #saveBtn, #editModeBtn');
      if (locked) {
        rejectedRevisionControlStates = new Map();
        controls.forEach(control => {
          rejectedRevisionControlStates.set(control, control.disabled);
          control.disabled = true;
        });
        return;
      }

      rejectedRevisionControlStates.forEach((wasDisabled, control) => {
        control.disabled = wasDisabled;
      });
      rejectedRevisionControlStates.clear();
    }

    function cancelRejectedDelineationRevision(restoreSelection = true) {
      if (rejectedRevisionIndex < 0 || !rejectedRevisionOriginalFeatures.length) return;
      rejectedRevisionFeatureIndexes.forEach((featureIndex, snapshotIndex) => {
        drawnFeatures[featureIndex] = rejectedRevisionOriginalFeatures[snapshotIndex];
      });
      const selectedIndex = selectedDrawnIndex;
      finishRejectedDelineationRevision();
      redrawFeatures();
      if (restoreSelection && selectedDrawnIndex === selectedIndex) {
        selectDrawnFeature(selectedIndex);
      }
    }

    function beginRejectedDelineationRevision() {
      const feature = drawnFeatures[selectedDrawnIndex];
      if (!feature?.is_own || !feature.is_rejected || feature.is_approved) return;

      rejectedRevisionIndex = selectedDrawnIndex;
      rejectedRevisionFeatureIndexes = drawnFeatures
        .map((item, index) => Number(item.delineation_id) === Number(feature.delineation_id) ? index : -1)
        .filter(index => index >= 0);
      rejectedRevisionOriginalFeatures = rejectedRevisionFeatureIndexes
        .map(index => JSON.parse(JSON.stringify(drawnFeatures[index])));
      isEditingRejectedRevision = true;
      rejectedRevisionFeatureIndexes.forEach(index => {
        drawnFeatures[index].is_editing_revision = true;
      });
      setRejectedRevisionControlsLocked(true);
      const drawTypeSelect = document.getElementById('drawTypeSelect');
      if (drawTypeSelect) drawTypeSelect.value = '';
      drawingMode = null;
      document.querySelectorAll('.draw-btn').forEach(button => button.classList.remove('active'));
      redrawFeatures();
      selectDrawnFeature(selectedDrawnIndex);
    }

    async function submitRejectedDelineationRevision() {
      const feature = drawnFeatures[rejectedRevisionIndex];
      const selectedFeature = drawnFeatures[selectedDrawnIndex];
      if (!feature || !isEditingRejectedRevision) return;

      const notes = document.getElementById('delineationNotes')?.value.trim() || '';
      if (!notes) {
        alert('Please fill in Notes before resubmitting.');
        document.getElementById('delineationNotes')?.focus();
        return;
      }

      const delineationFeatures = drawnFeatures
        .filter(item => Number(item.delineation_id) === Number(feature.delineation_id))
        .map(item => ({
          type: item.type,
          coords: item.coords,
          genera: item === selectedFeature ? currentSelectedGenera : (Array.isArray(item.genera) ? item.genera : []),
        }));

      showProcessingModal('Submitting revision...', 'Saving the revised boundary and sending it for expert review.');
      try {
        const response = await fetch(reviseDelineationUrlTemplate.replace('__DELINEATION_ID__', encodeURIComponent(feature.delineation_id)), {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({
            features: delineationFeatures,
            name: feature.location?.trim() || feature.label?.trim() || 'Saved delineation',
            notes,
          }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.delineation) {
          throw new Error(payload.message || 'Unable to submit the revised delineation.');
        }

        const relatedFeatures = drawnFeatures.filter(item => Number(item.delineation_id) === Number(feature.delineation_id));
        (payload.delineation.features || []).forEach((savedFeature, index) => {
          const target = relatedFeatures[index];
          if (!target) return;
          Object.assign(target, savedFeature, {
            label: payload.delineation.name,
            notes: payload.delineation.notes,
            is_approved: false,
            is_rejected: false,
            rejection_notes: null,
          });
          delete target.is_editing_revision;
        });

        const selectedIndex = rejectedRevisionIndex;
        const activeIndex = selectedDrawnIndex;
        finishRejectedDelineationRevision();
        redrawFeatures();
        selectDrawnFeature(activeIndex >= 0 ? activeIndex : selectedIndex);
        hideProcessingModal();
        showSuccessModal(payload.message || 'Revised delineation submitted for expert review.');
      } catch (error) {
        hideProcessingModal();
        alert(error.message || 'Unable to submit the revised delineation. Please try again.');
      }
    }

    document.getElementById('startRejectedRevisionBtn')?.addEventListener('click', beginRejectedDelineationRevision);
    document.getElementById('saveRejectedRevisionBtn')?.addEventListener('click', submitRejectedDelineationRevision);
    document.getElementById('cancelRejectedRevisionBtn')?.addEventListener('click', cancelRejectedDelineationRevision);

    // ---- Edit an existing saved delineation's boundary (OSM-style vertex editing) ----
    function canEditDelineationBoundary(feature) {
      if (!feature?.delineation_id) return false;
      if (isExpertUser) return true; // server also enforces: own records + resident records
      return !!feature.is_own && !feature.is_approved && !feature.is_rejected;
    }

    function updateBoundaryEditActions(feature) {
      const actions = document.getElementById('boundaryEditActions');
      if (!actions) return;
      const editing = !!(boundaryEdit && feature && Number(feature.delineation_id) === Number(boundaryEdit.delineationId));
      const canEdit = editing || (!isEditingRejectedRevision && !boundaryEdit && canEditDelineationBoundary(feature));
      actions.style.display = canEdit ? 'block' : 'none';
      document.getElementById('startBoundaryEditBtn').style.display = canEdit && !editing ? 'block' : 'none';
      document.getElementById('boundaryEditHint').style.display = editing ? 'block' : 'none';
      document.getElementById('saveBoundaryEditBtn').style.display = editing ? 'block' : 'none';
      document.getElementById('cancelBoundaryEditBtn').style.display = editing ? 'block' : 'none';
      const deleteVertexBtn = document.getElementById('deleteBoundaryVertexBtn');
      deleteVertexBtn.style.display = editing ? 'block' : 'none';
      deleteVertexBtn.disabled = !(editing && selectedEditVertex);
    }

    function boundaryEditHasChanges() {
      if (!boundaryEdit) return false;
      return boundaryEdit.featureIndexes.some((featureIndex, snapshotIndex) =>
        JSON.stringify(drawnFeatures[featureIndex]?.coords) !== JSON.stringify(boundaryEdit.originalFeatures[snapshotIndex]?.coords));
    }

    function confirmDiscardBoundaryEdit() {
      return !boundaryEditHasChanges() || confirm('Discard your unsaved boundary changes?');
    }

    function finishBoundaryEdit() {
      const previousDrawingMode = boundaryEdit?.previousDrawingMode || null;
      boundaryEdit = null;
      selectedEditVertex = null;
      setRejectedRevisionControlsLocked(false);
      // Restore the drawing mode that was active before editing (e.g. line mode on the Delineate page).
      if (document.getElementById('delineationToolbar')?.classList.contains('active')) {
        drawingMode = previousDrawingMode;
        const drawTypeSelect = document.getElementById('drawTypeSelect');
        if (drawTypeSelect) drawTypeSelect.value = previousDrawingMode || '';
        document.querySelectorAll('.draw-btn[data-mode]').forEach(button =>
          button.classList.toggle('active', button.dataset.mode === previousDrawingMode));
      }
      updateHistoryButtons();
    }

    function beginBoundaryEdit() {
      const feature = drawnFeatures[selectedDrawnIndex];
      if (boundaryEdit || isEditingRejectedRevision || !canEditDelineationBoundary(feature)) return;

      const featureIndexes = drawnFeatures
        .map((item, index) => Number(item.delineation_id) === Number(feature.delineation_id) ? index : -1)
        .filter(index => index >= 0);
      boundaryEdit = {
        delineationId: feature.delineation_id,
        featureIndexes,
        originalFeatures: featureIndexes.map(index => JSON.parse(JSON.stringify(drawnFeatures[index]))),
        previousDrawingMode: drawingMode,
      };
      selectedEditVertex = null;
      featureIndexes.forEach(index => { drawnFeatures[index].is_editing_boundary = true; });

      // Stop any drawing in progress so map clicks don't add new features while editing.
      currentFeature = null;
      drawingMode = null;
      const drawTypeSelect = document.getElementById('drawTypeSelect');
      if (drawTypeSelect) drawTypeSelect.value = '';
      document.querySelectorAll('.draw-btn[data-mode]').forEach(button => button.classList.remove('active'));
      setRejectedRevisionControlsLocked(true);

      redrawFeatures();
      selectDrawnFeature(selectedDrawnIndex);
    }

    function cancelBoundaryEdit(restoreSelection = true) {
      if (!boundaryEdit) return;
      boundaryEdit.featureIndexes.forEach((featureIndex, snapshotIndex) => {
        drawnFeatures[featureIndex] = boundaryEdit.originalFeatures[snapshotIndex];
      });
      const selectedIndex = selectedDrawnIndex;
      finishBoundaryEdit();
      redrawFeatures();
      if (restoreSelection && selectedIndex >= 0) {
        selectDrawnFeature(selectedIndex);
      }
    }

    async function saveBoundaryEdit() {
      if (!boundaryEdit) return;
      const { delineationId, featureIndexes } = boundaryEdit;
      const selectedFeature = drawnFeatures[selectedDrawnIndex];
      const editedFeatures = featureIndexes.map(index => drawnFeatures[index]);

      if (!boundaryEditHasChanges()) {
        cancelBoundaryEdit();
        return;
      }

      const saveBtn = document.getElementById('saveBoundaryEditBtn');
      if (saveBtn) saveBtn.disabled = true;
      showProcessingModal('Saving changes...', 'Updating the saved delineation boundary.');
      try {
        const response = await fetch(updateBoundaryUrlTemplate.replace('__DELINEATION_ID__', encodeURIComponent(delineationId)), {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({
            features: editedFeatures.map(item => ({
              type: item.type,
              coords: item.coords,
              genera: item === selectedFeature ? currentSelectedGenera : (Array.isArray(item.genera) ? item.genera : []),
            })),
          }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.delineation) {
          throw new Error(payload.message || 'Unable to save the boundary changes.');
        }

        (payload.delineation.features || []).forEach((savedFeature, index) => {
          const target = editedFeatures[index];
          if (!target) return;
          Object.assign(target, savedFeature, {
            location_status: savedFeature.location_status || (savedFeature.location ? 'matched' : null),
            last_revised_by_name: payload.delineation.last_revised_by_name || authUserName,
            last_revised_at: payload.delineation.last_revised_at || new Date().toISOString(),
          });
        });
        editedFeatures.forEach(target => { delete target.is_editing_boundary; });

        const activeIndex = selectedDrawnIndex;
        finishBoundaryEdit();
        redrawFeatures();
        if (activeIndex >= 0) selectDrawnFeature(activeIndex);
        hideProcessingModal();
        showSuccessModal(payload.message || 'Delineation boundary updated.', 'Changes saved');
      } catch (error) {
        hideProcessingModal();
        alert(error.message || 'Unable to save the boundary changes. Please try again.');
      } finally {
        if (saveBtn) saveBtn.disabled = false;
      }
    }

    function deleteSelectedEditVertex() {
      if (!selectedEditVertex) return;
      const { featureIndex, vertexIndex } = selectedEditVertex;
      const feature = drawnFeatures[featureIndex];
      if (feature) deleteEditableVertex(feature, featureIndex, vertexIndex);
    }

    document.getElementById('startBoundaryEditBtn')?.addEventListener('click', beginBoundaryEdit);
    document.getElementById('saveBoundaryEditBtn')?.addEventListener('click', saveBoundaryEdit);
    document.getElementById('cancelBoundaryEditBtn')?.addEventListener('click', () => cancelBoundaryEdit());
    document.getElementById('deleteBoundaryVertexBtn')?.addEventListener('click', deleteSelectedEditVertex);
    document.addEventListener('keydown', (event) => {
      if (!selectedEditVertex || !isGeometryEditLocked()) return;
      if (event.key !== 'Delete' && event.key !== 'Backspace') return;
      const tag = (event.target?.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target?.isContentEditable) return;
      event.preventDefault();
      deleteSelectedEditVertex();
    });

    // Vertex helpers shared by all editable line/area geometry.
    function editableRingInfo(feature) {
      const coords = Array.isArray(feature.coords) ? feature.coords : [];
      const first = coords[0];
      const last = coords[coords.length - 1];
      const closed = feature.type === 'area' && coords.length > 3 && first && last
        && Number(first[0]) === Number(last[0]) && Number(first[1]) === Number(last[1]);
      return { ring: closed ? coords.slice(0, -1) : coords.slice(), closed };
    }

    function commitEditableRing(feature, ring, closed) {
      feature.coords = closed && ring.length ? [...ring, [ring[0][0], ring[0][1]]] : ring;
    }

    function afterEditableGeometryChange(feature, featureIndex) {
      delete feature.location;
      delete feature.location_status;
      delete feature.location_details;
      saveDrawingStateToHistory();
      redrawFeatures();
      if (featureIndex === selectedDrawnIndex) {
        document.getElementById('delineationFeatureArea').textContent = formatFeatureAreaHectares(feature);
        updateFeatureLocationDisplay(feature, featureIndex);
      }
      updateBoundaryEditActions(drawnFeatures[selectedDrawnIndex]);
    }

    function refreshSelectedVertexStyles() {
      editVertexHandles.forEach(({ featureIndex, vertexIndex, handle }) => {
        const selected = !!selectedEditVertex
          && selectedEditVertex.featureIndex === featureIndex && selectedEditVertex.vertexIndex === vertexIndex;
        handle.getElement()?.classList.toggle('is-selected', selected);
      });
      updateBoundaryEditActions(drawnFeatures[selectedDrawnIndex]);
    }

    function deleteEditableVertex(feature, featureIndex, vertexIndex) {
      const { ring, closed } = editableRingInfo(feature);
      const minPoints = feature.type === 'area' ? 3 : 2;
      if (ring.length <= minPoints) {
        alert(feature.type === 'area'
          ? 'A polygon needs at least 3 points. Move this point instead of deleting it.'
          : 'A line needs at least 2 points.');
        return;
      }
      ring.splice(vertexIndex, 1);
      commitEditableRing(feature, ring, closed);
      selectedEditVertex = null;
      afterEditableGeometryChange(feature, featureIndex);
    }

    function renderEditableVertices(feature, featureIndex, shape) {
      const isArea = feature.type === 'area';
      const { ring, closed } = editableRingInfo(feature);
      const vertexIcon = (selected) => L.divIcon({
        className: 'delineation-vertex-handle' + (selected ? ' is-selected' : ''),
        html: '<div style="width:12px;height:12px;background:#1a73e8;border:2px solid #ffffff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,0.4);cursor:grab;"></div>',
        iconSize: [12, 12],
        iconAnchor: [6, 6]
      });
      const midpointIcon = L.divIcon({
        className: 'delineation-midpoint-handle',
        html: '<div style="width:10px;height:10px;background:rgba(255,255,255,0.75);border:2px solid #1a73e8;border-radius:50%;opacity:0.8;"></div>',
        iconSize: [10, 10],
        iconAnchor: [5, 5]
      });
      const refreshShape = () => {
        shape.setLatLngs(feature.coords);
        if (featureIndex === selectedDrawnIndex) {
          document.getElementById('delineationFeatureArea').textContent = formatFeatureAreaHectares(feature);
        }
      };

      // Existing points: drag to move, click to select, double-click / right-click to delete.
      ring.forEach((coord, vIdx) => {
        const isSelectedVertex = selectedEditVertex
          && selectedEditVertex.featureIndex === featureIndex && selectedEditVertex.vertexIndex === vIdx;
        const handle = L.marker(coord, {
          draggable: true,
          icon: vertexIcon(isSelectedVertex),
          zIndexOffset: 1000,
          title: 'Drag to move. Double-click or right-click to delete.',
        }).addTo(vertexLayer);
        editVertexHandles.push({ featureIndex, vertexIndex: vIdx, handle });
        handle.on('drag', (e) => {
          ring[vIdx] = [e.latlng.lat, e.latlng.lng];
          commitEditableRing(feature, ring, closed);
          refreshShape();
        });
        handle.on('dragend', () => {
          selectedEditVertex = { featureIndex, vertexIndex: vIdx };
          afterEditableGeometryChange(feature, featureIndex);
        });
        handle.on('click', (e) => {
          if (e?.originalEvent) L.DomEvent.stop(e.originalEvent);
          // Toggle selection without re-rendering so a following double-click still hits this handle.
          const alreadySelected = selectedEditVertex
            && selectedEditVertex.featureIndex === featureIndex && selectedEditVertex.vertexIndex === vIdx;
          selectedEditVertex = alreadySelected ? null : { featureIndex, vertexIndex: vIdx };
          refreshSelectedVertexStyles();
        });
        const removeHandler = (e) => {
          if (e?.originalEvent) L.DomEvent.stop(e.originalEvent);
          deleteEditableVertex(feature, featureIndex, vIdx);
        };
        handle.on('dblclick', removeHandler);
        handle.on('contextmenu', removeHandler);
      });

      // Midpoints between points: drag (or click) to insert a new point on that edge.
      const segmentCount = isArea ? ring.length : ring.length - 1;
      for (let sIdx = 0; sIdx < segmentCount; sIdx++) {
        const a = ring[sIdx];
        const b = ring[(sIdx + 1) % ring.length];
        if (!a || !b) continue;
        const mid = [(Number(a[0]) + Number(b[0])) / 2, (Number(a[1]) + Number(b[1])) / 2];
        const insertAt = sIdx + 1;
        let inserted = false;
        const midHandle = L.marker(mid, {
          draggable: true,
          icon: midpointIcon,
          title: 'Drag or click to add a point here.',
        }).addTo(vertexLayer);
        midHandle.on('dragstart', () => {
          ring.splice(insertAt, 0, mid);
          inserted = true;
          commitEditableRing(feature, ring, closed);
        });
        midHandle.on('drag', (e) => {
          if (!inserted) return;
          ring[insertAt] = [e.latlng.lat, e.latlng.lng];
          commitEditableRing(feature, ring, closed);
          refreshShape();
        });
        midHandle.on('dragend', () => {
          selectedEditVertex = { featureIndex, vertexIndex: insertAt };
          afterEditableGeometryChange(feature, featureIndex);
        });
        midHandle.on('click', (e) => {
          if (e?.originalEvent) L.DomEvent.stop(e.originalEvent);
          if (inserted) return;
          ring.splice(insertAt, 0, mid);
          commitEditableRing(feature, ring, closed);
          selectedEditVertex = { featureIndex, vertexIndex: insertAt };
          afterEditableGeometryChange(feature, featureIndex);
        });
      }
    }

    function validateDelineationMeta() {
      const notesEl = document.getElementById('delineationNotes');
      const notes = notesEl?.value.trim();
      const notesValid = Boolean(notes);

      notesEl?.classList.toggle('input-error', !notesValid);

      if (!notesValid) {
        alert('Please fill in Notes before saving.');
        notesEl?.focus();
        return null;
      }

      const selectedFeature = drawnFeatures[selectedDrawnIndex];
      return {
        name: selectedFeature?.location?.trim() || selectedFeature?.label?.trim() || 'Saved delineation',
        notes,
        genera: [...currentSelectedGenera]
      };
    }

    function saveDelineationMeta() {
      if (selectedDrawnIndex < 0) return;
      const meta = validateDelineationMeta();
      if (!meta) return;

      const feature = drawnFeatures[selectedDrawnIndex];
      feature.label = meta.name;
      feature.notes = meta.notes;
      feature.genera = meta.genera;
      renderDelineationGenera(meta.genera);
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
      if (isGeometryEditLocked()) return;
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
      const isDisabled = isGeometryEditLocked() || !hasUnsaved;
      saveBtn.disabled = isDisabled;
      saveBtn.style.opacity = isDisabled ? '0.4' : '1';
      saveBtn.style.cursor = isDisabled ? 'not-allowed' : 'pointer';
      saveBtn.classList.toggle('disabled', isDisabled);
    }

    function updateHistoryButtons() {
      const undoBtn = document.getElementById('undoBtn');
      const redoBtn = document.getElementById('redoBtn');

      undoBtn.disabled = isGeometryEditLocked() || historyIndex <= 0;
      redoBtn.disabled = isGeometryEditLocked() || historyIndex >= featureHistory.length - 1;

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
          drawTypeSelect.value = lineOnlyDelineation ? 'line' : '';
          drawingMode = lineOnlyDelineation ? 'line' : null;
          drawModeButtons.forEach(btn => btn.classList.remove('active'));
        } else {
          drawingMode = lineOnlyDelineation ? 'line' : null;
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
        if (selectedDrawnIndex >= 0) {
          if (selectedDrawnIndex < drawnFeatures.length) {
            selectDrawnFeature(selectedDrawnIndex);
          } else if (drawnFeatures.length > 0) {
            selectDrawnFeature(drawnFeatures.length - 1);
          } else {
            selectedDrawnIndex = -1;
            document.getElementById('delineationInfoCard').style.display = 'none';
          }
        }
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
        if (selectedDrawnIndex >= 0) {
          if (selectedDrawnIndex < drawnFeatures.length) {
            selectDrawnFeature(selectedDrawnIndex);
          } else if (drawnFeatures.length > 0) {
            selectDrawnFeature(drawnFeatures.length - 1);
          }
        }
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
            genera: meta.genera,
          })
        });

        const payload = await response.json();
        if (!response.ok) {
          throw new Error(payload.message || 'Unable to save delineation.');
        }

        if (payload.delineation) {
          newFeatures.forEach((feature, index) => {
            const savedFeature = payload.delineation.features?.[index] || {};
            feature.delineation_id = payload.delineation.id;
            feature.is_approved = !!payload.delineation.is_approved;
            feature.is_rejected = !!payload.delineation.is_rejected;
            feature.label = payload.delineation.name;
            feature.notes = payload.delineation.notes;
            feature.genera = meta.genera;
            feature.location = savedFeature.location || null;
            feature.location_status = savedFeature.location_status || 'pending';
            feature.location_details = savedFeature.location_details || null;
          });
          const generaRow = document.querySelector('.d-row-genera');
          if (generaRow && drawnFeatures[selectedDrawnIndex]?.delineation_id) {
            generaRow.style.display = '';
          }
          redrawFeatures();
          if (selectedDrawnIndex >= 0 && drawnFeatures[selectedDrawnIndex]) {
            updateFeatureLocationDisplay(drawnFeatures[selectedDrawnIndex]);
          }
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

    document.getElementById('saveBtn')?.addEventListener('click', () => {
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

    function resetExpertRejectionPrompt() {
      const section = document.getElementById('expertRejectionNotesSection');
      const notes = document.getElementById('expertRejectionNotes');
      const rejectButton = document.getElementById('expertRejectBtn');
      if (section) section.style.display = 'none';
      if (notes) notes.value = '';
      if (rejectButton) rejectButton.innerHTML = '<i class="bi bi-x-circle"></i> Reject';
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
          body: JSON.stringify(action === 'reject'
            ? { rejection_notes: rejectionNotes }
            : {}),
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
    document.getElementById('expertRejectBtn')?.addEventListener('click', () => {
      const section = document.getElementById('expertRejectionNotesSection');
      if (section?.style.display === 'none') {
        section.style.display = 'block';
        document.getElementById('expertRejectBtn').innerHTML = '<i class="bi bi-x-circle"></i> Confirm reject';
        document.getElementById('expertRejectionNotes')?.focus();
        return;
      }
      submitExpertReview('reject');
    });

    function redrawFeatures() {
      // Clear only our overlay layers; keep the base tiles + controls intact.
      delineationLayer.clearLayers();
      vertexLayer.clearLayers();
      editVertexHandles = [];

      function getFeatureColor(f) {
        if (f.is_rejected) return '#d04030';
        if (f.is_approved) return '#1e9e62';
        if (!f.is_approved && !f.is_rejected) return '#c07818';
        return '#4ecdc4';
      }

      // Draw completed features
      drawnFeatures.forEach((f, i) => {
        const isSelected = i === selectedDrawnIndex;
        const color = isSelected ? '#1a73e8' : getFeatureColor(f);
        const isSelectedEditable = (isEditingRejectedRevision && f.is_editing_revision)
          || (boundaryEdit !== null && f.is_editing_boundary)
          || (isSelected && !f.delineation_id && document.getElementById('delineationToolbar')?.classList.contains('active'));

        if (f.type === 'point') {
          const marker = L.circleMarker(f.coords, {
            radius: 7,
            color,
            fillColor: color,
            fillOpacity: 0.85,
          }).addTo(delineationLayer);
          marker.on('click', (e) => {
            if (drawingMode && !lineOnlyDelineation) return;
            if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
            selectDrawnFeature(i);
          });
          if (isSelectedEditable) {
            const handle = L.marker(f.coords, {
              draggable: true,
              icon: L.divIcon({
                className: 'delineation-vertex-handle',
                html: '<div style="width:14px;height:14px;background:#1a73e8;border:2px solid #ffffff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,0.4);cursor:grab;"></div>',
                iconSize: [14, 14],
                iconAnchor: [7, 7]
              })
            }).addTo(vertexLayer);
            handle.on('drag', (e) => {
              f.coords = [e.latlng.lat, e.latlng.lng];
              marker.setLatLng(f.coords);
            });
            handle.on('dragend', () => {
              delete f.location;
              delete f.location_status;
              delete f.location_details;
              saveDrawingStateToHistory();
              redrawFeatures();
              updateFeatureLocationDisplay(f, i);
            });
          }
        } else if (f.type === 'line') {
          const line = L.polyline(f.coords, {
            color,
            weight: isSelected ? 4 : 3,
          }).addTo(delineationLayer);
          line.on('click', (e) => {
            if (drawingMode && !lineOnlyDelineation) return;
            if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
            selectDrawnFeature(i);
          });
          if (isSelectedEditable) {
            renderEditableVertices(f, i, line);
          } else if (document.getElementById('delineationToolbar')?.classList.contains('active')) {
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
            fillOpacity: isSelected ? 0.55 : 0.45,
            weight: isSelected ? 3 : 2,
          }).addTo(delineationLayer);
          polygon.on('click', (e) => {
            if (drawingMode && !lineOnlyDelineation) return;
            if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
            selectDrawnFeature(i);
          });
          if (isSelectedEditable) {
            renderEditableVertices(f, i, polygon);
          } else if (document.getElementById('delineationToolbar')?.classList.contains('active')) {
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
      if (!drawingMode || isGeometryEditLocked()) return;
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
            if (lineOnlyDelineation) {
              drawingMode = 'line';
            } else {
              drawingMode = null;
              mainMap.dragging.enable();
              document.querySelectorAll('.draw-btn').forEach(b => b.classList.remove('active'));
            }
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
    let genusDistributionChart = null;
    try {
      const pieCanvas = document.getElementById('pieC');
      if (pieCanvas && typeof Chart !== 'undefined') {
        genusDistributionChart = new Chart(pieCanvas, {
          type: 'doughnut',
          data: {
            labels: [],
            datasets: [{
              data: [],
              backgroundColor: ['#1e9e62', '#5ab8de', '#f4a840', '#a070e0', '#c0c8b8', '#d96c5f', '#80a84b'],
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

    let annualCoverageChart = null;
    try {
      const trendCanvas = document.getElementById('trendC');
      if (trendCanvas && typeof Chart !== 'undefined') {
        annualCoverageChart = new Chart(trendCanvas, {
          type: 'line',
          data: {
            labels: [],
            datasets: [{
              data: [],
              borderColor: '#1e9e62',
              backgroundColor: 'rgba(30,158,98,.12)',
              fill: true,
              tension: .35,
              pointRadius: 3,
              pointBackgroundColor: '#1e9e62',
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
                  callback: v => new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(v)
                }
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

    let annualObservationRequest = null;
    function setAnnualObservationEmptyState(message) {
      const genusEmpty = document.getElementById('genusDistributionEmpty');
      const coverageEmpty = document.getElementById('coverageTrendEmpty');
      const title = document.getElementById('genusDistributionTitle');
      if (title) title.textContent = 'Genus Distribution';
      if (genusEmpty) {
        genusEmpty.textContent = message || 'No verified genus data for this zone.';
        genusEmpty.style.display = 'block';
      }
      if (coverageEmpty) {
        coverageEmpty.textContent = message || 'No annual measurements for this zone.';
        coverageEmpty.style.display = 'block';
      }
      if (genusDistributionChart) {
        genusDistributionChart.data.labels = [];
        genusDistributionChart.data.datasets[0].data = [];
        genusDistributionChart.update();
      }
      if (annualCoverageChart) {
        annualCoverageChart.data.labels = [];
        annualCoverageChart.data.datasets[0].data = [];
        annualCoverageChart.update();
      }
    }

    function loadAnnualObservationCharts(delineationId) {
      annualObservationRequest?.abort();
      if (!delineationId) {
        setTemporalHistory([], 'empty');
        setAnnualObservationEmptyState(null);
        return;
      }
      setTemporalHistory([], 'loading');
      setAnnualObservationEmptyState(delineationId ? 'Loading verified observations...' : null);

      annualObservationRequest = new AbortController();
      fetch(`${annualObservationBaseUrl}/${encodeURIComponent(delineationId)}/annual-observations`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: annualObservationRequest.signal
      })
        .then(response => {
          if (!response.ok) throw new Error('Unable to load annual observations.');
          return response.json();
        })
        .then(data => {
          const coverage = Array.isArray(data.coverage) ? data.coverage : [];
          setTemporalHistory(coverage);
          const genusDistribution = Array.isArray(data.genusDistribution) ? data.genusDistribution : [];
          const genusEmpty = document.getElementById('genusDistributionEmpty');
          const coverageEmpty = document.getElementById('coverageTrendEmpty');
          const title = document.getElementById('genusDistributionTitle');

          if (annualCoverageChart) {
            annualCoverageChart.data.labels = coverage.map(item => String(item.year));
            annualCoverageChart.data.datasets[0].data = coverage.map(item => Number(item.coverageAreaHa));
            annualCoverageChart.update();
          }
          if (coverageEmpty) {
            coverageEmpty.textContent = coverage.length
              ? ''
              : 'No annual measurements have been entered for this zone.';
            coverageEmpty.style.display = coverage.length ? 'none' : 'block';
          }

          if (genusDistributionChart) {
            genusDistributionChart.data.labels = genusDistribution.map(item => item.label);
            genusDistributionChart.data.datasets[0].data = genusDistribution.map(item => Number(item.share));
            genusDistributionChart.update();
          }
          if (title) {
            title.textContent = data.genusYear
              ? `Genus Distribution (${data.genusYear})`
              : 'Genus Distribution';
          }
          if (genusEmpty) {
            genusEmpty.textContent = genusDistribution.length
              ? ''
              : 'No verified genus breakdown has been entered for this zone.';
            genusEmpty.style.display = genusDistribution.length ? 'none' : 'block';
          }
        })
        .catch(error => {
          if (error.name !== 'AbortError') {
            console.error(error);
            setAnnualObservationEmptyState('Annual observations could not be loaded.');
            setTemporalHistory([], 'error');
          }
        });
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

    // =========================================================
    // TEMPORAL MONITORING (CHANGES OVER TIME) TIMELINE CONTROLLER
    // =========================================================
    const sampleTemporalData = [
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

    let temporalData = [];
    let currentTimelineIndex = 0;
    let temporalDelineationGroup = null;

    function applyTimelineEpoch(index) {
      const item = temporalData[index];
      if (!item) return;

      currentTimelineIndex = index;

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

      const sourceEl = document.getElementById('timelineSourceStat');
      if (sourceEl) sourceEl.textContent = item.sourceText;

      renderTemporalObservation(item);
      updateTemporalEpochList();
    }

    function setTemporalHistory(observations, state = 'ready') {
      const timelineControl = document.getElementById('mapTimelineControl');
      const hasSelectedArea = state !== 'idle';
      if (timelineControl) timelineControl.style.display = hasSelectedArea ? 'block' : 'none';

      temporalData = observations.map((observation, index) => {
        const coverage = Number(observation.coverageAreaHa);
        const previousCoverage = index > 0 ? Number(observations[index - 1].coverageAreaHa) : null;
        const change = previousCoverage > 0 ? ((coverage - previousCoverage) / previousCoverage) * 100 : null;
        const changeText = change === null
          ? 'First recorded observation'
          : `${change > 0 ? '+' : ''}${change.toFixed(1)}% since ${observations[index - 1].year}`;

        return {
          year: String(observation.year),
          label: `${observation.year} observation`,
          areaFormatted: `${new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(coverage)} ha`,
          sourceText: observation.source || 'Not recorded',
          changeBadge: changeText,
          isNegative: change !== null && change < 0,
          features: Array.isArray(observation.features) ? observation.features : [],
        };
      });
      currentTimelineIndex = Math.max(temporalData.length - 1, 0);

      if (temporalData.length) {
        applyTimelineEpoch(currentTimelineIndex);
      } else {
        const badge = document.getElementById('timelineChangeBadge');
        const year = document.getElementById('timelineSelectedYear');
        const area = document.getElementById('timelineAreaStat');
        const source = document.getElementById('timelineSourceStat');
        if (badge) {
          badge.textContent = state === 'restricted'
            ? 'Expert history only'
            : state === 'loading' ? 'Loading history'
              : state === 'idle' ? 'Select an area'
                : state === 'error' ? 'History unavailable' : 'No annual history';
          badge.classList.remove('badge-negative');
        }
        if (year) year.textContent = state === 'loading'
          ? 'Loading...'
          : state === 'idle' ? 'Select an area'
            : state === 'error' ? 'Unable to load' : 'No observations';
        if (area) area.textContent = '-';
        if (source) source.textContent = '-';
        const epochList = document.getElementById('temporalEpochList');
        if (epochList) {
          const emptyState = document.createElement('div');
          emptyState.className = 'timeline-empty';
          emptyState.textContent = state === 'restricted'
            ? 'Annual observation history is available to experts only.'
            : state === 'loading' ? 'Loading annual observations...'
              : state === 'error' ? 'Annual observations could not be loaded.'
                : 'No annual observations have been recorded for this area.';
          epochList.replaceChildren(emptyState);
        }
        temporalDelineationGroup?.clearLayers();
      }
    }

    function renderTemporalObservation(item) {
      if (typeof mainMap === 'undefined' || !mainMap) return;
      if (!temporalDelineationGroup) {
        temporalDelineationGroup = L.layerGroup().addTo(mainMap);
      }
      temporalDelineationGroup.clearLayers();
      const boundsPoints = [];

      item.features.forEach(feature => {
        const points = extractPoints(feature.coords)
          .filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1]));
        if (points.length === 0) return;

        let layer;
        if (feature.type === 'point') {
          layer = L.circleMarker(points[0], {
            radius: 8,
            color: '#fff',
            weight: 2,
            fillColor: '#1e9e62',
            fillOpacity: 0.9,
          });
        } else if (feature.type === 'line') {
          layer = L.polyline(points, { color: '#1e9e62', weight: 4 });
        } else if (feature.type === 'area') {
          layer = L.polygon(points, { color: '#1e9e62', weight: 2, fillOpacity: 0.3 });
        }
        if (!layer) return;
        layer.addTo(temporalDelineationGroup);
        boundsPoints.push(...points);
      });

      if (boundsPoints.length === 1) {
        mainMap.flyTo(boundsPoints[0], Math.max(mainMap.getZoom(), 16), { duration: 0.6 });
      } else if (boundsPoints.length > 1) {
        mainMap.flyToBounds(L.latLngBounds(boundsPoints), { padding: [50, 50], maxZoom: 17, duration: 0.6 });
      }
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
      openRightPanel();

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
      container.replaceChildren();
      temporalData.forEach((item, index) => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = `temporal-epoch-row ${index === currentTimelineIndex ? 'active' : ''}`;
        row.setAttribute('aria-pressed', String(index === currentTimelineIndex));
        row.addEventListener('click', () => applyTimelineEpoch(index));
        row.addEventListener('keydown', event => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            applyTimelineEpoch(index);
          }
        });

        const year = document.createElement('span');
        year.className = 'epoch-year-badge';
        year.textContent = item.year;
        const content = document.createElement('span');
        content.className = 'epoch-content';
        const headline = document.createElement('span');
        headline.className = 'epoch-headline';
        const source = document.createElement('span');
        source.className = 'epoch-source';
        source.textContent = item.sourceText;
        const area = document.createElement('span');
        area.className = 'epoch-area';
        area.textContent = item.areaFormatted;
        const detail = document.createElement('span');
        detail.className = 'epoch-sub';
        detail.textContent = item.changeBadge;

        headline.append(source, area);
        content.append(headline, detail);
        row.append(year, content);
        container.appendChild(row);
      });
    }

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

    setTemporalHistory([], 'idle');


    if (APP_PAGE === 'delineate') {
      const toolbar = document.getElementById('delineationToolbar');
      if (toolbar && !toolbar.classList.contains('active') && typeof window.showEditMode === 'function') {
        window.showEditMode();
      }
    }
</script>
