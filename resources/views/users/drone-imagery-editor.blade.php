@extends('layouts.enduser')

@section('title', 'Review drone imagery - MangroveMap')

@section('content-class', 'content-flush')

@section('styles')
    <link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.17.0/dist/leaflet-geoman.css" />
    <style>
        .drone-editor {
            position: relative;
            display: flex;
            flex: 1;
            min-height: 0;
        }

        .drone-editor-map {
            position: relative;
            display: flex;
            flex: 1;
            min-width: 0;
        }

        #droneEditorMap {
            flex: 1;
            min-width: 0;
            background: #dfe7df;
        }

        /* Top toolbar on the map, like the delineation toolbar: Draw / Edit toggles, then Undo / Redo. */
        .drone-editor-toolbar {
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 900;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: calc(100% - 24px);
        }

        .drone-editor-tools-bar {
            display: inline-flex;
            gap: 6px;
        }

        .drone-editor-tool {
            all: unset;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #4c6b5d;
            border: 1px solid #d4dfd4;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
            transition: all .15s ease;
        }

        .drone-editor-tool:hover:not(:disabled) {
            background: #f7faf7;
            border-color: #c4d4c4;
        }

        .drone-editor-tool.is-active,
        .drone-editor-tool.is-active:hover {
            background: #1e9e62;
            border-color: #1e9e62;
            color: #fff;
        }

        .drone-editor-tool:disabled {
            color: #b8c6bd;
            cursor: not-allowed;
        }

        @media (max-width: 560px) {
            .drone-editor-tool span {
                display: none;
            }
        }

        .drone-editor-separator {
            width: 1px;
            height: 24px;
            background: rgba(255, 255, 255, .7);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, .08);
        }

        .drone-editor-history {
            display: inline-flex;
            align-items: center;
            border: 1px solid #d4dfd4;
            border-radius: 10px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
        }

        .drone-editor-history button {
            all: unset;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            font-size: 15px;
            color: #4c6b5d;
        }

        .drone-editor-history button + button {
            border-left: 1px solid #e0e8e0;
        }

        .drone-editor-history button:hover:not(:disabled) {
            background: #f7faf7;
            color: #1e9e62;
        }

        .drone-editor-history button:disabled {
            color: #c2cec6;
            cursor: not-allowed;
        }

        .drone-editor-panel {
            width: 340px;
            flex-shrink: 0;
            overflow: hidden;
            background: #fff;
            border-left: 1px solid #e0e8e0;
            display: flex;
            flex-direction: column;
            color: #1a2e1a;
        }

        .drone-editor-panel-body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 18px 18px 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        /* Bottom-sheet header and backdrop: mobile only (see the 780px media query). */
        .drone-editor-sheet-header,
        .drone-editor-backdrop {
            display: none;
        }

        .drone-editor-back {
            display: inline-flex;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #1e9e62;
            text-decoration: none;
        }

        .drone-editor-panel h1 {
            font-size: 18px;
            font-weight: 800;
            margin: 6px 0 6px;
            overflow-wrap: anywhere;
        }

        .drone-editor-status {
            font-size: 13px;
            line-height: 1.5;
            padding: 10px 12px;
            border-radius: 10px;
            background: #edf7f2;
            color: #2f5a46;
        }

        .drone-editor-status.is-failed {
            background: #fdecea;
            color: #a12f22;
        }

        .drone-editor-section h2 {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: #6b8a78;
            margin-bottom: 8px;
        }

        .drone-editor-section p {
            font-size: 13px;
            line-height: 1.5;
            color: #4c6b5d;
            margin-bottom: 10px;
        }






        .drone-editor-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .drone-editor-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 9px;
            border: 1px solid #d4e0d4;
            background: #fff;
            color: #1a2e1a;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .drone-editor-btn:hover:not(:disabled) {
            border-color: #1e9e62;
            color: #1e9e62;
        }

        .drone-editor-btn.is-active {
            background: #1e9e62;
            border-color: #1e9e62;
            color: #fff;
        }

        .drone-editor-btn.primary {
            background: #1e9e62;
            border-color: #1e9e62;
            color: #fff;
        }

        .drone-editor-btn.primary:hover:not(:disabled) {
            background: #16824f;
            color: #fff;
        }

        /* Same as the delineation's Save: dimmed until there is something to save or confirm. */
        .drone-editor-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .drone-editor-btn.is-saving {
            opacity: .85;
            cursor: wait;
        }

        /* Follows the button's text color, so it works on green and light buttons alike. */
        .drone-editor-spinner {
            display: inline-block;
            flex: none;
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: drone-editor-spin .7s linear infinite;
        }

        @keyframes drone-editor-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .drone-editor-panel [hidden] {
            display: none !important;
        }

        .drone-editor-ai-status {
            font-size: 13px;
            line-height: 1.5;
            color: #4c6b5d;
        }

        .drone-editor-ai-status.is-failed {
            color: #b42318;
        }

        .drone-editor-count {
            font-size: 12px;
            color: #6b8a78;
            margin-top: 8px;
        }

        .drone-editor-shapes {
            list-style: none;
            margin: 8px 0 0;
            padding: 0;
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid #e0e8e0;
            border-radius: 10px;
        }

        .drone-editor-shape {
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eef3ee;
        }

        .drone-editor-shape:last-child {
            border-bottom: none;
        }

        .drone-editor-shape:hover {
            background: #f4fbf7;
        }

        .drone-editor-shape-label {
            flex: 1;
            text-align: left;
            padding: 7px 10px;
            border: none;
            background: none;
            font: inherit;
            font-size: 13px;
            color: #1a2e1a;
            cursor: pointer;
        }

        .drone-editor-shape-label::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-right: 7px;
            border-radius: 50%;
            background: var(--shape-color, #d04030);
        }

        .drone-editor-shape.is-ai {
            --shape-color: #2fd27f;
        }


        .drone-editor-hint {
            font-size: 12.5px !important;
            margin: 6px 0 0 !important;
        }

        .drone-editor-shape-remove {
            width: 34px;
            align-self: stretch;
            border: none;
            background: none;
            color: #b42318;
            cursor: pointer;
        }

        .drone-editor-shape-remove:hover {
            background: #fdecea;
        }

        .drone-editor-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .drone-editor-row input[type="range"] {
            flex: 1;
            accent-color: #1e9e62;
        }

        .drone-editor-row select {
            flex: 1;
            padding: 7px 8px;
            border: 1px solid #d4e0d4;
            border-radius: 8px;
            font: inherit;
            font-size: 13px;
        }




        .drone-editor-actions {
            margin-top: auto;
            display: grid;
            gap: 8px;
            padding-top: 6px;
        }

        .drone-editor-message {
            font-size: 13px;
            line-height: 1.45;
            min-height: 1em;
            color: #4c6b5d;
        }

        .drone-editor-message.is-error {
            color: #b42318;
        }

        /* Mobile: full-height map with the panel as a bottom sheet, matching the Map / Delineate right
           panel (#mapRightPanel in workspace-styles): peeks from the bottom, tap or swipe the header
           to expand, swipe down or tap the backdrop to collapse. */
        @media (max-width: 780px) {
            .drone-editor-panel {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                height: min(85vh, calc(100vh - 76px));
                border-left: none;
                border-top: 1px solid #d4dfd4;
                border-radius: 20px 20px 0 0;
                box-shadow: 0 -4px 18px rgba(0, 0, 0, .16);
                z-index: 1200;
                transform: translateY(calc(100% - min(355px, 45vh)));
                transition: transform .32s cubic-bezier(.16, 1, .3, 1), box-shadow .32s;
            }

            .drone-editor-panel.is-expanded {
                transform: translateY(0);
                box-shadow: 0 -8px 32px rgba(0, 0, 0, .22);
            }

            @supports (height: 100dvh) {
                .drone-editor-panel {
                    height: min(85dvh, calc(100dvh - 76px));
                    transform: translateY(calc(100% - min(355px, 45dvh)));
                }

                .drone-editor-panel.is-expanded {
                    transform: translateY(0);
                }
            }

            .drone-editor-sheet-header {
                display: flex;
                align-items: center;
                gap: 12px;
                position: relative;
                flex-shrink: 0;
                min-height: 56px;
                box-sizing: border-box;
                padding: 16px 16px 10px;
                border-bottom: 1px solid #edf2ed;
                background: #fff;
                cursor: pointer;
                user-select: none;
                -webkit-user-select: none;
                touch-action: pan-y;
            }

            .drone-editor-drag-bar {
                position: absolute;
                top: 6px;
                left: 50%;
                width: 40px;
                height: 4px;
                border-radius: 2px;
                background: #c5d5c5;
                transform: translateX(-50%);
                transition: background .2s, transform .2s;
            }

            .drone-editor-sheet-header:active .drone-editor-drag-bar {
                background: #7a9a7a;
                transform: translateX(-50%) scale(1.08);
            }

            .drone-editor-sheet-info {
                display: flex;
                flex-direction: column;
                justify-content: center;
                flex: 1;
                min-width: 0;
                margin-top: 2px;
            }

            .drone-editor-sheet-title {
                overflow: hidden;
                color: #1b2e1b;
                font-size: 13.5px;
                font-weight: 700;
                line-height: 1.25;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .drone-editor-sheet-sub {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                margin-top: 2px;
                color: #2e6b3c;
                font-size: 11px;
                font-weight: 600;
                line-height: 1.2;
            }

            .drone-editor-sheet-sub i {
                font-size: 13px;
            }

            .drone-editor-panel-body {
                padding: 10px 14px 20px;
                gap: 16px;
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
            }

            /* The sheet header already shows the name. */
            .drone-editor-panel h1 {
                display: none;
            }

            .drone-editor-status {
                margin-top: 8px;
            }

            .drone-editor-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 1199;
                background: rgba(0, 0, 0, .38);
                opacity: 0;
                pointer-events: none;
                transition: opacity .28s ease;
            }

            .drone-editor-backdrop.is-shown {
                opacity: 1;
                pointer-events: auto;
            }

            .drone-editor-toolbar {
                top: 10px;
            }

            .drone-editor-tool {
                padding: 9px 12px;
                font-size: 15px;
            }

            .drone-editor-history button {
                padding: 9px 14px;
            }

            .drone-editor-shape-label {
                padding: 10px;
            }

            .drone-editor-shape-remove {
                width: 44px;
            }

            .drone-editor-btn {
                padding: 11px 14px;
            }

            /* Save draft / Confirm float at the bottom of the sheet while the content scrolls, like the
               floating save button of the Planting Recommendation / Delineate panels
               (#mapRightPanel .delineation-save-btn in workspace-styles). */
            .drone-editor-actions {
                position: sticky;
                bottom: -10px;
                z-index: 2;
                margin-top: 8px;
                padding-top: 8px;
                background: #fff;
                box-shadow: 0 -6px 12px rgba(247, 250, 247, .95);
            }

            .drone-editor-actions .drone-editor-btn {
                padding: 10px;
                border-radius: 8px;
            }

            .drone-editor-message:empty {
                display: none;
            }

            /* 16px stops iOS Safari from zooming in when the control is focused. */
            .drone-editor-row select {
                font-size: 16px;
            }
        }
    </style>
@endsection

@section('content')
    <div class="drone-editor">
        <div class="drone-editor-map">
            <div id="droneEditorMap" aria-label="Drone imagery preview map"></div>
            {{-- Top toolbar like the manual delineation: tool toggles, then Undo / Redo. --}}
            <div class="drone-editor-toolbar">
                <div class="drone-editor-tools-bar" role="group" aria-label="Mangrove area tools">
                    <button type="button" class="drone-editor-tool" id="droneEditorDrawToggle" aria-pressed="false" title="Draw mangrove areas (click again to stop)">
                        <i class="bi bi-pentagon"></i><span>Draw area</span>
                    </button>
                    <button type="button" class="drone-editor-tool" id="droneEditorEditToggle" aria-pressed="false" title="Edit corners: drag to move, double-click to delete (click again to stop)">
                        <i class="bi bi-pencil"></i><span>Edit</span>
                    </button>
                </div>
                <div class="drone-editor-separator"></div>
                <div class="drone-editor-history" role="group" aria-label="Undo and redo">
                    <button type="button" id="droneEditorUndo" title="Undo (Ctrl+Z)" aria-label="Undo" disabled><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button type="button" id="droneEditorRedo" title="Redo (Ctrl+Y)" aria-label="Redo" disabled><i class="bi bi-arrow-clockwise"></i></button>
                </div>
            </div>
        </div>

        {{-- Mobile: the panel is a bottom sheet like the Map / Delineate right panel. --}}
        <div class="drone-editor-backdrop" id="droneEditorBackdrop"></div>
        <aside class="drone-editor-panel" id="droneEditorPanel" aria-labelledby="droneEditorTitle">
            <div class="drone-editor-sheet-header" id="droneEditorSheetHeader" role="button" tabindex="0"
                aria-controls="droneEditorPanelBody" aria-expanded="false">
                <div class="drone-editor-drag-bar"></div>
                <div class="drone-editor-sheet-info">
                    <div class="drone-editor-sheet-title">{{ $layer->name }}</div>
                    <div class="drone-editor-sheet-sub" id="droneEditorSheetSub"><span>Adjust upwards for details</span> <i class="bi bi-chevron-up"></i></div>
                </div>
            </div>

            <div class="drone-editor-panel-body" id="droneEditorPanelBody">
            <div>
                <a href="{{ $editor['back_url'] }}" class="drone-editor-back"><i class="bi bi-arrow-left"></i> Back to Drone Upload</a>
                <h1 id="droneEditorTitle">{{ $layer->name }}</h1>
                <div id="droneEditorStatus" class="drone-editor-status"></div>
            </div>

            <section class="drone-editor-section" aria-labelledby="droneEditorAiTitle">
                <h2 id="droneEditorAiTitle">1. Mangrove areas (AI delineation)</h2>
                <div id="droneEditorAiStatus" class="drone-editor-ai-status" aria-live="polite"></div>
                <p class="drone-editor-hint">Green shapes are the mangrove areas. Use the toolbar at the top of the map:
                    <i class="bi bi-pentagon"></i> <strong>Draw area</strong> adds areas the AI missed, <i class="bi bi-pencil"></i>
                    <strong>Edit</strong> lets you drag corners (<strong>double-click</strong> a corner to delete it), and
                    Undo / Redo step through your changes. Click an area to remove it.</p>
                <ul id="droneEditorAiList" class="drone-editor-shapes" aria-label="Mangrove areas" hidden></ul>
                <div class="drone-editor-tools">
                    <button type="button" class="drone-editor-btn" id="droneEditorAiRun" hidden><i class="bi bi-cpu"></i> Run AI detection</button>
                </div>
            </section>

            <section class="drone-editor-section" aria-labelledby="droneEditorLookTitle">
                <h2 id="droneEditorLookTitle">2. Appearance</h2>
                <div class="drone-editor-row">
                    <label for="droneEditorOpacity">Opacity</label>
                    <input type="range" id="droneEditorOpacity" min="10" max="100" step="5">
                    <output id="droneEditorOpacityValue" for="droneEditorOpacity"></output>
                </div>
                <div class="drone-editor-row" style="margin-top:10px">
                    <label for="droneEditorBase">Base map</label>
                    <select id="droneEditorBase">
                        <option value="sat">Satellite</option>
                        <option value="osm">Street</option>
                        <option value="topo">Topo</option>
                    </select>
                </div>
            </section>

            <div class="drone-editor-actions">
                <div id="droneEditorMessage" class="drone-editor-message" role="status" aria-live="polite"></div>
                <button type="button" class="drone-editor-btn" id="droneEditorSave"><i class="bi bi-floppy"></i> Save draft</button>
                <button type="button" class="drone-editor-btn primary" id="droneEditorConfirm"><i class="bi bi-check2-circle"></i> Confirm &amp; add to map</button>
            </div>
            </div>
        </aside>
    </div>
@endsection

@section('scripts')
    <script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.17.0/dist/leaflet-geoman.js"></script>
    @include('users.partials.drone-imagery-editor-scripts')
@endsection
