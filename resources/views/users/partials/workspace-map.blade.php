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
              <input id="quickJumpInput" type="search" autocomplete="off" aria-label="Jump to a saved area by name, genus, or status"
                aria-controls="quickJumpResults" aria-expanded="false" placeholder="Jump to saved area by name, genus, or status">
            </div>
            <div class="quick-jump-results" id="quickJumpResults" role="group" aria-label="Saved areas" hidden></div>
          </div>
          @endunless
          <div class="delineation-toolbar" id="delineationToolbar">
            <div class="draw-select-wrapper">
              @if (request()->routeIs('delineate', 'expert.delineate'))
                <div class="draw-mode-group" aria-label="Drawing mode">
                  <button type="button" class="draw-btn" data-mode="line" title="Draw a new area">
                    <i class="bi bi-pentagon"></i><span>Draw area</span>
                  </button>
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
          @if (request()->routeIs('delineate', 'expert.delineate'))
            <div class="delineation-draw-hint" id="delineationDrawHint" role="status" aria-live="polite" hidden>
              Click to add points. Click the starting point or double-click to finish the area. Esc cancels.
            </div>
          @endif
          <x-map-layer-control>
            <button id="editModeBtn" type="button" onclick="showEditMode()" title="Delineate" aria-label="Delineate">
              <i class="bi bi-pencil-square"></i>
            </button>
            @if (request()->routeIs('map', 'expert.map'))
              <button id="plantingRecommendationToggle" type="button" class="active-toggle" onclick="togglePlantingRecommendationLayer()"
                title="Hide planting recommendations" aria-label="Show or hide planting recommendations" aria-pressed="true">
                <i class="bi bi-flower1"></i>
              </button>
            @endif
          </x-map-layer-control>

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
            <div class="d-row d-row-genera" style="display:none;"><span class="d-key">Mangrove Genus Present</span><span class="d-val" id="delineationFeatureGenera">None recorded</span>
            </div>
            <div class="d-row"><span class="d-key">Delineated by</span><span class="d-val"
                id="delineationCreatedBy">-</span></div>
            <div class="d-row"><span class="d-key">Review status</span><span class="d-val"
                id="delineationReviewStatus">-</span></div>
            <div class="d-row d-row-recommendation"><span class="d-key">Planting recommendation</span><span class="d-val"
                id="delineationPlantingRecommendation" style="white-space:pre-line;overflow-wrap:anywhere;">No recommendation provided</span></div>
            <div class="d-row" id="delineationLastEditedRow" style="display:none;"><span class="d-key">Boundary last edited by</span><span class="d-val"
                id="delineationLastEditedBy">-</span></div>
            <div id="delineationRejectionBox" class="delineation-rejection-box" style="display:none;">
              <strong>Expert feedback</strong>
              <p id="delineationRejectionNotes" style="margin:0;"></p>
            </div>
            @if(Auth::user()->isExpert())
              <div class="d-row" id="delineationSubmitterRow" style="display:none;"><span class="d-key">Submitted
                  by</span><span class="d-val" id="delineationSubmitter">-</span></div>
            @endif
            <div class="div"></div>
            <div id="delineationGenusEditor" style="margin-bottom:12px;">
              <label style="display:block;font-size:12px;color:var(--fg-556b56, #556b56);margin-bottom:6px;">Mangrove Genus Present</label>
              <div id="genusQuickSelector" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                <button type="button" class="genus-pill-btn" data-genus="Rhizophora" onclick="toggleGenusSelection('Rhizophora')"><i class="bi bi-plus"></i> Rhizophora <span class="genus-local-name">(Bakawan)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Avicennia" onclick="toggleGenusSelection('Avicennia')"><i class="bi bi-plus"></i> Avicennia <span class="genus-local-name">(Bungalon)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Sonneratia" onclick="toggleGenusSelection('Sonneratia')"><i class="bi bi-plus"></i> Sonneratia <span class="genus-local-name">(Pagatpat)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Bruguiera" onclick="toggleGenusSelection('Bruguiera')"><i class="bi bi-plus"></i> Bruguiera <span class="genus-local-name">(Miapi)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Ceriops" onclick="toggleGenusSelection('Ceriops')"><i class="bi bi-plus"></i> Ceriops <span class="genus-local-name">(Tungog)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Xylocarpus" onclick="toggleGenusSelection('Xylocarpus')"><i class="bi bi-plus"></i> Xylocarpus <span class="genus-local-name">(Tabigi)</span></button>
                <button type="button" class="genus-pill-btn" data-genus="Nypa" onclick="toggleGenusSelection('Nypa')"><i class="bi bi-plus"></i> Nypa <span class="genus-local-name">(Nipa/Sasa)</span></button>
              </div>
              <div style="display:flex;gap:6px;">
                <input id="delineationCustomGenusInput" type="text"
                  style="flex:1;padding:8px 10px;border:1px solid var(--bd-d4dfd4, #d4dfd4);border-radius:8px;background:var(--bg-f8faf7, #f8faf7);color:var(--fg-182918, #182918);font-size:12px;"
                  placeholder="Or type other genus and press Enter..." />
                <button type="button" id="addCustomGenusBtn" onclick="addCustomGenusFromInput()" class="btn btn-g" style="padding:7px 12px;font-size:12px;border-radius:8px;white-space:nowrap;">
                  <i class="bi bi-plus-lg"></i> Add
                </button>
              </div>
              <div id="delineationSelectedGeneraContainer" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
            </div>
            <div id="delineationNotesEditor" style="margin-bottom:12px;">
              <label for="delineationNotes"
                style="display:block;font-size:12px;color:var(--fg-556b56, #556b56);margin-bottom:6px;">Notes</label>
              <textarea id="delineationNotes" rows="4"
                style="width:100%;padding:10px;border:1px solid var(--bd-d4dfd4, #d4dfd4);border-radius:10px;background:var(--bg-f8faf7, #f8faf7);color:var(--fg-182918, #182918);resize:none;overflow-y:auto;"
                placeholder="Fill in information about this delineated feature"></textarea>
            </div>
            @if(Auth::user()->isExpert())
              <div id="expertReviewActions" class="expert-review-actions" style="display:none;">
                <div class="div"></div>
                <div id="expertRejectionNotesSection" style="display:none;margin-bottom:8px;">
                  <textarea id="expertRejectionNotes" rows="3" aria-label="Rejection notes (required)"
                    style="width:100%;padding:10px;border:1px solid var(--bd-d4dfd4, #d4dfd4);border-radius:10px;background:var(--bg-f8faf7, #f8faf7);color:var(--fg-182918, #182918);resize:none;"
                    placeholder="Explain what the resident should revise (min 10 characters)"></textarea>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px;">
                  <button type="button" id="expertApproveBtn" class="btn btn-g"
                    style="flex:1;min-width:0;padding:8px 4px;font-size:12px;">
                    <i class="bi bi-check-circle"></i> Approve
                  </button>
                  <button type="button" id="expertRejectBtn" class="btn"
                    style="flex:1;min-width:0;padding:8px 4px;font-size:12px;background:var(--bg-fdf0ee, #fdf0ee);color:var(--fg-d04030, #d04030);border-color:var(--bd-e8b8b0, #e8b8b0);">
                    <i class="bi bi-x-circle"></i> Reject
                  </button>
                </div>
              </div>
            @endif
            @if (request()->routeIs('delineate') || request()->routeIs('expert.delineate'))
              <button id="saveBtn" type="button" class="btn btn-g delineation-save-btn" style="width:100%;">
                <i class="bi bi-save"></i> Save delineation
              </button>
            @endif
          </div>
          @if(request()->routeIs('delineate'))
            <div id="rejectedRevisionActions" style="display:none;margin-bottom:12px;">
              <button type="button" id="startRejectedRevisionBtn" class="btn" style="width:100%;margin-bottom:8px;">
                <i class="bi bi-pencil-square"></i> Revise rejected delineation
              </button>
              <button type="button" id="saveRejectedRevisionBtn" class="btn btn-g delineation-save-btn" style="display:none;width:100%;margin-bottom:8px;">
                <i class="bi bi-send"></i> Resubmit for review
              </button>
              <button type="button" id="cancelRejectedRevisionBtn" class="btn" style="display:none;width:100%;">
                Cancel revision
              </button>
            </div>
          @endif
          <div id="boundaryEditActions" class="boundary-edit-actions" style="display:none;">
            <button type="button" id="startBoundaryEditBtn" class="btn" style="width:100%;margin-bottom:8px;">
              <i class="bi bi-bounding-box-circles"></i> Edit Delineation
            </button>
            <div id="boundaryEditHint" class="boundary-edit-hint" style="display:none;">
              <strong>Editing boundary</strong>
              <ul>
                <li>Drag a point to move it.</li>
                <li>Drag or click a faded midpoint to add a point.</li>
                <li>Double-click or right-click a point to delete it, or select it and press <kbd>Delete</kbd>.</li>
              </ul>
            </div>
            @if(Auth::user()->isExpert())
              {{-- An observed change becomes that year's entry in "Changes over time"; a correction does not. --}}
              <fieldset id="boundaryRecordAs" class="boundary-record-as" style="display:none;">
                <legend>Record this change as</legend>
                <label class="boundary-record-option">
                  <input type="radio" name="boundaryRecordAs" value="correction" checked>
                  <span><strong>Correction</strong><small>Fixes how the area was drawn. Not added to the timeline.</small></span>
                </label>
                <label class="boundary-record-option">
                  <input type="radio" name="boundaryRecordAs" value="observation">
                  <span><strong>Observed change</strong><small>The mangroves grew or shrank. Saved as that year's observation.</small></span>
                </label>
                <div id="boundaryObservationFields" class="boundary-observation-fields" hidden>
                  <label for="boundaryObservationDate">Date observed</label>
                  @php($localToday = now(\App\Models\DelineationAnnualObservation::LOCAL_TIMEZONE)->toDateString())
                  <input type="date" id="boundaryObservationDate" min="2000-01-01" max="{{ $localToday }}" value="{{ $localToday }}">
                  <label for="boundaryObservationSource">Observed through</label>
                  <select id="boundaryObservationSource">
                    @foreach (\App\Models\DelineationAnnualObservation::SOURCES as $value => $label)
                      <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                  </select>
                  <p class="boundary-observation-note">Hectares and genera are taken from the edited boundary.</p>
                </div>
              </fieldset>
            @endif
            <button type="button" id="deleteBoundaryVertexBtn" class="btn" style="display:none;width:100%;margin-bottom:8px;" disabled>
              <i class="bi bi-dash-circle"></i> Delete selected point
            </button>
            <button type="button" id="saveBoundaryEditBtn" class="btn btn-g delineation-save-btn" style="display:none;width:100%;margin-bottom:8px;">
              <i class="bi bi-check2-circle"></i> Save Changes
            </button>
            <button type="button" id="cancelBoundaryEditBtn" class="btn" style="display:none;width:100%;margin-bottom:8px;">
              <i class="bi bi-x-circle"></i> Cancel
            </button>
          </div>
          <button id="removeDelineationBtn" class="btn"
            style="width:100%;margin-top:8px;margin-bottom:14px;background:var(--bg-fdf0ee, #fdf0ee);color:var(--fg-d04030, #d04030);border-color:var(--bd-e8b8b0, #e8b8b0);"
            onclick="window.removeCurrentDelineation()">Remove delineation</button>
          <div id="zoneDetailsContent">
            <div class="d-card">
              <div class="d-title" id="dName"></div>
              <div class="d-row"><span class="d-key">Area</span><span class="d-val" id="dArea"></span></div>
              <div class="d-row"><span class="d-key">Status</span><span class="d-val" id="dStatus"></span></div>
              <div class="d-row"><span class="d-key">Dominant genus</span><span class="d-val" id="dGenus"></span></div>
              <div class="d-row"><span class="d-key">Last scan</span><span class="d-val" id="dScan"></span></div>
            </div>
            @if (Auth::user()->isExpert())
            <div class="div"></div>
            <div class="sec" id="genusDistributionTitle">Genus Distribution</div>
            <div id="genusDistributionEmpty" style="font-size:11px;color:var(--fg-6a8a6a, #6a8a6a);margin-bottom:8px;">No verified genus data for this zone.</div>
            <div class="cw" style="height:148px"><canvas id="pieC"></canvas></div>
            <div class="div"></div>
            <div class="div"></div>
            <div class="sec">Coverage Trend (ha)</div>
            <div id="coverageTrendEmpty" style="font-size:11px;color:var(--fg-6a8a6a, #6a8a6a);margin-bottom:8px;">No annual measurements for this zone.</div>
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

  @include('users.partials.workspace-modals')
