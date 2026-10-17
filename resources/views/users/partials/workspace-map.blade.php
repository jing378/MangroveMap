<div class="enduser-workspace">
    <!-- COVERAGE MAP -->
    <div class="view on" id="v-map">
      <div class="left-panel">
        <div class="scroll">
          <div class="sec">Summary</div>
          <div class="stat-grid">
            <div class="stat-box">
              <div class="stat-num g" id="summaryTotalArea">{{ number_format($totalCoverage ?? 0, 0) }}</div>
              <div class="stat-lbl">Total area (ha)</div>
            </div>
            <div class="stat-box">
              <div class="stat-num" id="summaryGenusCount">{{ number_format($genusCount ?? 0) }}</div>
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
          @unless (request()->routeIs('delineate', 'expert.delineate'))
          <div class="map-quick-jump" id="mapQuickJump">
            <div class="quick-jump-field">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input id="quickJumpInput" type="search" autocomplete="off" aria-label="Search saved delineated areas"
                aria-controls="quickJumpResults" aria-expanded="false" placeholder="Search saved areas">
            </div>
            <div class="quick-jump-results" id="quickJumpResults" role="group" aria-label="Saved areas" hidden></div>
          </div>
          @endunless
          <div class="delineation-toolbar" id="delineationToolbar">
            <div class="draw-select-wrapper">
              @if (request()->routeIs('delineate', 'expert.delineate'))
                <div class="draw-mode-group" aria-label="Drawing mode">
                  <span class="draw-btn active"><i class="bi bi-slash-circle"></i><span>Line</span></span>
                </div>
              @else
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
              @endif
            </div>
            <div class="delineation-separator"></div>
            <div class="toolbar-right">
              <div class="toolbar-btn-group">
                <button id="undoBtn" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button id="redoBtn" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
              </div>
              @unless (request()->routeIs('delineate', 'expert.delineate'))
                <button id="saveBtn" class="toolbar-save-btn" title="Save"><i class="bi bi-download"></i></button>
              @endunless
            </div>
          </div>
          <div class="map-layer-control">
            <button id="layerToggle" type="button" onclick="toggleLayerMenu()" aria-expanded="false"
              title="Choose map layer" aria-label="Choose map layer">
              <i class="bi bi-layers-fill"></i>
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

        </div>
      </div>
      <div class="panel-backdrop" id="panelBackdrop" onclick="collapseOrClosePanel()"></div>
      <div class="right-panel" id="mapRightPanel">
        <div class="panel-header" id="panelHeader">
          <div class="panel-drag-bar"></div>
          <div class="panel-header-info">
            <div class="panel-header-title" id="panelHeaderTitle">Selected Zone</div>
            <div class="panel-header-sub" id="panelHeaderSub"><span>Adjust upwards for details</span> <i
                class="bi bi-chevron-up"></i></div>
          </div>
          <button type="button" onclick="closeRightPanel(event)" class="panel-close-btn" title="Close panel"
            aria-label="Close panel">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="scroll">
          <div id="delineationInfoCard" class="d-card" style="display:none;">
            <div class="d-title">Delineated Area Info</div>
            <div class="d-row"><span class="d-key">Type</span><span class="d-val" id="delineationFeatureType">-</span>
            </div>
            <div class="d-row"><span class="d-key">Area (ha)</span><span class="d-val"
              id="delineationFeatureArea">-</span></div>
            <div class="d-row"><span class="d-key">Location</span><span class="d-val" id="delineationFeatureLocation">-</span>
            </div>
            <div class="d-row"><span class="d-key">Mangrove Genus Present</span><span class="d-val" id="delineationFeatureGenera">None recorded</span>
            </div>
            <div class="d-row"><span class="d-key">Delineated by</span><span class="d-val"
                id="delineationCreatedBy">-</span></div>
            <div class="d-row"><span class="d-key">Review status</span><span class="d-val"
                id="delineationReviewStatus">-</span></div>
            <div id="approvedPlantingRecommendationSection" class="d-row" style="display:none;">
              <span class="d-key">Planting Recommendation</span>
              <span class="d-val" id="approvedPlantingRecommendation">-</span>
            </div>
            <div id="delineationRejectionBox" class="delineation-rejection-box" style="display:none;">
              <strong>Expert feedback</strong>
              <p id="delineationRejectionNotes" style="margin:0;"></p>
            </div>
            @if(Auth::user()->isExpert())
              <div class="d-row" id="delineationSubmitterRow" style="display:none;"><span class="d-key">Submitted
                  by</span><span class="d-val" id="delineationSubmitter">-</span></div>
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
              <label style="display:block;font-size:12px;color:#556b56;margin-bottom:6px;">Mangrove Genus Present</label>
              <div id="genusQuickSelector" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                <button type="button" class="genus-pill-btn" data-genus="Rhizophora" onclick="toggleGenusSelection('Rhizophora')"><i class="bi bi-plus"></i> Rhizophora <span class="genus-local-name">(Bakawan)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Avicennia" onclick="toggleGenusSelection('Avicennia')"><i class="bi bi-plus"></i> Avicennia <span class="genus-local-name">(Bungalon)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Sonneratia" onclick="toggleGenusSelection('Sonneratia')"><i class="bi bi-plus"></i> Sonneratia <span class="genus-local-name">(Pagatpat)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Bruguiera" onclick="toggleGenusSelection('Bruguiera')"><i class="bi bi-plus"></i> Bruguiera <span class="genus-local-name">(Miapi)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Ceriops" onclick="toggleGenusSelection('Ceriops')"><i class="bi bi-plus"></i> Ceriops <span class="genus-local-name">(Tungog)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Xylocarpus" onclick="toggleGenusSelection('Xylocarpus')"><i class="bi bi-plus"></i> Xylocarpus <span class="genus-local-name">(Tabigi)</span></button>
              </div>
              <div style="display:flex;gap:6px;">
                <input id="delineationCustomGenusInput" type="text"
                  style="flex:1;padding:8px 10px;border:1px solid #d4dfd4;border-radius:8px;background:#f8faf7;color:#182918;font-size:12px;"
                  placeholder="Or type other genus and press Enter..." />
                <button type="button" id="addCustomGenusBtn" onclick="addCustomGenusFromInput()" class="btn btn-g" style="padding:7px 12px;font-size:12px;border-radius:8px;white-space:nowrap;">
                  <i class="bi bi-plus-lg"></i> Add
                </button>
              </div>
              <div id="delineationSelectedGeneraContainer" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
            </div>
            <div style="margin-bottom:12px;">
              <label for="delineationNotes"
                style="display:block;font-size:12px;color:#556b56;margin-bottom:6px;">Notes</label>
              <textarea id="delineationNotes" rows="4"
                style="width:100%;padding:10px;border:1px solid #d4dfd4;border-radius:10px;background:#f8faf7;color:#182918;resize:none;overflow-y:auto;"
                placeholder="Fill in information about this delineated feature"></textarea>
            </div>
            @if(Auth::user()->isExpert())
              <div id="expertReviewActions" class="expert-review-actions" style="display:none;">
                <div class="div"></div>
                <div class="sec" style="padding:0;margin-bottom:8px;">Expert review</div>
                <div id="plantingRecommendationSection" style="display:none;margin-bottom:12px;">
                  <label for="plantingRecommendation"
                    style="display:block;font-size:12px;color:#556b56;margin-bottom:6px;">Planting Recommendation</label>
                  <textarea id="plantingRecommendation" rows="4"
                    style="width:100%;padding:10px;border:1px solid #d4dfd4;border-radius:10px;background:#f8faf7;color:#182918;resize:none;overflow-y:auto;"
                    placeholder="Add planting guidance before approving this delineation"></textarea>
                </div>
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
            @elseif (request()->routeIs('delineate'))
              <button id="saveBtn" type="button" class="btn btn-g delineation-save-btn" style="width:100%;">
                <i class="bi bi-save"></i> Save delineation
              </button>
            @endif
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
            @if (Auth::user()->isExpert())
            <div class="div"></div>
            <div class="sec" id="genusDistributionTitle">Genus Distribution</div>
            <div id="genusDistributionEmpty" style="font-size:11px;color:#6a8a6a;margin-bottom:8px;">No verified genus data for this zone.</div>
            <div class="cw" style="height:148px"><canvas id="pieC"></canvas></div>
            <div class="div"></div>
            <div class="div"></div>
            <div class="sec">Coverage Trend (ha)</div>
            <div id="coverageTrendEmpty" style="font-size:11px;color:#6a8a6a;margin-bottom:8px;">No annual measurements for this zone.</div>
            <div class="cw" style="height:108px"><canvas id="trendC"></canvas></div>
            @endif
          </div>
          <section class="map-timeline-control" id="mapTimelineControl" style="display:none;"
            aria-label="Changes over time for selected area">
            <div class="timeline-header" onclick="handleTimelineHeaderClick(event)">
              <div class="timeline-title-wrap">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <span class="timeline-title">Changes over time</span>
                <span class="timeline-badge" id="timelineChangeBadge">Select an area</span>
              </div>
              <button type="button" class="timeline-collapse-btn" id="timelineCollapseBtn"
                onclick="toggleTimelineCollapse(event)" title="Minimize / Expand timeline"
                aria-label="Toggle timeline collapse">
                <i class="bi bi-chevron-down"></i>
              </button>
            </div>
            <div class="timeline-area-name" id="timelineAreaName">No area selected</div>
            <div class="timeline-body">
              <div class="timeline-info-row">
                <div class="timeline-stat">
                  <span>Year:</span>
                  <strong id="timelineSelectedYear">Select an area</strong>
                </div>
                <div class="timeline-stat">
                  <span>Area:</span>
                  <strong class="text-green" id="timelineAreaStat">-</strong>
                </div>
                <div class="timeline-stat">
                  <span>Source:</span>
                  <strong id="timelineSourceStat">-</strong>
                </div>
              </div>
              <div class="div"></div>
              <div class="sec">Annual observations</div>
              <p class="timeline-history-caption">Recorded history for this delineated area.</p>
              <div class="temporal-epoch-list" id="temporalEpochList"></div>
            </div>
          </section>
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

