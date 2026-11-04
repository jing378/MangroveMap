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
            white-space: nowrap;
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
        .rec-location-marker {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: var(--rec-marker-color);
            border: 2px solid var(--bd-ffffff, #fff);
            border-radius: 50% 50% 50% 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .35);
            transform: rotate(-45deg);
            font-size: 16px;
        }
        .rec-location-marker i {
            transform: rotate(45deg);
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
          <x-map-layer-control />
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
            <div class="d-title" id="recFormTitle">New recommendation</div>
            <div class="d-row"><span class="d-key">Area (ha)</span><span class="d-val" id="recArea">-</span></div>
            <div class="d-row"><span class="d-key">Location</span><span class="d-val" id="recLocation">-</span></div>
            <div class="d-row"><span class="d-key">Mapped area</span><span class="d-val" id="recSource">-</span></div>
            <div class="d-row" id="recExpertRow" style="display:none;"><span class="d-key">Recommended by</span><span class="d-val" id="recExpert">-</span></div>
            <div class="d-row" id="recUpdatedRow" style="display:none;"><span class="d-key">Last updated</span><span class="d-val" id="recUpdated">-</span></div>
            <div class="div"></div>

            <div id="recReadonlyNote" class="rec-readonly-note" style="display:none;">
              Recommended by another expert. Only its author can edit it.
            </div>

            <div class="rec-field">
              <label for="recName">Name</label>
              <input id="recName" class="rec-input" type="text" maxlength="191" placeholder="e.g. Brgy. San Isidro mudflat">
            </div>

            <div class="rec-field">
              <label for="recSuitability">Planting suitability</label>
              <select id="recSuitability" class="rec-input">
                <option value="" disabled selected>Select suitability</option>
                @foreach ($suitabilityLevels as $key => $label)
                  <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="rec-field" id="recGenusField">
              <label>Recommended mangrove genus</label>
              <div class="rec-genus-group" id="recGenusGroup">
                @foreach ($recommendedGenera as $genus)
                  <button type="button" class="genus-pill-btn" data-genus="{{ $genus }}" aria-pressed="false"></button>
                @endforeach
              </div>
            </div>

            <div class="rec-field">
              <label for="recRecommendation">Recommendations <span style="color:#9ab0a0;">(optional)</span></label>
              <textarea id="recRecommendation" class="rec-input" rows="5" maxlength="5000"
                placeholder="Planting method, spacing, season, propagule source, protection and monitoring"></textarea>
            </div>

            <div class="rec-field">
              <label for="recSiteNotes">Site assessment notes <span style="color:#9ab0a0;">(optional)</span></label>
              <textarea id="recSiteNotes" class="rec-input" rows="3" maxlength="5000"
                placeholder="Substrate, tidal inundation, salinity, existing vegetation, threats"></textarea>
            </div>

            <div class="boundary-edit-actions">
              <button type="button" id="recEditAreaBtn" class="btn" style="width:100%;margin-bottom:8px;display:none;">
                <i class="bi bi-bounding-box-circles"></i> Edit area
              </button>
              <div id="recEditHint" class="boundary-edit-hint" style="display:none;">
                <strong>Editing area</strong>
                <ul>
                  <li>Drag a point to move it.</li>
                  <li>Drag or click a faded midpoint to add a point.</li>
                  <li>Double-click or right-click a point to delete it, or select it and press <kbd>Delete</kbd>.</li>
                </ul>
              </div>
              <button type="button" id="recDeleteVertexBtn" class="btn" style="width:100%;margin-bottom:8px;display:none;" disabled>
                <i class="bi bi-dash-circle"></i> Delete selected point
              </button>
            </div>

            <button type="submit" id="recSaveBtn" class="btn btn-g delineation-save-btn" style="width:100%;margin-bottom:8px;">
              <i class="bi bi-save"></i> <span>Save recommendation</span>
            </button>
            <button type="button" id="recCancelBtn" class="btn" style="width:100%;margin-bottom:8px;display:none;">
              <i class="bi bi-x-circle"></i> Discard changes
            </button>
            <button type="button" id="recDeleteBtn" class="btn"
              style="width:100%;display:none;background:var(--bg-fdf0ee, #fdf0ee);color:var(--fg-d04030, #d04030);border-color:var(--bd-e8b8b0, #e8b8b0);">
              <i class="bi bi-trash"></i> Delete recommendation
            </button>
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
