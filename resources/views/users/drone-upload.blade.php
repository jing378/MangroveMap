@extends('layouts.enduser')

@section('title', 'Drone Upload - MangroveMap')

@section('styles')
    <style>
        .drone-upload-page {
            max-width: 1180px;
            margin: 0 auto;
            color: var(--fg-1a2e1a, #1a2e1a);
        }

        .drone-upload-page h1 {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .drone-upload-sub {
            font-size: 14px;
            color: var(--fg-6b8a78, #6b8a78);
            margin-bottom: 18px;
        }

        .drone-flow {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            list-style: none;
            padding: 0;
            margin: 0 0 22px;
            counter-reset: flow;
        }

        .drone-flow li {
            counter-increment: flow;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--bg-edf7f2, #edf7f2);
            color: var(--fg-2f5a46, #2f5a46);
            font-size: 12.5px;
            font-weight: 700;
        }

        .drone-flow li::before {
            content: counter(flow);
            display: inline-grid;
            place-items: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #1e9e62;
            color: #fff;
            font-size: 11px;
        }

        .drone-upload-grid {
            display: grid;
            grid-template-columns: minmax(280px, 380px) 1fr;
            gap: 18px;
            align-items: start;
        }

        .drone-card {
            background: var(--bg-ffffff, #fff);
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .04);
        }

        .drone-card h2 {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: var(--fg-6b8a78, #6b8a78);
            margin-bottom: 14px;
        }

        .drone-form {
            display: grid;
            gap: 10px;
        }

        .drone-form label {
            font-size: 13px;
            font-weight: 700;
        }

        .drone-form input[type="text"] {
            padding: 9px 11px;
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            border-radius: 9px;
            font: inherit;
            font-size: 14px;
        }

        .drone-drop {
            position: relative;
            display: grid;
            place-items: center;
            gap: 6px;
            padding: 24px 14px;
            border: 2px dashed var(--bd-b9d3c2, #b9d3c2);
            border-radius: 12px;
            background: var(--bg-f7fbf8, #f7fbf8);
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }

        .drone-drop:hover,
        .drone-drop.is-over {
            border-color: #1e9e62;
            background: var(--bg-edf7f2, #edf7f2);
        }

        .drone-drop i {
            font-size: 30px;
            color: var(--fg-1e9e62, #1e9e62);
        }

        .drone-drop strong {
            font-size: 14px;
        }

        .drone-drop span {
            font-size: 12.5px;
            color: var(--fg-6b8a78, #6b8a78);
        }

        .drone-drop input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .drone-files {
            font-size: 12.5px;
            color: var(--fg-4c6b5d, #4c6b5d);
            overflow-wrap: anywhere;
        }

        .drone-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
            border-radius: 9px;
            background: var(--bg-ffffff, #fff);
            color: var(--fg-1a2e1a, #1a2e1a);
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .drone-btn:hover:not(:disabled) {
            border-color: #1e9e62;
            color: var(--fg-1e9e62, #1e9e62);
        }

        .drone-btn.primary {
            background: #1e9e62;
            border-color: #1e9e62;
            color: #fff;
        }

        .drone-btn.primary:hover:not(:disabled) {
            background: #16824f;
            color: #fff;
        }

        .drone-btn.danger {
            color: var(--fg-b42318, #b42318);
            border-color: var(--bd-e8b8b0, #e8b8b0);
        }

        .drone-btn.danger:hover:not(:disabled) {
            border-color: #b42318;
            color: var(--fg-b42318, #b42318);
            background: var(--bg-fdecea, #fdecea);
        }

        /* Same as the delineation's Save: dimmed until there is something to send. */
        .drone-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .drone-btn.is-saving {
            opacity: .85;
            cursor: wait;
        }

        .drone-spinner {
            display: inline-block;
            flex: none;
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: drone-spin .7s linear infinite;
        }

        @keyframes drone-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .drone-progress[hidden] {
            display: none;
        }

        .drone-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            color: var(--fg-4c6b5d, #4c6b5d);
            margin-bottom: 5px;
        }

        .drone-progress-track {
            height: 8px;
            border-radius: 999px;
            background: var(--bg-e5eee7, #e5eee7);
            overflow: hidden;
        }

        .drone-progress-fill {
            width: 0;
            height: 100%;
            border-radius: inherit;
            background: #1e9e62;
            transition: width .15s ease;
        }

        .drone-message {
            font-size: 13px;
            line-height: 1.45;
            min-height: 1em;
            color: var(--fg-4c6b5d, #4c6b5d);
        }

        .drone-message.is-error {
            color: var(--fg-b42318, #b42318);
        }

        .drone-list {
            display: grid;
            gap: 12px;
        }

        .drone-empty {
            padding: 30px 10px;
            text-align: center;
            color: var(--fg-6b8a78, #6b8a78);
            font-size: 14px;
        }

        .drone-empty i {
            display: block;
            font-size: 34px;
            margin-bottom: 8px;
        }

        .drone-item {
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 12px;
            padding: 14px 16px;
            display: grid;
            gap: 10px;
            transition: box-shadow .3s, border-color .3s;
        }

        .drone-item.is-highlighted {
            border-color: #1e9e62;
            box-shadow: 0 0 0 3px rgba(30, 158, 98, .18);
        }

        .drone-item-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }

        .drone-item-name {
            font-size: 15px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .drone-item-meta {
            font-size: 12.5px;
            color: var(--fg-6b8a78, #6b8a78);
            margin-top: 2px;
        }

        .drone-item-uploader {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--fg-1a2e1a, #1a2e1a);
            margin-top: 4px;
        }

        .drone-role {
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            background: var(--bg-eef3ee, #eef3ee);
            color: var(--fg-4c6b5d, #4c6b5d);
        }

        .drone-role.is-expert {
            background: var(--bg-e6f0ff, #e6f0ff);
            color: var(--fg-1d4fa3, #1d4fa3);
        }

        .drone-others {
            margin-top: 18px;
        }

        .drone-others-hint {
            font-size: 13px;
            color: var(--fg-6b8a78, #6b8a78);
            margin: -4px 0 12px;
        }

        .drone-badge {
            flex-shrink: 0;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            background: var(--bg-eef3ee, #eef3ee);
            color: var(--fg-4c6b5d, #4c6b5d);
        }

        .drone-badge.is-busy { background: var(--bg-fff4e0, #fff4e0); color: var(--fg-9a5b00, #9a5b00); }
        .drone-badge.is-review { background: var(--bg-e6f0ff, #e6f0ff); color: var(--fg-1d4fa3, #1d4fa3); }
        .drone-badge.is-ready { background: var(--bg-e3f6ec, #e3f6ec); color: var(--fg-16824f, #16824f); }
        .drone-badge.is-failed { background: var(--bg-fdecea, #fdecea); color: var(--fg-b42318, #b42318); }

        .drone-steps {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 4px;
        }

        .drone-step {
            display: grid;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #9ab0a0;
        }

        .drone-step::before {
            content: '';
            height: 5px;
            border-radius: 999px;
            background: var(--bg-e5eee7, #e5eee7);
        }

        .drone-step.is-done { color: var(--fg-16824f, #16824f); }
        .drone-step.is-done::before { background: #1e9e62; }
        .drone-step.is-active { color: var(--fg-1a2e1a, #1a2e1a); }
        .drone-step.is-active::before {
            background: linear-gradient(90deg, #1e9e62 0 40%, var(--bg-bfe3cf, #bfe3cf) 40% 100%);
            background-size: 200% 100%;
            animation: drone-step-pulse 1.2s linear infinite;
        }
        .drone-step.is-waiting::before { background: var(--bg-6aa7ff, #6aa7ff); }
        .drone-step.is-failed { color: var(--fg-b42318, #b42318); }
        .drone-step.is-failed::before { background: #d04030; }

        @keyframes drone-step-pulse {
            from { background-position: 100% 0; }
            to { background-position: -100% 0; }
        }

        .drone-item-state {
            font-size: 13px;
            line-height: 1.45;
            color: var(--fg-4c6b5d, #4c6b5d);
        }

        .drone-item-state.is-failed {
            color: var(--fg-b42318, #b42318);
        }

        .drone-item-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        @media (max-width: 900px) {
            .drone-upload-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .drone-upload-page h1 {
                font-size: 20px;
            }

            .drone-card {
                padding: 16px;
                border-radius: 14px;
            }

            .drone-item {
                padding: 12px;
            }

            /* 16px stops iOS Safari from zooming in when the field is focused. */
            .drone-form input[type="text"] {
                font-size: 16px;
                padding: 10px 12px;
            }

            .drone-btn {
                padding: 10px 14px;
            }
        }

        @media (max-width: 560px) {
            .drone-flow li {
                font-size: 12px;
                padding: 5px 10px;
            }

            .drone-item-head {
                flex-wrap: wrap;
            }

            .drone-item-actions .drone-btn {
                flex: 1 1 auto;
            }

            .drone-steps {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }

            .drone-step {
                font-size: 0;
            }
        }
    </style>
@endsection

@section('content')
    <main class="drone-upload-page">
        <h1>Drone Upload</h1>
        <p class="drone-upload-sub">Upload georeferenced drone orthomosaics (GeoTIFF). Each upload is checked by the AI and
            reviewed by you before it is placed on the map.</p>

        <ol class="drone-flow" aria-label="How drone uploads work">
            <li>Upload GeoTIFF</li>
            <li>Preview is prepared</li>
            <li>AI detects mangroves</li>
            <li>You remove non-mangrove areas</li>
            <li>Imagery is placed on the map</li>
        </ol>

        <div class="drone-upload-grid">
            <section class="drone-card" aria-labelledby="droneUploadTitle">
                <h2 id="droneUploadTitle">New upload</h2>
                <form id="droneUploadForm" class="drone-form">
                    <label for="droneUploadName">Survey name</label>
                    <input id="droneUploadName" name="name" type="text" maxlength="120" placeholder="e.g. Brgy. San Isidro, March 2026">

                    <label for="droneUploadFiles">GeoTIFF files</label>
                    <label class="drone-drop" id="droneUploadDrop">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <strong>Drop GeoTIFF files here or click to choose</strong>
                        <span>.tif / .tiff, 8-bit RGB orthomosaic · up to {{ $page['max_files'] }} files per upload</span>
                        <input id="droneUploadFiles" name="files[]" type="file" accept=".tif,.tiff,image/tiff" multiple required>
                    </label>
                    <div id="droneUploadSelected" class="drone-files" aria-live="polite"></div>

                    <button type="submit" class="drone-btn primary" id="droneUploadSubmit"><i class="bi bi-upload"></i> Upload and process</button>

                    <div id="droneUploadProgress" class="drone-progress" hidden>
                        <div class="drone-progress-label"><span>Uploading</span><span id="droneUploadPercent">0%</span></div>
                        <div class="drone-progress-track" role="progressbar" aria-label="Upload progress" aria-valuemin="0"
                            aria-valuemax="100" aria-valuenow="0" id="droneUploadBar">
                            <div class="drone-progress-fill"></div>
                        </div>
                    </div>
                    <div id="droneUploadMessage" class="drone-message" role="status" aria-live="polite"></div>
                </form>
            </section>

            <section class="drone-card" aria-labelledby="droneListTitle">
                <h2 id="droneListTitle">Your drone imagery</h2>
                <div id="droneList" class="drone-list" aria-live="polite">
                    <div class="drone-empty"><i class="bi bi-hourglass-split"></i>Loading…</div>
                </div>
            </section>
        </div>

        @if ($page['shows_others'])
            {{-- Experts: everyone else's uploads (residents and other experts), with the uploader's name. --}}
            <section class="drone-card drone-others" aria-labelledby="droneOthersTitle">
                <h2 id="droneOthersTitle">Uploads by residents and other experts</h2>
                <p class="drone-others-hint">Residents' imagery reaches the map once an expert approves its delineation.
                    Open a submitted delineation to review it next to the imagery.</p>
                <div id="droneOthersList" class="drone-list" aria-live="polite">
                    <div class="drone-empty"><i class="bi bi-hourglass-split"></i>Loading…</div>
                </div>
            </section>
        @endif
    </main>
@endsection

@section('scripts')
    @include('users.partials.drone-upload-scripts')
@endsection
