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
    const opacityInput = document.getElementById('droneEditorOpacity');
    const opacityValue = document.getElementById('droneEditorOpacityValue');
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
    const hasUnsavedChanges = () => areasChanged() || opacityChanged();

    const ai = {
      status: editor.segmentation_status,
      stats: editor.segmentation_stats,
      error: editor.segmentation_error,
      url: editor.segmentation_url,
      loaded: false,
      pollTimer: null,
    };
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

    // The preview sits below the mangrove areas but above the base map.
    map.createPane('dronePreview');
    map.getPane('dronePreview').style.zIndex = 350;
    map.getPane('dronePreview').style.pointerEvents = 'none';

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
    preview.on('error', () => showMessage('The imagery preview could not be loaded. Reload the page to try again.', true));
    // On mobile the peeking sheet covers the bottom of the map (min(355px, 45vh), see the view's CSS):
    // keep fitted content above it.
    function fitOptions(pad) {
      const covered = isMobileSheet() ? Math.min(355, window.innerHeight * 0.45) : 0;
      return { paddingTopLeft: [pad, pad], paddingBottomRight: [pad, pad + covered], maxZoom: MAP_MAX_ZOOM };
    }

    map.fitBounds(previewBounds(), fitOptions(30));

    document.getElementById('droneEditorBase').addEventListener('change', event => {
      map.removeLayer(currentBase);
      currentBase = baseLayers[event.target.value] || baseLayers.sat;
      map.addLayer(currentBase);
      currentBase.bringToBack();
    });

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

    // Toggle buttons in the top toolbar (next to Undo / Redo).
    const drawToggle = document.getElementById('droneEditorDrawToggle');
    const editToggle = document.getElementById('droneEditorEditToggle');

    const CORNER_EDIT_OPTIONS = {
      allowSelfIntersection: false,
      removeVertexOn: 'dblclick',
      removeLayerBelowMinVertexCount: false,
      removeVertexValidation: ({ layer: edited }) => {
        if (outerRing(edited).length > 3) return true;
        showMessage('An area needs at least 3 corners. Move this corner instead, or remove the whole area.', true);
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
      drawToggle.disabled = aiIsRunning();
      editToggle.disabled = aiIsRunning() || (!areas().length && !toggles.edit);
    }

    function setEditing(on) {
      if (on === toggles.edit) return;
      if (on) setDrawing(false);
      toggles.edit = on;
      map.closePopup();
      if (on) {
        map.doubleClickZoom.disable(); // so double-clicking a corner deletes it instead of zooming
        areas().forEach(enableCornerEditing);
        showMessage('Edit is on: drag a corner to move it, double-click a corner to delete it. Click Edit again when you are done.');
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
        showMessage('Draw is on: click on the map to place the corners, click the first corner to finish an area. Keep drawing, or click Draw again to stop.');
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
      layer.on('mouseover', () => layer.setStyle({ weight: 4 }));
      layer.on('mouseout', () => layer.setStyle({ weight: AREA_STYLE.weight }));
      if (toggles.edit) enableCornerEditing(layer);
      return layer;
    }

    function addArea(latLngs, label = null) {
      return watchArea(L.polygon(latLngs, AREA_STYLE).addTo(map), label);
    }

    // ---- Undo / redo (like the manual delineation): snapshots of all mangrove areas ----------
    // Every change (draw, remove, move or delete a corner) records a snapshot; undo/redo rebuilds the areas.
    const HISTORY_LIMIT = 100;
    const history = { states: [], index: -1 };

    function snapshotAreas() {
      return areas().map(layer => ({
        label: layer.options.droneLabel,
        latLngs: outerRing(layer).map(point => [point.lat, point.lng]),
      }));
    }

    function recordHistory() {
      const snapshot = snapshotAreas();
      const current = history.states[history.index];
      // Geoman can report one change twice (e.g. vertex removed + edit); keep a single step.
      if (current && JSON.stringify(current) === JSON.stringify(snapshot)) return;
      history.states = history.states.slice(0, history.index + 1);
      history.states.push(snapshot);
      if (history.states.length > HISTORY_LIMIT) history.states.shift();
      history.index = history.states.length - 1;
    }

    function resetHistory() {
      history.states = [snapshotAreas()];
      history.index = 0;
    }

    function restoreHistory(index) {
      if (index < 0 || index >= history.states.length || index === history.index) return;
      map.closePopup();
      areas().forEach(layer => {
        layer.pm.disable();
        map.removeLayer(layer);
      });
      history.states[index].forEach(area => addArea(area.latLngs.map(([lat, lng]) => L.latLng(lat, lng)), area.label));
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
      const hint = document.createElement('span');
      hint.style.color = '#4c6b5d';
      hint.textContent = 'Not mangrove? Remove it so it is left out of the delineation.';
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
        item.addEventListener('mouseenter', () => layer.setStyle({ weight: 4 }));
        item.addEventListener('mouseleave', () => layer.setStyle({ weight: AREA_STYLE.weight }));
        item.append(label, remove);
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
        if (!response.ok) throw new Error(body.message || 'Unable to load the AI mangrove areas.');
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

    function renderAi() {
      aiStatusElement.classList.toggle('is-failed', ai.status === 'failed');
      aiRunButton.hidden = aiIsRunning();
      if (aiRunButton.dataset.loading !== 'true') { // keep the busy label while the request runs
        aiRunButton.innerHTML = `<i class="bi bi-cpu"></i> ${ai.status === 'ready' ? 'Re-run AI' : (ai.status === 'failed' ? 'Retry AI detection' : 'Run AI detection')}`;
      }

      const all = areas();
      const totalHa = all.reduce((sum, layer) => sum + ringAreaHa(outerRing(layer)), 0);
      const unsavedAreas = areasChanged();
      const edited = unsavedAreas || ai.stats?.edited;
      if (ai.status === 'queued' || ai.status === 'processing') {
        aiStatusElement.textContent = ai.status === 'queued'
          ? 'Waiting for the AI to start detecting mangroves…'
          : 'The AI is detecting mangroves… This can take a few minutes. The areas appear here when it is done.';
      } else if (ai.status === 'failed' && !all.length) {
        aiStatusElement.textContent = `AI detection failed: ${ai.error || 'unknown error'}. You can retry it, or draw the mangrove areas yourself.`;
      } else if (all.length) {
        aiStatusElement.textContent = `${all.length} mangrove area${all.length === 1 ? '' : 's'}, ${totalHa.toFixed(2)} ha`
          + (edited ? ' · corrected by you' : ' · as detected by the AI')
          + (unsavedAreas ? ' (not saved yet)' : '');
      } else if (ai.status === 'ready' && ai.loaded) {
        aiStatusElement.textContent = 'No mangrove areas. Draw them yourself if the AI missed some.';
      } else if (ai.status === 'ready') {
        aiStatusElement.textContent = 'Loading the AI mangrove areas…';
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
            if (ai.status === 'ready') await loadAiAreas();
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
          ? { title: 'Run the AI again?', message: 'Your corrections to the mangrove areas are replaced by the new AI result.', confirmLabel: 'Run AI', tone: 'danger' }
          : (ai.status === 'ready'
            ? { title: 'Run AI detection again?', message: 'The current mangrove areas are replaced when it finishes.', confirmLabel: 'Run AI' }
            : { title: 'Run AI detection?', message: 'The AI detects the mangrove areas on this imagery. This can take a few minutes.', confirmLabel: 'Run AI' }),
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
          if (!response.ok) throw new Error(body.message || 'Unable to start AI detection.');
        },
      });
      if (outcome.status === 'done') {
        ai.status = 'queued';
        ai.loaded = false;
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

    // ---- Opacity ---------------------------------------------------------------------------
    opacityInput.value = String(Math.round(state.opacity * 100));
    opacityInput.addEventListener('input', () => {
      state.opacity = Number(opacityInput.value) / 100;
      preview.setOpacity(state.opacity);
      markChanged(false);
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
      } else if (editor.status === 'ready') {
        statusElement.textContent = editor.has_pending_edits
          ? 'This imagery is on the map, but saved changes to it are not applied yet. Confirm to rebuild it.'
          : 'This imagery is on the map. Mangrove area corrections apply as soon as you save.';
      } else {
        statusElement.textContent = 'Not on the map yet. Correct the mangrove areas, then confirm.';
      }
    }

    function refresh() {
      opacityValue.textContent = `${opacityInput.value}%`;
      renderList();
      renderAi();
      renderToggles();
      renderHistoryButtons();
      renderActionButtons();
    }

    // Why Confirm cannot be used yet (null = it can).
    function confirmBlocker(unsaved) {
      // The AI job reads the mosaic that confirming rebuilds from, so wait for it to finish.
      if (aiIsRunning()) return 'Available when AI detection has finished.';
      if (ai.status === 'ready' && !ai.loaded) return 'Available when the mangrove areas have loaded.';
      // Already on the map: only a rebuild with something new is worth confirming.
      if (editor.status === 'ready' && !unsaved && !editor.has_pending_edits) {
        return 'No changes to apply. Edit the mangrove areas or the appearance first.';
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
      const unsaved = hasUnsavedChanges();
      const blocker = confirmBlocker(unsaved);
      setActionButton(saveButton, {
        disabled: Boolean(state.busy) || !unsaved,
        busy: state.busy === 'save',
        idleHtml: SAVE_IDLE_HTML,
        busyLabel: 'Saving draft...',
        title: unsaved ? '' : 'No unsaved changes. Edit the mangrove areas or the appearance first.',
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
      saved.areas = sent;
    }

    // Confirmation (partials.action-feedback) → Processing (button label) → Success or Error (message line).
    saveButton.addEventListener('click', async () => {
      if (saveButton.disabled) return;
      const confirmed = await window.AppActions.confirm({
        title: 'Save draft?',
        message: editor.status === 'ready'
          ? 'Your mangrove area corrections and appearance are saved and shown on the map.'
          : 'Your mangrove area corrections and appearance are saved. You can come back and confirm later.',
        confirmLabel: 'Save',
      });
      if (!confirmed || state.busy || !hasUnsavedChanges()) return;
      state.busy = 'save';
      refresh();
      showMessage('Saving…');
      try {
        await saveAiAreas();
        const sentOpacity = state.opacity;
        const body = await send(editor.save_url, 'PUT', editsPayload());
        saved.opacity = sentOpacity;
        editor.has_pending_edits = body.has_pending_edits;
        renderStatus();
        showMessage(editor.status === 'ready' && body.has_pending_edits
          ? 'Saved. The mangrove areas and opacity are updated; confirm to apply the other changes.'
          : 'Saved.');
      } catch (error) {
        showMessage(error.message, true);
      } finally {
        state.busy = null;
        refresh();
      }
    });

    confirmButton.addEventListener('click', async () => {
      if (confirmButton.disabled) return;
      const confirmed = await window.AppActions.confirm(editor.status === 'ready'
        ? { title: 'Confirm?', message: 'This imagery is rebuilt on the map with your changes. It is hidden while it is processed.', confirmLabel: 'Confirm' }
        : { title: 'Confirm?', message: 'This imagery and its mangrove areas are added to the map. Processing runs in the background.', confirmLabel: 'Confirm' });
      if (!confirmed || state.busy || confirmButton.disabled) return;

      state.busy = 'confirm';
      refresh();
      showMessage('Sending your edits…');
      try {
        await saveAiAreas();
        const body = await send(editor.confirm_url, 'POST', editsPayload());
        state.leaving = true;
        showMessage('Confirmed. Opening Drone Upload, where you can follow the progress…');
        window.location.href = body.redirect_url || editor.back_url;
      } catch (error) {
        showMessage(error.message, true);
        state.busy = null;
        refresh();
      }
    });

    window.addEventListener('beforeunload', event => {
      if (state.leaving || !hasUnsavedChanges()) return;
      event.preventDefault();
      event.returnValue = '';
    });

    renderStatus();
    resetHistory(); // starts empty; replaced when the AI areas load
    refresh();
    loadAiAreas();
    scheduleAiPoll();
    setTimeout(() => map.invalidateSize(), 100);
  })();
</script>
