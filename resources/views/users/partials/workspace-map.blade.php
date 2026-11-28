@include('partials.genus-info')
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
                  {{-- Shapefile import (workspace-scripts, importShapefileFiles): polygons become unsaved areas. --}}
                  <button type="button" class="draw-btn" id="importShapefileBtn"
                    title="Import a shapefile: a .zip, or the .shp with its .dbf, .shx and .prj files">
                    <i class="bi bi-file-earmark-arrow-up"></i><span>Import shapefile</span>
                  </button>
                </div>
                <input type="file" id="importShapefileInput" hidden multiple
                  accept=".zip,.shp,.dbf,.shx,.prj,.cpg,application/zip">              @else
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
          {{-- Shown when new delineation updates arrived while the user is drawing or editing (workspace-scripts). --}}
          <div class="map-update-chip" id="mapUpdateChip" role="status" aria-live="polite" hidden>
            <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
            <span>New updates. The map refreshes when you finish drawing or editing.</span>
          </div>
          <x-map-layer-control>
            <button id="editModeBtn" type="button" onclick="showEditMode()" title="Delineate" aria-label="Delineate">
              <i class="bi bi-pencil-square"></i>
            </button>
            @if (request()->routeIs('map', 'expert.map'))
              <button id="plantingRecommendationToggle" type="button" class="active-toggle" onclick="togglePlantingRecommendationLayer()"
                title="Hide planting recommendations" aria-label="Show or hide planting recommendations" aria-pressed="true">
                <i class="bi bi-flower1"></i>
              </button>
              <button id="mangroveHistoryToggle" type="button" onclick="toggleMangroveHistory()"
                title="{{ config('map.mangrove_history.enabled') ? 'Mangrove extent by year' : 'Mangroves mapped by year' }}"
                aria-label="Show or hide {{ config('map.mangrove_history.enabled') ? 'mangrove extent by year' : 'mangroves mapped by year' }}" aria-controls="mangroveHistoryCard" aria-pressed="true" class="active-toggle">
                <i class="bi bi-graph-up"></i>
              </button>
            @endif
            <button id="mapRefreshBtn" type="button" onclick="refreshMapFromButton()" title="Refresh map data"
              aria-label="Refresh map data">
              <i class="bi bi-arrow-repeat"></i>
            </button>
            <button id="mapGuideBtn" type="button" onclick="toggleMapGuide()" title="Quick guide" aria-label="Quick guide"
              aria-controls="mapGuideCard" aria-expanded="false">
              <i class="bi bi-question-lg"></i>
            </button>
          </x-map-layer-control>
          @include('users.partials.map-quick-guide', ['page' => request()->routeIs('delineate', 'expert.delineate') ? 'delineate' : 'map'])
          @if (request()->routeIs('map', 'expert.map'))
            @include('users.partials.mangrove-history')
          @endif

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
            {{-- Each fact is its own labelled box (icon + label above the value), like the drone review editor. --}}
            <div class="d-title"><i class="bi bi-info-circle-fill" aria-hidden="true"></i> Area info</div>
            <div class="d-fields">
              <div class="d-row"><span class="d-key"><i class="bi bi-layers-fill" aria-hidden="true"></i> Source</span><span class="d-val" id="delineationFeatureType">-</span></div>
              <div class="d-row"><span class="d-key"><i class="bi bi-rulers" aria-hidden="true"></i> Area (ha)</span><span class="d-val" id="delineationFeatureArea">-</span></div>
              <div class="d-row d-row-wide"><span class="d-key"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Location</span><span class="d-val" id="delineationFeatureLocation">-</span></div>
              <div class="d-row d-row-wide d-row-genera" style="display:none;"><span class="d-key"><i class="bi bi-tree-fill" aria-hidden="true"></i> Mangrove Genus Present</span><div class="d-val" id="delineationFeatureGenera">None recorded</div><div class="genus-canopy" id="delineationGenusCanopy" hidden></div></div>
              <div class="d-row"><span class="d-key"><i class="bi bi-person-fill" aria-hidden="true"></i> Delineated by</span><span class="d-val" id="delineationCreatedBy">-</span></div>
              <div class="d-row"><span class="d-key"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Review status</span><span class="d-val" id="delineationReviewStatus">-</span></div>
              <div class="d-row d-row-wide d-row-recommendation"><span class="d-key"><i class="bi bi-flower1" aria-hidden="true"></i> Planting recommendation</span><span class="d-val" id="delineationPlantingRecommendation" style="white-space:pre-line;overflow-wrap:anywhere;">No recommendation provided</span></div>
              <div class="d-row d-row-wide" id="delineationLastEditedRow" style="display:none;"><span class="d-key"><i class="bi bi-pencil-fill" aria-hidden="true"></i> Boundary last edited by</span><span class="d-val" id="delineationLastEditedBy">-</span></div>
            </div>
            <div id="delineationRejectionBox" class="delineation-rejection-box" style="display:none;">
              <strong>Expert feedback</strong>
              <p id="delineationRejectionNotes" style="margin:0;"></p>
              <p id="delineationRejectionHint" style="display:none;margin:8px 0 0;font-size:12px;"></p>
            </div>
            <div class="div"></div>
            <div class="d-title d-title-details" id="delineationDetailsTitle"><i class="bi bi-pencil-square" aria-hidden="true"></i> Details</div>
            <div id="delineationGenusEditor" class="d-field">
              <span class="d-field-label"><i class="bi bi-tree-fill" aria-hidden="true"></i> Mangrove Genus Present <em class="is-required">required</em></span>
              <div id="genusQuickSelector" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                @foreach(\App\Support\MangroveGenera::INFO as $genus => $genusInfo)
                  <span class="genus-pick">
                    <button type="button" class="genus-pill-btn" data-genus="{{ $genus }}" onclick="toggleGenusSelection('{{ $genus }}')"><i class="bi bi-plus"></i> {{ $genus }} <span class="genus-local-name">({{ $genusInfo['local'] }})</span></button>
                    <button type="button" class="genus-info-btn" data-genus-info="{{ $genus }}" aria-label="What does {{ $genus }} look like?" title="What does it look like?"><i class="bi bi-info-circle"></i></button>
                  </span>
                @endforeach
              </div>
              <div id="genusPickerInfo" hidden style="margin:-2px 0 8px;"></div>
              <script>GenusInfo.bindInfoButtons(document.getElementById('genusQuickSelector'), document.getElementById('genusPickerInfo'));</script>
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
            <div id="delineationNotesEditor" class="d-field">
              <label for="delineationNotes" class="d-field-label"><i class="bi bi-chat-left-text-fill" aria-hidden="true"></i> Notes <em class="is-required">required</em></label>
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
                  <button type="button" id="expertApproveBtn" class="btn btn-g panel-btn"
                    style="flex:1;min-width:0;margin:0;">
                    <i class="bi bi-check-circle"></i> Approve
                  </button>
                  <button type="button" id="expertRejectBtn" class="btn panel-btn"
                    style="flex:1;min-width:0;margin:0;background:var(--bg-fdf0ee, #fdf0ee);color:var(--fg-d04030, #d04030);border-color:var(--bd-e8b8b0, #e8b8b0);">
                    <i class="bi bi-x-circle"></i> Reject
                  </button>
                </div>
              </div>
            @endif
            @if (request()->routeIs('delineate') || request()->routeIs('expert.delineate'))
              <button id="saveBtn" type="button" class="btn btn-g delineation-save-btn panel-btn" style="width:100%;">
                <i class="bi bi-save"></i> Save delineation
              </button>
            @endif
          </div>
          @if(request()->routeIs('delineate'))
            {{-- Same button layout as the Planting Recommendation panel: viewing = one Edit button (and Remove at
                 the bottom); editing = an "Editing" note, then Cancel / Save side by side, Remove hidden. --}}
            <div id="rejectedRevisionActions" style="display:none;margin-bottom:12px;">
              <button type="button" id="startRejectedRevisionBtn" class="btn panel-btn" style="width:100%;margin-bottom:8px;">
                <i class="bi bi-pencil-square"></i> Revise rejected delineation
              </button>
              <div id="revisionEditingNote" class="panel-editing-note" style="display:none;" role="status">
                <i class="bi bi-pencil-fill" aria-hidden="true"></i>
                <span>Revising. Resubmit it for review, or Cancel to undo your changes.</span>
              </div>
              {{-- The same area tools as Edit Delineation: add a missed patch, or take one out, before resubmitting. --}}
              <button type="button" id="addRevisionAreaBtn" class="btn panel-btn area-edit-add" data-area-edit="revision" style="display:none;width:100%;margin-bottom:8px;">
                <i class="bi bi-plus-square-dotted"></i> <span>Draw a new area</span>
              </button>
              <button type="button" id="removeRevisionAreaBtn" class="btn panel-btn area-edit-remove" data-area-edit="revision" style="display:none;width:100%;margin-bottom:8px;" disabled>
                <i class="bi bi-trash3"></i> <span>Remove selected area</span>
              </button>
              <p id="removeRevisionAreaNote" class="boundary-observation-note area-edit-note" data-area-edit="revision" style="display:none;margin:-2px 2px 10px;"></p>
              <div id="revisionActionRow" class="panel-actions" style="display:none;">
                <button type="button" id="cancelRejectedRevisionBtn" class="btn panel-btn panel-cancel-btn">
                  <i class="bi bi-x-lg"></i> Cancel
                </button>
                <button type="button" id="saveRejectedRevisionBtn" class="btn btn-g delineation-save-btn panel-btn">
                  <i class="bi bi-send"></i> Resubmit for review
                </button>
              </div>
            </div>
          @endif
          <div id="boundaryEditActions" class="boundary-edit-actions" style="display:none;">
            <button type="button" id="startBoundaryEditBtn" class="btn panel-btn" style="width:100%;margin-bottom:8px;">
              <i class="bi bi-bounding-box-circles"></i> Edit Delineation
            </button>
            <div id="boundaryEditingNote" class="panel-editing-note" style="display:none;" role="status">
              <i class="bi bi-pencil-fill" aria-hidden="true"></i>
              <span>Editing the boundary. Save your changes, or Cancel to undo them.</span>
            </div>
            <div id="boundaryEditHint" class="boundary-edit-hint" style="display:none;">
              <strong>Editing boundary</strong>
              <ul>
                <li>Drag a point to move it.</li>
                <li>Drag or click a faded midpoint to add a point.</li>
                <li>Double-click or right-click a point to delete it, or select it and press <kbd>Delete</kbd>.</li>
                <li>Use <strong>Draw a new area</strong> to add another mangrove patch to this delineation: click around it, then click the first point (or double-click) to close it.</li>
                <li>Click one of its areas, then <strong>Remove selected area</strong> to take it out (at least one area stays).</li>
              </ul>
            </div>
            {{-- Anyone editing a boundary (experts, and residents on their own pending delineations) can add a shape
                 to the delineation or take one out; both are saved with the boundary change. --}}
            <button type="button" id="addBoundaryAreaBtn" class="btn panel-btn area-edit-add" data-area-edit="boundary" style="display:none;width:100%;margin-bottom:8px;">
              <i class="bi bi-plus-square-dotted"></i> <span>Draw a new area</span>
            </button>
            {{-- Takes the selected shape out of the delineation (kept until Save; Cancel or Undo bring it back). --}}
            <button type="button" id="removeBoundaryAreaBtn" class="btn panel-btn area-edit-remove" data-area-edit="boundary" style="display:none;width:100%;margin-bottom:8px;" disabled>
              <i class="bi bi-trash3"></i> <span>Remove selected area</span>
            </button>
            {{-- Why Remove is greyed out (a disabled button shows no tooltip). --}}
            <p id="removeBoundaryAreaNote" class="boundary-observation-note area-edit-note" data-area-edit="boundary" style="display:none;margin:-2px 2px 10px;"></p>
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
            <button type="button" id="deleteBoundaryVertexBtn" class="btn panel-btn" style="display:none;width:100%;margin-bottom:8px;" disabled>
              <i class="bi bi-dash-circle"></i> Delete selected point
            </button>
            <div id="boundaryEditActionRow" class="panel-actions" style="display:none;">
              <button type="button" id="cancelBoundaryEditBtn" class="btn panel-btn panel-cancel-btn">
                <i class="bi bi-x-lg"></i> Cancel
              </button>
              <button type="button" id="saveBoundaryEditBtn" class="btn btn-g delineation-save-btn panel-btn">
                <i class="bi bi-save"></i> Save changes
              </button>
            </div>
          </div>
          {{-- Only while viewing (hidden during an edit or revision), apart from the Cancel / Save row. --}}
          <div id="removeDelineationZone" class="panel-delete-zone" style="display:none;">
            <button type="button" id="removeDelineationBtn" class="btn panel-btn panel-delete-btn"
              onclick="window.removeCurrentDelineation()"><i class="bi bi-trash"></i> <span>Remove delineation</span></button>
          </div>
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
              {{-- Previous data: the satellite's mangrove hectares inside this area's boundary, every year since
                   1985 (annual-observations' previousData), so the area can be compared with what came before. --}}
              <div class="timeline-previous" id="timelinePrevious" hidden>
                <div class="div"></div>
                <div class="sec">Previous data and now</div>
                <p class="timeline-history-caption" id="timelinePreviousCaption"></p>
                {{-- One timeline, two series kept apart: previous data (satellite, up to its latest year) in amber, then
                     MangroveMap's own figure for the area (from the year it was added) in green, with a break between.
                     Pick or play a year: satellite years show that year's previous data on the map, later years only
                     the mapped boundary. --}}
                {{-- Year filter: the timeline, chart and table cover only these years. --}}
                <div class="timeline-previous-years">
                  Years
                  <select id="timelinePreviousFrom" aria-label="Show from year"></select>
                  to
                  <select id="timelinePreviousTo" aria-label="Show to year"></select>
                </div>
                <div class="timeline-previous-scrub">
                  <button type="button" class="timeline-previous-play" id="timelinePreviousPlay" aria-label="Play through the years" title="Play through the years">
                    <i class="bi bi-play-fill"></i>
                  </button>
                  <span class="timeline-previous-readout" aria-live="polite">
                    <strong id="timelinePreviousYear">-</strong>
                    <span id="timelinePreviousHa">-</span>
                  </span>
                </div>
                <div class="timeline-previous-chart" id="timelinePreviousChart" title="">
                  <svg viewBox="0 0 300 48" preserveAspectRatio="none" aria-hidden="true">
                    <path class="timeline-previous-fill" id="timelinePreviousFill" d=""></path>
                    <path class="timeline-previous-line" id="timelinePreviousLine" d="" vector-effect="non-scaling-stroke"></path>
                    <line class="timeline-previous-break" id="timelinePreviousBreak" y1="0" y2="48" vector-effect="non-scaling-stroke"></line>
                    <path class="timeline-mapped-line" id="timelineMappedLine" d="" vector-effect="non-scaling-stroke"></path>
                    <g id="timelineMappedDots"></g>
                    <line class="timeline-previous-cursor" id="timelinePreviousCursor" y1="0" y2="48" vector-effect="non-scaling-stroke"></line>
                  </svg>
                  <span class="timeline-previous-dot" id="timelinePreviousDot"></span>
                </div>
                <input type="range" class="timeline-previous-range" id="timelinePreviousRange" min="0" max="0" step="1" value="0" aria-label="Year">
                <div class="timeline-previous-axis" aria-hidden="true"><span id="timelinePreviousFirst"></span><span id="timelinePreviousLast"></span></div>
                <div class="timeline-previous-legend" aria-hidden="true">
                  <span><i class="timeline-swatch timeline-swatch-previous"></i> Previous data (satellite, ~30 m)</span>
                  <span><i class="timeline-swatch timeline-swatch-mapped"></i> MangroveMap (surveyed)</span>
                </div>
                <details class="timeline-previous-table">
                  <summary>Year by year</summary>
                  <div class="timeline-previous-scroll">
                    <table>
                      <thead><tr><th scope="col">Year</th><th scope="col">Previous data (ha)</th><th scope="col">MangroveMap (ha)</th></tr></thead>
                      <tbody id="timelinePreviousRows"></tbody>
                    </table>
                  </div>
                </details>
                <button type="button" class="timeline-previous-btn" id="timelinePreviousShow">
                  <i class="bi bi-layers"></i> Compare with previous data on the map
                </button>
                <p class="timeline-previous-source" id="timelinePreviousSource"></p>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
</div>

  @include('users.partials.workspace-modals')
