{{-- Drone imagery review editor (users.drone-imagery-editor). The whole image goes on the map; on top of the
     preview the owner corrects the mangrove areas (AI delineation) as Geoman shapes: remove, reshape or add.
     They are saved with PUT segmentation_update_url and then used everywhere the AI extent is used.
     The imagery stays where the GeoTIFF places it (no position offset); rings are sent as WGS84 [lng, lat]. --}}
<script>
  (() => {
    const editor = @json($editor);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const METRES_PER_DEGREE = 111319.49;
    const MAP_MAX_ZOOM = 22;

    const statusElement = document.getElementById('droneEditorStatus');
    const messageElement = document.getElementById('droneEditorMessage');
    const saveButton = document.getElementById('droneEditorSave');
    const confirmButton = document.getElementById('droneEditorConfirm');
    const aiList = document.getElementById('droneEditorAiList');
    const undoButton = document.getElementById('droneEditorUndo');
    const redoButton = document.getElementById('droneEditorRedo');
    const aiStatusElement = document.getElementById('droneEditorAiStatus');
    const aiRunButton = document.getElementById('droneEditorAiRun');

    // ---- Mobile bottom sheet (same behaviour as the Map / Delineate right panel) ---------------
    const sheet = document.getElementById('droneEditorPanel');
    const sheetHeader = document.getElementById('droneEditorSheetHeader');
    const sheetSub = document.getElementById('droneEditorSheetSub');
    const sheetBody = document.getElementById('droneEditorPanelBody');
    const sheetBackdrop = document.getElementById('droneEditorBackdrop');
    const isMobileSheet = () => window.innerWidth <= 780;

    function setSheetExpanded(expanded) {
      expanded = expanded && isMobileSheet();
      sheet.classList.toggle('is-expanded', expanded);
      sheetBackdrop.classList.toggle('is-shown', expanded);
      sheetHeader.setAttribute('aria-expanded', String(expanded));
      sheetSub.innerHTML = expanded
        ? '<span>Swipe down to collapse</span> <i class="bi bi-chevron-down"></i>'
        : '<span>Adjust upwards for details</span> <i class="bi bi-chevron-up"></i>';
      if (!expanded) sheetBody.scrollTop = 0;
    }

    sheetHeader.addEventListener('click', () => {
      if (isMobileSheet()) setSheetExpanded(!sheet.classList.contains('is-expanded'));
    });
    sheetHeader.addEventListener('keydown', event => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      sheetHeader.click();
    });
    sheetBackdrop.addEventListener('click', () => setSheetExpanded(false));
    window.addEventListener('resize', () => { if (!isMobileSheet()) setSheetExpanded(false); });

    (() => {
      let startY = 0;
      let currentY = 0;
      let startTime = 0;
      let tracking = false;
      // Peek: swipe anywhere on the sheet. Expanded: only the header, so the content scrolls normally.
      sheet.addEventListener('touchstart', event => {
        if (!isMobileSheet() || event.target.closest('input, select, textarea, button, a')) return;
        if (sheet.classList.contains('is-expanded') && !event.target.closest('.drone-editor-sheet-header')) return;
        startY = currentY = event.touches[0].clientY;
        startTime = Date.now();
        tracking = true;
      }, { passive: true });
      sheet.addEventListener('touchmove', event => {
        if (tracking) currentY = event.touches[0].clientY;
      }, { passive: true });
      sheet.addEventListener('touchend', () => {
        if (!tracking) return;
        tracking = false;
        const deltaY = currentY - startY;
        const quick = Date.now() - startTime < 250;
        if (deltaY < -20 || (deltaY < -8 && quick)) setSheetExpanded(true);
        else if (deltaY > 20 || (deltaY > 8 && quick)) setSheetExpanded(false);
      }, { passive: true });

      // Pulling down at the top of the scrolled content also collapses the sheet.
      let bodyStartY = 0;
      sheetBody.addEventListener('touchstart', event => { bodyStartY = event.touches[0].clientY; }, { passive: true });
      sheetBody.addEventListener('touchend', event => {
        const deltaY = event.changedTouches[0].clientY - bodyStartY;
        if (sheetBody.scrollTop <= 2 && deltaY > 45 && sheet.classList.contains('is-expanded')) setSheetExpanded(false);
      }, { passive: true });
    })();

    const state = {
      // Always the GeoTIFF's own position: an offset nudged in an earlier version is reset on save.
      offset: { east_m: 0, north_m: 0 },
      opacity: Number(editor.opacity) || 0.78,
      busy: null, // 'save' | 'confirm' while a request runs
      leaving: false, // confirmed: navigating away, nothing left to lose
    };

    // What the server has, to tell real changes apart from edits that were undone again.
    // Like the delineation's Save, Save draft / Confirm only become clickable when there is something to send.
    const saved = { areas: '[]', opacity: state.opacity };
    const areasSnapshot = () => JSON.stringify(snapshotAreas());
    const areasChanged = () => areasSnapshot() !== saved.areas;
    // The slider moves in 5% steps, so a saved 0.78 shows as 80%: within half a step counts as unchanged.
    const opacityChanged = () => Math.abs(state.opacity - saved.opacity) >= 0.025;
    // detailsChanged (section 3: name, genus, notes) is a hoisted function declaration further down.
    // Canopy pins removed since the last save (keys from canopyKey), sent with Save draft / Confirm.
    const removedCanopies = new Set();
    const canopyKey = center => `${Number(center[0]).toFixed(7)},${Number(center[1]).toFixed(7)}`;
    const canopiesChanged = () => removedCanopies.size > 0;
    const hasUnsavedChanges = () => areasChanged() || canopiesChanged() || opacityChanged() || detailsChanged();

    const ai = {
      status: editor.segmentation_status,
      stats: editor.segmentation_stats,
      error: editor.segmentation_error,
      url: editor.segmentation_url,
      loaded: false,
      pollTimer: null,
    };
    // Every canopy the canopy model pinpointed (canopies_url): clickable shapes coloured by genus, plus pins when
    // zoomed out. Loaded the first time they are shown; a new AI run replaces them.
    const canopies = { url: editor.canopies_url || null, payload: null, shown: false, loading: false };
    // Live genus suggestion for unsaved area edits (see scheduleGenusPreview).
    const GENUS_PREVIEW_DELAY_MS = 800;
    const GENUS_MIN_CONFIDENT_PERCENT = 1; // "Confident" tag only when confident detections cover >= 1% of the area
    const genusPreview = { snapshot: null, suggestion: null, loading: false, failed: false, timer: null, sequence: 0 };
    const aiIsRunning = () => ['queued', 'processing'].includes(ai.status);

    // ---- Map, base layers and preview ------------------------------------------------------
    const tileOptions = { maxZoom: MAP_MAX_ZOOM };
    const baseLayers = {
      sat: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { ...tileOptions, maxNativeZoom: 18 }),
      osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { ...tileOptions, maxNativeZoom: 19 }),
      topo: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', { ...tileOptions, maxNativeZoom: 17 }),
    };
    let currentBase = baseLayers.sat;
    const map = L.map('droneEditorMap', { maxZoom: MAP_MAX_ZOOM, layers: [currentBase] });
    @include('partials.map-place-labels')
    attachPlaceLabels(map, [baseLayers.sat]);

    // The preview sits below the mangrove areas but above the base map.
    map.createPane('dronePreview');
    map.getPane('dronePreview').style.zIndex = 350;
    map.getPane('dronePreview').style.pointerEvents = 'none';
    // Canopy pins sit above the areas so they stay clickable while the areas are edited.
    map.createPane('droneCanopies');
    map.getPane('droneCanopies').style.zIndex = 450;

    const source = editor.preview_bounds;
    const centerLatitude = (source.south + source.north) / 2;
    const metresToDegrees = (east, north) => ({
      lng: east / (METRES_PER_DEGREE * Math.cos(centerLatitude * Math.PI / 180)),
      lat: north / METRES_PER_DEGREE,
    });

    function previewBounds() {
      const shift = metresToDegrees(state.offset.east_m, state.offset.north_m);
      return L.latLngBounds(
        [source.south + shift.lat, source.west + shift.lng],
        [source.north + shift.lat, source.east + shift.lng]
      );
    }

    const preview = L.imageOverlay(editor.preview_url, previewBounds(), {
      pane: 'dronePreview',
      opacity: state.opacity,
      interactive: false,
    }).addTo(map);
    preview.on('error', () => showMessage('The preview failed to load. Reload the page.', true));
    // On mobile the peeking sheet covers the bottom of the map (min(355px, 45vh), see the view's CSS):
    // keep fitted content above it.
    function fitOptions(pad) {
      const covered = isMobileSheet() ? Math.min(355, window.innerHeight * 0.45) : 0;
      return { paddingTopLeft: [pad, pad], paddingBottomRight: [pad, pad + covered], maxZoom: MAP_MAX_ZOOM };
    }

    map.fitBounds(previewBounds(), fitOptions(30));

    // ---- Mangrove areas (Geoman shapes) ----------------------------------------------------
    const AREA_STYLE = { color: '#1e9e62', weight: 2, fillColor: '#2fd27f', fillOpacity: 0.28 };

    // No Geoman toolbar: areas are drawn with "Draw mangrove area" and edited or removed from their
    // popup (click an area) or the list.
    map.pm.setGlobalOptions({ snappable: false, allowSelfIntersection: false });

    function areas() {
      return map.pm.getGeomanLayers()
        .filter(layer => layer instanceof L.Polygon && !layer.options.pmIgnore && layer.options.droneArea);
    }

    function outerRing(layer) {
      const latLngs = layer.getLatLngs();
      return Array.isArray(latLngs[0]) ? latLngs[0] : latLngs;
    }

    const toLngLat = layer => outerRing(layer).map(point => [Math.round(point.lng * 1e7) / 1e7, Math.round(point.lat * 1e7) / 1e7]);

    // Approximate ground area of a ring (local equirectangular projection; fine at survey scale).
    function ringAreaHa(latLngs) {
      if (latLngs.length < 3) return 0;
      const cos = Math.cos(centerLatitude * Math.PI / 180);
      let twiceArea = 0;
      latLngs.forEach((point, index) => {
        const next = latLngs[(index + 1) % latLngs.length];
        twiceArea += (point.lng * cos * METRES_PER_DEGREE) * (next.lat * METRES_PER_DEGREE)
          - (next.lng * cos * METRES_PER_DEGREE) * (point.lat * METRES_PER_DEGREE);
      });
      return Math.abs(twiceArea) / 2 / 10000;
    }

    function markChanged(areasEdited = true) {
      if (areasEdited) recordHistory();
      refresh();
    }

    // ---- Draw / Edit toggles ---------------------------------------------------------------
    // Draw: keeps drawing new mangrove areas until toggled off. Edit: every area shows its corners;
    // drag a corner to move it, double-click it to delete it (like the manual delineation).
    const toggles = { draw: false, edit: false };
    // Mangrove areas hidden on the map (Hide areas, see setAreasHidden).
    let areasHidden = false;
    const areasToggle = document.getElementById('droneEditorAreasToggle');

    // Toggle buttons in the top toolbar (next to Undo / Redo).
    const drawToggle = document.getElementById('droneEditorDrawToggle');
    const editToggle = document.getElementById('droneEditorEditToggle');

    const CORNER_EDIT_OPTIONS = {
      allowSelfIntersection: false,
      removeVertexOn: 'dblclick',
      removeLayerBelowMinVertexCount: false,
      removeVertexValidation: ({ layer: edited }) => {
        if (outerRing(edited).length > 3) return true;
        showMessage('An area needs at least 3 corners.', true);
        return false;
      },
    };

    function enableCornerEditing(layer) {
      if (!layer.pm.enabled()) layer.pm.enable(CORNER_EDIT_OPTIONS);
    }

    function renderToggles() {
      [[drawToggle, toggles.draw], [editToggle, toggles.edit]].forEach(([button, on]) => {
        button.classList.toggle('is-active', on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      // Hidden areas cannot be drawn on or edited (see setAreasHidden).
      drawToggle.disabled = aiIsRunning() || areasHidden;
      editToggle.disabled = aiIsRunning() || areasHidden || (!areas().length && !toggles.edit);
      drawToggle.title = areasHidden ? 'Show the AI delineation to draw' : 'Draw areas (click again to stop)';
      editToggle.title = areasHidden ? 'Show the AI delineation to edit' :'Edit corners: drag to move, double-click to delete';
      renderAreasToggle();
    }

    function setEditing(on) {
      if (on === toggles.edit) return;
      if (on) setDrawing(false);
      toggles.edit = on;
      map.closePopup();
      if (on) {
        map.doubleClickZoom.disable(); // so double-clicking a corner deletes it instead of zooming
        areas().forEach(enableCornerEditing);
        showMessage('Edit on: drag a corner to move it, double-click to delete it.');
      } else {
        areas().forEach(layer => layer.pm.disable());
        map.doubleClickZoom.enable();
        showMessage('Edit is off.');
      }
      renderToggles();
    }

    function setDrawing(on) {
      if (on === toggles.draw) return;
      if (on) {
        if (aiIsRunning()) return;
        if (areas().length >= editor.max_polygons) {
          showMessage(`You can have up to ${editor.max_polygons} mangrove areas.`, true);
          return;
        }
        setEditing(false);
        toggles.draw = true; // before enableDraw, which fires pm:drawstart right away
        map.pm.enableDraw('Polygon', { pathOptions: AREA_STYLE, allowSelfIntersection: false, continueDrawing: true });
        showMessage('Draw on: click to add corners, click the first corner to finish.');
      } else {
        toggles.draw = false;
        map.pm.disableDraw();
        showMessage('Draw is off.');
      }
      renderToggles();
    }

    drawToggle.addEventListener('click', () => setDrawing(!toggles.draw));
    editToggle.addEventListener('click', () => setEditing(!toggles.edit));
    // Geoman can end drawing itself (e.g. Escape); keep the button in step.
    map.on('pm:drawend', () => {
      if (!toggles.draw) return;
      toggles.draw = false;
      renderToggles();
    });

    let areaCounter = 0;
    function watchArea(layer, label = null) {
      layer.options.droneArea = true;
      if (label) {
        layer.options.droneLabel = label;
      } else {
        areaCounter += 1;
        layer.options.droneLabel = `Mangrove area ${areaCounter}`;
      }
      layer.on('pm:edit', () => markChanged());
      layer.on('pm:vertexremoved', () => {
        markChanged();
        showMessage(`Corner deleted from ${layer.options.droneLabel}.`);
      });
      layer.on('click', event => {
        if (toggles.draw) return; // clicks place corners while drawing
        L.DomEvent.stopPropagation(event);
        openAreaPopup(layer, event.latlng);
      });
      layer.on('mouseover', () => { if (!areasHidden) layer.setStyle({ weight: 4 }); });
      layer.on('mouseout', () => { if (!areasHidden) layer.setStyle({ weight: AREA_STYLE.weight }); });
      if (toggles.edit) enableCornerEditing(layer);
      applyAreaVisibility(layer); // areas rebuilt by undo/redo or a new AI result stay hidden while hidden
      return layer;
    }

    // ---- Hide / show the mangrove areas (AI delineation) ------------------------------------
    // Hidden areas stay on the map (so nothing counts as removed or unsaved), just invisible and not
    // clickable. Draw and Edit are off while they are hidden, so nothing changes unseen.
    // (State: areasHidden / areasToggle, declared next to `toggles`.)

    function applyAreaVisibility(layer) {
      layer.setStyle(areasHidden
        ? { opacity: 0, fillOpacity: 0 }
        : { opacity: 1, fillOpacity: AREA_STYLE.fillOpacity, weight: AREA_STYLE.weight });
      const element = layer.getElement?.();
      if (element) element.style.pointerEvents = areasHidden ? 'none' : '';
    }

    function renderAreasToggle() {
      if (!areasToggle) return;
      areasToggle.hidden = !areas().length && !areasHidden;
      // Its card (step 1) shows whether the delineation is on the map; pressed = shown.
      const card = document.getElementById('droneEditorAreasCard');
      if (card) {
        card.hidden = areasToggle.hidden;
        card.classList.toggle('is-on', !areasHidden);
      }
      areasToggle.setAttribute('aria-pressed', areasHidden ? 'false' : 'true');
      areasToggle.innerHTML = areasHidden
        ? '<i class="bi bi-eye"></i> Show'
        : '<i class="bi bi-eye-slash"></i> Hide';
      areasToggle.setAttribute('aria-label', areasHidden ? 'Show AI delineation' : 'Hide AI delineation');
      areasToggle.title = areasHidden
        ? 'Show the AI delineation'
        : 'Hide the AI delineation (it is kept)';
    }

    function setAreasHidden(hidden) {
      if (hidden) {
        setDrawing(false);
        setEditing(false);
        map.closePopup();
      }
      areasHidden = hidden;
      areas().forEach(applyAreaVisibility);
      showMessage(hidden ? 'AI delineation hidden. It is kept; click Show on its card (step 1) to see and edit it again.' : '');
      refresh();
    }

    areasToggle?.addEventListener('click', () => setAreasHidden(!areasHidden));

    function addArea(latLngs, label = null) {
      return watchArea(L.polygon(latLngs, AREA_STYLE).addTo(map), label);
    }

    // ---- Undo / redo (like the manual delineation): snapshots of all mangrove areas ----------
    // Every change (draw, remove, move or delete a corner, remove a canopy) records a snapshot of the
    // areas and the removed canopies; undo/redo rebuilds them.
    const HISTORY_LIMIT = 100;
    const history = { states: [], index: -1 };

    function snapshotAreas() {
      return areas().map(layer => ({
        label: layer.options.droneLabel,
        latLngs: outerRing(layer).map(point => [point.lat, point.lng]),
      }));
    }

    function snapshotEditor() {
      return { areas: snapshotAreas(), canopies: [...removedCanopies].sort() };
    }

    function recordHistory() {
      const snapshot = snapshotEditor();
      const current = history.states[history.index];
      // Geoman can report one change twice (e.g. vertex removed + edit); keep a single step.
      if (current && JSON.stringify(current) === JSON.stringify(snapshot)) return;
      history.states = history.states.slice(0, history.index + 1);
      history.states.push(snapshot);
      if (history.states.length > HISTORY_LIMIT) history.states.shift();
      history.index = history.states.length - 1;
    }

    function resetHistory() {
      history.states = [snapshotEditor()];
      history.index = 0;
    }

    // Saved canopy removals cannot be undone (the server no longer has those canopies): drop them from
    // every step so undo/redo only brings back unsaved ones.
    function forgetSavedCanopies(keys) {
      history.states.forEach(snapshot => {
        snapshot.canopies = snapshot.canopies.filter(key => !keys.has(key));
      });
    }

    function restoreHistory(index) {
      if (index < 0 || index >= history.states.length || index === history.index) return;
      map.closePopup();
      areas().forEach(layer => {
        layer.pm.disable();
        map.removeLayer(layer);
      });
      history.states[index].areas.forEach(area => addArea(area.latLngs.map(([lat, lng]) => L.latLng(lat, lng)), area.label));
      removedCanopies.clear();
      history.states[index].canopies.forEach(key => removedCanopies.add(key));
      buildCanopyLayers();
      history.index = index;
      if (toggles.edit && !areas().length) setEditing(false);
      refresh();
    }

    undoButton.addEventListener('click', () => {
      restoreHistory(history.index - 1);
      showMessage('Undone.');
    });
    redoButton.addEventListener('click', () => {
      restoreHistory(history.index + 1);
      showMessage('Redone.');
    });
    // Ctrl+Z / Ctrl+Y (Ctrl+Shift+Z), except while typing in a field.
    document.addEventListener('keydown', event => {
      const typing = event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable]');
      if (!(event.ctrlKey || event.metaKey) || typing) return;
      const key = event.key.toLowerCase();
      if (key === 'z' && !event.shiftKey && !undoButton.disabled) {
        event.preventDefault();
        undoButton.click();
      } else if ((key === 'y' || (key === 'z' && event.shiftKey)) && !redoButton.disabled) {
        event.preventDefault();
        redoButton.click();
      }
    });

    function renderHistoryButtons() {
      undoButton.disabled = state.busy || aiIsRunning() || history.index <= 0;
      redoButton.disabled = state.busy || aiIsRunning() || history.index >= history.states.length - 1;
    }

    // ---- Removing (popup, list) --------------------------------------------------------------
    function removeArea(layer) {
      map.closePopup();
      layer.pm.disable();
      map.removeLayer(layer);
      markChanged();
      if (toggles.edit && !areas().length) setEditing(false);
      showMessage(`${layer.options.droneLabel} removed from the mangrove areas. Use Undo at the top of the map to bring it back.`);
    }

    function openAreaPopup(layer, latlng) {
      const box = document.createElement('div');
      box.style.cssText = 'display:grid;gap:6px;font:13px Manrope,sans-serif;min-width:180px;max-width:240px';
      const title = document.createElement('strong');
      title.textContent = `${layer.options.droneLabel} · ${ringAreaHa(outerRing(layer)).toFixed(2)} ha`;
      if (editor.read_only) { // approved: view only, nothing to edit or remove
        box.append(title);
        L.popup({ closeButton: true }).setLatLng(latlng).setContent(box).openOn(map);
        return;
      }
      const hint = document.createElement('span');
      hint.style.color = '#4c6b5d';
      hint.textContent = 'Not mangrove? Remove it.';
      box.append(title, hint);
      if (!toggles.edit) {
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'drone-editor-btn';
        edit.innerHTML = '<i class="bi bi-pencil"></i> Edit corners';
        edit.addEventListener('click', () => setEditing(true));
        box.append(edit);
      }
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'drone-editor-btn';
      remove.style.color = '#b42318';
      remove.innerHTML = '<i class="bi bi-x-circle"></i> Remove this area';
      remove.addEventListener('click', () => removeArea(layer));
      box.append(remove);
      L.popup({ closeButton: true }).setLatLng(latlng).setContent(box).openOn(map);
    }

    function renderList() {
      if (!aiList) return; // the panel no longer lists the areas; they are edited on the map
      aiList.replaceChildren();
      areas().forEach(layer => {
        const item = document.createElement('li');
        item.className = 'drone-editor-shape is-ai';
        const label = document.createElement('button');
        label.type = 'button';
        label.className = 'drone-editor-shape-label';
        label.title = 'Zoom to this area';
        label.textContent = `${layer.options.droneLabel} · ${ringAreaHa(outerRing(layer)).toFixed(2)} ha`;
        label.addEventListener('click', () => {
          setSheetExpanded(false); // mobile: drop the sheet so the area is visible
          map.fitBounds(layer.getBounds(), fitOptions(60));
        });
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'drone-editor-shape-remove';
        remove.setAttribute('aria-label', `Remove ${layer.options.droneLabel}`);
        remove.title = 'Remove this area';
        remove.innerHTML = '<i class="bi bi-x-lg"></i>';
        remove.addEventListener('click', () => removeArea(layer));
        item.addEventListener('mouseenter', () => { if (!areasHidden) layer.setStyle({ weight: 4 }); });
        item.addEventListener('mouseleave', () => { if (!areasHidden) layer.setStyle({ weight: AREA_STYLE.weight }); });
        item.append(label);
        if (!editor.read_only) item.append(remove);
        aiList.appendChild(item);
      });
      aiList.hidden = !aiList.childElementCount;
    }

    map.on('pm:create', event => {
      watchArea(event.layer);
      markChanged();
      showMessage(`${event.layer.options.droneLabel} added. Draw another area, or click Draw again to stop.`);
      if (areas().length >= editor.max_polygons) setDrawing(false);
    });

    // ---- AI mangrove areas -----------------------------------------------------------------
    // The stored coordinates may contain an offset the imagery had in an earlier version (stats.offset);
    // move them back so they line up with the preview at the GeoTIFF's position.
    function aiLatLngs(feature) {
      const stored = ai.stats?.offset || { east_m: 0, north_m: 0 };
      const shift = metresToDegrees(state.offset.east_m - (Number(stored.east_m) || 0), state.offset.north_m - (Number(stored.north_m) || 0));
      return (feature.coords || []).map(([lat, lng]) => L.latLng(lat + shift.lat, lng + shift.lng));
    }

    async function loadAiAreas() {
      if (ai.status !== 'ready' || !ai.url) return;
      try {
        const response = await fetch(ai.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'Could not load the mangrove areas.');
        if (body.stats) ai.stats = body.stats;
        setEditing(false);
        setDrawing(false);
        areas().forEach(layer => map.removeLayer(layer));
        areaCounter = 0;
        (body.features || [])
          .filter(feature => Array.isArray(feature.coords) && feature.coords.length >= 3)
          .slice(0, editor.max_polygons)
          .forEach(feature => addArea(aiLatLngs(feature)));
        ai.loaded = true;
        saved.areas = areasSnapshot();
        resetHistory();
      } catch (error) {
        showMessage(error.message, true);
      }
      refresh();
    }

    // ---- Pinpointed canopies ----------------------------------------------------------------
    const CANOPY_PIN_MAX_ZOOM = 18; // centre pins below this zoom; from here on the canopy shapes are big enough
    const GENUS_COLORS = { rhizophora: '#e4572e', avicennia: '#2e86de', sonneratia: '#f5b700', nypa: '#8e44ad', bruguiera: '#16a085', ceriops: '#d35400' };
    const FALLBACK_GENUS_COLORS = ['#ff6f91', '#00b8a9', '#f8a100', '#7b61ff', '#4caf50', '#ff3d3d'];
    const canopyToggle = document.getElementById('droneEditorCanopyToggle');
    const canopyLegend = document.getElementById('droneEditorCanopyLegend');
    const canopyPins = L.layerGroup();
    const canopyOutlines = L.layerGroup();
    const genusColorCache = new Map();

    function canopyColor(genus) {
      const key = String(genus || '').trim().toLowerCase();
      if (GENUS_COLORS[key]) return GENUS_COLORS[key];
      if (!genusColorCache.has(key)) genusColorCache.set(key, FALLBACK_GENUS_COLORS[genusColorCache.size % FALLBACK_GENUS_COLORS.length]);
      return genusColorCache.get(key);
    }

    const escapeText = text => String(text ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

    // The canopies were placed at the AI run's offset (payload.offset): shift them like the areas (aiLatLngs).
    function canopyLatLng(point, stored) {
      const shift = metresToDegrees(state.offset.east_m - (Number(stored?.east_m) || 0), state.offset.north_m - (Number(stored?.north_m) || 0));
      return L.latLng(point[0] + shift.lat, point[1] + shift.lng);
    }

    // A canopy can be several separate patches (rings; older results only have "coords", a single ring).
    // Each patch is its own shape and can be removed on its own ("r:" + its first point); a canopy without
    // an outline is removed as a whole ("c:" + its centre). A canopy with every patch removed is gone.
    const canopyRings = canopy => (Array.isArray(canopy.rings) ? canopy.rings : [canopy.coords])
      .filter(ring => Array.isArray(ring) && ring.length >= 3);
    const ringRemovalKey = ring => `r:${canopyKey(ring[0])}`;
    const canopyRemovalKey = canopy => `c:${canopyKey(canopy.center)}`;
    const remainingRings = canopy => canopyRings(canopy).filter(ring => !removedCanopies.has(ringRemovalKey(ring)));
    function canopyRemoved(canopy) {
      if (!Array.isArray(canopy.center) || canopy.center.length < 2) return true;
      if (removedCanopies.has(canopyRemovalKey(canopy))) return true;
      return canopyRings(canopy).length > 0 && remainingRings(canopy).length === 0;
    }

    function buildCanopyLayers() {
      canopyPins.clearLayers();
      canopyOutlines.clearLayers();
      const payload = canopies.payload;
      if (!payload) return;
      (payload.canopies || []).forEach(canopy => {
        if (canopyRemoved(canopy)) return;
        const color = canopyColor(canopy.genus);
        const percent = Math.round((Number(canopy.confidence) || 0) * 100);
        const label = `${escapeText(canopy.genus || 'Mangrove')} · ${percent}%`;
        const rings = remainingRings(canopy);
        const pin = L.circleMarker(canopyLatLng(canopy.center, payload.offset), {
          pane: 'droneCanopies', radius: 5, color: '#fff', weight: 1.5, fillColor: color, fillOpacity: 0.95,
        });
        pin.bindTooltip(label, { direction: 'top', offset: [0, -6], className: 'drone-canopy-tooltip' });
        pin.bindPopup(() => canopyPopup(canopy, percent, null));
        if (!rings.length) { // no outline: the pin is the canopy at every zoom
          canopyOutlines.addLayer(pin);
          return;
        }
        canopyPins.addLayer(pin);
        // Every patch is its own clickable shape, like a mangrove area (details and "Remove this canopy").
        rings.forEach(ring => {
          const shape = L.polygon(ring.map(point => canopyLatLng(point, payload.offset)), {
            pane: 'droneCanopies', color, weight: 2, fillColor: color, fillOpacity: 0.25, bubblingMouseEvents: false,
          });
          shape.bindTooltip(label, { sticky: true, className: 'drone-canopy-tooltip' });
          shape.bindPopup(() => canopyPopup(canopy, percent, ring));
          shape.on('mouseover', () => shape.setStyle({ weight: 4, fillOpacity: 0.4 }));
          shape.on('mouseout', () => shape.setStyle({ weight: 2, fillOpacity: 0.25 }));
          canopyOutlines.addLayer(shape);
        });
      });
    }

    // Details, plus "Remove this canopy" while the imagery can still be edited (like the areas' popup).
    // ring: the clicked patch (removes only that shape); null for the pin (removes the whole canopy).
    function canopyPopup(canopy, percent, ring) {
      const box = document.createElement('div');
      box.style.cssText = 'display:grid;gap:6px;font:13px Manrope,sans-serif;min-width:180px;max-width:240px';
      const details = document.createElement('div');
      const patches = remainingRings(canopy).length;
      const areaM2 = ring ? ringAreaHa(ring.map(([lat, lng]) => L.latLng(lat, lng))) * 10000 : canopy.area_m2;
      details.innerHTML = `<strong>${escapeText(canopy.genus || 'Mangrove')}</strong><br>Confidence ${percent}%`
        + (areaM2 != null ? `<br>Canopy about ${Number(areaM2).toFixed(1)} m²` : '');
      box.append(details);
      if (!editor.read_only && !aiIsRunning()) {
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'drone-editor-btn';
        remove.style.color = '#b42318';
        remove.innerHTML = !ring && patches > 1
          ? `<i class="bi bi-x-circle"></i> Remove all ${patches} shapes`
          : '<i class="bi bi-x-circle"></i> Remove this canopy';
        remove.addEventListener('click', () => removeCanopy(canopy, ring));
        box.append(remove);
      }
      return box;
    }

    function removeCanopy(canopy, ring) {
      map.closePopup();
      if (ring) {
        removedCanopies.add(ringRemovalKey(ring));
      } else {
        const rings = remainingRings(canopy);
        if (rings.length) rings.forEach(each => removedCanopies.add(ringRemovalKey(each)));
        else removedCanopies.add(canopyRemovalKey(canopy));
      }
      buildCanopyLayers();
      markChanged();
      showMessage(`${canopy.genus || 'Mangrove'} canopy removed. Use Undo at the top of the map to bring it back.`);
    }

    // Canopy counts as the owner sees them: the AI's minus the canopies with nothing left.
    function visibleCanopyCounts() {
      const payload = canopies.payload;
      const byGenus = { ...(payload?.by_genus || ai.stats?.canopies_by_genus || {}) };
      let removed = 0;
      (payload?.canopies || []).forEach(canopy => {
        if (!Array.isArray(canopy.center) || !canopyRemoved(canopy)) return;
        removed++;
        if (canopy.genus && Number(byGenus[canopy.genus]) > 0) byGenus[canopy.genus] = Number(byGenus[canopy.genus]) - 1;
      });
      const total = Number(ai.stats?.canopy_count ?? payload?.count ?? 0) - removed;
      const kept = Number(payload?.kept || (payload?.canopies || []).length) - removed;
      return { total: Math.max(0, total), kept: Math.max(0, kept), byGenus };
    }

    // Save draft / Confirm: the removed canopies are saved like the area corrections.
    async function saveCanopies() {
      if (!canopiesChanged() || !canopies.url) return;
      const sent = new Set(removedCanopies);
      const points = prefix => [...sent].filter(key => key.startsWith(prefix)).map(key => key.slice(2).split(',').map(Number));
      const body = await send(canopies.url, 'PUT', { removed: points('c:'), removed_rings: points('r:') });
      canopies.payload = canopies.payload ? { ...canopies.payload, ...body } : null;
      ai.stats = { ...(ai.stats || {}), canopy_count: body.count, canopies_by_genus: body.by_genus };
      sent.forEach(key => removedCanopies.delete(key));
      forgetSavedCanopies(sent);
      buildCanopyLayers();
    }

    function renderCanopyLegend() {
      if (!canopyLegend) return;
      const payload = canopies.payload;
      if (!canopies.shown || !payload) {
        canopyLegend.hidden = true;
        canopyLegend.replaceChildren();
        return;
      }
      canopyLegend.replaceChildren();
      const counts = visibleCanopyCounts();
      const byGenus = Object.entries(counts.byGenus).filter(([, count]) => Number(count) > 0).sort((a, b) => b[1] - a[1]);
      byGenus.forEach(([genus, count]) => {
        const chip = document.createElement('span');
        const dot = document.createElement('i');
        dot.style.background = canopyColor(genus);
        dot.setAttribute('aria-hidden', 'true');
        chip.append(dot, document.createTextNode(`${genus} ${Number(count).toLocaleString()}`));
        canopyLegend.append(chip);
      });
      const note = document.createElement('small');
      const { total, kept } = counts;
      // How to use them is on the card; the note only says when not every canopy is drawn.
      if (kept < total) {
        note.textContent = `Showing the ${kept.toLocaleString()} largest of ${total.toLocaleString()}.`;
        canopyLegend.append(note);
      }
      canopyLegend.hidden = false;
    }

    function syncCanopyLayers() {
      // The shapes are always shown; the centre pins only when zoomed out, where the shapes get too small to spot.
      const showOutlines = canopies.shown && Boolean(canopies.payload);
      const showPins = showOutlines && map.getZoom() < CANOPY_PIN_MAX_ZOOM;
      if (showPins && !map.hasLayer(canopyPins)) canopyPins.addTo(map);
      if (!showPins && map.hasLayer(canopyPins)) map.removeLayer(canopyPins);
      if (showOutlines && !map.hasLayer(canopyOutlines)) canopyOutlines.addTo(map);
      if (!showOutlines && map.hasLayer(canopyOutlines)) map.removeLayer(canopyOutlines);
    }
    map.on('zoomend', syncCanopyLayers);

    function renderCanopies() {
      if (!canopyToggle) return;
      const available = ai.status === 'ready' && Boolean(canopies.url);
      canopyToggle.hidden = !available;
      canopyToggle.setAttribute('aria-pressed', canopies.shown ? 'true' : 'false');
      canopyToggle.disabled = canopies.loading;
      const count = visibleCanopyCounts().total;
      // Its card (step 1) shows whether the classification is on the map, and how many canopies it has.
      const card = document.getElementById('droneEditorCanopyCard');
      if (card) {
        card.hidden = !available;
        card.classList.toggle('is-on', canopies.shown && !canopies.loading);
      }
      const countElement = document.getElementById('droneEditorCanopyCount');
      if (countElement) countElement.textContent = count ? `· ${count.toLocaleString()} canop${count === 1 ? 'y' : 'ies'}` : '';
      canopyToggle.innerHTML = canopies.loading ? '<i class="bi bi-hourglass-split"></i> Loading…'
        : (canopies.shown ? '<i class="bi bi-eye-slash"></i> Hide' : '<i class="bi bi-eye"></i> Show');
      canopyToggle.setAttribute('aria-label', canopies.shown ? 'Hide mangrove classification' : 'Show mangrove classification');
      canopyToggle.title = canopies.shown ? 'Hide on the map' : 'Show on the map';
      syncCanopyLayers();
      renderCanopyLegend();
    }

    async function loadCanopies() {
      if (!canopies.url || canopies.loading) return;
      canopies.loading = true;
      renderCanopies();
      try {
        const response = await fetch(canopies.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'Could not load the canopies.');
        canopies.payload = body;
        buildCanopyLayers();
      } catch (error) {
        canopies.shown = false;
        showMessage(error.message, true);
      }
      canopies.loading = false;
      renderCanopies();
    }

    // A new AI run (or a reloaded result) replaces the canopies.
    function resetCanopies(url) {
      canopies.url = url || null;
      canopies.payload = null;
      canopies.shown = false;
      removedCanopies.clear();
      history.states.forEach(snapshot => { snapshot.canopies = []; });
      canopyPins.clearLayers();
      canopyOutlines.clearLayers();
      renderCanopies();
    }

    // Opening view (and after an AI run): the mangrove classification first; the AI delineation stays
    // hidden until "Show AI delineation". Without a classification the delineation shows as before.
    async function showClassificationFirst() {
      if (!canopies.url || canopies.shown) return;
      if (areas().length) {
        setDrawing(false);
        setEditing(false);
        areasHidden = true;
        areas().forEach(applyAreaVisibility);
      }
      canopies.shown = true;
      await loadCanopies();
      if (!canopies.shown && areasHidden) { // the classification failed to load: show the delineation instead
        areasHidden = false;
        areas().forEach(applyAreaVisibility);
      }
      refresh();
    }

    canopyToggle?.addEventListener('click', async () => {
      canopies.shown = !canopies.shown;
      if (canopies.shown && !canopies.payload) {
        await loadCanopies();
        return;
      }
      renderCanopies();
    });

    function renderAi() {
      aiStatusElement.classList.toggle('is-failed', ai.status === 'failed');
      renderCanopies();
      aiRunButton.hidden = editor.read_only || aiIsRunning();
      if (aiRunButton.dataset.loading !== 'true') { // keep the busy label while the request runs
        aiRunButton.innerHTML = `<i class="bi bi-cpu"></i> ${ai.status === 'ready' ? 'Re-run AI' : (ai.status === 'failed' ? 'Retry AI detection' : 'Run AI detection')}`;
      }

      const all = areas();
      const totalHa = all.reduce((sum, layer) => sum + ringAreaHa(outerRing(layer)), 0);
      const unsavedAreas = areasChanged();
      const edited = unsavedAreas || ai.stats?.edited;
      if (ai.status === 'queued' || ai.status === 'processing') {
        aiStatusElement.textContent = ai.status === 'queued'
          ? 'Waiting for the AI to start…'
          : 'AI is detecting mangroves… (a few minutes)';
      } else if (ai.status === 'failed' && !all.length) {
        aiStatusElement.textContent = `AI detection failed: ${ai.error || 'unknown error'}. You can retry it, or draw the mangrove areas yourself.`;
      } else if (all.length) {
        const canopyCount = Number(ai.stats?.canopy_count ?? 0);
        aiStatusElement.textContent = `${all.length} mangrove area${all.length === 1 ? '' : 's'}, ${totalHa.toFixed(2)} ha`
          + (edited ? ' · corrected by you' : ' · as detected by the AI')
          + (unsavedAreas ? ' (not saved yet)' : '')
          + (canopyCount > 0 ? ` · ${canopyCount.toLocaleString()} canop${canopyCount === 1 ? 'y' : 'ies'} pinpointed` : '');
      } else if (ai.status === 'ready' && ai.loaded) {
        aiStatusElement.textContent = 'No mangrove areas found. Draw any the AI missed.';
      } else if (ai.status === 'ready') {
        aiStatusElement.textContent = 'Loading mangrove areas…';
      } else {
        aiStatusElement.textContent = 'AI detection has not run on this imagery yet. Run it, or draw the mangrove areas yourself.';
      }
    }

    function scheduleAiPoll() {
      clearTimeout(ai.pollTimer);
      if (!aiIsRunning()) return;
      ai.pollTimer = setTimeout(async () => {
        try {
          const response = await fetch(editor.status_url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
          const body = await response.json();
          if (response.ok) {
            ai.status = body.segmentation_status;
            ai.stats = body.segmentation_stats;
            ai.error = body.segmentation_error;
            ai.url = body.segmentation_url;
            if (ai.status === 'ready') {
              resetCanopies(body.canopies_url);
              await loadAiAreas();
              await showClassificationFirst();
            }
          }
        } catch (error) {
          console.error('Unable to refresh the AI detection status.', error);
        }
        refresh();
        scheduleAiPoll();
      }, 6000);
    }

    aiRunButton.addEventListener('click', async () => {
      const replacing = areas().length && (areasChanged() || ai.stats?.edited);
      // Confirmation → Processing → Success or Error (partials.action-feedback); errors use the panel's message line.
      const outcome = await window.AppActions.run({
        confirm: replacing
          ? { title: 'Run the AI again?', message: 'Your area corrections will be replaced.', confirmLabel: 'Run AI', tone: 'danger' }
          : (ai.status === 'ready'
            ? { title: 'Run AI detection again?', message: 'The current areas will be replaced.', confirmLabel: 'Run AI' }
            : { title: 'Run AI detection?', message: 'Takes a few minutes.', confirmLabel: 'Run AI' }),
        button: aiRunButton,
        busyLabel: 'Starting AI...',
        lock: [saveButton, confirmButton, drawToggle, editToggle],
        errorMessage: false,
        task: async () => {
          const response = await fetch(editor.segment_url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            credentials: 'same-origin',
          });
          const body = await response.json().catch(() => ({}));
          if (!response.ok) throw new Error(body.message || 'Could not start AI detection.');
        },
      });
      if (outcome.status === 'done') {
        ai.status = 'queued';
        ai.loaded = false;
        resetCanopies(null);
        setEditing(false);
        setDrawing(false);
        areas().forEach(layer => map.removeLayer(layer));
        saved.areas = areasSnapshot(); // the new AI result replaces them; nothing to save
        resetHistory();
        scheduleAiPoll();
        showMessage('AI detection started. The mangrove areas appear here when it is done.');
      } else if (outcome.status === 'failed') {
        showMessage(outcome.error.message, true);
      }
      refresh();
    });

    // ---- Status, saving and confirming -----------------------------------------------------
    function showMessage(text, isError = false) {
      messageElement.textContent = text;
      messageElement.classList.toggle('is-error', isError);
      // On mobile the message sits below the fold of the peeking sheet: bring it into view.
      if (isError && text && isMobileSheet()) {
        setSheetExpanded(true);
        messageElement.scrollIntoView({ block: 'nearest' });
      }
    }

    function renderStatus() {
      statusElement.classList.toggle('is-failed', editor.status === 'failed');
      if (editor.status === 'failed') {
        statusElement.textContent = `Adding this imagery to the map failed: ${editor.error_message || 'unknown error'}. Confirm again to retry.`;
      } else if (editor.read_only) {
        statusElement.textContent = 'Approved and on the map. View only.';
      } else if (editor.status === 'ready' && editor.needs_delineation) {
        statusElement.textContent = 'Not submitted yet. Choose the genus and confirm.';
      } else if (editor.status === 'ready') {
        statusElement.textContent = editor.has_pending_edits
          ? 'Saved changes not applied yet. Confirm to apply.'
          : 'Processed. Edit its delineation on the map.';
      } else {
        statusElement.textContent = editor.needs_expert_review
          ? 'Not on the map yet. Fix the areas, choose the genus, then submit.'
          : 'Not on the map yet. Fix the areas, choose the genus, then publish.';
      }
    }

    function refresh() {
      scheduleGenusPreview(); // before rendering, so the list shows "Updating…" right after an edit
      renderList();
      renderAi();
      renderToggles();
      renderHistoryButtons();
      renderActionButtons();
      renderLocation();
    }

    // ---- 2. Details (first confirm only): submitted with the imagery ------------------
    const generaBox = document.getElementById('droneEditorGenera');
    const otherGeneraInput = document.getElementById('droneEditorOtherGenera');
    const addGenusButton = document.getElementById('droneEditorAddGenus');
    const selectedGeneraContainer = document.getElementById('droneEditorSelectedGenera');
    const customGenera = [];

    function selectedGenera() {
      if (!generaBox) return [];
      const picked = [...generaBox.querySelectorAll('.drone-editor-genus[aria-pressed="true"]')]
        .map(button => button.dataset.genus)
        .filter(Boolean);
      const other = (otherGeneraInput?.value || '').split(',').map(value => value.trim()).filter(Boolean);
      return [...new Set([...picked, ...customGenera, ...other])];
    }

    function renderCustomGenera() {
      if (!selectedGeneraContainer) return;
      selectedGeneraContainer.replaceChildren(...customGenera.map(genus => {
        const tag = document.createElement('span');
        tag.className = 'drone-editor-selected-genus';

        const name = document.createElement('span');
        name.textContent = genus;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.title = `Remove ${genus}`;
        remove.setAttribute('aria-label', `Remove ${genus}`);
        remove.addEventListener('click', () => {
          customGenera.splice(customGenera.indexOf(genus), 1);
          renderCustomGenera();
          renderActionButtons();
        });

        tag.append(name, remove);
        return tag;
      }));
    }

    function addCustomGenera() {
      if (!otherGeneraInput) return;
      const entered = otherGeneraInput.value
        .split(',')
        .map(value => value.trim())
        .filter(Boolean)
        .map(value => value.charAt(0).toUpperCase() + value.slice(1));
      const selected = [...(generaBox
        ? [...generaBox.querySelectorAll('.drone-editor-genus[aria-pressed="true"]')]
          .map(button => button.dataset.genus)
          .filter(Boolean)
        : []), ...customGenera];
      const existing = new Set(selected.map(genus => genus.toLowerCase()));

      entered.forEach(genus => {
        if (!existing.has(genus.toLowerCase())) {
          customGenera.push(genus);
          existing.add(genus.toLowerCase());
        }
      });

      otherGeneraInput.value = '';
      renderCustomGenera();
      renderActionButtons();
    }

    // AI genus suggestion (genus model run with the extent detection). Only a hint: nothing is selected
    // until the owner clicks a chip. DOM lookups only, since renderActionButtons() can run before this
    // section's constants are initialised.
    function chosenGeneraFromDom() {
      const pills = [...document.querySelectorAll('#droneEditorGenera .drone-editor-genus[aria-pressed="true"]')]
        .map(button => button.dataset.genus);
      const tags = [...document.querySelectorAll('#droneEditorSelectedGenera .drone-editor-selected-genus > span')]
        .map(span => span.textContent);
      return new Set([...pills, ...tags].filter(Boolean).map(genus => genus.toLowerCase()));
    }

    function chooseSuggestedGenus(genus) {
      const button = generaBox
        ? [...generaBox.querySelectorAll('.drone-editor-genus')].find(item => item.dataset.genus?.toLowerCase() === genus.toLowerCase())
        : null;
      if (button) {
        setGenusPressed(button, true);
      } else if (!customGenera.some(item => item.toLowerCase() === genus.toLowerCase())) {
        customGenera.push(genus);
        renderCustomGenera();
      }
      renderActionButtons();
    }

    // ---- Live genus suggestion -------------------------------------------------------------------
    // Shortly after the areas change, the unsaved areas are recounted on the server from the AI run's
    // genus map (genus_preview_url: nothing is saved) and the list follows them straight away.
    // (State: genusPreview, declared next to `ai` because refresh() can run before this point.)
    function genusSuggestionFollowsEdits() {
      return editor.needs_delineation && !editor.read_only && Boolean(editor.genus_preview_url)
        && ai.status === 'ready' && ai.stats?.genus_suggestion?.recountable === true;
    }

    function scheduleGenusPreview() {
      if (!genusSuggestionFollowsEdits() || !areasChanged()) {
        clearTimeout(genusPreview.timer);
        genusPreview.snapshot = null; // back to the saved areas: the stored suggestion applies again
        genusPreview.suggestion = null;
        genusPreview.loading = false;
        genusPreview.failed = false;
        return;
      }
      const snapshot = areasSnapshot();
      if (snapshot === genusPreview.snapshot) return; // already counted (or being counted) for these areas
      genusPreview.snapshot = snapshot;
      genusPreview.loading = true;
      genusPreview.failed = false;
      clearTimeout(genusPreview.timer);
      genusPreview.timer = setTimeout(() => requestGenusPreview(snapshot), GENUS_PREVIEW_DELAY_MS);
    }

    async function requestGenusPreview(snapshot) {
      const sequence = ++genusPreview.sequence;
      try {
        const body = await send(editor.genus_preview_url, 'POST', {
          areas: areas().map(toLngLat),
          offset: state.offset,
        });
        // A newer edit (or a save) happened meanwhile: that one's result is what counts.
        if (sequence !== genusPreview.sequence || snapshot !== genusPreview.snapshot) return;
        genusPreview.suggestion = body.genus_suggestion || null;
        genusPreview.failed = Boolean(body.genus_suggestion?.recount_failed);
      } catch (error) {
        if (sequence !== genusPreview.sequence || snapshot !== genusPreview.snapshot) return;
        genusPreview.failed = true;
      }
      genusPreview.loading = false;
      renderGenusSuggestion();
    }

    /** The suggestion to show: the live one for unsaved area edits once it is in, else the saved one. */
    function currentGenusSuggestion() {
      const stored = ai.stats?.genus_suggestion;
      if (genusPreview.suggestion && genusPreview.snapshot === areasSnapshot() && areasChanged()) {
        return genusPreview.suggestion;
      }
      return stored;
    }

    function genusSuggestionFreshness(suggestion) {
      const edited = Boolean(ai.stats?.edited);
      if (suggestion.recountable === false || (suggestion.recountable === undefined && edited)) {
        return edited
          ? 'Based on the AI’s original areas. Re-run AI to follow your edits (replaces them).'
          : null;
      }
      if (areasChanged()) {
        if (genusPreview.loading) return 'Updating…';
        if (genusPreview.failed) return 'Could not update. Showing the previous result.';
        return 'Updated for your changes.';
      }
      if (suggestion.recount_failed) return 'Could not update. Showing the previous result.';
      if (suggestion.recounted_at) return 'Updated for your edits.';
      return null;
    }

    function renderGenusSuggestion() {
      const box = document.getElementById('droneEditorGenusSuggestion');
      if (!box) return;
      const suggestion = editor.needs_delineation && !editor.read_only ? currentGenusSuggestion() : null;
      if (!suggestion || ai.status !== 'ready') {
        box.hidden = true;
        box.replaceChildren();
        return;
      }

      box.replaceChildren();
      box.hidden = false;
      if (suggestion.error) {
        const message = document.createElement('p');
        message.textContent = 'No AI suggestion for this imagery. Choose the genus yourself.';
        box.append(message);
        return;
      }

      // One list: every genus with its probability (detections down to 0.01), tagged "Confident" when the
      // stricter check (ml.genus.confidence) also found it. Older results only have the confident list.
      const genera = Array.isArray(suggestion.genera) ? suggestion.genera : [];
      const confident = new Map(genera.map(item => [String(item.genus || '').toLowerCase(), item]));
      let rows = Array.isArray(suggestion.probabilities) ? suggestion.probabilities : [];
      if (!rows.length) {
        rows = genera.map(item => ({ genus: item.genus, probability: Number(item.share_percent || 0) / 100 }));
      }
      if (!rows.length) {
        const message = document.createElement('p');
        message.textContent = 'The AI found no genus here. Choose it yourself.';
        box.append(message);
        return;
      }

      const chosen = chosenGeneraFromDom();
      const section = document.createElement('div');
      section.className = 'drone-editor-genus-probabilities';

      const heading = document.createElement('p');
      heading.innerHTML = '<strong>AI genus suggestion</strong> <span class="drone-editor-genus-badge">Experimental</span>'
        + '<br><small>Click to add.</small>';
      section.append(heading);

      // The suggestion is recounted for the corrected areas when they are saved (from the AI run's genus map).
      const freshness = genusSuggestionFreshness(suggestion);
      if (freshness) {
        const status = document.createElement('p');
        status.className = 'drone-editor-genus-freshness';
        status.textContent = freshness;
        section.append(status);
      }

      // Every genus found gets a row (clickable), small ones included. Genera that would show as 0.0%
      // were not found in these areas, so they are left out (they can still be picked from the list below).
      rows.forEach(item => {
        const genus = String(item.genus || '').trim();
        if (!genus) return;
        const percent = Math.max(0, Math.min(100, Number(item.probability || 0) * 100));
        if (percent < 0.05) return;
        const isChosen = chosen.has(genus.toLowerCase());
        // "Confident" needs confident detections covering a real part of the area, not a few pixels.
        const confidentItem = confident.get(genus.toLowerCase());
        const sure = confidentItem && Number(confidentItem.share_percent || 0) >= GENUS_MIN_CONFIDENT_PERCENT ? confidentItem : null;

        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'drone-editor-genus-probability';
        row.disabled = isChosen;
        row.title = isChosen ? `${genus} is selected` : `Select ${genus}`;

        // + / check icon so the rows read as buttons.
        const icon = document.createElement('i');
        icon.className = isChosen ? 'bi bi-check2' : 'bi bi-plus-lg';
        icon.setAttribute('aria-hidden', 'true');
        const name = document.createElement('span');
        name.className = 'drone-editor-genus-probability-name';
        name.textContent = genus;
        const track = document.createElement('span');
        track.className = 'drone-editor-genus-probability-track';
        const fill = document.createElement('span');
        fill.className = 'drone-editor-genus-probability-fill';
        fill.style.width = `${percent.toFixed(1)}%`;
        track.append(fill);
        const value = document.createElement('span');
        value.className = 'drone-editor-genus-probability-value';
        value.textContent = `${percent.toFixed(percent < 10 ? 1 : 0)}%`;
        const tag = document.createElement('span');
        tag.className = `drone-editor-genus-probability-tag${sure ? ' is-confident' : ''}`;
        tag.textContent = sure ? `Confident · ${Math.round(Number(sure.mean_confidence || 0) * 100)}%` : 'Low confidence';

        row.setAttribute('aria-label', `${genus}: ${value.textContent}, ${tag.textContent}${isChosen ? ' (selected)' : ''}`);
        row.append(icon, name, track, value, tag);
        row.addEventListener('click', () => chooseSuggestedGenus(genus));
        section.append(row);
      });

      const note = document.createElement('p');
      note.textContent = suggestion.probability_basis === 'none'
        ? 'Nothing found, so all genera are equally likely.'
        : 'Still in training: check the imagery before choosing.';
      section.append(note);
      box.append(section);
    }

    const delineationNameInput = document.getElementById('droneEditorDelineationName');
    const delineationNotesInput = document.getElementById('droneEditorDelineationNotes');

    function delineationDetails() {
      return {
        name: delineationNameInput?.value.trim() || '',
        genera: selectedGenera(),
        notes: delineationNotesInput?.value.trim() || '',
      };
    }

    // "Save draft" keeps the details too; compare with what the server has to enable it.
    const detailsSnapshot = () => JSON.stringify(delineationDetails());
    function detailsChanged() {
      return editor.needs_delineation && saved.details !== undefined && detailsSnapshot() !== saved.details;
    }

    function setGenusPressed(button, selected) {
      button.setAttribute('aria-pressed', String(selected));
      button.classList.toggle('active', selected);
      const icon = button.querySelector('i');
      icon?.classList.toggle('bi-plus', !selected);
      icon?.classList.toggle('bi-check2', selected);
    }

    generaBox?.addEventListener('click', event => {
      const button = event.target.closest('.drone-editor-genus');
      if (!button) return;

      setGenusPressed(button, button.getAttribute('aria-pressed') !== 'true');
      renderActionButtons();
    });
    [delineationNameInput, delineationNotesInput].forEach(input => input?.addEventListener('input', () => renderActionButtons()));

    // Reopen with the details kept by "Save draft": listed genera as pressed pills, others as custom tags.
    (() => {
      const draft = editor.delineation_draft;
      if (!editor.needs_delineation || !draft) return;
      if (delineationNameInput && draft.name) delineationNameInput.value = draft.name;
      if (delineationNotesInput && draft.notes) delineationNotesInput.value = draft.notes;
      const buttons = generaBox ? [...generaBox.querySelectorAll('.drone-editor-genus')] : [];
      (draft.genera || []).forEach(genus => {
        const button = buttons.find(item => item.dataset.genus?.toLowerCase() === String(genus).toLowerCase());
        if (button) setGenusPressed(button, true);
        else if (!customGenera.some(item => item.toLowerCase() === String(genus).toLowerCase())) customGenera.push(genus);
      });
      renderCustomGenera();
    })();
    saved.details = detailsSnapshot();
    addGenusButton?.addEventListener('click', addCustomGenera);
    otherGeneraInput?.addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        addCustomGenera();
      }
    });
    otherGeneraInput?.addEventListener('input', () => renderActionButtons());

    // Why Confirm cannot be used yet (null = it can).
    function confirmBlocker(unsaved) {
      // The AI job reads the mosaic that confirming rebuilds from, so wait for it to finish.
      if (aiIsRunning()) return 'Wait for AI detection to finish.';
      if (ai.status === 'ready' && !ai.loaded) return 'Wait for the areas to load.';
      if (editor.needs_delineation) {
        const count = areas().length;
        if (!count) return 'Draw at least one area first.';
        if (count > editor.max_delineation_areas) {
          return `A delineation can hold up to ${editor.max_delineation_areas} areas; there are ${count}. Remove the smallest ones.`;
        }
        if (!selectedGenera().length) return 'Choose a genus first.';
        return null;
      }
      // Already on the map: only a rebuild with something new is worth confirming.
      if (editor.status === 'ready' && !unsaved && !editor.has_pending_edits) {
        return 'No changes to apply.';
      }
      return null;
    }

    const SAVE_IDLE_HTML = saveButton.innerHTML;
    const CONFIRM_IDLE_HTML = confirmButton.innerHTML;

    // Same states as the delineation's Save: disabled and dimmed until usable (with a tooltip saying
    // why), spinner and label while the request runs.
    function setActionButton(button, { disabled, busy, idleHtml, busyLabel, title }) {
      button.disabled = disabled || busy;
      button.classList.toggle('is-saving', busy);
      button.setAttribute('aria-busy', busy ? 'true' : 'false');
      button.innerHTML = busy ? `<span class="drone-editor-spinner" aria-hidden="true"></span> ${busyLabel}` : idleHtml;
      button.title = busy ? '' : (title || '');
    }

    function renderActionButtons() {
      renderGenusSuggestion(); // its chips show which suggested genera are already chosen
      const unsaved = hasUnsavedChanges();
      const blocker = confirmBlocker(unsaved);
      setActionButton(saveButton, {
        disabled: Boolean(state.busy) || !unsaved,
        busy: state.busy === 'save',
        idleHtml: SAVE_IDLE_HTML,
        busyLabel: 'Saving draft...',
        title: unsaved ? '' : (editor.needs_delineation
          ? 'No unsaved changes.'
          : 'No unsaved changes.'),
      });
      setActionButton(confirmButton, {
        disabled: Boolean(state.busy) || blocker !== null,
        busy: state.busy === 'confirm',
        idleHtml: CONFIRM_IDLE_HTML,
        busyLabel: 'Confirming...',
        title: blocker || '',
      });
    }

    async function send(url, method, body) {
      const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify(body),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(validationError || payload.message || `The server returned HTTP ${response.status}.`);
      }
      return payload;
    }

    // The whole image goes on the map at the GeoTIFF's position: no erase/crop polygons, no offset.
    function editsPayload() {
      return { mode: 'remove', polygons: [], offset: state.offset, opacity: state.opacity };
    }

    // Corrected mangrove areas are saved separately: they become the layer's AI result.
    async function saveAiAreas() {
      if (!areasChanged()) return;
      const sent = areasSnapshot();
      const body = await send(editor.segmentation_update_url, 'PUT', {
        areas: areas().map(toLngLat),
        offset: state.offset,
      });
      ai.status = body.segmentation_status;
      ai.stats = body.segmentation_stats;
      ai.url = body.segmentation_url;
      canopies.url = body.canopies_url || canopies.url; // the canopies are the model's: area edits keep them
      saved.areas = sent;
      if (body.location) editor.location = body.location;
      renderLocation();
    }

    // Section 3: barangay of the mangrove areas, as the delineation will get it (resolved by the server
    // from the saved areas; unsaved corrections are looked up on the next save).
    function renderLocation() {
      const locationElement = document.getElementById('droneEditorLocation'); // looked up here: refresh() can run first
      if (!locationElement) return;
      const location = editor.location || { text: null, status: 'none' };
      let text = location.text;
      if (!text) {
        text = {
          none: 'Shown once there are areas.',
          pending: 'Pending boundary import.',
        }[location.status] || 'Outside the loaded barangays.';
      }
      const stale = location.status !== 'none' && areasChanged();
      locationElement.textContent = stale ? `${text} (updates when you save)` : text;
      locationElement.classList.toggle('is-muted', !location.text);
    }

    // Confirmation (partials.action-feedback) → Processing (button label) → Success or Error (message line).
    saveButton.addEventListener('click', async () => {
      if (saveButton.disabled) return;
      const what = editor.needs_delineation
        ? 'Areas and details saved.'
        : 'Areas saved.';
      const confirmed = await window.AppActions.confirm({
        title: 'Save draft?',
        message: editor.needs_delineation || editor.status !== 'ready'
          ? `${what} You can come back and confirm later.`
          : `${what} They are shown on the map.`,
        confirmLabel: 'Save',
      });
      if (!confirmed || state.busy || !hasUnsavedChanges()) return;
      await performSaveDraft();
    });

    /** Saves the draft (areas, opacity, delineation details). true when everything was saved. */
    async function performSaveDraft() {
      state.busy = 'save';
      refresh();
      showMessage('Saving…');
      try {
        await saveAiAreas();
        await saveCanopies();
        const sentOpacity = state.opacity;
        const sentDetails = editor.needs_delineation ? delineationDetails() : null;
        const body = await send(editor.save_url, 'PUT', {
          ...editsPayload(),
          ...(sentDetails ? { delineation: sentDetails } : {}),
        });
        saved.opacity = sentOpacity;
        if (sentDetails) saved.details = JSON.stringify(sentDetails);
        if (body.name) { // the typed name also renames the imagery
          document.getElementById('droneEditorTitle').textContent = body.name;
          const sheetTitle = document.querySelector('.drone-editor-sheet-title');
          if (sheetTitle) sheetTitle.textContent = body.name;
        }
        editor.has_pending_edits = body.has_pending_edits;
        renderStatus();
        showMessage(editor.status === 'ready' && body.has_pending_edits
          ? 'Saved. Confirm to apply the other changes.'
          : 'Saved.');
        return true;
      } catch (error) {
        showMessage(error.message, true);
        return false;
      } finally {
        state.busy = null;
        refresh();
      }
    }

    confirmButton.addEventListener('click', async () => {
      if (confirmButton.disabled) return;
      let dialog;
      if (!editor.needs_delineation) {
        dialog = { title: 'Confirm?', message: 'The imagery is rebuilt with your changes (hidden meanwhile).', confirmLabel: 'Confirm' };
      } else if (editor.needs_expert_review) {
        dialog = { title: 'Submit for expert review?', message: 'An expert reviews your areas. Once approved, they appear on the map.', confirmLabel: 'Submit' };
      } else {
        dialog = { title: 'Publish?', message: 'It is processed, then published on the map.', confirmLabel: 'Publish' };
      }
      const confirmed = await window.AppActions.confirm(dialog);
      if (!confirmed || state.busy || confirmButton.disabled) return;

      state.busy = 'confirm';
      refresh();
      showMessage('Sending your edits…');
      try {
        await saveAiAreas();
        await saveCanopies();
        const body = await send(editor.confirm_url, 'POST', {
          ...editsPayload(),
          ...(editor.needs_delineation ? { delineation: delineationDetails() } : {}),
        });
        state.leaving = true;
        showMessage('Confirmed. Opening Drone Upload…');
        window.location.href = body.redirect_url || editor.back_url;
      } catch (error) {
        showMessage(error.message, true);
        state.busy = null;
        refresh();
      }
    });

    // ---- Leaving with unsaved changes ---------------------------------------------------------
    // Links and Logout inside the app ask first, offering to save the draft. Closing the tab, reloading or
    // typing another address can only get the browser's own "Leave site?" prompt (beforeunload below).
    function unsavedChangesSummary() {
      const parts = [];
      if (areasChanged()) parts.push('the mangrove areas');
      if (canopiesChanged()) parts.push('the canopies');
      if (detailsChanged()) parts.push('the details');
      if (opacityChanged()) parts.push('the imagery opacity');
      return parts.length > 1 ? `${parts.slice(0, -1).join(', ')} and ${parts[parts.length - 1]}` : parts[0];
    }

    async function confirmLeave(leave) {
      const answer = await window.AppActions.confirm({
        title: 'Leave without saving?',
        message: `You changed ${unsavedChangesSummary()}. Save a draft before leaving?`,
        confirmLabel: 'Save draft & leave',
        extraLabel: 'Leave without saving',
        cancelLabel: 'Stay on this page',
        icon: 'bi-exclamation-triangle',
        iconTone: 'danger', // red warning icon; the buttons stay as they are
      });
      if (answer === 'extra') {
        state.leaving = true;
        leave();
      } else if (answer === true) {
        if (state.busy) return;
        if (await performSaveDraft()) {
          state.leaving = true;
          leave();
        } // on failure the error shows in the panel and the page stays open
      }
    }

    document.addEventListener('click', event => {
      if (state.leaving || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const link = event.target.closest('a[href]');
      if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
      const url = new URL(link.href, window.location.href);
      // Same-page anchors and map controls (href="#") do not leave the page.
      if (url.origin !== window.location.origin || (url.pathname === window.location.pathname && url.search === window.location.search)) return;
      if (!hasUnsavedChanges()) return;
      event.preventDefault();
      event.stopPropagation();
      confirmLeave(() => { window.location.href = url.href; });
    }, true);

    document.addEventListener('submit', event => {
      const form = event.target;
      if (state.leaving || !(form instanceof HTMLFormElement) || !/\/logout$/.test(new URL(form.action, window.location.href).pathname)) return;
      if (!hasUnsavedChanges()) return;
      event.preventDefault();
      event.stopPropagation();
      confirmLeave(() => form.submit());
    }, true);

    window.addEventListener('beforeunload', event => {
      if (state.leaving || !hasUnsavedChanges()) return;
      event.preventDefault();
      event.returnValue = '';
    });

    renderStatus();
    resetHistory(); // starts empty; replaced when the AI areas load
    refresh();
    loadAiAreas().then(showClassificationFirst);
    scheduleAiPoll();
    setTimeout(() => map.invalidateSize(), 100);
  })();
</script>
