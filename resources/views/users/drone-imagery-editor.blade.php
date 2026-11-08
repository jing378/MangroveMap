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
            background: var(--bg-dfe7df, #dfe7df);
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
            color: var(--fg-4c6b5d, #4c6b5d);
            border: 1px solid var(--bd-d4dfd4, #d4dfd4);
            border-radius: 8px;
            background: var(--bg-ffffff, #fff);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
            transition: all .15s ease;
        }

        .drone-editor-tool:hover:not(:disabled) {
            background: var(--bg-f7faf7, #f7faf7);
            border-color: var(--bd-c4d4c4, #c4d4c4);
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
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), .7);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, .08);
        }

        .drone-editor-history {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--bd-d4dfd4, #d4dfd4);
            border-radius: 10px;
            background: var(--bg-ffffff, #fff);
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
            color: var(--fg-4c6b5d, #4c6b5d);
        }

        .drone-editor-history button + button {
            border-left: 1px solid var(--bd-e0e8e0, #e0e8e0);
        }

        .drone-editor-history button:hover:not(:disabled) {
            background: var(--bg-f7faf7, #f7faf7);
            color: var(--fg-1e9e62, #1e9e62);
        }

        .drone-editor-history button:disabled {
            color: #c2cec6;
            cursor: not-allowed;
        }

        .drone-editor-panel {
            width: 340px;
            flex-shrink: 0;
            overflow: hidden;
            background: var(--bg-ffffff, #fff);
            border-left: 1px solid var(--bd-e0e8e0, #e0e8e0);
            display: flex;
            flex-direction: column;
            color: var(--fg-1a2e1a, #1a2e1a);
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
            color: var(--fg-1e9e62, #1e9e62);
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
            background: var(--bg-edf7f2, #edf7f2);
            color: var(--fg-2f5a46, #2f5a46);
        }

        .drone-editor-status.is-failed {
            background: var(--bg-fdecea, #fdecea);
            color: var(--fg-a12f22, #a12f22);
        }

        .drone-editor-section h2 {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: var(--fg-6b8a78, #6b8a78);
            margin-bottom: 8px;
        }

        .drone-editor-section p {
            font-size: 13px;
            line-height: 1.5;
            color: var(--fg-4c6b5d, #4c6b5d);
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
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            background: var(--bg-ffffff, #fff);
            color: var(--fg-1a2e1a, #1a2e1a);
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .drone-editor-btn:hover:not(:disabled) {
            border-color: #1e9e62;
            color: var(--fg-1e9e62, #1e9e62);
        }

        .drone-editor-btn.is-active {
            background: #1e9e62;
            border-color: #1e9e62;
            color: #fff;
        }

        /* A link styled as a full-width panel button (no link underline). */
        .drone-editor-link-btn {
            display: flex;
            width: 100%;
            box-sizing: border-box;
            margin-top: 4px;
            text-decoration: none;
        }

        .drone-editor-link-btn:hover,
        .drone-editor-link-btn:focus-visible {
            text-decoration: none;
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
            color: var(--fg-4c6b5d, #4c6b5d);
        }

        .drone-editor-ai-status.is-failed {
            color: var(--fg-b42318, #b42318);
        }

        .drone-editor-count {
            font-size: 12px;
            color: var(--fg-6b8a78, #6b8a78);
            margin-top: 8px;
        }

        .drone-editor-shapes {
            list-style: none;
            margin: 8px 0 0;
            padding: 0;
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 10px;
        }

        .drone-editor-shape {
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--bd-eef3ee, #eef3ee);
        }

        .drone-editor-shape:last-child {
            border-bottom: none;
        }

        .drone-editor-shape:hover {
            background: var(--bg-f4fbf7, #f4fbf7);
        }

        .drone-editor-shape-label {
            flex: 1;
            text-align: left;
            padding: 7px 10px;
            border: none;
            background: none;
            font: inherit;
            font-size: 13px;
            color: var(--fg-1a2e1a, #1a2e1a);
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
            color: var(--fg-b42318, #b42318);
            cursor: pointer;
        }

        .drone-editor-shape-remove:hover {
            background: var(--bg-fdecea, #fdecea);
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
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            border-radius: 8px;
            font: inherit;
            font-size: 13px;
        }

        /* 3. Delineation details: submitted with the imagery on the first confirm. */
        .drone-editor-field {
            display: block;
            margin-bottom: 10px;
        }

        .drone-editor-field > span {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--fg-4c6b5d, #4c6b5d);
            margin-bottom: 5px;
        }

        .drone-editor-field input[type="text"],
        .drone-editor-field textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            border-radius: 8px;
            background: var(--bg-f8faf7, #f8faf7);
            color: var(--fg-182918, #182918);
            font: inherit;
            font-size: 13px;
            resize: vertical;
        }

        .drone-editor-genera {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }

        .drone-editor-genus {
            all: unset;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 9px;
            border: 1px solid var(--bd-d4dfd4, #d4dfd4);
            border-radius: 100px;
            background: var(--bg-f8faf7, #f8faf7);
            color: var(--fg-445944, #445944);
            font-family: inherit;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s ease;
            user-select: none;
        }

        .drone-editor-genus:hover {
            border-color: #1e9e62;
            background: var(--bg-f0f7f2, #f0f7f2);
            color: var(--fg-1e9e62, #1e9e62);
        }

        .drone-editor-genus.active {
            background: #1e9e62;
            border-color: #1e9e62;
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(30, 158, 98, 0.25);
        }

        .drone-editor-genus:focus-visible {
            outline: 2px solid #1e9e62;
            outline-offset: 2px;
        }

        .drone-editor-genus small {
            color: inherit;
            font-weight: 400;
        }

        #droneEditorOtherGenera {
            border-color: var(--bd-d4dfd4, #d4dfd4);
            font-size: 12px;
        }

        .drone-editor-other-genera-row {
            display: flex;
            gap: 6px;
        }

        .drone-editor-add-genus {
            flex: none;
            padding: 7px 12px;
            font-size: 12px;
            border-radius: 8px;
            white-space: nowrap;
        }

        .drone-editor-selected-genera {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .drone-editor-selected-genus {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            border: 1px solid var(--bd-b0e0c0, #b0e0c0);
            border-radius: 100px;
            background: var(--bg-edf7f2, #edf7f2);
            color: var(--fg-1e9e62, #1e9e62);
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
        }

        .drone-editor-location {
            font-size: 13px;
            font-weight: 700;
            color: var(--fg-182918, #182918);
        }

        .drone-editor-location.is-muted {
            font-weight: 400;
            color: var(--fg-6b8a78, #6b8a78);
        }

        .drone-editor-summary {
            display: grid;
            gap: 8px;
            margin: 0 0 10px;
            font-size: 13px;
        }

        .drone-editor-summary dt {
            font-size: 12px;
            font-weight: 700;
            color: var(--fg-4c6b5d, #4c6b5d);
            margin-bottom: 2px;
        }

        .drone-editor-summary dd {
            margin: 0;
            color: var(--fg-182918, #182918);
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            overflow-wrap: anywhere;
        }

        .drone-editor-summary-notes {
            white-space: pre-line;
        }

        .drone-editor-state {
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .drone-editor-state.is-pending { background: var(--bg-e6f0ff, #e6f0ff); color: var(--fg-1d4fa3, #1d4fa3); }
        .drone-editor-state.is-approved { background: var(--bg-e3f6ec, #e3f6ec); color: var(--fg-16824f, #16824f); }
        .drone-editor-state.is-rejected { background: var(--bg-fdecea, #fdecea); color: var(--fg-b42318, #b42318); }

        .drone-editor-selected-genus button {
            all: unset;
            cursor: pointer;
            font-size: 12px;
            margin-left: 2px;
            opacity: .7;
        }

        .drone-editor-selected-genus button:hover {
            opacity: 1;
            color: var(--fg-d04030, #d04030);
        }

        .drone-editor-genus-suggestion {
            margin-bottom: 8px;
            padding: 8px 10px;
            border: 1px dashed var(--bd-9fc9e8, #9fc9e8);
            border-radius: 8px;
            background: var(--bg-f2f8fd, #f2f8fd);
            font-size: 12px;
            color: var(--fg-35526b, #35526b);
        }

        .drone-editor-genus-suggestion p {
            margin: 0 0 6px;
            line-height: 1.4;
        }

        .drone-editor-genus-suggestion p:last-child {
            margin-bottom: 0;
        }

        .drone-editor-genus-badge {
            display: inline-block;
            margin-left: 4px;
            padding: 0 6px;
            border-radius: 100px;
            background: var(--bg-fff3cd, #fff3cd);
            color: var(--fg-7a5a00, #7a5a00);
            font-size: 10px;
            font-weight: 600;
            vertical-align: 1px;
        }

        .drone-editor-genus-probability {
            all: unset;
            box-sizing: border-box;
            display: grid;
            grid-template-columns: 14px 82px 1fr 42px;
            align-items: center;
            gap: 2px 8px;
            width: 100%;
            margin-bottom: 4px;
            padding: 5px 8px;
            /* Bordered so each row reads as a button. */
            border: 1px solid var(--bd-9fc9e8, #9fc9e8);
            border-radius: 6px;
            background: var(--bg-ffffff, #ffffff);
            color: var(--fg-1d4fa3, #1d4fa3);
            cursor: pointer;
            font-size: 11px;
        }

        .drone-editor-genus-probability:hover:not(:disabled) {
            border-color: var(--bd-1d4fa3, #1d4fa3);
            background: var(--bg-e3effa, #e3effa);
        }

        .drone-editor-genus-probabilities small {
            font-size: 11px;
            opacity: .8;
        }

        .drone-editor-genus-probability:focus-visible {
            outline: 2px solid #1d4fa3;
            outline-offset: 1px;
        }

        .drone-editor-genus-probability:disabled {
            cursor: default;
            opacity: .6;
        }

        .drone-editor-genus-probability-name {
            font-weight: 600;
            color: var(--fg-1d4fa3, #1d4fa3);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .drone-editor-genus-probability-track {
            height: 8px;
            border-radius: 100px;
            background: var(--bg-d6e6f3, #d6e6f3);
            overflow: hidden;
        }

        .drone-editor-genus-probability-fill {
            display: block;
            height: 100%;
            border-radius: 100px;
            background: #3a7bd5;
        }

        .drone-editor-genus-probability-value {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .drone-editor-genus-freshness {
            margin: 0 0 6px;
            padding: 4px 8px;
            border-radius: 6px;
            background: var(--bg-e3effa, #e3effa);
            font-size: 11px;
        }

        /* Second line under the genus name: how sure the model is. */
        .drone-editor-genus-probability-tag {
            grid-column: 2 / -1;
            font-size: 10px;
            color: var(--fg-6b7f90, #6b7f90);
        }

        .drone-editor-genus-probability-tag.is-confident {
            color: var(--fg-1a7f4b, #1a7f4b);
            font-weight: 600;
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
            color: var(--fg-4c6b5d, #4c6b5d);
        }

        .drone-editor-message.is-error {
            color: var(--fg-b42318, #b42318);
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
                border-top: 1px solid var(--bd-d4dfd4, #d4dfd4);
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
                border-bottom: 1px solid var(--bd-edf2ed, #edf2ed);
                background: var(--bg-ffffff, #fff);
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
                background: var(--bg-c5d5c5, #c5d5c5);
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
                color: var(--fg-1b2e1b, #1b2e1b);
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
                color: var(--fg-2e6b3c, #2e6b3c);
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
            /* Flush with the bottom of the sheet: the negative margins cancel the body's side/bottom padding,
               so the bar spans the sheet and nothing scrolls visibly underneath it. The two buttons sit side
               by side (the message, when shown, takes the row above them); room is kept for the home
               indicator on phones without a home button. */
            .drone-editor-actions {
                position: sticky;
                bottom: -20px;
                z-index: 2;
                grid-template-columns: 1fr 1fr;
                margin: 8px -14px -20px;
                padding: 10px 14px calc(10px + env(safe-area-inset-bottom, 0px));
                background: var(--bg-ffffff, #fff);
                border-top: 1px solid var(--bd-edf2ed, #edf2ed);
                box-shadow: 0 -4px 12px rgba(0, 0, 0, .06);
            }

            .drone-editor-actions .drone-editor-message {
                grid-column: 1 / -1;
            }

            .drone-editor-actions .drone-editor-btn {
                min-width: 0;
                min-height: 44px;
                padding: 10px 8px;
                border-radius: 8px;
                font-size: 13px;
                line-height: 1.2;
                text-align: center;
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
            <div class="drone-editor-toolbar" @if ($editor['read_only']) style="display:none" @endif>
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

            {{-- AI status (area count / failure + retry). The heading, instructions and per-area list were removed on request; areas are edited on the map. --}}
            <section class="drone-editor-section" aria-label="Mangrove areas">
                <div id="droneEditorAiStatus" class="drone-editor-ai-status" aria-live="polite"></div>
                <div class="drone-editor-tools">
                    <button type="button" class="drone-editor-btn" id="droneEditorAiRun" hidden><i class="bi bi-cpu"></i> Run AI detection</button>
                </div>
            </section>

            @if ($editor['needs_delineation'])
                <section class="drone-editor-section" aria-labelledby="droneEditorDelineationTitle">
                    <h2 id="droneEditorDelineationTitle">Delineation details</h2>
                    <p class="drone-editor-hint">
                        @if ($editor['needs_expert_review'])
                            The mangrove areas are submitted as a delineation with this imagery. An expert reviews them;
                            once approved, the imagery and the delineation appear on the map.
                        @else
                            The mangrove areas are published as a delineation with this imagery as soon as it is processed.
                        @endif
                    </p>
                    <div class="drone-editor-field">
                        <span>Location</span>
                        <div class="drone-editor-location" id="droneEditorLocation" aria-live="polite"></div>
                    </div>
                    <label class="drone-editor-field">
                        <span>Name</span>
                        <input type="text" id="droneEditorDelineationName" maxlength="191" value="{{ $layer->name }}">
                    </label>
                    <div class="drone-editor-field" role="group" aria-labelledby="droneEditorGeneraLabel">
                        <span id="droneEditorGeneraLabel">Mangrove Genus Present (required)</span>
                        {{-- Filled from segmentation_stats.genus_suggestion by the editor script. --}}
                        <div class="drone-editor-genus-suggestion" id="droneEditorGenusSuggestion" aria-live="polite" hidden></div>
                        <div class="drone-editor-genera" id="droneEditorGenera">
                            @foreach ([
                                'Rhizophora' => 'Bakawan',
                                'Avicennia' => 'Bungalon',
                                'Sonneratia' => 'Pagatpat',
                                'Bruguiera' => 'Miapi',
                                'Ceriops' => 'Tungog',
                                'Xylocarpus' => 'Tabigi',
                                'Nypa' => 'Nipa/Sasa',
                            ] as $genus => $localName)
                                <button type="button" class="drone-editor-genus" data-genus="{{ $genus }}" aria-pressed="false">
                                    <i class="bi bi-plus" aria-hidden="true"></i> {{ $genus }} <small>({{ $localName }})</small>
                                </button>
                            @endforeach
                        </div>
                        <div class="drone-editor-other-genera-row">
                            <input type="text" id="droneEditorOtherGenera" maxlength="200" placeholder="Or type other genus..." aria-label="Other genus">
                            <button type="button" class="drone-editor-btn primary drone-editor-add-genus" id="droneEditorAddGenus">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> Add
                            </button>
                        </div>
                        <div class="drone-editor-selected-genera" id="droneEditorSelectedGenera" aria-live="polite"></div>
                    </div>
                    <label class="drone-editor-field">
                        <span>Notes (optional)</span>
                        <textarea id="droneEditorDelineationNotes" rows="3" maxlength="5000" placeholder="Anything the reviewer should know about this area"></textarea>
                    </label>
                </section>
            @elseif ($submitted = $editor['submitted_delineation'])
                {{-- Already submitted: what was sent, read-only. It is edited on the map like any delineation. --}}
                <section class="drone-editor-section" aria-labelledby="droneEditorSubmittedTitle">
                    <h2 id="droneEditorSubmittedTitle">Submitted delineation</h2>
                    <dl class="drone-editor-summary">
                        <div>
                            <dt>Name</dt>
                            <dd>{{ $submitted['name'] }}</dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>
                                @switch($submitted['review_state'])
                                    @case('approved')
                                        <span class="drone-editor-state is-approved">Approved · on the map</span>
                                        @break
                                    @case('rejected')
                                        <span class="drone-editor-state is-rejected">Not approved</span>
                                        @break
                                    @default
                                        <span class="drone-editor-state is-pending">Awaiting expert review</span>
                                @endswitch
                            </dd>
                        </div>
                        @if ($submitted['rejection_notes'])
                            <div>
                                <dt>Expert feedback</dt>
                                <dd>{{ $submitted['rejection_notes'] }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt>Location</dt>
                            <dd>
                                @if ($submitted['location']['text'])
                                    {{ $submitted['location']['text'] }}
                                @elseif ($submitted['location']['status'] === 'pending')
                                    Location pending boundary import
                                @else
                                    Outside loaded barangay boundaries
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Mangrove areas</dt>
                            <dd>{{ $submitted['area_count'] }}</dd>
                        </div>
                        <div>
                            <dt>Mangrove Genus Present</dt>
                            <dd>
                                @forelse ($submitted['genera'] as $genus)
                                    <span class="drone-editor-selected-genus">{{ $genus }}</span>
                                @empty
                                    —
                                @endforelse
                            </dd>
                        </div>
                        <div>
                            <dt>Notes</dt>
                            <dd class="drone-editor-summary-notes">{{ $submitted['notes'] ?: '—' }}</dd>
                        </div>
                    </dl>
                    <p class="drone-editor-hint">Changes to the submitted areas, genus or notes are made on the map, like any delineation.</p>
                    <a href="{{ $submitted['url'] }}" class="drone-editor-btn drone-editor-link-btn"><i class="bi bi-geo-alt"></i> Open delineation on the map</a>
                </section>
            @endif

            {{-- View-only (approved): kept in the page for the script, but nothing to save or confirm. --}}
            <div class="drone-editor-actions" @if ($editor['read_only']) style="display:none" @endif>
                <div id="droneEditorMessage" class="drone-editor-message" role="status" aria-live="polite"></div>
                <button type="button" class="drone-editor-btn" id="droneEditorSave"><i class="bi bi-floppy"></i> Save draft</button>
                <button type="button" class="drone-editor-btn primary" id="droneEditorConfirm">
                    <i class="bi bi-check2-circle"></i>
                    @if (! $editor['needs_delineation'])
                        Confirm &amp; apply
                    @elseif ($editor['needs_expert_review'])
                        Confirm &amp; submit for review
                    @else
                        Confirm &amp; publish
                    @endif
                </button>
            </div>
            </div>
        </aside>
    </div>
@endsection

@section('scripts')
    <script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.17.0/dist/leaflet-geoman.js"></script>
    @include('users.partials.drone-imagery-editor-scripts')
@endsection
