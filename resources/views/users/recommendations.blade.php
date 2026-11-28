@extends('layouts.enduser')

@section('title', 'Planting Recommendations - MangroveMap')

@section('content-class', 'content-flush')

@section('styles')
    @include('users.partials.workspace-styles')
    <style>
        .content.content-flush {
            position: relative;
            width: 100%;
            height: calc(100vh - 60px);
            overflow: hidden !important;
        }
        .enduser-workspace {
            width: 100%;
            height: 100%;
        }
        body[data-page='recommendations'] #delineationToolbar {
            display: flex !important;
        }
        .rec-list-empty {
            font-size: 12px;
            color: var(--fg-6a8a6a, #6a8a6a);
            padding: 6px 2px;
        }
        .rec-row-meta {
            display: block;
            font-size: 11px;
            font-weight: 400;
            color: var(--fg-7a917a, #7a917a);
        }
        .rec-field {
            margin-bottom: 12px;
        }
        .rec-field > label {
            display: block;
            font-size: 12px;
            color: var(--fg-556b56, #556b56);
            margin-bottom: 6px;
        }
        .rec-input {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid var(--bd-d4dfd4, #d4dfd4);
            border-radius: 10px;
            background: var(--bg-f8faf7, #f8faf7);
            color: var(--fg-182918, #182918);
            font-size: 13px;
            font-family: inherit;
        }
        textarea.rec-input {
            resize: none;
            overflow-y: auto;
        }
        .rec-input:disabled {
            opacity: .8;
            cursor: default;
        }
        .rec-genus-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .rec-genus-group .genus-pill-btn:disabled {
            cursor: default;
            opacity: .6;
        }
        .rec-genus-add {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }
        .rec-genus-add .rec-input {
            flex: 1;
            min-width: 0;
        }
        #recSelectedGenera {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }
        #recSelectedGenera .genus-tag-remove {
            padding: 0;
            border: 0;
            background: none;
            color: inherit;
            font: inherit;
        }
        .rec-readonly-note {
            font-size: 11px;
            color: var(--fg-3c5a3c, #3c5a3c);
            background: var(--bg-eef6ee, #eef6ee);
            border: 1px solid var(--bd-cfe3cf, #cfe3cf);
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        .rec-draw-hint {
            position: absolute;
            top: 58px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 900;
            background: rgba(24, 41, 24, .86);
            color: #fff;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 8px;
            pointer-events: none;
            width: max-content;
            max-width: calc(100% - 24px);
            text-align: center;
            line-height: 1.4;
            box-sizing: border-box;
        }
        .rec-draw-hint[hidden] {
            display: none;
        }
        @media (max-width: 580px) {
            .rec-draw-hint {
                top: 54px;
                font-size: 11.5px;
                padding: 6px 10px;
            }
        }
        .rec-legend {
            position: absolute;
            left: 12px;
            bottom: 24px;
            z-index: 800;
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), .95);
            border: 1px solid var(--bd-d4dfd4, #d4dfd4);
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 11px;
            color: var(--fg-2d432d, #2d432d);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
        }
        .rec-legend div {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 2px 0;
        }
        .rec-legend span {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            display: inline-block;
        }
        .rec-legend .rec-legend-reference {
            background: transparent;
            border: 2px dashed var(--bd-e8f5e8, #e8f5e8);
            outline: 1px solid #7fa88a;
        }

        /* Viewing a saved recommendation: its details as text (the .rec-view-only rows), no form. */
        #recommendationForm.is-viewing .rec-edit-only,
        #recommendationForm.is-viewing .panel-actions { /* Save / Cancel: only while editing (no empty floating bar) */
            display: none !important;
        }

        .rec-view-text {
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .rec-suitability-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--rec-color, #7a9a7a) 14%, transparent);
            color: var(--rec-color, #7a9a7a);
            font-size: 12px;
            font-weight: 800;
        }

        .rec-suitability-pill::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        #recGeneraView {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        /* Editing a saved recommendation (setAreaEditFocus), laid out by section like a delineation's edit:
           1 "Editing" note, 2 Area card, 3 name, 4 suitability, 5 genus, 6 recommendations, 7 site notes,
           8 Cancel / Save. The area info and Delete step aside; the map toolbar keeps only Undo / Redo. */
        body.is-area-editing #mapRightPanel .scroll {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        body.is-area-editing #recommendationForm,
        body.is-area-editing #recEditActions {
            display: contents !important;
        }

        body.is-area-editing #recommendationForm > .d-title,
        body.is-area-editing #recommendationForm > .d-fields,
        body.is-area-editing #recommendationForm > .div,
        body.is-area-editing #recReadonlyNote,
        body.is-area-editing #recDeleteZone,
        body.is-area-editing #recEditAreaBtn,
        body.is-area-editing #delineationToolbar .draw-select-wrapper,
        body.is-area-editing #delineationToolbar .delineation-separator,
        body.is-area-editing #recModeHint {
            display: none !important;
        }

        body.is-area-editing #recEditingNote { order: 1; margin: 0; }
        body.is-area-editing #recAreaSection { order: 2; display: block; }
        body.is-area-editing #recNameField { order: 3; margin: 0; }
        body.is-area-editing #recSuitabilityField { order: 4; margin: 0; }
        body.is-area-editing #recGenusField { order: 5; margin: 0; }
        body.is-area-editing #recRecommendationField { order: 6; margin: 0; }
        body.is-area-editing #recSiteNotesField { order: 7; margin: 0; }
        body.is-area-editing #recommendationForm .panel-actions { order: 8; margin: auto 0 0; }

        /* Cancel / Save stay in view at the bottom of the panel while the form scrolls. */
        @media (min-width: 781px) {
            body.is-area-editing #recommendationForm .panel-actions {
                position: sticky;
                bottom: -14px;
                z-index: 2;
                padding: 10px 0 14px;
                background: var(--bg-ffffff, #ffffff);
                box-shadow: 0 -8px 12px rgba(var(--bd-ffffff-rgb, 255, 255, 255), 0.9);
            }
        }
    </style>
@endsection

@section('content')
<div class="enduser-workspace">
    <div class="view on" id="v-map">
      <div class="main">
        <div class="map-wrap">
          <div id="mainMap"></div>
          <div class="delineation-toolbar active" id="delineationToolbar">
            <div class="draw-select-wrapper">
              <div class="draw-mode-group" role="group" aria-label="Area mode">
                <button type="button" class="draw-btn" data-mode="draw" title="Draw a new area to evaluate">
                  <i class="bi bi-pentagon"></i><span>Draw area</span>
                </button>
                <button type="button" class="draw-btn" data-mode="select" title="Pick an approved delineation as the area">
                  <i class="bi bi-hand-index"></i><span>Select area</span>
                </button>
              </div>
            </div>
            <div class="delineation-separator"></div>
            <div class="toolbar-right">
              <div class="toolbar-btn-group">
                <button id="undoBtn" type="button" title="Undo" disabled><i class="bi bi-arrow-counterclockwise"></i></button>
                <button id="redoBtn" type="button" title="Redo" disabled><i class="bi bi-arrow-clockwise"></i></button>
              </div>
            </div>
          </div>
          <div class="rec-draw-hint" id="recModeHint" hidden></div>
          <x-map-layer-control>
            <button id="mapGuideBtn" type="button" onclick="toggleMapGuide()" title="Quick guide" aria-label="Quick guide"
              aria-controls="mapGuideCard" aria-expanded="false">
              <i class="bi bi-question-lg"></i>
            </button>
          </x-map-layer-control>
          @include('users.partials.map-quick-guide', ['page' => 'recommendations'])
          <div class="rec-legend" aria-label="Map legend">
            @foreach ($suitabilityLevels as $key => $label)
              <div><span data-suitability-swatch="{{ $key }}"></span>{{ $label }}</div>
            @endforeach
            <div><span class="rec-legend-reference"></span>Approved delineation</div>
          </div>
        </div>
      </div>
      <div class="panel-backdrop" id="panelBackdrop"></div>
      <div class="right-panel" id="mapRightPanel">
        <div class="panel-header" id="panelHeader">
          <div class="panel-drag-bar"></div>
          <div class="panel-header-info">
            <div class="panel-header-title" id="panelHeaderTitle">Planting Recommendation</div>
            <div class="panel-header-sub" id="panelHeaderSub"><span>Adjust upwards for details</span> <i
                class="bi bi-chevron-up"></i></div>
          </div>
          <button type="button" id="recClosePanelBtn" class="panel-close-btn" title="Close panel" aria-label="Close panel">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="scroll">
          <form id="recommendationForm" class="d-card" novalidate>
            {{-- Same layout as the map's delineation panel: each fact in its own labelled box, then the fields. --}}
            <div class="d-title"><i class="bi bi-flower1" aria-hidden="true"></i> <span id="recFormTitle">New recommendation</span></div>
            <div class="d-fields">
              <div class="d-row"><span class="d-key"><i class="bi bi-rulers" aria-hidden="true"></i> Area (ha)</span><span class="d-val" id="recArea">-</span></div>
              <div class="d-row" id="recExpertRow" style="display:none;"><span class="d-key"><i class="bi bi-person-fill" aria-hidden="true"></i> Recommended by</span><span class="d-val" id="recExpert">-</span></div>
              <div class="d-row d-row-wide"><span class="d-key"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Location</span><span class="d-val" id="recLocation">-</span></div>
              <div class="d-row d-row-wide"><span class="d-key"><i class="bi bi-layers-fill" aria-hidden="true"></i> Source</span><span class="d-val" id="recSource">-</span></div>
              <div class="d-row d-row-wide" id="recUpdatedRow" style="display:none;"><span class="d-key"><i class="bi bi-clock-fill" aria-hidden="true"></i> Last updated</span><span class="d-val" id="recUpdated">-</span></div>
              {{-- Viewing a saved recommendation: its details as text (recommendation-scripts, renderViewRows).
                   They are changed with "Edit recommendation", which shows the form fields below instead. --}}
              <div class="d-row d-row-wide rec-view-only" id="recSuitabilityViewRow" style="display:none;"><span class="d-key"><i class="bi bi-clipboard2-check-fill" aria-hidden="true"></i> Planting suitability</span><span class="d-val" id="recSuitabilityView">-</span></div>
              <div class="d-row d-row-wide rec-view-only" id="recGeneraViewRow" style="display:none;"><span class="d-key"><i class="bi bi-tree-fill" aria-hidden="true"></i> Recommended genus</span><div class="d-val" id="recGeneraView">-</div></div>
              <div class="d-row d-row-wide rec-view-only" id="recRecommendationViewRow" style="display:none;"><span class="d-key"><i class="bi bi-list-check" aria-hidden="true"></i> Recommendations</span><span class="d-val rec-view-text" id="recRecommendationView">-</span></div>
              <div class="d-row d-row-wide rec-view-only" id="recSiteNotesViewRow" style="display:none;"><span class="d-key"><i class="bi bi-chat-left-text-fill" aria-hidden="true"></i> Site assessment notes</span><span class="d-val rec-view-text" id="recSiteNotesView">-</span></div>
            </div>
            <div class="div rec-edit-only"></div>
            <div class="d-title d-title-details rec-edit-only"><i class="bi bi-pencil-square" aria-hidden="true"></i> Details</div>

            <div id="recReadonlyNote" class="rec-readonly-note" style="display:none;">
              Recommended by another expert. Only its author can edit it.
            </div>

            <div class="rec-field d-field rec-edit-only" id="recNameField">
              <label for="recName" class="d-field-label"><i class="bi bi-tag-fill" aria-hidden="true"></i> Name <em class="is-required">required</em></label>
              <input id="recName" class="rec-input" type="text" maxlength="191" placeholder="e.g. Brgy. San Isidro mudflat">
            </div>

            <div class="rec-field d-field rec-edit-only" id="recSuitabilityField">
              <label for="recSuitability" class="d-field-label"><i class="bi bi-clipboard2-check-fill" aria-hidden="true"></i> Planting suitability <em class="is-required">required</em></label>
              <select id="recSuitability" class="rec-input">
                <option value="" disabled selected>Select suitability</option>
                @foreach ($suitabilityLevels as $key => $label)
                  <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="rec-field d-field rec-edit-only" id="recGenusField">
              <span class="d-field-label"><i class="bi bi-tree-fill" aria-hidden="true"></i> Recommended mangrove genus <em class="is-required">required</em></span>
              <div class="rec-genus-group" id="recGenusGroup">
                @foreach ($recommendedGenera as $genus)
                  <button type="button" class="genus-pill-btn" data-genus="{{ $genus }}" aria-pressed="false"></button>
                @endforeach
              </div>
              <div class="rec-genus-add">
                <input id="recCustomGenusInput" class="rec-input" type="text" maxlength="80"
                  placeholder="Or type another genus..." aria-label="Other recommended mangrove genus">
                <button type="button" id="recAddCustomGenusBtn" class="btn btn-g"
                  style="padding:7px 12px;font-size:12px;border-radius:8px;white-space:nowrap;">
                  <i class="bi bi-plus-lg"></i> Add
                </button>
              </div>
              <div id="recSelectedGenera" aria-live="polite"></div>
            </div>

            <div class="rec-field d-field rec-edit-only" id="recRecommendationField">
              <label for="recRecommendation" class="d-field-label"><i class="bi bi-list-check" aria-hidden="true"></i> Recommendations <em>optional</em></label>
              <textarea id="recRecommendation" class="rec-input" rows="5" maxlength="5000"
                placeholder="Planting method, spacing, season, propagule source, protection and monitoring"></textarea>
            </div>

            <div class="rec-field d-field rec-edit-only" id="recSiteNotesField">
              <label for="recSiteNotes" class="d-field-label"><i class="bi bi-chat-left-text-fill" aria-hidden="true"></i> Site assessment notes <em>optional</em></label>
              <textarea id="recSiteNotes" class="rec-input" rows="3" maxlength="5000"
                placeholder="Substrate, tidal inundation, salinity, existing vegetation, threats"></textarea>
            </div>

            {{-- Buttons by state (updatePanelActions): viewing = Edit recommendation + Delete; editing (after Edit,
                 or a new recommendation) = an "Editing" note + Save / Cancel side by side, Delete hidden. --}}
            <div id="recEditingNote" class="panel-editing-note" style="display:none;" role="status">
              <i class="bi bi-pencil-fill" aria-hidden="true"></i>
              <span id="recEditingNoteText">Editing. Save your changes, or Cancel to undo them.</span>
            </div>

            <div class="boundary-edit-actions" id="recEditActions">
              <button type="button" id="recEditAreaBtn" class="btn panel-btn" style="width:100%;margin-bottom:8px;display:none;"
                title="Change the area, suitability, genus and notes">
                <i class="bi bi-pencil-square"></i> Edit recommendation
              </button>
              <section id="recAreaSection" class="edit-section" aria-labelledby="recAreaTitle">
                <div class="edit-section-title" id="recAreaTitle"><i class="bi bi-bounding-box-circles" aria-hidden="true"></i> Area</div>
                <button type="button" id="recDeleteVertexBtn" class="btn panel-btn" style="width:100%;display:none;" disabled
                  title="Click a point on the map first">
                  <i class="bi bi-dash-circle"></i> Delete selected point
                </button>
                <details id="recEditHint" class="boundary-edit-hint" style="display:none;">
                  <summary>How to change the area</summary>
                  <ul>
                    <li>Drag a point to move it.</li>
                    <li>Drag or click a faded midpoint to add a point.</li>
                    <li>Double-click or right-click a point to delete it, or select it and press <kbd>Delete</kbd>.</li>
                  </ul>
                </details>
              </section>
            </div>

            <div class="panel-actions">
              <button type="button" id="recCancelBtn" class="btn panel-btn panel-cancel-btn" style="display:none;"><i class="bi bi-x-lg"></i> Cancel</button>
              <button type="submit" id="recSaveBtn" class="btn btn-g delineation-save-btn panel-btn">
                <i class="bi bi-save"></i> <span>Save recommendation</span>
              </button>
            </div>

            {{-- Only while viewing a saved recommendation, apart from the Save / Cancel row. --}}
            <div id="recDeleteZone" class="panel-delete-zone" style="display:none;">
              <button type="button" id="recDeleteBtn" class="btn panel-btn panel-delete-btn">
                <i class="bi bi-trash"></i> Delete recommendation
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
</div>
@include('users.partials.workspace-modals')
@endsection

@section('scripts')
    <script>
        document.body.setAttribute('data-page', 'recommendations');
    </script>
    @include('users.partials.recommendation-scripts')
@endsection
