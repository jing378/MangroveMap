<div class="enduser-workspace">
    <!-- COVERAGE MAP -->
    <div class="view on" id="v-map">
      <div class="left-panel">
        <div class="scroll">
          <div class="sec">Summary</div>
          <div class="stat-grid">
            <div class="stat-box">
              <div class="stat-num g" id="summaryTotalArea">47,382</div>
              <div class="stat-lbl">Total area (ha)</div>
            </div>
            <div class="stat-box">
              <div class="stat-num" id="summaryGenusCount">4</div>
              <div class="stat-lbl">Genus found</div>
            </div>
          </div>
          <div class="div"></div>
          <div class="sec">{{ Auth::user()->isExpert() ? 'Resident delineations' : 'Mangrove Zones' }}</div>
          <div id="zoneListContainer" class="zone-list"></div>
        </div>
      </div>
      <div class="main">
        <div class="map-wrap">
          <div id="mainMap"></div>
          <div class="delineation-toolbar" id="delineationToolbar">
            <div class="draw-select-wrapper">
              <div class="draw-mode-group" role="group" aria-label="Drawing mode">
                <button type="button" class="draw-btn" data-mode="point"><i
                    class="bi bi-geo-alt-fill"></i><span>Point</span></button>
                <button type="button" class="draw-btn" data-mode="line"><i
                    class="bi bi-slash-circle"></i><span>Line</span></button>
                <button type="button" class="draw-btn" data-mode="area"><i
                    class="bi bi-grid-3x3-gap"></i><span>Area</span></button>
              </div>
              <select id="drawTypeSelect" class="draw-select" aria-label="Select drawing mode" disabled>
                <option value="" selected disabled>Select mode</option>
                <option value="point">Point</option>
                <option value="line">Line</option>
                <option value="area">Area</option>
              </select>
            </div>
            <div class="delineation-separator"></div>
            <div class="toolbar-right">
              <div class="toolbar-btn-group">
                <button id="undoBtn" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button id="redoBtn" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
              </div>
              <button id="saveBtn" class="toolbar-save-btn" title="Save"><i class="bi bi-download"></i></button>
            </div>
          </div>
          <div class="map-layer-control">
            <button id="layerToggle" type="button" onclick="toggleLayerMenu()" aria-expanded="false"
              title="Choose map layer" aria-label="Choose map layer">
              <i class="bi bi-layers-fill"></i>
            </button>
            <button id="timelineToggleBtn" type="button" class="active-toggle" onclick="toggleTimelineControl()"
              title="Toggle Temporal Monitoring Timeline" aria-label="Toggle Temporal Monitoring Timeline">
              <i class="bi bi-clock-history"></i>
            </button>
            <button id="editModeBtn" type="button" onclick="showEditMode()" title="Delineate" aria-label="Delineate">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button id="classifyBtn" type="button" onclick="showDelineation()" title="Upload image for delineation"
              aria-label="Upload image for delineation">
              <i class="bi bi-image"></i>
            </button>
            <div id="layerMenu" class="layer-menu" aria-label="Base layer options">
              <button class="layer-option active" onclick="setBase('sat', this)">Satellite</button>
              <button class="layer-option" onclick="setBase('osm', this)">Street</button>
              <button class="layer-option" onclick="setBase('topo', this)">Topo</button>
              <button class="layer-option" onclick="setBase('mangrove', this)"
                title="High-resolution view for identifying mangrove areas">Mangrove 400-ft View</button>
            </div>
          </div>
          <div class="map-legend-float">
            <div class="leg-title">Zone type</div>
            <div class="leg-row">
              <div class="leg-dot" style="background:rgba(30,158,98,.4);border:1.5px solid #1e9e62"></div>Healthy
            </div>
            <div class="leg-row">
              <div class="leg-dot" style="background:rgba(192,120,24,.4);border:1.5px solid #c07818"></div>Sparse
            </div>
            <div class="leg-row">
              <div class="leg-dot" style="background:rgba(208,64,48,.4);border:1.5px solid #d04030"></div>Degraded
            </div>
          </div>

          <!-- FLOATING TEMPORAL MONITORING TIMELINE -->
          <div class="map-timeline-control" id="mapTimelineControl">
            <div class="timeline-header" onclick="handleTimelineHeaderClick(event)">
              <div class="timeline-title-wrap">
                <i class="bi bi-clock-history"></i>
                <span class="timeline-title">Temporal Monitoring</span>
                <span class="timeline-badge" id="timelineChangeBadge">+3.2% net recovery</span>
                <button type="button" class="timeline-zone-focus-btn" onclick="focusTemporalSampleZone(event)"
                  title="Focus on sample temporal delineation">
                  <i class="bi bi-crosshair2"></i> Sample Site
                </button>
              </div>
              <button type="button" class="timeline-collapse-btn" id="timelineCollapseBtn"
                onclick="toggleTimelineCollapse(event)" title="Minimize / Expand timeline"
                aria-label="Toggle timeline collapse">
                <i class="bi bi-chevron-down"></i>
              </button>
            </div>
            <div class="timeline-body">
              <div class="timeline-controls">
                <button type="button" class="timeline-play-btn" id="timelinePlayBtn" onclick="toggleTimelinePlayback()"
                  title="Play / Pause timelapse">
                  <i class="bi bi-play-fill" id="timelinePlayIcon"></i>
                </button>
                <div class="timeline-slider-track-wrap">
                  <input type="range" class="timeline-range-input" id="timelineRangeInput" min="0" max="5" step="1"
                    value="5" oninput="onTimelineSliderChange(this.value)"
                    aria-label="Select year for temporal change analysis">
                  <div class="timeline-marks" id="timelineMarks">
                    <span class="timeline-mark" data-index="0" onclick="setTimelineYearIndex(0)">2021</span>
                    <span class="timeline-mark" data-index="1" onclick="setTimelineYearIndex(1)">2022</span>
                    <span class="timeline-mark" data-index="2" onclick="setTimelineYearIndex(2)">2023</span>
                    <span class="timeline-mark" data-index="3" onclick="setTimelineYearIndex(3)">2024</span>
                    <span class="timeline-mark" data-index="4" onclick="setTimelineYearIndex(4)">2025</span>
                    <span class="timeline-mark active" data-index="5" onclick="setTimelineYearIndex(5)">2026</span>
                  </div>
                </div>
              </div>
              <div class="timeline-info-row">
                <div class="timeline-stat">
                  <span>Year:</span>
                  <strong id="timelineSelectedYear">2026 (Current)</strong>
                </div>
                <div class="timeline-stat">
                  <span>Area:</span>
                  <strong class="text-green" id="timelineAreaStat">47,382 ha</strong>
                </div>
                <div class="timeline-stat">
                  <span>Health:</span>
                  <strong id="timelineNdviStat">0.78 (Optimal)</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="panel-backdrop" id="panelBackdrop" onclick="collapseOrClosePanel()"></div>
      <div class="right-panel" id="mapRightPanel">
        <div class="panel-header" id="panelHeader">
          <div class="panel-drag-bar"></div>
          <div class="panel-header-info">
            <div class="panel-header-title" id="panelHeaderTitle">Selected Area</div>
            <div class="panel-header-sub" id="panelHeaderSub"><span>Adjust upwards for details</span> <i
                class="bi bi-chevron-up"></i></div>
          </div>
          <button type="button" onclick="closeRightPanel(event)" class="panel-close-btn" title="Close panel"
            aria-label="Close panel">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="scroll">
          <div class="sec" style="padding-right: 20px;">Selected Zone</div>
          <div id="delineationInfoCard" class="d-card" style="display:none;">
            <div class="d-title">Delineated Area Info</div>
            <div class="d-row"><span class="d-key">Type</span><span class="d-val" id="delineationFeatureType">-</span>
            </div>
            <div class="d-row"><span class="d-key">Coords</span><span class="d-val"
                id="delineationFeatureCoords">-</span></div>
            <div class="d-row"><span class="d-key">Label</span><span class="d-val" id="delineationFeatureLabel">-</span>
            </div>
            <div class="d-row"><span class="d-key">Review status</span><span class="d-val"
                id="delineationReviewStatus">-</span></div>
            <div id="delineationRejectionBox" class="delineation-rejection-box" style="display:none;">
              <strong>Expert feedback</strong>
              <p id="delineationRejectionNotes" style="margin:0;"></p>
            </div>
            @if(Auth::user()->isExpert())
              <div class="d-row" id="delineationSubmitterRow" style="display:none;"><span class="d-key">Submitted
                  by</span><span class="d-val" id="delineationSubmitter">-</span></div>
              <div id="expertReviewActions" class="expert-review-actions" style="display:none;">
                <div class="div"></div>
                <div class="sec" style="padding:0;margin-bottom:8px;">Expert review</div>
                <button type="button" id="expertApproveBtn" class="btn btn-g" style="width:100%;margin-bottom:8px;">
                  <i class="bi bi-check-circle"></i> Approve delineation
                </button>
                <label for="expertRejectionNotes"
                  style="display:block;font-size:12px;color:#556b56;margin-bottom:6px;">Rejection
                  notes (required to reject)</label>
                <textarea id="expertRejectionNotes" rows="3"
                  style="width:100%;padding:10px;border:1px solid #d4dfd4;border-radius:10px;background:#f8faf7;color:#182918;resize:none;"
                  placeholder="Explain what the resident should revise (min 10 characters)"></textarea>
                <button type="button" id="expertRejectBtn" class="btn"
                  style="width:100%;margin-top:8px;background:#fdf0ee;color:#d04030;border-color:#e8b8b0;">
                  <i class="bi bi-x-circle"></i> Reject delineation
                </button>
              </div>
            @endif
            <div class="div"></div>
            <div style="margin-bottom:12px;">
              <label for="delineationLabel" style="display:block;font-size:12px;color:#556b56;margin-bottom:6px;">Name /
                Label</label>
              <input id="delineationLabel" type="text"
                style="width:100%;padding:10px;border:1px solid #d4dfd4;border-radius:10px;background:#f8faf7;color:#182918;"
                placeholder="Enter zone name or note" />
            </div>
            <div style="margin-bottom:12px;">
              <label for="delineationNotes"
                style="display:block;font-size:12px;color:#556b56;margin-bottom:6px;">Notes</label>
              <textarea id="delineationNotes" rows="4"
                style="width:100%;padding:10px;border:1px solid #d4dfd4;border-radius:10px;background:#f8faf7;color:#182918;resize:none;overflow-y:auto;"
                placeholder="Fill in information about this delineated feature"></textarea>
            </div>
          </div>
          <button id="removeDelineationBtn" class="btn"
            style="width:100%;margin-top:8px;margin-bottom:14px;background:#fdf0ee;color:#d04030;border-color:#e8b8b0;"
            onclick="window.removeCurrentDelineation()">Remove delineation</button>
          <div id="zoneDetailsContent">
            <div class="d-card">
              <div class="d-title" id="dName"></div>
              <div class="d-row"><span class="d-key">Area</span><span class="d-val" id="dArea"></span></div>
              <div class="d-row"><span class="d-key">Health (NDVI)</span><span class="d-val" id="dNDVI"></span></div>
              <div class="d-row"><span class="d-key">Status</span><span class="d-val" id="dStatus"></span></div>
              <div class="d-row"><span class="d-key">Dominant genus</span><span class="d-val" id="dGenus"></span></div>
              <div class="d-row"><span class="d-key">Last scan</span><span class="d-val" id="dScan"></span></div>
            </div>
            <div class="div"></div>
            <div class="sec">Genus Distribution</div>
            <div class="cw" style="height:148px"><canvas id="pieC"></canvas></div>
            <div class="div"></div>
            <div class="div"></div>
            <div class="sec">Coverage Trend</div>
            <div class="cw" style="height:108px"><canvas id="trendC"></canvas></div>
            <div id="temporalZoneBreakdown" style="display:none;margin-top:14px;">
              <div class="div"></div>
              <div class="sec">Temporal Progression (2021–2026)</div>
              <p style="font-size:11px;color:#6a8a6a;margin-bottom:8px;">Multi-year boundary expansion &amp; canopy
                recovery over time. Click any epoch to jump.</p>
              <div class="temporal-epoch-list" id="temporalEpochList"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
</div>

  <!-- Processing Modal to prevent multiple clicks -->
  <div id="processingModal" class="processing-modal-backdrop" role="dialog" aria-modal="true"
    aria-label="Processing request">
    <div class="processing-modal-card">
      <div class="processing-spinner"></div>
      <h3 class="processing-modal-title">Processing...</h3>
      <p class="processing-modal-subtitle">Saving your delineation, please wait.</p>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="processing-modal-backdrop" role="dialog" aria-modal="true" aria-label="Success">
    <div class="processing-modal-card">
      <div class="success-icon-wrapper">
        <i class="bi bi-check-lg"></i>
      </div>
      <h3 class="processing-modal-title" id="successModalTitle">Success!</h3>
      <p class="processing-modal-subtitle" id="successModalMessage">Delineation saved and submitted for expert review.
      </p>
      <button type="button" class="btn btn-g success-modal-btn" onclick="hideSuccessModal()">Got it</button>
    </div>
  </div>

