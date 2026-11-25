{{--
  "Mangrove extent by year" card on the map page: a year slider (1985 onwards), a play button and a chart of
  mapped hectares, for the whole province or one municipality. Opened by #mangroveHistoryToggle in the map
  controls. The data is Global Mangrove Watch (route map.mangrove-history); the drawing code is
  users.partials.mangrove-history-scripts. Not the same as "Changes over time", which is one delineated
  area's history as recorded by experts.

  While the satellite data is hidden (config map.mangrove_history.enabled off), the same card and slider show
  this system's own records instead, driven by users.partials.map-timeline-scripts: approved hectares per year
  (the dashboard's Coverage Trend), and the delineations and drone uploads added up to the chosen year.
--}}
@php
  $satelliteHistory = (bool) config('map.mangrove_history.enabled');
@endphp
<section class="mh-card" id="mangroveHistoryCard" aria-labelledby="mangroveHistoryTitle" hidden>
  <div class="mh-head">
    <i class="bi bi-graph-up" aria-hidden="true"></i>
    <h2 id="mangroveHistoryTitle">{{ $satelliteHistory ? 'Mangrove extent by year' : 'Mangroves mapped by year' }}</h2>
    @if ($satelliteHistory)
      <select id="mangroveHistoryPlace" class="mh-select" aria-label="Show hectares for">
        <option value="0">All of Southern Leyte</option>
      </select>
    @else
      {{-- Own dropdown, not a <select>: the card sits at the bottom of the map, so the list opens upward. --}}
      <div class="mh-place" id="mangroveHistoryPlace">
        <button type="button" class="mh-select mh-place-btn" id="mangroveHistoryPlaceBtn" aria-haspopup="listbox"
          aria-expanded="false" aria-controls="mangroveHistoryPlaceMenu" title="Choose a municipality">
          <span id="mangroveHistoryPlaceLabel">All areas</span>
          <i class="bi bi-chevron-up" aria-hidden="true"></i>
        </button>
        <div class="mh-place-menu" id="mangroveHistoryPlaceMenu" role="listbox" aria-label="Municipality" hidden></div>
      </div>
    @endif
    <button type="button" class="mh-close" id="mangroveHistoryMinimize" aria-expanded="true" aria-controls="mangroveHistoryBody"
      aria-label="Minimize" title="Minimize">
      <i class="bi bi-chevron-down"></i>
    </button>
    <button type="button" class="mh-close" onclick="toggleMangroveHistory(false)" aria-label="Close {{ $satelliteHistory ? 'mangrove extent by year' : 'mangroves mapped by year' }}">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <p class="mh-status" id="mangroveHistoryStatus" role="status" aria-live="polite">{{ $satelliteHistory ? 'Loading satellite records…' : 'Loading…' }}</p>

  <div class="mh-body" id="mangroveHistoryBody" hidden>
    <div class="mh-readout">
      <button type="button" class="mh-play" id="mangroveHistoryPlay" aria-label="Play through the years" title="Play through the years">
        <i class="bi bi-play-fill"></i>
      </button>
      <div class="mh-figure">
        <span class="mh-year" id="mangroveHistoryYear">-</span>
        <span class="mh-area"><strong id="mangroveHistoryArea">-</strong> ha mapped</span>
      </div>
      <div class="mh-change" id="mangroveHistoryChange"></div>
    </div>

    <div class="mh-chart-wrap">
      <div class="mh-axis" aria-hidden="true"><span id="mangroveHistoryMax"></span><span id="mangroveHistoryMin"></span></div>
      <div class="mh-chart" id="mangroveHistoryChart">
        <svg viewBox="0 0 400 72" preserveAspectRatio="none" aria-hidden="true">
          <path class="mh-chart-fill" id="mangroveHistoryFill" d=""></path>
          <path class="mh-chart-line" id="mangroveHistoryLine" d="" vector-effect="non-scaling-stroke"></path>
          {{-- Previous data (satellite) beside the approved hectares while "Compare with previous data" is on. --}}
          <path class="mh-chart-prev" id="mangroveHistoryPrevLine" d="" vector-effect="non-scaling-stroke"></path>
          <line class="mh-chart-base" id="mangroveHistoryBaseLine" y1="0" y2="72" vector-effect="non-scaling-stroke"></line>
          <line class="mh-chart-cursor" id="mangroveHistoryCursor" y1="0" y2="72" vector-effect="non-scaling-stroke"></line>
        </svg>
        <span class="mh-chart-dot" id="mangroveHistoryDot"></span>
        <span class="mh-chart-tip" id="mangroveHistoryTip" hidden></span>
      </div>
    </div>
    <input type="range" class="mh-range" id="mangroveHistoryRange" min="0" max="0" step="1" value="0" aria-label="Year">
    <div class="mh-ticks" aria-hidden="true"><span id="mangroveHistoryFirst"></span><span id="mangroveHistoryLast"></span></div>
    @unless ($satelliteHistory)
      {{-- Year filter while comparing with previous data (map-timeline-scripts): the slider, play and chart
           cover only these years. --}}
      <div class="mh-years" id="mangroveHistoryYears" hidden>
        Years
        <select id="mangroveHistoryFrom" class="mh-select" aria-label="Show from year"></select>
        to
        <select id="mangroveHistoryTo" class="mh-select" aria-label="Show to year"></select>
      </div>
    @endunless

    @if ($satelliteHistory)
      <div class="mh-legend">
        <span><i class="mh-swatch mh-swatch-stable"></i><span id="mangroveHistoryStableLabel">Mangrove</span></span>
        <span class="mh-compare-only"><i class="mh-swatch mh-swatch-gain"></i>Gained</span>
        <span class="mh-compare-only"><i class="mh-swatch mh-swatch-loss"></i>Lost</span>
        <label class="mh-compare">Compare with
          <select id="mangroveHistoryBase" class="mh-select" aria-label="Compare with year"></select>
        </label>
      </div>
    @else
      {{-- "Compare with previous data" (map-timeline-scripts): the Global Mangrove Watch satellite record for the
           chosen year in amber under the areas, its hectares, and how much of the approved areas it also has. --}}
      <div class="mh-sat" id="mangroveHistorySat" hidden aria-live="polite">
        <span class="mh-sat-figure"><i class="mh-swatch mh-swatch-sat"></i> Previous data <span id="mangroveHistorySatYear"></span>:
          <strong id="mangroveHistorySatArea">-</strong> ha</span>
        <span class="mh-sat-agree" id="mangroveHistorySatAgree"></span>
      </div>
      <div class="mh-legend">
        <span id="mangroveHistoryLegendText">The map shows the areas and drone uploads added up to this year.</span>
        <label class="mh-sat-toggle" id="mangroveHistorySatToggleWrap" title="Show the earlier satellite record of mangroves (Global Mangrove Watch) for the chosen year">
          <input type="checkbox" id="mangroveHistorySatToggle"> Compare with previous data
        </label>
      </div>
    @endif

    <details class="mh-table">
      <summary>{{ $satelliteHistory ? 'Table and source' : 'Table' }}</summary>
      <div class="mh-table-scroll">
        <table>
          <thead><tr><th scope="col">Year</th><th scope="col">Hectares</th>@unless ($satelliteHistory)<th scope="col" id="mangroveHistorySatColumn" hidden>Previous data</th>@endunless</tr></thead>
          <tbody id="mangroveHistoryRows"></tbody>
        </table>
      </div>
      <p class="mh-note">
        @if ($satelliteHistory)
          Satellite estimate, about 30 m per pixel, not checked on the ground. Years before 2000 are less certain.
          Source: <a href="https://doi.org/10.5281/zenodo.21346456" target="_blank" rel="noopener">Global Mangrove Watch v4.1</a> (CC BY 4.0).
        @else
          Hectares are the expert-approved areas in this system, the same figures as the dashboard's Coverage Trend.
          An area or drone upload counts from the year it was added. Pick a municipality to see only its figures.
          <span id="mangroveHistorySatNote" hidden>Previous data: the satellite record of <a href="https://doi.org/10.5281/zenodo.21346456" target="_blank" rel="noopener">Global Mangrove Watch v4.1</a>
            (CC BY 4.0), about 30 m per pixel, so small stands are missed; years after its latest use the latest.</span>
        @endif
      </p>
    </details>
  </div>
</section>

<style>
  .mh-card {
    position: absolute;
    left: 50%;
    bottom: 12px;
    z-index: 1001;
    width: min(390px, calc(100% - 24px));
    transform: translateX(-50%);
    padding: 8px 12px 9px;
    border-radius: 12px;
    border: 1px solid var(--bd-cfe5d8, #cfe5d8);
    background: var(--bg-ffffff, #ffffff);
    box-shadow: 0 8px 24px rgba(15, 40, 25, .2);
    font-family: 'Manrope', sans-serif;
    color: var(--fg-2f4a3a, #2f4a3a);
  }

  .mh-card[hidden],
  .mh-card [hidden] {
    display: none !important;
  }

  /* Minimized: only the title, play button, year, hectares and the slider stay. */
  .mh-card.is-minimized {
    width: min(300px, calc(100% - 24px));
  }

  .mh-card.is-minimized .mh-select,
  .mh-card.is-minimized .mh-place,
  .mh-card.is-minimized .mh-change,
  .mh-card.is-minimized .mh-chart-wrap,
  .mh-card.is-minimized .mh-ticks,
  .mh-card.is-minimized .mh-legend,
  .mh-card.is-minimized .mh-years,
  .mh-card.is-minimized .mh-table {
    display: none;
  }

  .mh-card.is-minimized .mh-range {
    width: 100%;
    margin-left: 0;
  }

  .mh-head {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--fg-1e7a4f, #1e7a4f);
  }

  .mh-head h2 {
    flex: 1;
    min-width: 0;
    margin: 0;
    font-size: 12.5px;
    font-weight: 800;
    color: var(--fg-1a2e1a, #1a2e1a);
  }

  .mh-select {
    max-width: 150px;
    padding: 2px 4px;
    border-radius: 8px;
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    background: var(--bg-f8faf7, #f8faf7);
    color: var(--fg-182918, #182918);
    font: inherit;
    font-size: 11.5px;
  }

  /* Municipality picker: a button whose list opens upward, above the card. */
  .mh-place {
    position: relative;
    flex-shrink: 0;
  }

  .mh-place-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 7px;
    cursor: pointer;
  }

  .mh-place-btn span {
    max-width: 128px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .mh-place-btn .bi {
    font-size: 10px;
    transition: transform .15s;
  }

  .mh-place-btn[aria-expanded="true"] .bi {
    transform: rotate(180deg);
  }

  .mh-place-menu {
    position: absolute;
    right: 0;
    bottom: calc(100% + 6px);
    z-index: 2;
    display: flex;
    flex-direction: column;
    width: max-content;
    min-width: 100%;
    max-width: 240px;
    max-height: min(260px, 45vh);
    overflow-y: auto;
    padding: 4px;
    border-radius: 10px;
    border: 1px solid var(--bd-cfe5d8, #cfe5d8);
    background: var(--bg-ffffff, #ffffff);
    box-shadow: 0 8px 24px rgba(15, 40, 25, .2);
  }

  .mh-place-option {
    padding: 6px 9px;
    border: none;
    border-radius: 7px;
    background: transparent;
    color: var(--fg-2f4a3a, #2f4a3a);
    font: inherit;
    font-size: 12px;
    text-align: left;
    cursor: pointer;
  }

  .mh-place-option:hover,
  .mh-place-option:focus-visible {
    background: var(--bg-f1f5f1, #f1f5f1);
    outline: none;
  }

  .mh-place-option[aria-selected="true"] {
    background: var(--bg-edf7f2, #edf7f2);
    color: var(--fg-1e7a4f, #1e7a4f);
    font-weight: 800;
  }

  .mh-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: var(--fg-6a8a6a, #6a8a6a);
    cursor: pointer;
  }

  .mh-close:hover {
    background: var(--bg-f1f5f1, #f1f5f1);
  }

  .mh-status {
    margin: 10px 0 2px;
    font-size: 12.5px;
    color: var(--fg-4c6b5d, #4c6b5d);
  }

  .mh-readout {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-top: 6px;
  }

  .mh-play {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 50%;
    background: #1e9e62;
    color: #ffffff;
    font-size: 16px;
    cursor: pointer;
  }

  .mh-play:hover {
    background: #1a8a56;
  }

  .mh-figure {
    display: flex;
    align-items: baseline;
    gap: 7px;
    flex-direction: row;
    line-height: 1.2;
  }

  .mh-year {
    font-size: 19px;
    font-weight: 800;
    color: var(--fg-1a2e1a, #1a2e1a);
    font-variant-numeric: tabular-nums;
  }

  .mh-area {
    font-size: 12px;
    color: var(--fg-4c6b5d, #4c6b5d);
  }

  .mh-area strong {
    color: var(--fg-1a2e1a, #1a2e1a);
    font-variant-numeric: tabular-nums;
  }

  .mh-change {
    margin-left: auto;
    text-align: right;
    font-size: 11px;
    color: var(--fg-4c6b5d, #4c6b5d);
  }

  .mh-change strong {
    display: block;
    font-size: 12.5px;
    color: var(--fg-1a2e1a, #1a2e1a);
    font-variant-numeric: tabular-nums;
  }

  .mh-chart-wrap {
    display: flex;
    gap: 6px;
    margin-top: 6px;
  }

  .mh-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 34px;
    text-align: right;
    font-size: 10.5px;
    color: var(--fg-6a8a6a, #6a8a6a);
    font-variant-numeric: tabular-nums;
  }

  .mh-chart {
    position: relative;
    flex: 1;
    height: 40px;
    border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
    cursor: pointer;
    touch-action: none;
  }

  .mh-chart svg {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
  }

  .mh-chart-fill {
    fill: var(--fg-1e9e62, #1e9e62);
    opacity: .14;
  }

  .mh-chart-prev {
    fill: none;
    stroke: var(--fg-c98f00, #c98f00);
    stroke-width: 2;
    stroke-linejoin: round;
  }

  .mh-chart-line {
    fill: none;
    stroke: var(--fg-1e9e62, #1e9e62);
    stroke-width: 2;
    stroke-linejoin: round;
    stroke-linecap: round;
  }

  .mh-chart-cursor {
    stroke: var(--fg-1a2e1a, #1a2e1a);
    stroke-width: 1;
  }

  .mh-chart-base {
    stroke: var(--fg-6a8a6a, #6a8a6a);
    stroke-width: 1;
    stroke-dasharray: 3 3;
  }

  .mh-chart-dot {
    position: absolute;
    width: 10px;
    height: 10px;
    margin: -5px 0 0 -5px;
    border-radius: 50%;
    background: #1e9e62;
    border: 2px solid var(--bd-ffffff, #ffffff);
    pointer-events: none;
  }

  .mh-chart-tip {
    position: absolute;
    top: -6px;
    transform: translate(-50%, -100%);
    padding: 3px 7px;
    border-radius: 6px;
    background: #1a2e1a;
    color: #ffffff;
    font-size: 11px;
    white-space: nowrap;
    pointer-events: none;
    font-variant-numeric: tabular-nums;
  }

  .mh-range {
    display: block;
    width: calc(100% - 40px);
    margin: 2px 0 0 40px;
    accent-color: #1e9e62;
  }

  .mh-ticks {
    display: flex;
    justify-content: space-between;
    margin-left: 40px;
    font-size: 10.5px;
    color: var(--fg-6a8a6a, #6a8a6a);
    font-variant-numeric: tabular-nums;
  }

  .mh-legend {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 3px 10px;
    margin-top: 4px;
    font-size: 11px;
  }

  .mh-legend > span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }

  .mh-swatch {
    display: inline-block;
    width: 11px;
    height: 11px;
    border-radius: 3px;
    border: 1px solid rgba(0, 0, 0, .25);
  }

  /* Same colours as the map overlay (mangrove-history-scripts): fixed, they sit on satellite imagery. */
  .mh-swatch-stable { background-color: rgb(45, 212, 191); }
  .mh-swatch-gain { background-color: rgb(250, 204, 21); }
  .mh-swatch-loss { background-color: rgb(244, 63, 94); }

  /* Previous data (satellite pixels) in "Mangroves mapped by year" (map-timeline-scripts SATELLITE_COLOR). */
  .mh-swatch-sat { background-color: rgb(245, 179, 1); }

  .mh-sat {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px 10px;
    margin-top: 5px;
    padding: 5px 8px;
    border-radius: 8px;
    background: var(--bg-fff8e1, #fff8e1);
    font-size: 11.5px;
    color: var(--fg-4c6b5d, #4c6b5d);
  }

  .mh-sat-figure {
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }

  .mh-sat-figure strong {
    color: var(--fg-1a2e1a, #1a2e1a);
    font-variant-numeric: tabular-nums;
  }

  .mh-sat-agree {
    margin-left: auto;
    font-variant-numeric: tabular-nums;
  }

  .mh-sat-toggle {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: auto;
    font-weight: 700;
    color: var(--fg-1e7a4f, #1e7a4f);
    cursor: pointer;
    white-space: nowrap;
  }

  .mh-sat-toggle input {
    accent-color: #1e9e62;
    margin: 0;
  }

  .mh-years {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    font-size: 11px;
    color: var(--fg-4c6b5d, #4c6b5d);
  }

  .mh-compare {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
    color: var(--fg-4c6b5d, #4c6b5d);
  }

  .mh-table {
    margin-top: 4px;
    font-size: 11.5px;
  }

  .mh-table summary {
    cursor: pointer;
    color: var(--fg-1e7a4f, #1e7a4f);
    font-weight: 700;
  }

  .mh-table-scroll {
    max-height: 110px;
    margin-top: 6px;
    overflow-y: auto;
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    border-radius: 8px;
  }

  .mh-table table {
    width: 100%;
    border-collapse: collapse;
    font-variant-numeric: tabular-nums;
  }

  .mh-table th,
  .mh-table td {
    padding: 4px 10px;
    text-align: left;
    border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
  }

  .mh-table th {
    position: sticky;
    top: 0;
    background: var(--bg-f8faf7, #f8faf7);
    color: var(--fg-4c6b5d, #4c6b5d);
  }

  .mh-table td:last-child,
  .mh-table th:last-child {
    text-align: right;
  }

  .mh-table tr.is-current td {
    background: var(--bg-edf7f2, #edf7f2);
    font-weight: 700;
  }

  .mh-note {
    margin: 6px 0 0;
    font-size: 10.5px;
    line-height: 1.4;
    color: var(--fg-6a8a6a, #6a8a6a);
  }

  .mh-note a {
    color: var(--fg-1e7a4f, #1e7a4f);
  }

  @media (max-width: 780px) {
    .mh-card {
      bottom: 8px;
    }

    .mh-select {
      max-width: 118px;
    }

    .mh-chart {
      height: 34px;
    }
  }
</style>
