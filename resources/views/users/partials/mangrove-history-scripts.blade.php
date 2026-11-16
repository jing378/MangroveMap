    {{-- "Mangrove extent by year" (users.partials.mangrove-history): draws Global Mangrove Watch's yearly maps on
         `mainMap` and drives the year slider, play button and hectares chart. Inlined after map-base-scripts.
         The data is one list of pixel runs, each with the years it was mangrove as bits, so any year (and any
         comparison between two years) is painted in the browser without another request. --}}
    (function () {
      const card = document.getElementById('mangroveHistoryCard');
      const toggleButton = document.getElementById('mangroveHistoryToggle');
      if (!card || !toggleButton) return;

      const historyUrl = @json(route('map.mangrove-history'));
      // Fixed colours (not themed): they are drawn over satellite imagery. Checked for colour-blind separation.
      const COLORS = { stable: 'rgb(45, 212, 191)', gain: 'rgb(250, 204, 21)', loss: 'rgb(244, 63, 94)' };
      const PLAY_STEP_MS = 450;
      const CHART_W = 400, CHART_H = 72, CHART_PAD = 5;
      const el = id => document.getElementById(id);
      const statusEl = el('mangroveHistoryStatus');
      const bodyEl = el('mangroveHistoryBody');
      const rangeEl = el('mangroveHistoryRange');
      const placeEl = el('mangroveHistoryPlace');
      const baseEl = el('mangroveHistoryBase');
      const playEl = el('mangroveHistoryPlay');
      const chartEl = el('mangroveHistoryChart');
      const tipEl = el('mangroveHistoryTip');
      const formatHa = value => Number(value).toLocaleString('en-US', { maximumFractionDigits: value < 100 ? 1 : 0 });

      let history = null; // decoded payload
      let loading = null;
      let overlay = null;
      let yearIndex = 0;
      let baseIndex = 0; // -1 = no comparison
      let placeId = 0; // 0 = whole province
      let playTimer = null;

      function decode(payload) {
        const bytes = Uint8Array.from(atob(payload.runs), ch => ch.charCodeAt(0));
        const view = new DataView(bytes.buffer);
        const count = Math.floor(bytes.length / 16);
        const runs = {
          count,
          row: new Uint16Array(count), col: new Uint16Array(count), len: new Uint16Array(count),
          low: new Uint32Array(count), high: new Uint32Array(count),
          // First and one-past-last run of each grid row (runs are stored row by row).
          rowStart: new Int32Array(payload.grid.height).fill(-1),
          rowEnd: new Int32Array(payload.grid.height),
        };
        for (let i = 0; i < count; i++) {
          const at = i * 16;
          const row = view.getUint16(at, true);
          runs.row[i] = row;
          runs.col[i] = view.getUint16(at + 2, true);
          runs.len[i] = view.getUint16(at + 4, true);
          runs.low[i] = view.getUint32(at + 8, true);
          runs.high[i] = view.getUint32(at + 12, true);
          if (row < payload.grid.height) {
            if (runs.rowStart[row] < 0) runs.rowStart[row] = i;
            runs.rowEnd[row] = i + 1;
          }
        }
        return { ...payload, runs };
      }

      const wasMangrove = (runs, i, year) => (year < 32 ? (runs.low[i] >>> year) & 1 : (runs.high[i] >>> (year - 32)) & 1) === 1;

      function paintTile(canvas, coords) {
        const ctx = canvas.getContext('2d');
        const ratio = canvas.width / 256;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (!history) return;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

        const { grid, runs } = history;
        const world = 256 * Math.pow(2, coords.z);
        const x0 = coords.x * 256, y0 = coords.y * 256;
        const latAt = y => Math.atan(Math.sinh(Math.PI - 2 * Math.PI * y / world)) * 180 / Math.PI;
        const xOf = lng => (lng + 180) / 360 * world - x0;
        const yOf = lat => {
          const rad = lat * Math.PI / 180;
          return (1 - Math.log(Math.tan(rad) + 1 / Math.cos(rad)) / Math.PI) / 2 * world - y0;
        };
        const west = x0 / world * 360 - 180, east = (x0 + 256) / world * 360 - 180;
        const rowMin = Math.max(0, Math.floor((grid.north - latAt(y0)) / grid.pixel) - 1);
        const rowMax = Math.min(grid.height - 1, Math.ceil((grid.north - latAt(y0 + 256)) / grid.pixel));
        const colMin = Math.floor((west - grid.west) / grid.pixel) - 1;
        const colMax = Math.ceil((east - grid.west) / grid.pixel);
        // Zoomed out, one 30 m pixel is smaller than a screen pixel: keep it visible. Zoomed in, overlap
        // neighbours slightly so no hairlines show between them.
        const minSize = 1.6, overlap = 0.4;
        const comparing = baseIndex >= 0 && baseIndex !== yearIndex;

        for (let row = rowMin; row <= rowMax; row++) {
          const start = runs.rowStart[row];
          if (start < 0) continue;
          const top = yOf(grid.north - row * grid.pixel);
          const height = Math.max(yOf(grid.north - (row + 1) * grid.pixel) - top + overlap, minSize);
          for (let i = start; i < runs.rowEnd[row]; i++) {
            const col = runs.col[i], len = runs.len[i];
            if (col > colMax || col + len < colMin) continue;
            const now = wasMangrove(runs, i, yearIndex);
            const before = comparing ? wasMangrove(runs, i, baseIndex) : now;
            if (!now && !before) continue;
            ctx.fillStyle = now && before ? COLORS.stable : (now ? COLORS.gain : COLORS.loss);
            const left = xOf(grid.west + col * grid.pixel);
            const width = Math.max(xOf(grid.west + (col + len) * grid.pixel) - left + overlap, minSize);
            ctx.fillRect(left, top, width, height);
          }
        }
      }

      const HistoryLayer = L.GridLayer.extend({
        createTile(coords) {
          const canvas = L.DomUtil.create('canvas', 'leaflet-tile');
          const ratio = Math.min(window.devicePixelRatio || 1, 2);
          canvas.width = canvas.height = 256 * ratio;
          paintTile(canvas, coords);
          return canvas;
        },
        // Repaint the tiles already on screen (no reload, so dragging the slider does not flicker).
        repaint() {
          Object.values(this._tiles || {}).forEach(tile => paintTile(tile.el, tile.coords));
        },
      });

      const hectares = () => (placeId ? history.municipalities.find(m => m.id === placeId)?.ha : null) || history.total_ha;

      function chartPoint(values, index, low, high) {
        const x = values.length > 1 ? index / (values.length - 1) * CHART_W : CHART_W / 2;
        const share = high > low ? (values[index] - low) / (high - low) : 0.5;
        return [x, CHART_PAD + (1 - share) * (CHART_H - 2 * CHART_PAD)];
      }

      function render() {
        const values = hectares();
        const years = history.years;
        const year = years[yearIndex];
        const comparing = baseIndex >= 0 && baseIndex !== yearIndex;
        const low = Math.min(...values), high = Math.max(...values);

        el('mangroveHistoryYear').textContent = year;
        el('mangroveHistoryArea').textContent = formatHa(values[yearIndex]);
        rangeEl.value = yearIndex;
        rangeEl.setAttribute('aria-valuetext', `${year}, ${formatHa(values[yearIndex])} hectares`);

        const change = el('mangroveHistoryChange');
        change.replaceChildren();
        if (comparing) {
          const difference = values[yearIndex] - values[baseIndex];
          const percent = values[baseIndex] > 0 ? ` (${difference >= 0 ? '+' : '−'}${Math.abs(difference / values[baseIndex] * 100).toFixed(1)}%)` : '';
          const strong = document.createElement('strong');
          strong.textContent = `${difference >= 0 ? '+' : '−'}${formatHa(Math.abs(difference))} ha${percent}`;
          change.append(strong, `${baseIndex < yearIndex ? 'since' : 'compared with'} ${years[baseIndex]}`);
        }

        const points = values.map((_, index) => chartPoint(values, index, low, high));
        const line = points.map(([x, y], index) => `${index ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`).join(' ');
        el('mangroveHistoryLine').setAttribute('d', line);
        el('mangroveHistoryFill').setAttribute('d', `${line} L${CHART_W} ${CHART_H} L0 ${CHART_H} Z`);
        const [cursorX, cursorY] = points[yearIndex];
        const cursor = el('mangroveHistoryCursor');
        cursor.setAttribute('x1', cursorX);
        cursor.setAttribute('x2', cursorX);
        const baseLine = el('mangroveHistoryBaseLine');
        baseLine.style.display = comparing ? '' : 'none';
        if (comparing) {
          baseLine.setAttribute('x1', points[baseIndex][0]);
          baseLine.setAttribute('x2', points[baseIndex][0]);
        }
        const dot = el('mangroveHistoryDot');
        dot.style.left = `${cursorX / CHART_W * 100}%`;
        dot.style.top = `${cursorY / CHART_H * 100}%`;
        el('mangroveHistoryMax').textContent = formatHa(high);
        el('mangroveHistoryMin').textContent = high > low ? formatHa(low) : '';

        el('mangroveHistoryStableLabel').textContent = comparing ? 'Mangrove in both years' : `Mangrove in ${year}`;
        const [gainLabel, lossLabel] = card.querySelectorAll('.mh-compare-only');
        gainLabel.hidden = lossLabel.hidden = !comparing;
        if (comparing) {
          const forward = baseIndex < yearIndex;
          gainLabel.lastChild.textContent = forward ? `Gained since ${years[baseIndex]}` : `Only in ${year}`;
          lossLabel.lastChild.textContent = forward ? `Lost since ${years[baseIndex]}` : `Only in ${years[baseIndex]}`;
        }

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

        overlay?.repaint();
      }

      function setYear(index) {
        const next = Math.max(0, Math.min(history.years.length - 1, Number(index) || 0));
        if (next === yearIndex) return;
        yearIndex = next;
        render();
      }

      function stopPlaying() {
        if (playTimer) clearInterval(playTimer);
        playTimer = null;
        playEl.querySelector('i').className = 'bi bi-play-fill';
        playEl.setAttribute('aria-label', 'Play through the years');
      }

      function startPlaying() {
        if (yearIndex >= history.years.length - 1) setYear(0);
        playEl.querySelector('i').className = 'bi bi-pause-fill';
        playEl.setAttribute('aria-label', 'Pause');
        playTimer = setInterval(() => {
          if (yearIndex >= history.years.length - 1) return stopPlaying();
          setYear(yearIndex + 1);
        }, PLAY_STEP_MS);
      }

      function setUp(payload) {
        history = decode(payload);
        const years = history.years;
        yearIndex = years.length - 1;
        baseIndex = 0;
        rangeEl.max = years.length - 1;
        el('mangroveHistoryFirst').textContent = years[0];
        el('mangroveHistoryLast').textContent = years[years.length - 1];
        placeEl.options[0].textContent = `All of ${history.province}`;
        history.municipalities.forEach(place => placeEl.add(new Option(place.name, place.id)));
        baseEl.add(new Option('No comparison', -1));
        years.forEach((year, index) => baseEl.add(new Option(year, index)));
        baseEl.value = baseIndex;

        const { grid } = history;
        overlay = new HistoryLayer({
          bounds: L.latLngBounds(
            [grid.north - grid.height * grid.pixel, grid.west],
            [grid.north, grid.west + grid.width * grid.pixel]
          ),
          opacity: 0.85,
          zIndex: 6,
          minZoom: 7,
          maxZoom: MAP_MAX_ZOOM,
          updateWhenZooming: false,
        });
        statusEl.hidden = true;
        bodyEl.hidden = false;
        render();
      }

      function load() {
        loading ??= fetch(historyUrl, { headers: { Accept: 'application/json' } })
          .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
          })
          .then(setUp)
          .catch(() => {
            loading = null;
            statusEl.textContent = 'The satellite records could not be loaded. Close this and try again.';
          });
        return loading;
      }

      window.toggleMangroveHistory = function (open) {
        const show = typeof open === 'boolean' ? open : card.hidden;
        card.hidden = !show;
        toggleButton.classList.toggle('active-toggle', show);
        toggleButton.setAttribute('aria-pressed', show ? 'true' : 'false');
        if (!show) {
          if (history) stopPlaying();
          if (overlay) mainMap.removeLayer(overlay);
          return;
        }
        load().then(() => {
          if (overlay && !card.hidden) overlay.addTo(mainMap);
        });
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
      baseEl.addEventListener('change', () => {
        baseIndex = Number(baseEl.value);
        render();
      });
      placeEl.addEventListener('change', () => {
        placeId = Number(placeEl.value);
        const { grid } = history;
        const bounds = placeId
          ? history.municipalities.find(place => place.id === placeId)?.bounds
          : [grid.north - grid.height * grid.pixel, grid.west, grid.north, grid.west + grid.width * grid.pixel];
        if (bounds) mainMap.fitBounds([[bounds[0], bounds[1]], [bounds[2], bounds[3]]], { padding: [30, 30], maxZoom: 15 });
        render();
      });

      // Chart: hover shows that year's hectares, click or drag selects the year.
      const chartIndexAt = event => {
        const box = chartEl.getBoundingClientRect();
        const share = Math.max(0, Math.min(1, (event.clientX - box.left) / box.width));
        return Math.round(share * (history.years.length - 1));
      };
      let dragging = false;
      chartEl.addEventListener('pointerdown', event => {
        if (!history) return;
        dragging = true;
        chartEl.setPointerCapture(event.pointerId);
        stopPlaying();
        setYear(chartIndexAt(event));
      });
      chartEl.addEventListener('pointermove', event => {
        if (!history) return;
        const index = chartIndexAt(event);
        if (dragging) setYear(index);
        const values = hectares();
        tipEl.textContent = `${history.years[index]}: ${formatHa(values[index])} ha`;
        tipEl.style.left = `${Math.max(12, Math.min(88, index / Math.max(1, history.years.length - 1) * 100))}%`;
        tipEl.hidden = false;
      });
      const endChartPointer = () => {
        dragging = false;
        tipEl.hidden = true;
      };
      chartEl.addEventListener('pointerup', endChartPointer);
      chartEl.addEventListener('pointercancel', endChartPointer);
      chartEl.addEventListener('pointerleave', () => { if (!dragging) tipEl.hidden = true; });

      // Shown as soon as the map page opens; the graph button or the close button turns it off.
      window.toggleMangroveHistory(true);
    })();
