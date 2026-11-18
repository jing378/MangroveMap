    {{-- "Mangroves mapped by year" (the users.partials.mangrove-history card while the satellite data is hidden):
         the year slider over this system's own records. Hectares per year are the dashboard's Coverage Trend
         (approved areas); the map keeps only the delineations, pins and drone uploads added up to the chosen
         year (mapTimelineYear, read by redrawFeatures and renderZoneMarkers). Inlined in workspace-scripts
         after the delineations are loaded. --}}
    (function () {
      const card = document.getElementById('mangroveHistoryCard');
      const toggleButton = document.getElementById('mangroveHistoryToggle');
      if (!card || !toggleButton) return;

      const trendYears = @json(array_map('intval', $trendYears ?? []));
      const trendValues = @json(array_map('floatval', $trendValues ?? []));
      // Municipality name → { year: hectares }, the same trend for that municipality's approved areas.
      const trendByPlace = @json((object) ($trendByMunicipality ?? []));
      const municipalityBounds = @json($municipalityBounds ?? []);
      const placeEl = document.getElementById('mangroveHistoryPlace');
      const placeButton = document.getElementById('mangroveHistoryPlaceBtn');
      const placeLabel = document.getElementById('mangroveHistoryPlaceLabel');
      const placeMenu = document.getElementById('mangroveHistoryPlaceMenu');
      let place = ''; // '' = all areas
      const PLAY_STEP_MS = 900;
      const CHART_W = 400, CHART_H = 72, CHART_PAD = 5;
      const el = id => document.getElementById(id);
      const rangeEl = el('mangroveHistoryRange');
      const playEl = el('mangroveHistoryPlay');
      const chartEl = el('mangroveHistoryChart');
      const tipEl = el('mangroveHistoryTip');
      const formatHa = value => Number(value).toLocaleString('en-US', { maximumFractionDigits: value < 100 ? 1 : 0 });
      const plural = (count, word) => `${count} ${word}${count === 1 ? '' : 's'}`;

      let years = [];
      let values = []; // approved hectares per year
      let yearIndex = 0;
      let playTimer = null;
      let droneLayers = [];
      // Drone layers this slider took off the map, so it puts back only those when their year comes.
      const hiddenDroneLayers = new Set();

      // Saved areas in the chosen municipality (all of them when none is chosen).
      const placeFeatures = () => drawnFeatures
        .filter(feature => feature.delineation_id && (!place || feature.municipality === place));

      // A drone upload belongs to the municipality of the delineation made from it.
      const droneYears = () => {
        const inPlace = place
          ? new Set(placeFeatures().map(feature => Number(feature.drone_imagery_layer_id)).filter(Boolean))
          : null;
        return droneLayers
          .filter(layer => layer.status === 'ready' && (!inPlace || inPlace.has(Number(layer.id))))
          .map(layer => mapRecordYear(layer.created_at))
          .filter(year => year !== null);
      };

      // The picker lists every municipality that has an area on the map or in the trend.
      function buildPlaces() {
        const names = [...new Set([
          ...Object.keys(trendByPlace),
          ...drawnFeatures.map(feature => feature.delineation_id && feature.municipality).filter(Boolean),
        ])].sort((a, b) => a.localeCompare(b));
        if (!names.includes(place)) place = '';
        if (placeMenu) {
          placeMenu.replaceChildren(...[
            ['', 'All areas'],
            ...names.map(name => [name, name]),
          ].map(([value, name]) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'mh-place-option';
            option.setAttribute('role', 'option');
            option.dataset.value = value;
            option.setAttribute('aria-selected', value === place ? 'true' : 'false');
            option.textContent = name;
            return option;
          }));
          placeLabel.textContent = place || 'All areas';
        } else {
          placeEl.replaceChildren(new Option('All areas', ''), ...names.map(name => new Option(name, name)));
          placeEl.value = place;
        }
      }

      // From the first year anything was added (or the trend starts) to this year.
      function buildYears() {
        buildPlaces();
        const selected = years[yearIndex];
        const atEnd = !years.length || yearIndex === years.length - 1;
        const thisYear = Math.max(new Date().getFullYear(), ...trendYears);
        const known = [
          thisYear,
          ...trendYears,
          ...drawnFeatures.map(feature => feature.created_year).filter(Boolean),
          ...droneYears(),
        ];
        const first = Math.min(...known);
        years = Array.from({ length: thisYear - first + 1 }, (_, index) => first + index);
        const lastTrend = trendValues.length ? trendValues[trendValues.length - 1] : 0;
        const placeTrend = place ? (trendByPlace[place] || {}) : null;
        values = years.map(year => {
          const index = trendYears.indexOf(year);
          if (index >= 0) return placeTrend ? Number(placeTrend[year]) || 0 : trendValues[index];
          if (!trendYears.length || year < trendYears[trendYears.length - 1]) return 0;
          return placeTrend ? Number(placeTrend[trendYears[trendYears.length - 1]]) || 0 : lastTrend;
        });
        // Stay on the chosen year; a slider left at the latest year stays at the latest.
        const kept = years.indexOf(selected);
        yearIndex = atEnd || kept < 0 ? years.length - 1 : kept;
        rangeEl.max = years.length - 1;
        el('mangroveHistoryFirst').textContent = years[0];
        el('mangroveHistoryLast').textContent = years[years.length - 1];
      }

      function chartPoint(index, low, high) {
        const x = values.length > 1 ? index / (values.length - 1) * CHART_W : CHART_W / 2;
        const share = high > low ? (values[index] - low) / (high - low) : 0.5;
        return [x, CHART_PAD + (1 - share) * (CHART_H - 2 * CHART_PAD)];
      }

      function render() {
        const year = years[yearIndex];
        const low = Math.min(...values), high = Math.max(...values);

        el('mangroveHistoryYear').textContent = year;
        el('mangroveHistoryArea').textContent = formatHa(values[yearIndex]);
        rangeEl.value = yearIndex;
        rangeEl.setAttribute('aria-valuetext', `${year}, ${formatHa(values[yearIndex])} hectares approved`);

        // Areas and drone uploads added up to this year.
        const areaCount = new Set(placeFeatures()
          .filter(feature => (feature.created_year ?? year) <= year)
          .map(feature => feature.delineation_id)).size;
        const droneCount = droneYears().filter(droneYear => droneYear <= year).length;
        const counts = document.createElement('strong');
        counts.textContent = plural(areaCount, 'area');
        el('mangroveHistoryChange').replaceChildren(counts, plural(droneCount, 'drone upload'));

        const points = values.map((_, index) => chartPoint(index, low, high));
        const line = points.map(([x, y], index) => `${index ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`).join(' ');
        el('mangroveHistoryLine').setAttribute('d', line);
        el('mangroveHistoryFill').setAttribute('d', points.length > 1 ? `${line} L${CHART_W} ${CHART_H} L0 ${CHART_H} Z` : '');
        const [cursorX, cursorY] = points[yearIndex];
        const cursor = el('mangroveHistoryCursor');
        cursor.setAttribute('x1', cursorX);
        cursor.setAttribute('x2', cursorX);
        el('mangroveHistoryBaseLine').style.display = 'none';
        const dot = el('mangroveHistoryDot');
        dot.style.left = `${cursorX / CHART_W * 100}%`;
        dot.style.top = `${cursorY / CHART_H * 100}%`;
        el('mangroveHistoryMax').textContent = formatHa(high);
        el('mangroveHistoryMin').textContent = high > low ? formatHa(low) : '';

        el('mangroveHistoryRows').replaceChildren(...years.map((rowYear, index) => {
          const row = document.createElement('tr');
          if (index === yearIndex) row.className = 'is-current';
          const yearCell = document.createElement('td');
          yearCell.textContent = rowYear;
          const areaCell = document.createElement('td');
          areaCell.textContent = formatHa(values[index]);
          row.append(yearCell, areaCell);
          return row;
        }));
      }

      // Take drone layers uploaded after the chosen year off the map, and put back the ones this slider removed.
      function applyDroneLayers() {
        droneLayers.forEach(layer => {
          const tileLayer = droneImageryTileLayers.get(layer.id);
          if (!tileLayer) return;
          const hide = mapTimelineHides(mapRecordYear(layer.created_at));
          if (hide && mainMap.hasLayer(tileLayer)) {
            mainMap.removeLayer(tileLayer);
            hiddenDroneLayers.add(layer.id);
          } else if (!hide && hiddenDroneLayers.has(layer.id)) {
            hiddenDroneLayers.delete(layer.id);
            tileLayer.addTo(mainMap);
          }
          const checkbox = droneImageryLayersElement?.querySelector(`[data-layer-id="${layer.id}"] input[type="checkbox"]`);
          if (checkbox && !checkbox.disabled) checkbox.checked = mainMap.hasLayer(tileLayer);
        });
      }

      // Redraw the map for the chosen year (every record when the card is closed).
      function applyYear() {
        mapTimelineYear = card.hidden ? null : years[yearIndex];
        redrawFeatures();
        renderZoneMarkers();
        applyDroneLayers();
      }

      function setYear(index) {
        const next = Math.max(0, Math.min(years.length - 1, Number(index) || 0));
        if (next === yearIndex) return;
        yearIndex = next;
        render();
        applyYear();
      }

      function stopPlaying() {
        if (playTimer) clearInterval(playTimer);
        playTimer = null;
        playEl.querySelector('i').className = 'bi bi-play-fill';
        playEl.setAttribute('aria-label', 'Play through the years');
      }

      function startPlaying() {
        if (years.length < 2) return;
        if (yearIndex >= years.length - 1) setYear(0);
        playEl.querySelector('i').className = 'bi bi-pause-fill';
        playEl.setAttribute('aria-label', 'Pause');
        playTimer = setInterval(() => {
          if (yearIndex >= years.length - 1) return stopPlaying();
          setYear(yearIndex + 1);
        }, PLAY_STEP_MS);
      }

      window.toggleMangroveHistory = function (open) {
        const show = typeof open === 'boolean' ? open : card.hidden;
        card.hidden = !show;
        toggleButton.classList.toggle('active-toggle', show);
        toggleButton.setAttribute('aria-pressed', show ? 'true' : 'false');
        if (!show) stopPlaying();
        applyYear();
      };

      // Called after the delineations are reloaded (a save, review or removal): new range and counts.
      // The reload has just redrawn the shapes with the current year, so only the card is updated.
      window.refreshMapTimeline = function () {
        buildYears();
        render();
        if (!card.hidden) mapTimelineYear = years[yearIndex];
      };

      // Called each time the drone layer list is loaded (map-base-scripts).
      window.onDroneImageryLayers = function (layers) {
        droneLayers = Array.isArray(layers) ? layers : [];
        buildYears();
        render();
        if (!card.hidden) mapTimelineYear = years[yearIndex];
        applyDroneLayers();
      };

      // Minimize: keep only the year, hectares and slider. Remembered per browser.
      const minimizeEl = el('mangroveHistoryMinimize');
      const MINIMIZED_KEY = 'mangrovemap-history-minimized';
      function setMinimized(minimized, remember = true) {
        card.classList.toggle('is-minimized', minimized);
        minimizeEl.setAttribute('aria-expanded', minimized ? 'false' : 'true');
        minimizeEl.setAttribute('aria-label', minimized ? 'Expand' : 'Minimize');
        minimizeEl.title = minimized ? 'Expand' : 'Minimize';
        minimizeEl.querySelector('i').className = minimized ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        if (!remember) return;
        try { localStorage.setItem(MINIMIZED_KEY, minimized ? '1' : '0'); } catch (error) { /* private mode */ }
      }
      minimizeEl.addEventListener('click', () => setMinimized(!card.classList.contains('is-minimized')));
      // Starts as the user left it; on a phone it starts minimized, so it does not cover the map.
      let savedMinimized = null;
      try { savedMinimized = localStorage.getItem(MINIMIZED_KEY); } catch (error) { /* private mode */ }
      if (savedMinimized === '1' || (savedMinimized === null && window.matchMedia('(max-width: 780px)').matches)) {
        setMinimized(true, false);
      }

      rangeEl.addEventListener('input', () => {
        stopPlaying();
        setYear(rangeEl.value);
      });
      playEl.addEventListener('click', () => (playTimer ? stopPlaying() : startPlaying()));
      // Picking a municipality shows its hectares and counts, and moves the map to its areas.
      function changePlace(nextPlace) {
        place = nextPlace;
        if (placeMenu) {
          placeMenu.querySelectorAll('.mh-place-option').forEach(option => {
            option.setAttribute('aria-selected', option.dataset.value === place ? 'true' : 'false');
          });
          placeLabel.textContent = place || 'All areas';
          placeMenu.hidden = true;
          placeButton.setAttribute('aria-expanded', 'false');
        }
        buildYears();
        render();
        zoomToPlace();
      }

      // Zoom to the part of the municipality that holds all of its delineations: as close as still shows
      // every one of them (for "All areas", every area on the map).
      function zoomToPlace() {
        const features = placeFeatures();
        const points = features.flatMap(feature => extractPoints(feature.coords));
        mainMap.invalidateSize();
        if (!points.length) {
          // No area there: the municipality's own boundary.
          const box = municipalityBounds[place];
          if (Array.isArray(box) && box.length === 4) {
            mainMap.fitBounds([[box[0], box[1]], [box[2], box[3]]], { padding: [30, 30] });
          }
          return;
        }
        mainMap.fitBounds(L.latLngBounds(points), { padding: [24, 24], maxZoom: 18 });
      }

      if (placeMenu) {
        placeButton.addEventListener('click', () => {
          placeMenu.hidden = !placeMenu.hidden;
          placeButton.setAttribute('aria-expanded', placeMenu.hidden ? 'false' : 'true');
        });
        placeMenu.addEventListener('click', event => {
          const option = event.target.closest('.mh-place-option');
          if (option) changePlace(option.dataset.value);
        });
        document.addEventListener('click', event => {
          if (!placeEl.contains(event.target)) {
            placeMenu.hidden = true;
            placeButton.setAttribute('aria-expanded', 'false');
          }
        });
        document.addEventListener('keydown', event => {
          if (event.key === 'Escape') {
            placeMenu.hidden = true;
            placeButton.setAttribute('aria-expanded', 'false');
          }
        });
      } else {
        placeEl.addEventListener('change', () => changePlace(placeEl.value));
      }

      // Chart: hover shows that year's hectares, click or drag selects the year.
      const chartIndexAt = event => {
        const box = chartEl.getBoundingClientRect();
        const share = Math.max(0, Math.min(1, (event.clientX - box.left) / box.width));
        return Math.round(share * (years.length - 1));
      };
      let dragging = false;
      chartEl.addEventListener('pointerdown', event => {
        dragging = true;
        chartEl.setPointerCapture(event.pointerId);
        stopPlaying();
        setYear(chartIndexAt(event));
      });
      chartEl.addEventListener('pointermove', event => {
        const index = chartIndexAt(event);
        if (dragging) setYear(index);
        tipEl.textContent = `${years[index]}: ${formatHa(values[index])} ha`;
        tipEl.style.left = `${Math.max(12, Math.min(88, index / Math.max(1, years.length - 1) * 100))}%`;
        tipEl.hidden = false;
      });
      const endChartPointer = () => {
        dragging = false;
        tipEl.hidden = true;
      };
      chartEl.addEventListener('pointerup', endChartPointer);
      chartEl.addEventListener('pointercancel', endChartPointer);
      chartEl.addEventListener('pointerleave', () => { if (!dragging) tipEl.hidden = true; });

      buildYears();
      el('mangroveHistoryStatus').hidden = true;
      el('mangroveHistoryBody').hidden = false;
      render();
      // Shown as soon as the map page opens, on the latest year (everything on the map).
      window.toggleMangroveHistory(true);

      // ?municipality=Name (the dashboard report's "View"): open on that municipality's areas, after the
      // map's own first fit (same delay as ?delineation=).
      const askedPlace = new URLSearchParams(window.location.search).get('municipality');
      if (askedPlace) {
        setTimeout(() => {
          setMinimized(false, false);
          changePlace(askedPlace);
        }, 400);
      }
    })();
