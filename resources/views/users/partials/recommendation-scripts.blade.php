<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const recommendationsUrl = @json(route('expert.recommendations.store'));
    const resolveLocationUrl = @json(route('delineations.location-preview'));
    const suitabilityLabels = @json($suitabilityLevels);
    const referenceAreas = @json($referenceAreas);
    let recommendations = @json($recommendations);

    const SUITABILITY_COLORS = {
      highly_suitable: '#1e9e62',
      moderately_suitable: '#7cb342',
      marginally_suitable: '#c07818',
      not_suitable: '#d04030',
    };
    const REFERENCE_COLOR = '#7fa88a';
    const DRAFT_COLOR = '#1a73e8';
    const DRAWING_COLOR = '#4ecdc4';
    const SNAP_PIXEL_THRESHOLD = 15;
    const genusLocalNames = {
      Rhizophora: 'Bakawan',
      Avicennia: 'Bungalon',
      Sonneratia: 'Pagatpat',
      Bruguiera: 'Miapi',
      Ceriops: 'Tungog',
      Xylocarpus: 'Tabigi',
      Nypa: 'Nipa/Sasa',
    };

    // Same location pin as the landing page (.map-pin in workspace-styles), in the given color.
    function locationPinIcon(color) {
      return L.divIcon({
        className: '',
        html: `<span class="map-pin" style="--map-pin-color:${color}"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i></span>`,
        iconSize: [30, 30],
        iconAnchor: [15, 29],
        popupAnchor: [0, -26],
        tooltipAnchor: [0, -24],
      });
    }

    @include('users.partials.map-base-scripts')

    // ---- Modals (markup shared with the Delineate workspace) ----
    function showProcessingModal(title, subtitle) {
      const modal = document.getElementById('processingModal');
      if (!modal) return;
      modal.querySelector('.processing-modal-title').textContent = title;
      modal.querySelector('.processing-modal-subtitle').textContent = subtitle;
      modal.classList.add('active');
    }

    function hideProcessingModal() {
      document.getElementById('processingModal')?.classList.remove('active');
    }

    function showSuccessModal(message, title = 'Success!') {
      document.getElementById('successModalTitle').textContent = title;
      document.getElementById('successModalMessage').textContent = message;
      document.getElementById('successModal')?.classList.add('active');
    }

    window.hideSuccessModal = () => document.getElementById('successModal')?.classList.remove('active');

    // ---- Geometry helpers ----
    function ringInfo(feature) {
      const coords = Array.isArray(feature.coords) ? feature.coords : [];
      const first = coords[0];
      const last = coords[coords.length - 1];
      const closed = coords.length > 3 && first && last
        && Number(first[0]) === Number(last[0]) && Number(first[1]) === Number(last[1]);
      return { ring: closed ? coords.slice(0, -1) : coords.slice(), closed };
    }

    function commitRing(feature, ring, closed) {
      feature.coords = closed && ring.length ? [...ring, [ring[0][0], ring[0][1]]] : ring;
    }

    function polygonAreaHectares(coords) {
      if (!Array.isArray(coords) || coords.length < 3) return 0;
      const earthRadiusMeters = 6371008.8;
      const points = coords.map(([lat, lng]) => [Number(lat) * Math.PI / 180, Number(lng) * Math.PI / 180]);
      let area = 0;
      for (let i = 0; i < points.length; i++) {
        const [lat1, lng1] = points[i];
        const [lat2, lng2] = points[(i + 1) % points.length];
        area += (lng2 - lng1) * (2 + Math.sin(lat1) + Math.sin(lat2));
      }
      return Math.abs(area * earthRadiusMeters ** 2 / 2) / 10000;
    }

    function formatHectares(features) {
      const total = (features || []).reduce((sum, feature) => sum + polygonAreaHectares(feature.coords), 0);
      return `${total.toFixed(2)} ha`;
    }

    function featuresBounds(features) {
      const latLngs = (features || []).flatMap(feature => (feature.coords || []).map(c => L.latLng(c[0], c[1])));
      return latLngs.length ? L.latLngBounds(latLngs) : null;
    }

    function zoomToFeatures(features) {
      const bounds = featuresBounds(features);
      if (bounds?.isValid()) {
        mainMap.flyToBounds(bounds, { padding: [50, 50], maxZoom: 17, duration: 0.8 });
      }
    }

    function genusLabelHtml(genus) {
      const local = genusLocalNames[genus];
      const safe = String(genus).replace(/[&<>"']/g, ch => `&#${ch.charCodeAt(0)};`);
      return local ? `${safe} <span class="genus-local-name">(${local})</span>` : safe;
    }

    // ---- State ----
    let mode = null; // 'draw' | 'select' | null
    let drawingCoords = [];
    // The recommendation open in the panel: a new area (id = null) or a copy of a saved record.
    let draft = null;
    let selectedVertex = null; // { featureIndex, vertexIndex }
    let vertexHandles = [];
    let history = [];
    let historyIndex = -1;

    const referenceLayer = L.layerGroup().addTo(mainMap);
    const recommendationLayer = L.layerGroup().addTo(mainMap);
    const draftLayer = L.layerGroup().addTo(mainMap);
    const vertexLayer = L.layerGroup().addTo(mainMap);
    const snapMarker = L.circleMarker([0, 0], {
      radius: 8,
      color: '#1e9e62',
      weight: 2,
      fillColor: '#1e9e62',
      fillOpacity: 0.35,
      opacity: 0,
      interactive: false,
    }).addTo(mainMap);

    // Vertex that follows the cursor while drawing (like the drone imagery editor's draw hint).
    // Own pane so it stays above the shapes and never takes the click.
    mainMap.createPane('drawCursorPane');
    mainMap.getPane('drawCursorPane').style.zIndex = 650;
    mainMap.getPane('drawCursorPane').style.pointerEvents = 'none';
    const cursorVertexMarker = L.circleMarker([0, 0], {
      pane: 'drawCursorPane',
      radius: 5,
      color: '#1e9e62',
      weight: 2,
      fillColor: '#ffffff',
      fillOpacity: 1,
      opacity: 1,
      interactive: false,
    });

    function hideCursorVertex() {
      cursorVertexMarker.remove();
    }

    function showCursorVertex(latlng) {
      cursorVertexMarker.setLatLng(latlng);
      if (!mainMap.hasLayer(cursorVertexMarker)) cursorVertexMarker.addTo(mainMap);
    }

    function collectSnapVertices() {
      const vertices = [];
      const addCoordinates = (coordinates) => {
        if (!Array.isArray(coordinates)) return;
        if (coordinates.length >= 2 && Number.isFinite(Number(coordinates[0])) && Number.isFinite(Number(coordinates[1]))) {
          vertices.push([Number(coordinates[0]), Number(coordinates[1])]);
          return;
        }
        coordinates.forEach(addCoordinates);
      };
      referenceAreas.forEach(area => area.features?.forEach(feature => addCoordinates(feature.coords)));
      recommendations.forEach(recommendation => recommendation.features?.forEach(feature => addCoordinates(feature.coords)));
      draft?.features.forEach(feature => addCoordinates(feature.coords));
      addCoordinates(drawingCoords);
      return vertices;
    }

    function getSnappedLatLng(latlng) {
      const mousePixel = mainMap.latLngToContainerPoint(latlng);
      let nearest = null;
      let nearestDistance = SNAP_PIXEL_THRESHOLD;
      collectSnapVertices().forEach(([lat, lng]) => {
        const candidate = L.latLng(lat, lng);
        const distance = mousePixel.distanceTo(mainMap.latLngToContainerPoint(candidate));
        if (distance < nearestDistance) {
          nearest = candidate;
          nearestDistance = distance;
        }
      });
      return nearest;
    }

    function updateSnapMarker(latlng) {
      const snapped = getSnappedLatLng(latlng);
      snapMarker.setStyle({
        opacity: snapped ? 1 : 0,
        fillOpacity: snapped ? 0.35 : 0,
      });
      if (snapped) snapMarker.setLatLng(snapped);
      return snapped;
    }

    const panel = document.getElementById('mapRightPanel');
    const form = document.getElementById('recommendationForm');
    const nameInput = document.getElementById('recName');
    const suitabilityInput = document.getElementById('recSuitability');
    const recommendationInput = document.getElementById('recRecommendation');
    const siteNotesInput = document.getElementById('recSiteNotes');
    const genusButtons = [...document.querySelectorAll('#recGenusGroup .genus-pill-btn')];
    const customGenusInput = document.getElementById('recCustomGenusInput');
    const addCustomGenusButton = document.getElementById('recAddCustomGenusBtn');

    document.querySelectorAll('[data-suitability-swatch]').forEach(swatch => {
      swatch.style.background = SUITABILITY_COLORS[swatch.dataset.suitabilitySwatch];
    });

    // ---- Undo / redo: points while drawing, or the draft geometry while editing an area ----
    function currentGeometry() {
      if (mode === 'draw') return { drawingCoords };
      if (draft?.editingArea) return { features: draft.features };
      return null;
    }

    function resetHistory() {
      const geometry = currentGeometry();
      history = geometry ? [JSON.stringify(geometry)] : [];
      historyIndex = history.length - 1;
      updateHistoryButtons();
    }

    function pushHistory() {
      const geometry = currentGeometry();
      if (!geometry) return;
      history = history.slice(0, historyIndex + 1);
      history.push(JSON.stringify(geometry));
      historyIndex = history.length - 1;
      updateHistoryButtons();
    }

    function restoreHistory(index) {
      if (index < 0 || index >= history.length) return;
      historyIndex = index;
      const state = JSON.parse(history[index]);
      if (state.drawingCoords) drawingCoords = state.drawingCoords;
      if (state.features && draft) {
        draft.features = state.features;
        selectedVertex = null;
        onDraftGeometryChanged(false);
      }
      renderDraft();
      updateHistoryButtons();
    }

    function updateHistoryButtons() {
      document.getElementById('undoBtn').disabled = historyIndex <= 0;
      document.getElementById('redoBtn').disabled = historyIndex >= history.length - 1;
    }

    document.getElementById('undoBtn').addEventListener('click', () => restoreHistory(historyIndex - 1));
    document.getElementById('redoBtn').addEventListener('click', () => restoreHistory(historyIndex + 1));

    // ---- Rendering ----
    function renderReferenceAreas() {
      referenceLayer.clearLayers();
      referenceAreas.forEach(area => {
        area.features.forEach(feature => {
          const polygon = L.polygon(feature.coords, {
            color: REFERENCE_COLOR,
            weight: 2,
            dashArray: '6 4',
            fillColor: '#a8d5b5',
            fillOpacity: mode === 'select' ? 0.3 : 0.08,
          }).addTo(referenceLayer);
          polygon.bindTooltip(`${area.name}${area.created_by ? ` · ${area.created_by}` : ''}`, { sticky: true });
          const selectArea = (e, shouldFocus = true) => {
            if (mode !== 'select') return;
            L.DomEvent.stopPropagation(e);
            startDraftFromReference(area, feature, shouldFocus);
          };
          polygon.on('click', selectArea);
          const marker = L.marker(polygon.getCenter(), { icon: locationPinIcon(REFERENCE_COLOR), title: area.name, riseOnHover: true }).addTo(referenceLayer);
          marker.bindTooltip(`${area.name} · Approved delineation`, { direction: 'top' });
          marker.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            zoomToFeatures([feature]);
            selectArea(e, false);
          });
        });
      });
    }

    function renderRecommendations() {
      recommendationLayer.clearLayers();
      recommendations.forEach(rec => {
        if (draft && draft.id === rec.id) return; // drawn as the editable draft instead
        const color = SUITABILITY_COLORS[rec.suitability] || REFERENCE_COLOR;
        const locationIcon = locationPinIcon(color);
        (rec.features || []).forEach(feature => {
          const polygon = L.polygon(feature.coords, { color, fillColor: color, fillOpacity: 0.35, weight: 2 })
            .addTo(recommendationLayer);
          polygon.bindTooltip(`${rec.name} · ${suitabilityLabels[rec.suitability] || ''}`, { sticky: true });
          polygon.on('click', (e) => {
            if (mode) return; // let drawing / selection clicks reach the map
            L.DomEvent.stopPropagation(e);
            openRecommendation(rec.id);
          });
          const marker = L.marker(polygon.getCenter(), { icon: locationIcon, title: rec.name, riseOnHover: true }).addTo(recommendationLayer);
          marker.bindTooltip(`${rec.name} · Mangrove location`, { direction: 'top' });
          marker.on('click', (e) => {
            if (mode) return;
            L.DomEvent.stopPropagation(e);
            openRecommendation(rec.id);
          });
        });
      });
    }

    function renderDraft() {
      draftLayer.clearLayers();
      vertexLayer.clearLayers();
      vertexHandles = [];

      if (drawingCoords.length) {
        const preview = L.polyline(drawingCoords, { color: DRAWING_COLOR, opacity: 0.8, interactive: false });
        preview.addTo(draftLayer);
        drawingCoords.forEach((coord, index) => L.circleMarker(coord, {
          radius: index === 0 ? 6 : 3,
          color: DRAWING_COLOR,
          weight: 1,
          fillColor: index === 0 ? '#ffffff' : DRAWING_COLOR,
          fillOpacity: 0.9,
          interactive: false,
        }).addTo(vertexLayer));
      }

      if (!draft) return;
      // Panel hidden with ×: the draft is kept and clicking it brings the panel back.
      const panelHidden = !panel.classList.contains('open');
      draft.features.forEach((feature, featureIndex) => {
        const polygon = L.polygon(feature.coords, {
          color: DRAFT_COLOR,
          fillColor: DRAFT_COLOR,
          fillOpacity: 0.3,
          weight: 3,
          interactive: panelHidden,
        }).addTo(draftLayer);
        if (panelHidden) {
          polygon.bindTooltip(draft.id === null ? 'Unsaved recommendation · click to continue' : `${draft.name || 'Recommendation'} · click to continue editing`, { sticky: true });
          polygon.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            reopenDraftPanel();
          });
        }
        if (draft.editingArea) renderEditableVertices(feature, featureIndex, polygon);
      });
    }

    // Same interaction as the Delineate boundary editor: drag points, drag/click midpoints to add, double/right-click to delete.
    function renderEditableVertices(feature, featureIndex, shape) {
      const { ring, closed } = ringInfo(feature);
      const vertexIcon = (selected) => L.divIcon({
        className: 'delineation-vertex-handle' + (selected ? ' is-selected' : ''),
        html: '<div style="width:12px;height:12px;background:#1a73e8;border:2px solid #ffffff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,0.4);cursor:grab;"></div>',
        iconSize: [12, 12],
        iconAnchor: [6, 6],
      });
      const midpointIcon = L.divIcon({
        className: 'delineation-midpoint-handle',
        html: '<div style="width:10px;height:10px;background:rgba(255,255,255,0.75);border:2px solid #1a73e8;border-radius:50%;opacity:0.8;"></div>',
        iconSize: [10, 10],
        iconAnchor: [5, 5],
      });
      const refreshShape = () => {
        shape.setLatLngs(feature.coords);
        document.getElementById('recArea').textContent = formatHectares(draft.features);
      };
      const isSelected = (vIdx) => selectedVertex
        && selectedVertex.featureIndex === featureIndex && selectedVertex.vertexIndex === vIdx;

      ring.forEach((coord, vIdx) => {
        const handle = L.marker(coord, {
          draggable: true,
          icon: vertexIcon(isSelected(vIdx)),
          zIndexOffset: 1000,
          title: 'Drag to move. Double-click or right-click to delete.',
        }).addTo(vertexLayer);
        vertexHandles.push({ featureIndex, vertexIndex: vIdx, handle });
        handle.on('drag', (e) => {
          ring[vIdx] = [e.latlng.lat, e.latlng.lng];
          commitRing(feature, ring, closed);
          refreshShape();
        });
        handle.on('dragend', () => {
          selectedVertex = { featureIndex, vertexIndex: vIdx };
          onDraftGeometryChanged();
        });
        handle.on('click', (e) => {
          if (e?.originalEvent) L.DomEvent.stop(e.originalEvent);
          selectedVertex = isSelected(vIdx) ? null : { featureIndex, vertexIndex: vIdx };
          refreshSelectedVertexStyles();
        });
        const removeHandler = (e) => {
          if (e?.originalEvent) L.DomEvent.stop(e.originalEvent);
          deleteVertex(featureIndex, vIdx);
        };
        handle.on('dblclick', removeHandler);
        handle.on('contextmenu', removeHandler);
      });

      for (let sIdx = 0; sIdx < ring.length; sIdx++) {
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
          commitRing(feature, ring, closed);
        });
        midHandle.on('drag', (e) => {
          if (!inserted) return;
          ring[insertAt] = [e.latlng.lat, e.latlng.lng];
          commitRing(feature, ring, closed);
          refreshShape();
        });
        midHandle.on('dragend', () => {
          selectedVertex = { featureIndex, vertexIndex: insertAt };
          onDraftGeometryChanged();
        });
        midHandle.on('click', (e) => {
          if (e?.originalEvent) L.DomEvent.stop(e.originalEvent);
          if (inserted) return;
          ring.splice(insertAt, 0, mid);
          commitRing(feature, ring, closed);
          selectedVertex = { featureIndex, vertexIndex: insertAt };
          onDraftGeometryChanged();
        });
      }
    }

    function refreshSelectedVertexStyles() {
      vertexHandles.forEach(({ featureIndex, vertexIndex, handle }) => {
        const selected = !!selectedVertex
          && selectedVertex.featureIndex === featureIndex && selectedVertex.vertexIndex === vertexIndex;
        handle.getElement()?.classList.toggle('is-selected', selected);
      });
      updatePanelActions();
    }

    function deleteVertex(featureIndex, vertexIndex) {
      const feature = draft?.features[featureIndex];
      if (!feature) return;
      const { ring, closed } = ringInfo(feature);
      if (ring.length <= 3) {
        alert('An area needs at least 3 points. Move this point instead of deleting it.');
        return;
      }
      ring.splice(vertexIndex, 1);
      commitRing(feature, ring, closed);
      selectedVertex = null;
      onDraftGeometryChanged();
    }

    function onDraftGeometryChanged(recordHistory = true) {
      draft.features.forEach(feature => {
        delete feature.location;
        delete feature.location_status;
        delete feature.location_details;
      });
      if (recordHistory) pushHistory();
      renderDraft();
      document.getElementById('recArea').textContent = formatHectares(draft.features);
      scheduleLocationLookup();
      updatePanelActions();
    }

    // ---- Location (reuses the delineation barangay lookup) ----
    let locationTimer = null;
    let locationToken = 0;

    function scheduleLocationLookup() {
      clearTimeout(locationTimer);
      const locationEl = document.getElementById('recLocation');
      const feature = draft?.features[0];
      if (!feature) return;
      if (feature.location_status === 'matched' && feature.location) {
        locationEl.textContent = feature.location;
        return;
      }
      locationEl.textContent = 'Locating barangay...';
      const token = ++locationToken;
      locationTimer = setTimeout(async () => {
        try {
          const response = await fetch(resolveLocationUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ feature: { type: 'area', coords: feature.coords } }),
          });
          const payload = await response.json();
          if (!response.ok || !payload.feature) throw new Error(payload.message);
          if (token !== locationToken || draft?.features[0] !== feature) return;
          Object.assign(feature, {
            location: payload.feature.location || null,
            location_status: payload.feature.location_status || null,
            location_details: payload.feature.location_details || null,
          });
          locationEl.textContent = feature.location_status === 'matched' && feature.location
            ? feature.location
            : (feature.location_status === 'pending' ? 'Location pending boundary import' : 'Outside loaded barangay boundaries');
        } catch (error) {
          if (token === locationToken) locationEl.textContent = 'Location preview unavailable';
        }
      }, 350);
    }

    // ---- Panel ----
    function openPanel() {
      panel.classList.remove('expanded');
      panel.classList.add('open');
      setTimeout(() => mainMap.invalidateSize({ pan: false }), 330);
    }

    function hidePanel() {
      panel.classList.remove('open', 'peek', 'expanded');
      document.getElementById('panelBackdrop').classList.remove('show');
      setTimeout(() => mainMap.invalidateSize({ pan: false }), 330);
    }

    document.getElementById('panelHeader').addEventListener('click', (e) => {
      if (e.target.closest('.panel-close-btn') || window.innerWidth > 780) return;
      const expanded = panel.classList.toggle('expanded');
      document.getElementById('panelBackdrop').classList.toggle('show', expanded);
    });
    document.getElementById('panelBackdrop').addEventListener('click', () => {
      panel.classList.remove('expanded');
      document.getElementById('panelBackdrop').classList.remove('show');
    });
    // × only hides the panel, like the Delineate side panel: a new or edited draft stays on the map (nothing
    // is discarded) and clicking it reopens the panel. Discarding is the panel's Discard button.
    function closePanelKeepDraft() {
      if (draft && draft.id !== null && !isDraftDirty() && !draft.editingArea) {
        closeDraft(true); // a saved recommendation with nothing changed: nothing to keep
        return;
      }
      hidePanel();
      renderDraft(); // the draft becomes clickable to reopen the panel
    }

    function reopenDraftPanel() {
      if (!draft || panel.classList.contains('open')) return;
      openPanel();
      updatePanelActions();
      renderDraft();
    }

    document.getElementById('recClosePanelBtn').addEventListener('click', closePanelKeepDraft);

    function readForm() {
      return {
        name: nameInput.value.trim(),
        suitability: suitabilityInput.value,
        recommended_genera: suitabilityInput.value === 'not_suitable' ? [] : [...draft.genera],
        recommendation: recommendationInput.value.trim(),
        site_notes: siteNotesInput.value.trim(),
      };
    }

    function draftSignature() {
      return JSON.stringify({ coords: draft.features.map(feature => feature.coords), ...readForm() });
    }

    function isDraftDirty() {
      return !!draft && draft.can_edit && draftSignature() !== draft.savedSignature;
    }

    // true = nothing to lose, go on now. Otherwise asks with the shared dialog (partials.action-feedback)
    // and, if the user agrees, runs proceed() (the caller's forced variant) afterwards.
    function confirmDiscardDraft(proceed) {
      if (!isDraftDirty()) return true;
      window.AppActions.confirm({
        title: 'Discard changes?',
        message: 'Your unsaved planting recommendation changes are lost.',
        confirmLabel: 'Discard',
        tone: 'danger',
      }).then(ok => { if (ok) proceed(); });
      return false;
    }

    function renderGenusButtons() {
      const disabled = !draft?.can_edit || suitabilityInput.value === 'not_suitable';
      genusButtons.forEach(button => {
        const genus = button.dataset.genus;
        const active = !!draft && draft.genera.has(genus) && suitabilityInput.value !== 'not_suitable';
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.disabled = disabled;
        button.innerHTML = `<i class="bi ${active ? 'bi-check2' : 'bi-plus'}"></i> ${genusLabelHtml(genus)}`;
      });
      customGenusInput.disabled = disabled;
      addCustomGenusButton.disabled = disabled;
      document.getElementById('recSelectedGenera').innerHTML = draft
        ? [...draft.genera].map(genus => `
          <span class="genus-tag">
            ${genusLabelHtml(genus)}
            <button type="button" class="genus-tag-remove" data-remove-genus="${encodeURIComponent(genus)}"
              aria-label="Remove ${genus.replace(/[&<>"']/g, ch => `&#${ch.charCodeAt(0)};`)}" title="Remove genus"
              ${disabled ? 'disabled' : ''}>&times;</button>
          </span>
        `).join('')
        : '';
      document.querySelectorAll('#recSelectedGenera [data-remove-genus]').forEach(button => {
        button.addEventListener('click', () => {
          if (!draft?.can_edit) return;
          draft.genera.delete(decodeURIComponent(button.dataset.removeGenus));
          renderGenusButtons();
          updatePanelActions();
        });
      });
    }

    function addCustomGenus() {
      if (!draft?.can_edit || suitabilityInput.value === 'not_suitable') return;
      const raw = customGenusInput.value.trim();
      if (!raw) return;
      const genus = raw.charAt(0).toUpperCase() + raw.slice(1);
      if (![...draft.genera].some(selected => selected.toLowerCase() === genus.toLowerCase())) {
        draft.genera.add(genus);
        document.getElementById('recGenusField').classList.remove('input-error');
        renderGenusButtons();
        updatePanelActions();
      }
      customGenusInput.value = '';
      customGenusInput.focus();
    }

    addCustomGenusButton.addEventListener('click', addCustomGenus);
    customGenusInput.addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        addCustomGenus();
      }
    });

    genusButtons.forEach(button => button.addEventListener('click', () => {
      if (!draft?.can_edit) return;
      const genus = button.dataset.genus;
      draft.genera.has(genus) ? draft.genera.delete(genus) : draft.genera.add(genus);
      document.getElementById('recGenusField').classList.remove('input-error');
      renderGenusButtons();
      updatePanelActions();
    }));

    suitabilityInput.addEventListener('change', () => {
      suitabilityInput.classList.remove('input-error');
      renderGenusButtons();
      updatePanelActions();
    });
    [nameInput, recommendationInput, siteNotesInput].forEach(input => input.addEventListener('input', () => {
      input.classList.remove('input-error');
      updatePanelActions();
    }));

    function updatePanelActions() {
      if (!draft) return;
      const saved = draft.id !== null;
      const dirty = isDraftDirty();
      document.getElementById('recEditAreaBtn').style.display = saved && draft.can_edit && !draft.editingArea ? 'block' : 'none';
      document.getElementById('recEditHint').style.display = draft.editingArea ? 'block' : 'none';
      const deleteVertexBtn = document.getElementById('recDeleteVertexBtn');
      deleteVertexBtn.style.display = draft.editingArea ? 'block' : 'none';
      deleteVertexBtn.disabled = !selectedVertex;
      const saveBtn = document.getElementById('recSaveBtn');
      saveBtn.style.display = draft.can_edit ? 'block' : 'none';
      saveBtn.disabled = saved && !dirty;
      saveBtn.querySelector('span').textContent = saved ? 'Save changes' : 'Save recommendation';
      const cancelBtn = document.getElementById('recCancelBtn');
      cancelBtn.style.display = draft.can_edit && (!saved || dirty || draft.editingArea) ? 'block' : 'none';
      cancelBtn.lastChild.textContent = saved ? ' Discard changes' : ' Discard';
      document.getElementById('recDeleteBtn').style.display = saved && draft.can_edit ? 'block' : 'none';
      updateHistoryButtons();
    }

    function fillForm() {
      const saved = draft.id !== null;
      document.getElementById('recFormTitle').textContent = saved ? draft.name : 'New recommendation';
      document.getElementById('panelHeaderTitle').textContent = saved ? 'Planting Recommendation' : 'New Recommendation';
      document.getElementById('recArea').textContent = formatHectares(draft.features);
      document.getElementById('recSource').textContent = draft.delineation_id
        ? `Approved delineation: ${draft.source_name || `#${draft.delineation_id}`}`
        : 'Drawn by expert';
      document.getElementById('recExpertRow').style.display = saved ? '' : 'none';
      document.getElementById('recExpert').textContent = draft.can_edit ? 'You' : (draft.expert_name || 'Expert');
      document.getElementById('recUpdatedRow').style.display = saved ? '' : 'none';
      const updated = draft.updated_at ? new Date(draft.updated_at) : null;
      document.getElementById('recUpdated').textContent = updated && !isNaN(updated) ? updated.toLocaleString() : '-';
      document.getElementById('recReadonlyNote').style.display = draft.can_edit ? 'none' : 'block';

      nameInput.value = draft.name || '';
      suitabilityInput.value = draft.suitability || '';
      recommendationInput.value = draft.recommendation || '';
      siteNotesInput.value = draft.site_notes || '';
      customGenusInput.value = '';
      [nameInput, suitabilityInput, recommendationInput, siteNotesInput].forEach(input => {
        input.disabled = !draft.can_edit;
        input.classList.remove('input-error');
      });
      document.getElementById('recGenusField').classList.remove('input-error');
      renderGenusButtons();
    }

    function openDraft(nextDraft, shouldFocus = true) {
      draft = {
        id: null,
        delineation_id: null,
        name: '',
        suitability: '',
        recommendation: '',
        site_notes: '',
        can_edit: true,
        editingArea: false,
        ...nextDraft,
        genera: new Set(nextDraft.recommended_genera || []),
        features: JSON.parse(JSON.stringify(nextDraft.features || [])),
      };
      selectedVertex = null;
      fillForm();
      draft.savedSignature = draft.id === null ? null : draftSignature();
      resetHistory();
      openPanel();
      renderRecommendations();
      renderDraft();
      scheduleLocationLookup();
      updatePanelActions();
      if (shouldFocus) zoomToFeatures(draft.features);
    }

    // then: what the caller wanted to do once the draft is closed (also after a confirmed discard).
    function closeDraft(force = false, then = null) {
      if (!force && !confirmDiscardDraft(() => { closeDraft(true); then?.(); })) return false;
      draft = null;
      selectedVertex = null;
      hidePanel();
      resetHistory();
      renderRecommendations();
      renderDraft();
      return true;
    }

    function openRecommendation(id, force = false) {
      if (!force && draft && draft.id !== id && !confirmDiscardDraft(() => openRecommendation(id, true))) return;
      const rec = recommendations.find(item => item.id === id);
      if (!rec) return;
      setMode(null);
      openDraft({ ...rec, source_name: rec.delineation_name });
    }

    function startDraftFromReference(area, feature, shouldFocus = true, force = false) {
      if (!force && !confirmDiscardDraft(() => startDraftFromReference(area, feature, shouldFocus, true))) return;
      setMode(null);
      openDraft({
        delineation_id: area.id,
        source_name: area.name,
        name: area.name,
        editingArea: true,
        features: [{ type: 'area', coords: feature.coords, location: feature.location, location_status: feature.location_status }],
      }, shouldFocus);
    }

    document.getElementById('recEditAreaBtn').addEventListener('click', () => {
      if (!draft?.can_edit) return;
      draft.editingArea = true;
      resetHistory();
      renderDraft();
      updatePanelActions();
    });

    document.getElementById('recDeleteVertexBtn').addEventListener('click', () => {
      if (selectedVertex) deleteVertex(selectedVertex.featureIndex, selectedVertex.vertexIndex);
    });

    document.getElementById('recCancelBtn').addEventListener('click', () => {
      if (!draft) return;
      if (draft.id === null) {
        closeDraft();
      } else {
        const id = draft.id;
        if (confirmDiscardDraft(() => openRecommendation(id, true))) openRecommendation(id, true);
      }
    });

    // ---- Modes: draw a new area or select an approved delineation ----
    const modeButtons = document.querySelectorAll('.draw-btn[data-mode]');
    const modeHint = document.getElementById('recModeHint');

    function setMode(nextMode) {
      const requested = nextMode;
      if (nextMode && nextMode === mode) nextMode = null;
      if (nextMode && draft && !closeDraft(false, () => setMode(requested))) return;
      drawingCoords = [];
      mode = nextMode;
      modeButtons.forEach(button => button.classList.toggle('active', button.dataset.mode === mode));
      mainMap.getContainer().style.cursor = mode === 'draw' ? 'crosshair' : '';
      mode === 'draw' ? mainMap.doubleClickZoom.disable() : mainMap.doubleClickZoom.enable();
      if (mode !== 'draw') {
        snapMarker.setStyle({ opacity: 0, fillOpacity: 0 });
        hideCursorVertex();
      }
      modeHint.hidden = !mode;
      modeHint.textContent = mode === 'draw'
        ? 'Click to add points. Click the starting point or double-click to finish the area. Esc cancels.'
        : (referenceAreas.length ? 'Click an approved delineation to evaluate it.' : 'No approved delineations are available to select.');
      resetHistory();
      renderReferenceAreas();
      renderDraft();
    }

    modeButtons.forEach(button => button.addEventListener('click', () => setMode(button.dataset.mode)));

    function finishDrawing() {
      if (drawingCoords.length < 3) {
        alert('An area needs at least 3 points.');
        return;
      }
      const first = drawingCoords[0];
      const last = drawingCoords[drawingCoords.length - 1];
      const coords = [...drawingCoords];
      if (Number(first[0]) !== Number(last[0]) || Number(first[1]) !== Number(last[1])) {
        coords.push([first[0], first[1]]);
      }
      setMode(null);
      openDraft({ editingArea: true, features: [{ type: 'area', coords }] });
    }

    mainMap.on('click', (e) => {
      if (mode !== 'draw') return;
      const clickedLatLng = getSnappedLatLng(e.latlng) || e.latlng;
      if (drawingCoords.length >= 3) {
        const firstPoint = mainMap.latLngToContainerPoint(L.latLng(drawingCoords[0][0], drawingCoords[0][1]));
        if (firstPoint.distanceTo(mainMap.latLngToContainerPoint(clickedLatLng)) < SNAP_PIXEL_THRESHOLD * 2) {
          finishDrawing();
          return;
        }
      }
      const last = drawingCoords[drawingCoords.length - 1];
      if (last && mainMap.latLngToContainerPoint(L.latLng(last[0], last[1])).distanceTo(mainMap.latLngToContainerPoint(clickedLatLng)) < 3) {
        return; // second click of a double-click
      }
      drawingCoords.push([clickedLatLng.lat, clickedLatLng.lng]);
      pushHistory();
      renderDraft();
    });

    mainMap.on('mousemove', (e) => {
      if (mode !== 'draw') return;
      showCursorVertex(updateSnapMarker(e.latlng) || e.latlng);
    });

    mainMap.on('mouseout', hideCursorVertex);

    mainMap.on('dblclick', () => {
      if (mode === 'draw') finishDrawing();
    });

    document.addEventListener('keydown', (event) => {
      const tag = (event.target?.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || event.target?.isContentEditable) return;
      if (event.key === 'Escape' && mode) {
        setMode(null);
      } else if ((event.key === 'Delete' || event.key === 'Backspace') && selectedVertex && draft?.editingArea) {
        event.preventDefault();
        deleteVertex(selectedVertex.featureIndex, selectedVertex.vertexIndex);
      }
    });

    window.addEventListener('beforeunload', (event) => {
      if (isDraftDirty()) event.preventDefault();
    });

    // ---- Save / delete ----
    function validateForm(values) {
      const fail = (element, message) => {
        element.classList.add('input-error');
        alert(message);
        element.focus?.();
        return false;
      };
      if (!values.name) return fail(nameInput, 'Please enter a name for this area.');
      if (!values.suitability) return fail(suitabilityInput, 'Please select the planting suitability.');
      if (values.suitability !== 'not_suitable' && !values.recommended_genera.length) {
        return fail(document.getElementById('recGenusField'), 'Select at least one recommended genus, or mark the area as not suitable.');
      }
      return true;
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!draft?.can_edit) return;
      const values = readForm();
      if (!validateForm(values)) return;

      // Confirmation → Processing → Success or Error (partials.action-feedback). The panel is re-rendered
      // only after the buttons are restored, because rendering rewrites their labels.
      const saved = draft.id !== null;
      const saveBtn = document.getElementById('recSaveBtn');
      const panelButtons = ['recCancelBtn', 'recDeleteBtn', 'recEditAreaBtn'].map(id => document.getElementById(id));
      const outcome = await window.AppActions.run({
        confirm: saved
          ? { title: 'Save changes?', message: `The planting recommendation "${values.name}" is updated.`, confirmLabel: 'Save' }
          : { title: 'Save recommendation?', message: `"${values.name}" is saved as a planting recommendation.`, confirmLabel: 'Save' },
        button: saveBtn,
        busyLabel: saved ? 'Saving changes...' : 'Saving recommendation...',
        lock: panelButtons,
        task: async () => {
          const response = await fetch(saved ? `${recommendationsUrl}/${encodeURIComponent(draft.id)}` : recommendationsUrl, {
            method: saved ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
              ...values,
              delineation_id: draft.delineation_id,
              features: draft.features.map(feature => ({ type: 'area', coords: feature.coords })),
            }),
          });
          const payload = await response.json().catch(() => ({}));
          if (!response.ok || !payload.recommendation) {
            const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(firstError || payload.message || 'Unable to save the recommendation. Please try again.');
          }
          return payload;
        },
      });

      if (outcome.status === 'done') {
        const payload = outcome.result;
        const index = recommendations.findIndex(rec => rec.id === payload.recommendation.id);
        if (index >= 0) {
          recommendations[index] = payload.recommendation;
        } else {
          recommendations.unshift(payload.recommendation);
        }
        openRecommendation(payload.recommendation.id, true);
        showSuccessModal(payload.message, saved ? 'Changes saved' : 'Recommendation saved');
      } else if (outcome.status === 'failed') {
        updatePanelActions();
      }
    });

    document.getElementById('recDeleteBtn').addEventListener('click', async () => {
      if (!draft?.id || !draft.can_edit) return;
      const deletedId = draft.id;
      const outcome = await window.AppActions.run({
        confirm: {
          title: 'Delete this record?',
          message: `The planting recommendation "${draft.name}" is permanently deleted. This cannot be undone.`,
          confirmLabel: 'Delete',
          tone: 'danger',
        },
        button: document.getElementById('recDeleteBtn'),
        busyLabel: 'Deleting...',
        lock: ['recSaveBtn', 'recCancelBtn', 'recEditAreaBtn'].map(id => document.getElementById(id)),
        task: async () => {
          const response = await fetch(`${recommendationsUrl}/${encodeURIComponent(deletedId)}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
          });
          const payload = await response.json().catch(() => ({}));
          if (!response.ok) throw new Error(payload.message || 'Unable to delete the recommendation.');
          return payload;
        },
      });
      if (outcome.status !== 'done') return;
      recommendations = recommendations.filter(rec => rec.id !== deletedId);
      closeDraft(true);
      showSuccessModal(outcome.result.message || 'Planting recommendation deleted.', 'Deleted');
    });

    // ---- Initial render ----
    renderReferenceAreas();
    renderRecommendations();
    updateHistoryButtons();

    const focusRecommendationId = Number(new URLSearchParams(window.location.search).get('recommendation'));
    if (focusRecommendationId) {
      openRecommendation(focusRecommendationId, true);
    } else {
      const bounds = featuresBounds(recommendations.flatMap(rec => rec.features || []));
      if (bounds) mainMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
    }
</script>
