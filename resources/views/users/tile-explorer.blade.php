@extends('layouts.enduser')

@section('title', 'Tile Explorer - MangroveMap')

@section('styles')
    <style>
        .tx-page {
            max-width: 1320px;
            margin: 0 auto;
            color: var(--fg-1a2e1a, #1a2e1a);
        }

        .tx-page h1 {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .tx-sub {
            font-size: 14px;
            color: var(--fg-6b8a78, #6b8a78);
            margin-bottom: 18px;
        }

        .tx-card {
            background: var(--bg-ffffff, #fff);
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .04);
            margin-bottom: 16px;
        }


        .tx-field {
            display: grid;
            gap: 6px;
            min-width: 0;
        }

        .tx-field label,
        .tx-label {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--fg-6b8a78, #6b8a78);
        }

        .tx-select,
        .tx-input {
            width: 100%;
            min-width: 0;
            padding: 9px 11px;
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            border-radius: 10px;
            background: var(--bg-ffffff, #fff);
            color: var(--fg-1a2e1a, #1a2e1a);
            font: inherit;
            font-size: 14px;
        }

        .tx-select:focus,
        .tx-input:focus {
            outline: none;
            border-color: #1e9e62;
            box-shadow: 0 0 0 3px rgba(30, 158, 98, .15);
        }





        .tx-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 14px;
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            border-radius: 10px;
            background: var(--bg-ffffff, #fff);
            color: var(--fg-1a2e1a, #1a2e1a);
            font: inherit;
            font-size: 13.5px;
            font-weight: 700;
            white-space: nowrap;
            cursor: pointer;
            text-decoration: none;
        }

        .tx-btn:hover:not(:disabled) {
            border-color: #1e9e62;
            color: var(--fg-1e9e62, #1e9e62);
        }

        .tx-btn.primary {
            background: #1e9e62;
            border-color: #1e9e62;
            color: #fff;
        }

        .tx-btn.primary:hover:not(:disabled) {
            background: #16824f;
            color: #fff;
        }

        .tx-btn.ghost {
            border-color: transparent;
            background: transparent;
        }

        .tx-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .tx-spinner {
            display: inline-block;
            flex: none;
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: tx-spin .7s linear infinite;
        }

        @keyframes tx-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .tx-status {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 12px;
            background: var(--bg-f7fbf8, #f7fbf8);
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            font-size: 13.5px;
            color: var(--fg-2f5a46, #2f5a46);
            margin-bottom: 16px;
        }

        .tx-status[hidden],
        .tx-browser[hidden],
        .tx-empty[hidden] {
            display: none;
        }

        .tx-status.is-error {
            background: var(--bg-fdecea, #fdecea);
            border-color: var(--bd-e8b8b0, #e8b8b0);
            color: var(--fg-b42318, #b42318);
        }

        .tx-status.is-warning {
            background: var(--bg-fff8e6, #fff8e6);
            border-color: var(--bd-f0d9a0, #f0d9a0);
            color: var(--fg-8a5a00, #8a5a00);
        }

        .tx-status-body {
            flex: 1;
            min-width: 0;
        }

        .tx-status-body strong {
            display: block;
            margin-bottom: 2px;
        }

        .tx-progress-track {
            height: 8px;
            margin-top: 8px;
            border-radius: 999px;
            background: var(--bg-e3efe7, #e3efe7);
            overflow: hidden;
        }

        .tx-progress-fill {
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: #1e9e62;
            transition: width .4s ease;
        }

        .tx-progress-fill.is-indeterminate {
            width: 35%;
            animation: tx-slide 1.2s ease-in-out infinite;
        }

        @keyframes tx-slide {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(300%);
            }
        }

        .tx-top {
            display: grid;
            grid-template-columns: minmax(280px, 380px) 1fr;
            gap: 16px;
            align-items: start;
        }

        .tx-card-title {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: var(--fg-6b8a78, #6b8a78);
            margin: 0 0 14px;
        }

        .tx-upload-form {
            display: grid;
            gap: 10px;
        }

        .tx-drop {
            position: relative;
            display: grid;
            place-items: center;
            gap: 6px;
            padding: 22px 14px;
            border: 2px dashed var(--bd-b9d3c2, #b9d3c2);
            border-radius: 12px;
            background: var(--bg-f7fbf8, #f7fbf8);
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }

        .tx-drop:hover,
        .tx-drop.is-over {
            border-color: #1e9e62;
            background: var(--bg-edf7f2, #edf7f2);
        }

        .tx-drop i {
            font-size: 30px;
            color: var(--fg-1e9e62, #1e9e62);
        }

        .tx-drop strong {
            font-size: 14px;
        }

        .tx-drop span {
            font-size: 12.5px;
            color: var(--fg-6b8a78, #6b8a78);
        }

        .tx-drop input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .tx-file-info,
        .tx-upload-message {
            font-size: 12.5px;
            color: var(--fg-4c6b5d, #4c6b5d);
            overflow-wrap: anywhere;
        }

        .tx-file-info:empty,
        .tx-upload-message:empty {
            display: none;
        }

        .tx-upload-message.is-error {
            color: var(--fg-b42318, #b42318);
        }

        .tx-upload-progress[hidden] {
            display: none;
        }

        .tx-progress-label {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            color: var(--fg-4c6b5d, #4c6b5d);
        }

        .tx-source-list {
            display: grid;
            gap: 6px;
            max-height: 330px;
            overflow-y: auto;
        }

        .tx-source-row {
            display: flex;
            align-items: center;
            gap: 4px;
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 12px;
            background: var(--bg-ffffff, #fff);
            transition: border-color .12s, background .12s;
        }

        .tx-source-row:hover {
            border-color: var(--bd-9fd6b8, #9fd6b8);
        }

        .tx-source-row.is-active {
            border-color: #1e9e62;
            background: var(--bg-edf7f2, #edf7f2);
        }

        .tx-source-open {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 0;
            background: transparent;
            color: var(--fg-1a2e1a, #1a2e1a);
            font: inherit;
            text-align: left;
            cursor: pointer;
        }

        .tx-source-open>i {
            font-size: 20px;
            color: var(--fg-1e9e62, #1e9e62);
        }

        .tx-source-text {
            flex: 1;
            min-width: 0;
            display: grid;
        }

        .tx-source-text strong {
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tx-source-text small {
            font-size: 12px;
            color: var(--fg-6b8a78, #6b8a78);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tx-pill {
            flex: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 800;
            background: var(--bg-e3efe7, #e3efe7);
            color: var(--fg-2f5a46, #2f5a46);
        }

        .tx-pill.is-busy {
            background: var(--bg-e8f1fb, #e8f1fb);
            color: var(--fg-1d5fa8, #1d5fa8);
        }

        .tx-pill.is-failed {
            background: var(--bg-fdecea, #fdecea);
            color: var(--fg-b42318, #b42318);
        }

        .tx-pill .tx-spinner {
            width: 10px;
            height: 10px;
        }

        .tx-source-delete {
            flex: none;
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            margin-right: 6px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #8aa596;
            cursor: pointer;
        }

        .tx-source-delete:hover:not(:disabled) {
            background: var(--bg-fdecea, #fdecea);
            color: var(--fg-b42318, #b42318);
        }

        .tx-source-delete:disabled {
            opacity: .35;
            cursor: not-allowed;
        }

        .tx-empty.is-small {
            padding: 28px 12px;
            font-size: 13px;
        }

        .tx-empty.is-small i {
            font-size: 26px;
        }

        .tx-current {
            margin-top: 4px;
        }

        .tx-current[hidden] {
            display: none;
        }

        .tx-current-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px 16px;
            margin-bottom: 12px;
        }

        .tx-current-title {
            min-width: 0;
        }

        .tx-current-title h2 {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 2px;
            overflow-wrap: anywhere;
        }

        .tx-current-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tx-inline-label {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--fg-6b8a78, #6b8a78);
        }

        /* ---- Dataset browser (Roboflow-style) ---- */
        .tx-browser {
            background: var(--bg-ffffff, #fff);
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 16px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .04);
        }



        .tx-meta {
            font-size: 12.5px;
            color: var(--fg-6b8a78, #6b8a78);
        }

        .tx-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
        }

        .tx-search {
            position: relative;
            flex: 1 1 220px;
            max-width: 340px;
        }

        .tx-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #8aa596;
        }

        .tx-search .tx-input {
            padding-left: 34px;
        }

        .tx-chips {
            display: inline-flex;
            padding: 3px;
            gap: 2px;
            border-radius: 11px;
            background: var(--bg-f1f6f3, #f1f6f3);
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        }

        .tx-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 11px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--fg-4c6b5d, #4c6b5d);
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .tx-chip .tx-count {
            font-size: 11.5px;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 999px;
            background: var(--bg-e3efe7, #e3efe7);
            color: var(--fg-2f5a46, #2f5a46);
        }

        .tx-chip.is-active {
            background: var(--bg-ffffff, #fff);
            color: var(--fg-1a2e1a, #1a2e1a);
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        }

        .tx-chip.is-active .tx-count {
            background: #1e9e62;
            color: #fff;
        }

        .tx-sort {
            width: auto;
            padding: 8px 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .tx-toolbar-end {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }

        .tx-switch {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--fg-2f5a46, #2f5a46);
            cursor: pointer;
            user-select: none;
        }

        .tx-switch input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .tx-switch-track {
            position: relative;
            width: 34px;
            height: 20px;
            border-radius: 999px;
            background: var(--bg-cfdcd3, #cfdcd3);
            transition: background .15s;
        }

        .tx-switch-track::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--bg-ffffff, #fff);
            transition: transform .15s;
        }

        .tx-switch input:checked+.tx-switch-track {
            background: #1e9e62;
        }

        .tx-switch input:checked+.tx-switch-track::after {
            transform: translateX(14px);
        }

        .tx-switch input:focus-visible+.tx-switch-track {
            box-shadow: 0 0 0 3px rgba(30, 158, 98, .3);
        }

        .tx-density {
            display: inline-flex;
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            border-radius: 10px;
            overflow: hidden;
        }

        .tx-density button {
            padding: 7px 10px;
            border: 0;
            background: var(--bg-ffffff, #fff);
            color: var(--fg-6b8a78, #6b8a78);
            cursor: pointer;
            font-size: 15px;
        }

        .tx-density button+button {
            border-left: 1px solid var(--bd-d4e0d4, #d4e0d4);
        }

        .tx-density button.is-active {
            background: var(--bg-edf7f2, #edf7f2);
            color: var(--fg-1e9e62, #1e9e62);
        }

        .tx-selbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 14px;
            padding: 10px 20px;
            border-top: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
            background: var(--bg-f7fbf8, #f7fbf8);
        }

        .tx-selbar.has-selection {
            background: var(--bg-edf7f2, #edf7f2);
        }

        .tx-checkall {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
        }

        .tx-checkall input {
            width: 17px;
            height: 17px;
            accent-color: #1e9e62;
            cursor: pointer;
        }

        .tx-selcount {
            font-size: 13.5px;
            color: var(--fg-2f5a46, #2f5a46);
        }

        .tx-selcount strong {
            color: var(--fg-1a2e1a, #1a2e1a);
        }

        .tx-selbar-end {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .tx-format {
            width: auto;
            padding: 8px 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .tx-grid {
            --tx-card-min: 190px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(var(--tx-card-min), 1fr));
            gap: 20px 16px;
            padding: 20px;
        }

        .tx-grid.is-compact {
            --tx-card-min: 128px;
            gap: 14px 10px;
        }

        .tx-tile {
            position: relative;
            min-width: 0;
            cursor: pointer;
            outline: none;
        }

        .tx-thumb {
            position: relative;
            aspect-ratio: 1;
            border-radius: 6px;
            overflow: hidden;
            background: var(--bg-e9efeb, #e9efeb);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, .06);
            transition: box-shadow .12s, transform .12s;
        }

        .tx-tile:hover .tx-thumb {
            box-shadow: 0 0 0 2px var(--bd-9fd6b8, #9fd6b8), 0 4px 14px rgba(0, 0, 0, .12);
        }

        .tx-tile:focus-visible .tx-thumb {
            box-shadow: 0 0 0 3px #1e9e62;
        }

        .tx-tile.is-selected .tx-thumb {
            box-shadow: 0 0 0 3px #1e9e62, 0 4px 14px rgba(30, 158, 98, .25);
        }

        .tx-thumb img,
        .tx-thumb svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            display: block;
        }

        .tx-thumb img {
            object-fit: cover;
        }

        .tx-outline path,
        .tx-preview-outline path {
            fill: rgba(46, 230, 160, .14);
            stroke: var(--fg-2ee6a0, #2ee6a0);
            stroke-width: 2.4;
            stroke-linejoin: round;
            fill-rule: evenodd;
            vector-effect: non-scaling-stroke;
        }

        .tx-hide-areas .tx-outline {
            display: none;
        }

        .tx-check {
            position: absolute;
            top: 8px;
            left: 8px;
            display: grid;
            place-items: center;
            width: 22px;
            height: 22px;
            border-radius: 6px;
            border: 2px solid var(--bd-ffffff, #fff);
            background: rgba(0, 0, 0, .25);
            color: #fff;
            font-size: 15px;
            opacity: 0;
            transition: opacity .12s;
        }

        .tx-check i {
            visibility: hidden;
        }

        .tx-tile:hover .tx-check,
        .tx-tile:focus-visible .tx-check,
        .tx-grid.is-selecting .tx-check {
            opacity: 1;
        }

        .tx-tile.is-selected .tx-check {
            opacity: 1;
            border-color: #1e9e62;
            background: #1e9e62;
        }

        .tx-tile.is-selected .tx-check i {
            visibility: visible;
        }

        .tx-zoom {
            position: absolute;
            top: 8px;
            right: 8px;
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 8px;
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), .92);
            color: var(--fg-1a2e1a, #1a2e1a);
            font-size: 13px;
            cursor: pointer;
            opacity: 0;
            transition: opacity .12s;
        }

        .tx-tile:hover .tx-zoom,
        .tx-tile:focus-within .tx-zoom {
            opacity: 1;
        }

        .tx-badge {
            position: absolute;
            left: 8px;
            bottom: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px 3px 6px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 800;
            line-height: 1.3;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .2);
        }

        .tx-badge.is-mangrove {
            background: var(--bg-e5f8ee, #e5f8ee);
            color: var(--fg-127046, #127046);
            border: 1.5px solid #1e9e62;
        }

        .tx-badge.is-none {
            background: var(--bg-f2f4f3, #f2f4f3);
            color: var(--fg-5b6b62, #5b6b62);
            border: 1.5px solid var(--bd-c7d1cb, #c7d1cb);
        }

        .tx-badge.is-unknown {
            background: var(--bg-fff8e6, #fff8e6);
            color: var(--fg-8a5a00, #8a5a00);
            border: 1.5px solid #e8c56a;
        }

        .tx-name {
            margin-top: 7px;
            font-size: 12.5px;
            color: var(--fg-4c6b5d, #4c6b5d);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tx-grid.is-compact .tx-name,
        .tx-grid.is-compact .tx-zoom {
            display: none;
        }

        .tx-grid.is-compact .tx-badge {
            font-size: 10.5px;
            padding: 2px 6px 2px 5px;
        }

        .tx-sentinel {
            height: 1px;
        }

        .tx-empty {
            display: grid;
            place-items: center;
            gap: 8px;
            padding: 48px 20px;
            text-align: center;
            color: var(--fg-6b8a78, #6b8a78);
            font-size: 14px;
        }

        .tx-empty i {
            font-size: 34px;
            color: #9fbfae;
        }

        .tx-empty strong {
            color: var(--fg-1a2e1a, #1a2e1a);
            font-size: 15px;
        }

        /* ---- Preview ---- */
        .tx-dialog {
            width: min(1100px, calc(100vw - 32px));
            max-height: calc(100vh - 32px);
            padding: 0;
            border: 0;
            border-radius: 16px;
            background: var(--bg-ffffff, #fff);
            color: var(--fg-1a2e1a, #1a2e1a);
            box-shadow: 0 20px 60px rgba(0, 0, 0, .35);
            overflow: hidden;
        }

        .tx-dialog::backdrop {
            background: rgba(10, 20, 14, .72);
        }

        .tx-dialog-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            max-height: calc(100vh - 32px);
        }

        .tx-preview {
            position: relative;
            display: grid;
            place-items: center;
            background: #0d1611;
            min-height: 320px;
        }

        .tx-preview-frame {
            position: relative;
            width: min(100%, calc(100vh - 32px));
            aspect-ratio: 1;
        }

        .tx-preview-frame img,
        .tx-preview-frame svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }

        .tx-preview-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 50%;
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), .9);
            color: var(--fg-1a2e1a, #1a2e1a);
            font-size: 18px;
            cursor: pointer;
            z-index: 1;
        }

        .tx-preview-nav:disabled {
            opacity: .3;
            cursor: default;
        }

        .tx-preview-nav.prev {
            left: 12px;
        }

        .tx-preview-nav.next {
            right: 12px;
        }

        .tx-side {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 18px;
            overflow-y: auto;
        }

        .tx-side-head {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 10px;
        }

        .tx-side-head h3 {
            font-size: 15px;
            font-weight: 800;
            margin: 0;
            overflow-wrap: anywhere;
        }

        .tx-close {
            flex: none;
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 8px;
            background: var(--bg-f1f6f3, #f1f6f3);
            color: var(--fg-1a2e1a, #1a2e1a);
            font-size: 16px;
            cursor: pointer;
        }

        .tx-facts {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 8px 12px;
            margin: 0;
            font-size: 13px;
        }

        .tx-facts dt {
            color: var(--fg-6b8a78, #6b8a78);
            font-weight: 600;
        }

        .tx-facts dd {
            margin: 0;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .tx-coverage-bar {
            height: 6px;
            margin-top: 5px;
            border-radius: 999px;
            background: var(--bg-e3efe7, #e3efe7);
            overflow: hidden;
        }

        .tx-coverage-bar span {
            display: block;
            height: 100%;
            background: #1e9e62;
        }

        .tx-side-actions {
            display: grid;
            gap: 8px;
            margin-top: auto;
        }

        .tx-hint {
            font-size: 12px;
            color: var(--fg-6b8a78, #6b8a78);
        }

        .tx-hint kbd {
            padding: 1px 5px;
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            border-radius: 4px;
            background: var(--bg-f7fbf8, #f7fbf8);
            font-size: 11px;
        }

        @media (max-width: 900px) {
            .tx-top {
                grid-template-columns: 1fr;
            }



            .tx-dialog-body {
                grid-template-columns: 1fr;
                overflow-y: auto;
            }

            .tx-preview-frame {
                width: 100%;
            }
        }

        @media (max-width: 600px) {

            .tx-card,
            .tx-toolbar,
            .tx-selbar {
                padding-left: 14px;
                padding-right: 14px;
            }

            .tx-grid {
                --tx-card-min: 140px;
                padding: 14px;
                gap: 14px 10px;
            }

            .tx-grid.is-compact {
                --tx-card-min: 96px;
            }

            .tx-search {
                max-width: none;
            }

            .tx-toolbar-end,
            .tx-selbar-end {
                margin-left: 0;
            }

            .tx-check {
                opacity: 1;
            }
        }
    </style>
@endsection

@section('content')
    <div class="tx-page">
        <h1>Tile Explorer</h1>
        <p class="tx-sub">Upload a GeoTIFF, cut it into equal tiles, pick the ones with mangroves and download them together as one ZIP.
            Files uploaded here are only for tiles: they are not added to the map or sent for review.</p>

        <div class="tx-top">
            <section class="tx-card tx-upload" aria-labelledby="txUploadTitle">
                <h2 class="tx-card-title" id="txUploadTitle">Upload GeoTIFF</h2>
                <form id="txUploadForm" class="tx-upload-form">
                    <label class="tx-drop" id="txDrop">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <strong>Drop a GeoTIFF here or click to choose</strong>
                        <span>.tif / .tiff · 8-bit RGB with coordinates · up to 1 GB</span>
                        <input id="txFile" type="file" accept=".tif,.tiff,image/tiff">
                    </label>
                    <div id="txFileInfo" class="tx-file-info" aria-live="polite"></div>
                    <div class="tx-field">
                        <label for="txUploadSize">Tile size</label>
                        <select id="txUploadSize" class="tx-select"></select>
                    </div>
                    <button type="submit" class="tx-btn primary" id="txUploadButton" disabled><i class="bi bi-upload"></i> <span>Upload and make tiles</span></button>
                    <div class="tx-upload-progress" id="txUploadProgress" hidden>
                        <div class="tx-progress-label"><span>Uploading</span><span id="txUploadPercent"></span></div>
                        <div class="tx-progress-track" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="txUploadBar">
                            <div class="tx-progress-fill" id="txUploadFill"></div>
                        </div>
                    </div>
                    <div id="txUploadMessage" class="tx-upload-message" role="status" aria-live="polite"></div>
                </form>
            </section>

            <section class="tx-card tx-sources" aria-labelledby="txSourcesTitle">
                <h2 class="tx-card-title" id="txSourcesTitle">Your GeoTIFFs</h2>
                <div id="txSourceList" class="tx-source-list" role="list"></div>
                <div id="txNoSources" class="tx-empty is-small" hidden>
                    <i class="bi bi-images"></i>
                    <span>Nothing uploaded yet. Your GeoTIFFs and their tiles will be listed here.</span>
                </div>
            </section>
        </div>

        <section id="txCurrent" class="tx-current" aria-labelledby="txTitle" hidden>
            <div class="tx-current-head">
                <div class="tx-current-title">
                    <h2 id="txTitle"></h2>
                    <span class="tx-meta" id="txMeta"></span>
                </div>
                <div class="tx-current-actions">
                    <label for="txSize" class="tx-inline-label">Tile size</label>
                    <select id="txSize" class="tx-select tx-sort"></select>
                    <button type="button" class="tx-btn" id="txGenerate"></button>
                </div>
            </div>

            <div id="txStatus" class="tx-status" role="status" aria-live="polite" hidden>
                <div class="tx-status-body">
                    <strong id="txStatusTitle"></strong>
                    <span id="txStatusText"></span>
                    <div class="tx-progress-track" id="txProgressTrack" hidden>
                        <div class="tx-progress-fill" id="txProgressFill"></div>
                    </div>
                </div>
                <button type="button" class="tx-btn" id="txStatusAction" hidden></button>
            </div>

            <section id="txBrowser" class="tx-browser" aria-label="Tiles" hidden>

            <div class="tx-toolbar">
                <div class="tx-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="txSearch" class="tx-input" placeholder="Search tiles, e.g. r004 or c012" aria-label="Search tiles">
                </div>
                <div class="tx-chips" role="group" aria-label="Filter tiles">
                    <button type="button" class="tx-chip is-active" data-filter="all" aria-pressed="true">All <span class="tx-count" data-count="all">0</span></button>
                    <button type="button" class="tx-chip" data-filter="mangrove" aria-pressed="false"><i class="bi bi-tree-fill"></i> Mangrove <span class="tx-count" data-count="mangrove">0</span></button>
                    <button type="button" class="tx-chip" data-filter="none" aria-pressed="false">No mangrove <span class="tx-count" data-count="none">0</span></button>
                </div>
                <select id="txSort" class="tx-select tx-sort" aria-label="Sort tiles">
                    <option value="position">Sort: Position</option>
                    <option value="most">Sort: Most mangrove</option>
                    <option value="least">Sort: Least mangrove</option>
                </select>
                <div class="tx-toolbar-end">
                    <label class="tx-switch">
                        <input type="checkbox" id="txShowAreas" checked>
                        <span class="tx-switch-track" aria-hidden="true"></span>
                        Show mangrove areas
                    </label>
                    <div class="tx-density" role="group" aria-label="Tile size in the grid">
                        <button type="button" data-density="comfortable" class="is-active" title="Large tiles" aria-pressed="true"><i class="bi bi-grid"></i></button>
                        <button type="button" data-density="compact" title="Small tiles" aria-pressed="false"><i class="bi bi-grid-3x3"></i></button>
                    </div>
                </div>
            </div>

            <div class="tx-selbar" id="txSelbar">
                <label class="tx-checkall">
                    <input type="checkbox" id="txSelectAll">
                    <span id="txSelectAllLabel">Select all</span>
                </label>
                <span class="tx-selcount" id="txSelCount"></span>
                <div class="tx-selbar-end">
                    <button type="button" class="tx-btn ghost" id="txSelectMangrove"><i class="bi bi-tree"></i> Select mangrove tiles</button>
                    <button type="button" class="tx-btn ghost" id="txClear"><i class="bi bi-x-lg"></i> Clear</button>
                    <select id="txFormat" class="tx-select tx-format" aria-label="Download format">
                        <option value="both">GeoTIFF + JPEG</option>
                        <option value="tif">GeoTIFF only</option>
                        <option value="jpg">JPEG only (Roboflow)</option>
                    </select>
                    <button type="button" class="tx-btn primary" id="txDownload"><i class="bi bi-file-earmark-zip"></i> <span>Download selected</span></button>
                </div>
            </div>

            <div class="tx-grid" id="txGrid" role="listbox" aria-multiselectable="true" aria-label="Tiles"></div>
            <div class="tx-sentinel" id="txSentinel"></div>
            <div class="tx-empty" id="txGridEmpty" hidden>
                <i class="bi bi-funnel"></i>
                <strong>No tiles match</strong>
                <span>Change the search or the filter.</span>
            </div>
            </section>
        </section>
    </div>

    <dialog class="tx-dialog" id="txDialog" aria-labelledby="txPreviewName">
        <div class="tx-dialog-body">
            <div class="tx-preview">
                <button type="button" class="tx-preview-nav prev" id="txPrev" aria-label="Previous tile"><i class="bi bi-chevron-left"></i></button>
                <div class="tx-preview-frame">
                    <img id="txPreviewImg" alt="">
                    <svg class="tx-preview-outline" id="txPreviewOutline" preserveAspectRatio="none" aria-hidden="true"></svg>
                </div>
                <button type="button" class="tx-preview-nav next" id="txNext" aria-label="Next tile"><i class="bi bi-chevron-right"></i></button>
            </div>
            <aside class="tx-side">
                <div class="tx-side-head">
                    <h3 id="txPreviewName"></h3>
                    <button type="button" class="tx-close" id="txClose" aria-label="Close preview"><i class="bi bi-x-lg"></i></button>
                </div>
                <dl class="tx-facts" id="txFacts"></dl>
                <div class="tx-side-actions">
                    <button type="button" class="tx-btn primary" id="txPreviewSelect"></button>
                    <span class="tx-hint"><kbd>←</kbd> <kbd>→</kbd> next tile · <kbd>Space</kbd> select · <kbd>Esc</kbd> close</span>
                </div>
            </aside>
        </div>
    </dialog>

    {{-- The ZIP is posted into this frame: a download leaves the page alone, an error page is detected. --}}
    <iframe name="txDownloadFrame" id="txDownloadFrame" title="Download" hidden></iframe>
@endsection

@section('scripts')
    @include('partials.chunked-upload')
    @include('users.partials.tile-explorer-scripts')
@endsection
