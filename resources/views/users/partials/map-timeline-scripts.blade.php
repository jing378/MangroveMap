    {{-- "Mangroves mapped by year" (the users.partials.mangrove-history card while the satellite data is hidden):
         the year slider over this system's own records. Hectares per year are the dashboard's Mangrove Area Over Time chart
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
      let systemFirst = null; // first year with any record here; earlier years exist only for the previous data
      // Year filter (From / To) while comparing with previous data; null = every year.
      let yearFrom = null;
      let yearTo = null;
      let yearIndex = 0;
      let playTimer = null;
      let droneLayers = [];
      // Drone layers this slider took off the map, so it puts back only those when their year comes.
      const hiddenDroneLayers = new Set();

      // "Compare with previous data": the satellite record (Global Mangrove Watch) pixels of the chosen year (the nearest year it has) in
      // amber under the areas, its hectares for the place, and how much of the approved areas it also saw
      // (MangroveComparison, computed on the server). Loaded on first use; the choice is remembered per browser.
      const SATELLITE_COLOR = 'rgb(245, 179, 1)';
      const SATELLITE_URL = @json(route('map.mangrove-history'));
      const SATELLITE_KEY = 'mangrovemap-timeline-satellite';
      const satelliteAgreement = @json($satelliteAgreement ?? null);
      const satToggle = el('mangroveHistorySatToggle');
      let satellite = null; // the file, with its runs decoded
      let satelliteLoad = null;
      let satelliteOn = false;
      let satelliteLayer = null;
      let satelliteDrawnIndex = -1;
      // A year picked in an area's "Changes over time" > Previous data; until then the slider's year.
      let previousYearOverride = null;
      const previousDataYear = () => previousYearOverride ?? years[yearIndex];
      const comparing = () => satelliteOn && !!satellite;
      const lastSatelliteYear = () => satellite.years[satellite.years.length - 1];
      // Years after the satellite's latest have no previous data: the map shows only MangroveMap's areas.
      const pastSatellite = year => year > lastSatelliteYear();
      // The place's previous-data hectares in exactly this year (null outside the satellite's years).
      const previousHectaresIn = year => {
        const index = satellite.years.indexOf(year);
        return index < 0 ? null : satelliteHectares(index);
      };

      function decodeSatellite(payload) {
        const bytes = Uint8Array.from(atob(payload.runs), ch => ch.charCodeAt(0));
        const view = new DataView(bytes.buffer);
        const count = Math.floor(bytes.length / 16);
        const runs = {
          col: new Uint16Array(count), len: new Uint16Array(count), place: new Uint16Array(count),
          low: new Uint32Array(count), high: new Uint32Array(count),
          rowStart: new Int32Array(payload.grid.height).fill(-1),
          rowEnd: new Int32Array(payload.grid.height),
        };
        for (let i = 0; i < count; i++) {
          const at = i * 16;
          const row = view.getUint16(at, true);
          runs.col[i] = view.getUint16(at + 2, true);
          runs.len[i] = view.getUint16(at + 4, true);
          runs.place[i] = view.getUint16(at + 6, true); // municipality id (1-based), for the pixel popup
          runs.low[i] = view.getUint32(at + 8, true);
          runs.high[i] = view.getUint32(at + 12, true);
          if (row < payload.grid.height) {
            if (runs.rowStart[row] < 0) runs.rowStart[row] = i;
            runs.rowEnd[row] = i + 1;
          }
        }
        return { ...payload, runs };
      }

      function loadSatellite() {
        satelliteLoad ??= fetch(SATELLITE_URL, { headers: { Accept: 'application/json' } })
          .then(response => response.ok ? response.json() : Promise.reject(new Error(String(response.status))))
          .then(payload => { satellite = decodeSatellite(payload); });
        return satelliteLoad;
      }

      // Index into the satellite's years for a slider year: the same year, else the nearest it has.
      function satelliteIndex(year) {
        const first = satellite.years[0];
        return Math.max(0, Math.min(satellite.years.length - 1, year - first));
      }

      function satelliteHectares(index) {
        if (!place) return satellite.total_ha[index];
        const entry = satellite.municipalities.find(municipality => municipality.name === place);
        return entry ? entry.ha[index] : 0;
      }

      function makeSatelliteLayer() {
        if (!mainMap.getPane('satelliteMangroves')) {
          // Above the basemap and drone imagery, below the drawn areas.
          const pane = mainMap.createPane('satelliteMangroves');
          pane.style.zIndex = 390;
          pane.style.pointerEvents = 'none';
        }
        const SatelliteLayer = L.GridLayer.extend({
          createTile(coords) {
            const tile = document.createElement('canvas');
            const size = this.getTileSize();
            tile.width = size.x;
            tile.height = size.y;
            const { grid, runs } = satellite;
            const bit = satelliteDrawnIndex;
            const ctx = tile.getContext('2d');
            ctx.fillStyle = SATELLITE_COLOR;
            const z = coords.z;
            const origin = coords.scaleBy(size);
            const nw = mainMap.unproject(origin, z);
            const se = mainMap.unproject(origin.add(size), z);
            const rowMin = Math.max(0, Math.floor((grid.north - nw.lat) / grid.pixel) - 1);
            const rowMax = Math.min(grid.height - 1, Math.ceil((grid.north - se.lat) / grid.pixel) + 1);
            const colMin = Math.floor((nw.lng - grid.west) / grid.pixel) - 1;
            const colMax = Math.ceil((se.lng - grid.west) / grid.pixel) + 1;
            const xOf = lng => mainMap.project([0, lng], z).x - origin.x;
            const yOf = lat => mainMap.project([lat, 0], z).y - origin.y;
            for (let row = rowMin; row <= rowMax; row++) {
              const first = runs.rowStart[row];
              if (first < 0) continue;
              const top = yOf(grid.north - row * grid.pixel);
              const height = Math.max(1, yOf(grid.north - (row + 1) * grid.pixel) - top);
              for (let i = first; i < runs.rowEnd[row]; i++) {
                const col = runs.col[i], len = runs.len[i];
                if (col > colMax || col + len < colMin) continue;
                const set = bit < 32 ? (runs.low[i] >>> bit) & 1 : (runs.high[i] >>> (bit - 32)) & 1;
                if (!set) continue;
                const left = xOf(grid.west + col * grid.pixel);
                ctx.fillRect(left, top, Math.max(1, xOf(grid.west + (col + len) * grid.pixel) - left), height);
              }
            }
            return tile;
          },
        });
        return new SatelliteLayer({ pane: 'satelliteMangroves', opacity: 0.7, maxZoom: 22 });
      }

      // A year after the satellite's latest picked in an area's timeline, which goes year by year: no previous
      // data that year. The card's own slider instead keeps its latest year beside the current records.
      const timelinePastSatellite = () => previousYearOverride !== null && pastSatellite(previousYearOverride);

      // On the map only while the card is open and the comparison is on; redrawn when the satellite year changes.
      function applySatelliteLayer() {
        const show = satelliteOn && satellite && !card.hidden && !timelinePastSatellite();
        if (!show) {
          if (satelliteLayer && mainMap.hasLayer(satelliteLayer)) mainMap.removeLayer(satelliteLayer);
          return;
        }
        const index = satelliteIndex(previousDataYear());
        satelliteLayer ??= makeSatelliteLayer();
        if (!mainMap.hasLayer(satelliteLayer)) {
          satelliteDrawnIndex = index;
          satelliteLayer.addTo(mainMap);
        } else if (index !== satelliteDrawnIndex) {
          satelliteDrawnIndex = index;
          satelliteLayer.redraw();
        }
      }

      // Clicking an amber pixel: a popup with that spot's history in the previous data (the years it was
      // mangrove, first and last seen, municipality) and its ~30 m square outlined. Clicks on delineations,
      // markers and anything interactive keep doing what they do, and nothing happens while drawing or editing.
      let pixelOutline = null;
      const escapeText = text => String(text ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

      function satelliteRunAt(latlng) {
        const { grid, runs } = satellite;
        const row = Math.floor((grid.north - latlng.lat) / grid.pixel);
        const col = Math.floor((latlng.lng - grid.west) / grid.pixel);
        if (row < 0 || row >= grid.height || runs.rowStart[row] < 0) return null;
        for (let i = runs.rowStart[row]; i < runs.rowEnd[row]; i++) {
          if (col >= runs.col[i] && col < runs.col[i] + runs.len[i]) return { i, row, col };
        }
        return null;
      }

      // The map draws shapes on a canvas, so the clicked element doesn't tell whether a delineation was hit.
      // Leaflet fires a shape's or marker's click before the map's, with the same DOM event: mark it there.
      const markLayerClick = layerEvent => {
        if (layerEvent.originalEvent) layerEvent.originalEvent.mangroveLayerClick = true;
      };
      const watchLayerClicks = layer => {
        if (layer.mangroveClickWatched || !(layer instanceof L.Path || layer instanceof L.Marker)) return;
        layer.mangroveClickWatched = true;
        layer.on('click', markLayerClick);
      };
      mainMap.eachLayer(watchLayerClicks);
      mainMap.on('layeradd', event => watchLayerClicks(event.layer));

      mainMap.on('click', event => {
        const layerShown = satelliteOn && satellite && satelliteLayer && mainMap.hasLayer(satelliteLayer);
        if (!layerShown) return;
        if (event.originalEvent?.mangroveLayerClick) return; // a delineation, marker, ... handled it
        if (event.originalEvent?.target?.closest?.('.leaflet-marker-icon, .leaflet-popup')) return;
        if (drawingMode || isGeometryEditLocked() || mainMap.getContainer().style.cursor === 'crosshair') return;

        const hit = satelliteRunAt(event.latlng);
        if (!hit) return;
        const { grid, runs } = satellite;
        const shownIndex = satelliteIndex(previousDataYear());
        const bitSet = (i, bit) => ((bit < 32 ? runs.low[i] >>> bit : runs.high[i] >>> (bit - 32)) & 1) === 1;
        if (!bitSet(hit.i, shownIndex)) return; // no amber here in the year shown

        const mangroveYears = satellite.years.filter((_, bit) => bitSet(hit.i, bit));
        const place = satellite.municipalities.find(entry => Number(entry.id) === runs.place[hit.i]);
        const north = grid.north - hit.row * grid.pixel;
        const west = grid.west + hit.col * grid.pixel;
        const bounds = L.latLngBounds([north - grid.pixel, west], [north, west + grid.pixel]);
        const sideM = Math.round(mainMap.distance(bounds.getSouthWest(), bounds.getSouthEast()));
        const allYears = mangroveYears.length === satellite.years.length;

        pixelOutline?.remove();
        pixelOutline = L.rectangle(bounds, { color: '#ffffff', weight: 2, fill: false, interactive: false }).addTo(mainMap);
        L.popup({ maxWidth: 260 })
          .setLatLng(event.latlng)
          .setContent(`<div style="font-family: Manrope, sans-serif; font-size: 12.5px; line-height: 1.45;">
            <div style="font-weight: 800; font-size: 13px;">Previous data · ${satellite.years[shownIndex]}</div>
            <div>Mangrove (satellite pixel, about ${sideM} m)</div>
            ${place ? `<div>Municipality: ${escapeText(place.name)}</div>` : ''}
            <div style="margin-top: 4px;">${allYears
              ? `Mangrove here in every year from ${satellite.years[0]} to ${lastSatelliteYear()}.`
              : `Mangrove here in ${mangroveYears.length} of ${satellite.years.length} years · first seen ${mangroveYears[0]}, last ${mangroveYears[mangroveYears.length - 1]}.`}</div>
            <div style="margin-top: 4px; font-size: 11px; opacity: .75;">${escapeText(satellite.source || 'Global Mangrove Watch')}</div>
          </div>`)
          .on('remove', () => { pixelOutline?.remove(); pixelOutline = null; })
          .openOn(mainMap);
      });

      function renderSatellite() {
        const show = satelliteOn && !!satellite;
        el('mangroveHistorySat').hidden = !show;
        el('mangroveHistorySatColumn').hidden = !show;
        el('mangroveHistorySatNote').hidden = !show;
        if (el('mangroveHistoryYears')) el('mangroveHistoryYears').hidden = !show;
        const past = show && pastSatellite(previousDataYear());
        el('mangroveHistoryLegendText').textContent = !show
          ? 'The map shows the areas and drone uploads added up to this year.'
          : timelinePastSatellite()
            ? `Previous data ends in ${lastSatelliteYear()}: this year the map shows only MangroveMap's areas.`
            : past
              ? `Amber: the latest previous data (${lastSatelliteYear()}). Green: MangroveMap now. Different methods, kept apart.`
              : 'Amber line and pixels: previous data (satellite). Green: MangroveMap. Different methods, kept apart.';
        if (!show) return;
        const index = satelliteIndex(previousDataYear());
        el('mangroveHistorySatYear').textContent = past ? `${satellite.years[index]} (latest)` : satellite.years[index];
        el('mangroveHistorySatArea').textContent = formatHa(satelliteHectares(index));
        const share = place ? satelliteAgreement?.places?.[place] : satelliteAgreement?.total;
        el('mangroveHistorySatAgree').textContent = typeof share === 'number'
          ? `${Math.round(share * 1000) / 10}% of MangroveMap areas also in previous data`
          : '';
      }

      function setSatellite(on, remember = true) {
        satelliteOn = on;
        yearFrom = yearTo = null; // the year filter starts over with every year
        satToggle.checked = on;
        if (remember) {
          try { localStorage.setItem(SATELLITE_KEY, on ? '1' : '0'); } catch (error) { /* private mode */ }
        }
        if (!on) {
          buildYears();
          render();
          applyYear();
          return;
        }
        loadSatellite().then(() => {
          buildYears(); // stays on the chosen year: the current year unless the user moved the slider
          render();
          applyYear();
        }).catch(() => {
          satelliteOn = false;
          satToggle.checked = false;
          satToggle.disabled = true;
          el('mangroveHistorySatToggleWrap').title = 'The previous data is not available.';
          render();
        });
      }

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
        systemFirst = Math.min(...known);
        const first = comparing() ? Math.min(systemFirst, satellite.years[0]) : systemFirst;
        years = Array.from({ length: thisYear - first + 1 }, (_, index) => first + index);
        // The year filter: offered while comparing (the long range), then the slider covers only those years.
        const fromEl = el('mangroveHistoryFrom');
        const toEl = el('mangroveHistoryTo');
        if (fromEl && toEl) {
          if (!comparing()) {
            yearFrom = yearTo = null;
          } else {
            const options = years.map(year => `<option value="${year}">${year}</option>`).join('');
            if (fromEl.dataset.years !== `${years[0]}-${years[years.length - 1]}`) {
              fromEl.innerHTML = options;
              toEl.innerHTML = options;
              fromEl.dataset.years = `${years[0]}-${years[years.length - 1]}`;
            }
            let from = yearFrom ?? years[0];
            let to = yearTo ?? years[years.length - 1];
            if (from > to) [from, to] = [to, from];
            from = Math.max(from, years[0]);
            to = Math.min(to, years[years.length - 1]);
            fromEl.value = String(from);
            toEl.value = String(to);
            years = years.filter(year => year >= from && year <= to);
          }
        }
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

      function chartPoint(index, low, high, value = values[index]) {
        const x = values.length > 1 ? index / (values.length - 1) * CHART_W : CHART_W / 2;
        const share = high > low ? (value - low) / (high - low) : 0.5;
        return [x, CHART_PAD + (1 - share) * (CHART_H - 2 * CHART_PAD)];
      }

      // While comparing: approved hectares only from the first year with records here (green), previous data
      // only in the satellite's years (amber), on one scale from zero.
      const ownValue = index => comparing() && years[index] < systemFirst ? null : values[index];
      const previousValue = index => comparing() ? previousHectaresIn(years[index]) : null;
      const linePath = points => points
        .filter(Boolean)
        .map(([x, y], index) => `${index ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`)
        .join(' ');

      function render() {
        const year = years[yearIndex];
        const both = comparing();
        const shown = years.flatMap((_, index) => [ownValue(index), previousValue(index)]).filter(value => value !== null);
        const low = both ? 0 : Math.min(...values);
        const high = both ? Math.max(0.0001, ...shown) : Math.max(...values);

        el('mangroveHistoryYear').textContent = year;
        el('mangroveHistoryArea').textContent = formatHa(values[yearIndex]);
        rangeEl.value = yearIndex;
        rangeEl.setAttribute('aria-valuetext', `${year}, ${formatHa(values[yearIndex])} hectares mapped`);

        // Areas and drone uploads added up to this year.
        const areaCount = new Set(placeFeatures()
          .filter(feature => (feature.created_year ?? year) <= year)
          .map(feature => feature.delineation_id)).size;
        const droneCount = droneYears().filter(droneYear => droneYear <= year).length;
        const counts = document.createElement('strong');
        counts.textContent = plural(areaCount, 'area');
        el('mangroveHistoryChange').replaceChildren(counts, plural(droneCount, 'drone upload'));

        const ownPoints = years.map((_, index) => ownValue(index) === null ? null : chartPoint(index, low, high, ownValue(index)));
        const line = linePath(ownPoints);
        const firstOwn = ownPoints.find(Boolean);
        el('mangroveHistoryLine').setAttribute('d', line);
        el('mangroveHistoryFill').setAttribute('d', ownPoints.filter(Boolean).length > 1
          ? `${line} L${CHART_W} ${CHART_H} L${firstOwn[0].toFixed(1)} ${CHART_H} Z` : '');
        el('mangroveHistoryPrevLine').setAttribute('d', both
          ? linePath(years.map((_, index) => previousValue(index) === null ? null : chartPoint(index, low, high, previousValue(index))))
          : '');
        const cursorValue = ownValue(yearIndex) ?? previousValue(yearIndex) ?? 0;
        const [cursorX, cursorY] = chartPoint(yearIndex, low, high, cursorValue);
        const cursor = el('mangroveHistoryCursor');
        cursor.setAttribute('x1', cursorX);
        cursor.setAttribute('x2', cursorX);
        // Dashed break where the previous data ends and only MangroveMap's own figures go on.
        const breakLine = el('mangroveHistoryBaseLine');
        const lastPrevious = both ? years.indexOf(lastSatelliteYear()) : -1;
        if (lastPrevious >= 0 && lastPrevious < years.length - 1) {
          const breakX = (chartPoint(lastPrevious, low, high)[0] + chartPoint(lastPrevious + 1, low, high)[0]) / 2;
          breakLine.setAttribute('x1', breakX);
          breakLine.setAttribute('x2', breakX);
          breakLine.style.display = '';
        } else {
          breakLine.style.display = 'none';
        }
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
          if (satelliteOn && satellite) {
            // Only the years the satellite really has.
            const satCell = document.createElement('td');
            const satIndex = satellite.years.indexOf(rowYear);
            satCell.textContent = satIndex >= 0 ? formatHa(satelliteHectares(satIndex)) : '—';
            row.append(satCell);
          }
          return row;
        }));
        renderSatellite();
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
        applySatelliteLayer();
      }

      function setYear(index) {
        const next = Math.max(0, Math.min(years.length - 1, Number(index) || 0));
        if (next === yearIndex) return;
        previousYearOverride = null; // the slider takes the previous data back to its own year
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

      // Minimize: keep only the year, hectares and slider. Always starts minimized, so it does not cover the
      // map; the chevron expands it, and what needs the full card (?compare=previous, ?municipality=, the
      // "Compare with previous data on the map" button of an area) expands it itself.
      const minimizeEl = el('mangroveHistoryMinimize');
      function setMinimized(minimized) {
        card.classList.toggle('is-minimized', minimized);
        minimizeEl.setAttribute('aria-expanded', minimized ? 'false' : 'true');
        minimizeEl.setAttribute('aria-label', minimized ? 'Expand' : 'Minimize');
        minimizeEl.title = minimized ? 'Expand' : 'Minimize';
        minimizeEl.querySelector('i').className = minimized ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
      }
      minimizeEl.addEventListener('click', () => setMinimized(!card.classList.contains('is-minimized')));
      setMinimized(true);

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
        const previousTip = previousValue(index);
        tipEl.textContent = `${years[index]}: ${ownValue(index) === null ? '' : `${formatHa(values[index])} ha MangroveMap`}`
          + (previousTip === null ? '' : `${ownValue(index) === null ? '' : ' · '}previous data ${formatHa(previousTip)} ha`);
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

      // Previous data: ?compare=previous (the dashboard's "Compared with official figures") turns it on,
      // else as this browser last left it. Also turned on from an area's "Changes over time".
      satToggle.addEventListener('change', () => setSatellite(satToggle.checked));
      // Year filter: picking From / To narrows the slider to those years (the chosen year stays when it can).
      ['mangroveHistoryFrom', 'mangroveHistoryTo'].forEach(id => el(id)?.addEventListener('change', () => {
        yearFrom = Number(el('mangroveHistoryFrom').value);
        yearTo = Number(el('mangroveHistoryTo').value);
        if (yearFrom > yearTo) [yearFrom, yearTo] = [yearTo, yearFrom];
        stopPlaying();
        buildYears();
        render();
        applyYear();
      }));
      // From an area's "Changes over time" > Previous data timeline: show that year's previous data on the map
      // (opening the card and turning the comparison on); null goes back to the slider's year.
      window.showPreviousDataYear = function (year) {
        previousYearOverride = year === null || year === undefined ? null : Number(year);
        if (previousYearOverride === null) {
          if (satelliteOn) {
            render();
            applySatelliteLayer();
          }
          return;
        }
        if (card.hidden) window.toggleMangroveHistory(true);
        if (!satelliteOn) {
          setSatellite(true);
          return;
        }
        render();
        applySatelliteLayer();
      };
      window.setPreviousDataComparison = function (on = true) {
        window.toggleMangroveHistory(true);
        if (on) setMinimized(false);
        setSatellite(on);
      };
      let savedSatellite = null;
      try { savedSatellite = localStorage.getItem(SATELLITE_KEY); } catch (error) { /* private mode */ }
      const askedCompare = new URLSearchParams(window.location.search).get('compare') === 'previous';
      if (askedCompare || savedSatellite === '1') {
        if (askedCompare) setMinimized(false);
        setSatellite(true, askedCompare);
      }

      // ?municipality=Name (the dashboard report's "View"): open on that municipality's areas, after the
      // map's own first fit (same delay as ?delineation=).
      const askedPlace = new URLSearchParams(window.location.search).get('municipality');
      if (askedPlace) {
        setTimeout(() => {
          setMinimized(false);
          changePlace(askedPlace);
        }, 400);
      }
    })();
