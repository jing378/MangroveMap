@php
    $currentUser = auth()->user();
    $isAdmin = $currentUser->isAdmin();
    $isExpert = $currentUser->isExpert();
    // Admins see every section so they can help residents and experts.
    $showResident = $currentUser->isResident() || $isAdmin;
    $showExpert = $isExpert || $isAdmin;
    $mapRoute = $isExpert ? route('expert.map') : route('map');
    $delineateRoute = $isExpert ? route('expert.delineate') : route('delineate');

    // id => [title, icon, one-line summary for the "What do you want to do?" cards, audience badge]
    $sections = array_filter([
        'getting-started' => ['Getting started', 'bi-rocket-takeoff', 'Roles, first steps and installing the app', null],
        'map' => ['Using the map', 'bi-map', 'Explore mangrove areas and their details', null],
        'delineate' => $showResident ? ['Submit a delineation', 'bi-pencil-square', 'Draw a mangrove area and send it for review', 'Residents'] : null,
        'drone' => ['Upload drone imagery', 'bi-cloud-arrow-up', 'Let the AI find mangroves in your drone photos', null],
        'tiles' => ['Tile Explorer', 'bi-grid-3x3-gap', 'Cut a GeoTIFF into tiles for datasets', null],
        'review' => $showExpert ? ['Review delineations', 'bi-patch-check', 'Approve, reject or remove submissions', 'Experts'] : null,
        'observations' => $showExpert ? ['Record changes over time', 'bi-calendar3', 'Log yearly growth or loss of an area', 'Experts'] : null,
        'recommendations' => $showExpert ? ['Planting recommendations', 'bi-tree', 'Mark where and what to plant', 'Experts'] : null,
        'admin' => $isAdmin ? ['Administration', 'bi-shield-lock', 'Users, map layers, AI models, feedback', 'Admins'] : null,
        'account' => ['Account and settings', 'bi-person-gear', 'Profile, notifications, dark mode, feedback', null],
        'tips' => ['Troubleshooting', 'bi-lightbulb', 'Answers to common problems', null],
    ]);
@endphp

@extends($isAdmin ? 'layouts.admin' : 'layouts.enduser')

@section('title', 'Help & Guide - MangroveMap')

@section('styles')
<style>
    .guide-page {
        max-width: 940px;
        margin: 0 auto;
        line-height: 1.6;
    }

    /* ---------- Hero ---------- */
    .guide-hero {
        background: linear-gradient(135deg, #0b3a2b 0%, #0f3a45 100%);
        color: #fff;
        border-radius: 18px;
        padding: 26px 28px;
        margin-bottom: 22px;
    }

    .guide-hero h1 {
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .guide-hero p {
        margin-top: 6px;
        font-size: 14.5px;
        color: rgba(226, 232, 240, 0.92);
    }

    .guide-role {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 12px;
        padding: 5px 11px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.2);
        font-size: 12px;
        font-weight: 700;
    }

    /* ---------- "What do you want to do?" cards ---------- */
    .guide-tasks-title {
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--fg-5b7864, #5b7864);
        margin: 0 0 10px 2px;
    }

    .guide-tasks {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 10px;
        margin-bottom: 26px;
    }

    .guide-task {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px;
        border-radius: 14px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        background: var(--bg-ffffff, #fff);
        text-decoration: none;
        transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }

    .guide-task:hover,
    .guide-task:focus-visible {
        border-color: #1e9e62;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(15, 58, 43, 0.08);
    }

    .guide-task-icon {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e7a4f, #1e7a4f);
        font-size: 18px;
    }

    .guide-task strong {
        display: block;
        font-size: 14px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        line-height: 1.3;
    }

    .guide-task small {
        display: block;
        margin-top: 3px;
        font-size: 12.5px;
        line-height: 1.4;
        color: var(--fg-5b7864, #5b7864);
    }

    .guide-task em {
        display: inline-block;
        margin-top: 6px;
        font-style: normal;
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--fg-1e7a4f, #1e7a4f);
    }

    /* ---------- Sections ---------- */
    .guide-section {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 16px;
        padding: 22px 24px;
        margin-bottom: 16px;
        scroll-margin-top: 12px;
    }

    .guide-section-head {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .guide-section-head .guide-task-icon {
        flex-basis: 40px;
        width: 40px;
        height: 40px;
        font-size: 19px;
    }

    .guide-section h2 {
        flex: 1;
        font-size: 19px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        letter-spacing: -0.01em;
    }

    .guide-section h3 {
        font-size: 14.5px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        margin: 20px 0 8px;
    }

    .guide-section p,
    .guide-section li {
        font-size: 14px;
        color: var(--fg-2f4a3a, #2f4a3a);
    }

    .guide-badge {
        padding: 3px 9px;
        border-radius: 999px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e7a4f, #1e7a4f);
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    /* One-sentence summary under each heading: readable at a glance. */
    .guide-summary {
        margin-top: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        background: var(--bg-f6f9f6, #f6f9f6);
        font-size: 14px !important;
    }

    .guide-summary strong {
        color: var(--fg-1e7a4f, #1e7a4f);
    }

    .guide-section ul {
        padding-left: 22px;
    }

    .guide-section li+li {
        margin-top: 5px;
    }

    /* Numbered steps with round markers. */
    .guide-steps {
        list-style: none;
        counter-reset: step;
        padding-left: 0 !important;
        margin: 0;
    }

    .guide-steps>li {
        counter-increment: step;
        position: relative;
        padding-left: 38px;
        min-height: 26px;
    }

    .guide-steps>li+li {
        margin-top: 10px;
    }

    .guide-steps>li::before {
        content: counter(step);
        position: absolute;
        left: 0;
        top: 0;
        width: 26px;
        height: 26px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #1e9e62;
        color: #fff;
        font-size: 12.5px;
        font-weight: 800;
    }

    .guide-steps ul {
        margin-top: 6px;
    }

    .guide-tip,
    .guide-warn {
        margin-top: 14px;
        padding: 11px 14px;
        border-radius: 0 10px 10px 0;
        font-size: 13.5px;
    }

    .guide-tip {
        border-left: 4px solid #1e9e62;
        background: var(--bg-f3faf6, #f3faf6);
        color: var(--fg-2f4a3a, #2f4a3a);
    }

    .guide-warn {
        border-left: 4px solid #d08a30;
        background: var(--bg-fdf7ee, #fdf7ee);
        color: var(--fg-5a4020, #5a4020);
    }

    .guide-tip i,
    .guide-warn i {
        margin-right: 4px;
    }

    .guide-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-top: 16px;
    }

    .guide-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 10px;
        background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
    }

    .guide-top {
        margin-left: auto;
        font-size: 12.5px;
        font-weight: 700;
        color: var(--fg-5b7864, #5b7864);
        text-decoration: none;
    }

    .guide-top:hover {
        color: var(--fg-1e9e62, #1e9e62);
    }

    /* Two-column "who / what" and status tables. */
    .guide-defs {
        display: grid;
        grid-template-columns: max-content 1fr;
        gap: 8px 14px;
        margin-top: 8px;
        font-size: 14px;
        align-items: baseline;
    }

    .guide-defs dt {
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .guide-defs dd {
        color: var(--fg-2f4a3a, #2f4a3a);
    }

    .guide-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 800;
    }

    .guide-pill::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }

    .guide-pill.is-pending {
        background: var(--bg-fdf3e1, #fdf3e1);
        color: var(--fg-9a5b0b, #9a5b0b);
    }

    .guide-pill.is-approved {
        background: var(--bg-e3f5ea, #e3f5ea);
        color: var(--fg-17703f, #17703f);
    }

    .guide-pill.is-rejected {
        background: var(--bg-fde8e8, #fde8e8);
        color: var(--fg-b42323, #b42323);
    }

    /* Pipeline: upload -> preview -> AI -> you check -> map */
    .guide-flow {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        margin-top: 12px;
        font-size: 12.5px;
        font-weight: 700;
        color: var(--fg-365a45, #365a45);
    }

    .guide-flow span {
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid var(--bd-d4dfd4, #d4dfd4);
        background: var(--bg-f8faf7, #f8faf7);
    }

    .guide-flow i {
        color: var(--fg-9aaa9f, #9aaa9f);
    }

    .guide-faq details {
        border-top: 1px solid var(--bd-eef2ee, #eef2ee);
        padding: 11px 0;
    }

    .guide-faq details:first-of-type {
        border-top: none;
        margin-top: 8px;
    }

    .guide-faq summary {
        cursor: pointer;
        font-weight: 700;
        font-size: 14px;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .guide-faq details p {
        margin-top: 6px;
    }

    kbd {
        padding: 1px 6px;
        border: 1px solid var(--bd-c8d4cb, #c8d4cb);
        border-bottom-width: 2px;
        border-radius: 5px;
        background: var(--bg-f8faf7, #f8faf7);
        font-family: inherit;
        font-size: 12px;
    }

    .guide-footer {
        text-align: center;
        font-size: 13px;
        color: var(--fg-7a8a7f, #7a8a7f);
        margin: 8px 0 12px;
    }

    /* ---------- Card animations ---------- */
    /* Cards rise in one after another when the page opens. */
    .guide-task {
        position: relative;
        overflow: hidden;
        animation: guideCardIn .5s cubic-bezier(.16, 1, .3, 1) backwards;
        animation-delay: calc(var(--i, 0) * 45ms);
    }

    @keyframes guideCardIn {
        from { opacity: 0; transform: translateY(12px) scale(.97); }
    }

    .guide-task-icon {
        transition: transform .3s cubic-bezier(.34, 1.56, .64, 1), background .2s ease, color .2s ease;
    }

    .guide-task:hover .guide-task-icon {
        transform: scale(1.1) rotate(-6deg);
    }

    /* Press: the card sinks, the icon fills green. */
    .guide-task:active,
    .guide-task.is-pressed {
        transform: scale(.96) !important;
        transition-duration: .08s;
    }

    .guide-task.is-pressed .guide-task-icon {
        background: #1e9e62;
        color: #fff;
        transform: scale(1.15) rotate(-8deg);
    }

    /* Ripple from where the card was clicked (--rx/--ry set in JS). */
    .guide-ripple {
        position: absolute;
        left: var(--rx);
        top: var(--ry);
        width: 12px;
        height: 12px;
        margin: -6px 0 0 -6px;
        border-radius: 50%;
        background: rgba(30, 158, 98, .28);
        pointer-events: none;
        animation: guideRipple .6s ease-out forwards;
    }

    @keyframes guideRipple {
        to { transform: scale(40); opacity: 0; }
    }

    /* The section you jumped to glows and its icon bounces. */
    .guide-section.is-arriving {
        animation: guideArrive 1.6s ease-out;
    }

    .guide-section.is-arriving .guide-section-head .guide-task-icon {
        animation: guideIconBounce .7s cubic-bezier(.34, 1.56, .64, 1) .25s;
    }

    @keyframes guideArrive {
        0% { box-shadow: 0 0 0 0 rgba(30, 158, 98, 0); border-color: var(--bd-e0e8e0, #e0e8e0); }
        20% { box-shadow: 0 0 0 6px rgba(30, 158, 98, .22); border-color: #1e9e62; }
        100% { box-shadow: 0 0 0 14px rgba(30, 158, 98, 0); border-color: var(--bd-e0e8e0, #e0e8e0); }
    }

    @keyframes guideIconBounce {
        0% { transform: scale(1); }
        40% { transform: scale(1.3) rotate(-10deg); background: #1e9e62; color: #fff; }
        100% { transform: scale(1); }
    }

    @media (prefers-reduced-motion: reduce) {
        .guide-task,
        .guide-task-icon,
        .guide-section.is-arriving,
        .guide-section.is-arriving .guide-section-head .guide-task-icon {
            animation: none !important;
            transition: none !important;
        }

        .guide-ripple {
            display: none;
        }
    }

    @media (max-width: 640px) {
        .guide-hero {
            padding: 18px;
            border-radius: 14px;
            margin-bottom: 18px;
        }

        .guide-hero h1 {
            font-size: 22px;
        }

        .guide-hero p {
            font-size: 13.5px;
        }

        /* Phones: compact two-column menu (icon + title only) so it fits on the first screen. */
        .guide-tasks {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 20px;
        }

        .guide-task {
            align-items: center;
            gap: 9px;
            padding: 10px;
            border-radius: 12px;
            min-width: 0;
        }

        .guide-task:hover,
        .guide-task:focus-visible {
            transform: none;
        }

        .guide-task .guide-task-icon {
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            border-radius: 9px;
            font-size: 15px;
        }

        .guide-task strong {
            font-size: 12.5px;
            hyphens: auto;
        }

        .guide-task small,
        .guide-task em {
            display: none;
        }

        .guide-section {
            padding: 16px 14px;
            border-radius: 14px;
            margin-bottom: 12px;
        }

        .guide-section-head {
            gap: 10px;
        }

        .guide-section-head .guide-task-icon {
            flex-basis: 34px;
            width: 34px;
            height: 34px;
            font-size: 16px;
        }

        .guide-section h2 {
            font-size: 17px;
        }

        .guide-section h3 {
            margin-top: 16px;
        }

        .guide-summary {
            padding: 9px 12px;
            font-size: 13.5px !important;
        }

        .guide-steps>li {
            padding-left: 34px;
        }

        /* The drone flow: two numbered pills per row, no arrows (they'd dangle at line ends). */
        .guide-flow {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .guide-flow i {
            display: none;
        }

        .guide-flow span {
            text-align: center;
        }

        .guide-flow span:last-of-type {
            grid-column: 1 / -1;
        }

        .guide-tip,
        .guide-warn {
            padding: 10px 12px;
        }

        .guide-actions {
            gap: 6px;
        }

        .guide-link {
            flex: 1 1 auto;
            justify-content: center;
        }

        .guide-top {
            flex-basis: 100%;
            text-align: right;
        }

        .guide-defs {
            grid-template-columns: 1fr;
            gap: 2px;
        }

        .guide-defs dd {
            margin-bottom: 8px;
        }
    }
</style>
@endsection

@section('content')
@php
    // Section header: icon, title and audience badge from $sections.
    $head = function (string $id) use ($sections) {
        [$title, $icon, , $badge] = $sections[$id];

        return '<div class="guide-section-head"><span class="guide-task-icon" aria-hidden="true"><i class="bi '.$icon.'"></i></span>'
            .'<h2>'.e($title).'</h2>'
            .($badge ? '<span class="guide-badge">'.e($badge).'</span>' : '')
            .'</div>';
    };
@endphp
<div class="guide-page" id="guide-top">
    <header class="guide-hero">
        <h1>Help &amp; User Guide</h1>
        <p>Short, step-by-step instructions for mapping mangroves, uploading drone imagery and working with expert reviews.</p>
        <span class="guide-role"><i class="bi bi-person-badge"></i>
            Showing the guide for: {{ $currentUser->roleLabel() }}
        </span>
    </header>

    <h2 class="guide-tasks-title">What do you want to do?</h2>
    <nav class="guide-tasks" aria-label="Guide sections">
        @foreach ($sections as $id => [$title, $icon, $blurb, $badge])
            <a class="guide-task" href="#{{ $id }}" style="--i: {{ $loop->index }}">
                <span class="guide-task-icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                <span>
                    <strong>{{ $title }}</strong>
                    <small>{{ $blurb }}</small>
                    @if ($badge)<em>{{ $badge }}</em>@endif
                </span>
            </a>
        @endforeach
    </nav>

    {{-- ===== Everyone ===== --}}
    <section class="guide-section" id="getting-started">
        {!! $head('getting-started') !!}
        <p class="guide-summary"><strong>In short:</strong> MangroveMap maps the mangrove areas of Southern Leyte. Residents submit areas, experts check them, and approved areas appear on the public map.</p>

        <h3>Who does what</h3>
        <dl class="guide-defs">
            <dt>Residents</dt>
            <dd>Draw mangrove areas, upload drone imagery, and send both to experts for review.</dd>
            <dt>Experts</dt>
            <dd>Approve or reject submissions, record yearly changes, and write planting recommendations.</dd>
            <dt>Administrators</dt>
            <dd>Manage users, map layers, AI models, feedback, and activity logs.</dd>
        </dl>

        <h3>Your first steps</h3>
        <ol class="guide-steps">
            <li><strong>Verify your email</strong> with the link sent when you registered. Mapping features stay locked until you do.</li>
            <li>Open <strong>My Profile</strong> and check your name, contact number, and photo.</li>
            <li>Open the <strong>Map</strong> and look at existing mangrove areas before you draw your own.</li>
        </ol>
        <div class="guide-tip"><i class="bi bi-mortarboard"></i><strong>Want to become an expert?</strong> Tick "Apply for an expert account" when you
            register and describe your qualifications. An administrator reviews it, and you get a notification with the decision.</div>
        <div class="guide-tip"><i class="bi bi-phone"></i><strong>Use it like an app:</strong> click <strong>Install App</strong> to put
            MangroveMap on your phone's home screen or your computer's desktop.</div>
        <div class="guide-actions"><a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a></div>
    </section>

    <section class="guide-section" id="map">
        {!! $head('map') !!}
        <p class="guide-summary"><strong>In short:</strong> click any shape on the map to see its details. Use the satellite view to see the mangroves themselves.</p>

        <h3>Moving around</h3>
        <ul>
            <li><strong>Pan and zoom:</strong> drag to move, scroll or pinch to zoom.</li>
            <li><strong>Base map:</strong> use the layer control to switch between <em>Satellite</em>, <em>Street</em>, and <em>Topo</em>.
                Satellite is best for seeing the mangrove canopy.</li>
            <li><strong>Search:</strong> type a name, genus, or status in the search box at the top of the map to jump to an area.</li>
        </ul>

        <h3>What you can see</h3>
        <ul>
            <li><strong>Area details:</strong> click a shape to open the side panel. It shows the size in hectares, the barangay and municipality,
                the genera present, who drew it, its review status, and any planting recommendation.</li>
            <li><strong>Drone imagery:</strong> approved drone surveys appear as photo layers. Use the Drone Imagery panel to show or hide them,
                and to see the tree canopies the AI found, coloured by genus.</li>
            <li><strong>Planting recommendations:</strong> the <i class="bi bi-flower1"></i> button shows or hides the recommendation layer.</li>
            <li><strong>Changes over time:</strong> when experts have recorded yearly observations, the <em>Changes over time</em> timeline shows
                how the area grew or shrank.</li>
            <li><strong>On a phone:</strong> the side panel opens as a sheet at the bottom. Drag it up for more, or tap outside it to close it.</li>
        </ul>
        <div class="guide-actions">
            <a class="guide-link" href="{{ $mapRoute }}"><i class="bi bi-map"></i> Open the map</a>
            <a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a>
        </div>
    </section>

    {{-- ===== Residents ===== --}}
    @if ($showResident)
    <section class="guide-section" id="delineate">
        {!! $head('delineate') !!}
        <p class="guide-summary"><strong>In short:</strong> draw a shape around a mangrove area, choose the genera you saw, and save. An expert then approves or rejects it.</p>

        <h3>Draw and save an area</h3>
        <ol class="guide-steps">
            <li>Open <strong>Delineate</strong> from the sidebar. Switch to the Satellite base map and zoom in on the mangroves.</li>
            <li>Click <i class="bi bi-pentagon"></i> <strong>Draw area</strong>.</li>
            <li>Click along the edge of the mangroves to add points. Click the first point or double-click to finish.
                Press <kbd>Esc</kbd> to cancel. <i class="bi bi-arrow-counterclockwise"></i> Undo and <i class="bi bi-arrow-clockwise"></i> Redo fix mistakes.</li>
            <li>In the side panel, choose the <strong>Mangrove Genus Present</strong>, for example Rhizophora (Bakawan) or Avicennia (Bungalon).
                For another genus, type it and press <kbd>Enter</kbd>.</li>
            <li>Add <strong>Notes</strong>: what you saw, when you visited, and the condition of the trees.</li>
            <li>Click <strong>Save delineation</strong>. The location is filled in automatically and experts are notified.</li>
        </ol>

        <h3>What the review status means</h3>
        <dl class="guide-defs">
            <dt><span class="guide-pill is-pending">Pending</span></dt>
            <dd>Waiting for an expert. You can still edit the boundary.</dd>
            <dt><span class="guide-pill is-approved">Approved</span></dt>
            <dd>Checked by an expert. It now appears on the public map with your name as the contributor.</dd>
            <dt><span class="guide-pill is-rejected">Rejected</span></dt>
            <dd>The expert explained what to fix. Open it, read the <em>Expert feedback</em>, click <strong>Revise rejected delineation</strong>,
                fix it, then click <strong>Resubmit for review</strong>.</dd>
        </dl>
        <div class="guide-warn"><i class="bi bi-hourglass-split"></i><strong>You have 7 days to revise a rejected delineation.</strong> After that,
            an expert may remove it. The deadline is shown in the rejection notice, in the map panel, and on your dashboard.</div>

        <h3>Edit a boundary</h3>
        <ol class="guide-steps">
            <li>Select the area and click <i class="bi bi-bounding-box-circles"></i> <strong>Edit Delineation</strong>. The panel switches to
                just the edit controls.</li>
            <li>Drag a point to move it. Drag or click a faded midpoint to add a point. To delete a point, double-click or right-click it,
                or select it and press <kbd>Delete</kbd>.</li>
            <li>Click <strong>Save Changes</strong>, or <strong>Cancel</strong> to undo everything.</li>
        </ol>
        <div class="guide-tip"><i class="bi bi-check2-circle"></i><strong>How to get approved:</strong> trace only the mangrove canopy, not open water,
            fishponds, or roads. Zoom in close for the edges, draw one shape per continuous patch, and always add the genera you saw.</div>
        <div class="guide-actions">
            <a class="guide-link" href="{{ $delineateRoute }}"><i class="bi bi-pencil-square"></i> Start delineating</a>
            <a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a>
        </div>
    </section>
    @endif

    <section class="guide-section" id="drone">
        {!! $head('drone') !!}
        <p class="guide-summary"><strong>In short:</strong> upload your drone orthomosaic, check the mangrove areas the AI finds, choose the genus,
            and confirm. {{ $isExpert ? 'As an expert, your imagery goes on the map right away.' : 'An expert then reviews it before it goes on the map.' }}</p>
        <div class="guide-flow" aria-label="How drone imagery is processed">
            <span>1. Upload GeoTIFF</span><i class="bi bi-chevron-right"></i>
            <span>2. Preview is prepared</span><i class="bi bi-chevron-right"></i>
            <span>3. AI detects mangroves</span><i class="bi bi-chevron-right"></i>
            <span>4. You check the results</span><i class="bi bi-chevron-right"></i>
            <span>5. Imagery is placed on the map</span>
        </div>

        <h3>Upload</h3>
        <ol class="guide-steps">
            <li>Open <strong>Drone Upload</strong> from the sidebar.</li>
            <li>Enter a <strong>Survey name</strong>, for example "Brgy. San Isidro, March 2026".</li>
            <li>Drop your files in the box or click to choose them. Use georeferenced <strong>.tif / .tiff</strong> files (8-bit RGB orthomosaics).
                You can add several files from the same survey at once.</li>
            <li>Click <strong>Upload and process</strong>. You can leave the page while it processes, which can take a while for large surveys.</li>
        </ol>
        <div class="guide-tip"><i class="bi bi-arrow-repeat"></i><strong>Upload interrupted?</strong> Choose the same files again on the same device.
            The upload continues from where it stopped instead of starting over.</div>

        <h3>Check the AI results</h3>
        <ol class="guide-steps">
            <li>When the imagery is ready, click <strong>Edit imagery</strong> in <em>Your drone imagery</em>.</li>
            <li><strong>Mangrove classification</strong> shows each tree canopy the AI found as a pin, coloured by genus. Click a pin to see it or remove it.</li>
            <li><strong>AI delineation</strong> is the green outline that becomes your delineation. Click <strong>Show</strong> to edit it:
                <ul>
                    <li><i class="bi bi-pentagon"></i> <strong>Draw area</strong> adds mangroves the AI missed.</li>
                    <li>Drag corners to adjust a shape. Double-click a corner to delete it.</li>
                    <li>Click an area to remove it if it isn't mangrove, for example coconut trees or shadows.</li>
                </ul>
            </li>
        </ol>

        <h3>Add the details and confirm</h3>
        <ol class="guide-steps">
            <li>The <strong>Location</strong> is set automatically. Change the <strong>Name</strong> if you like.</li>
            <li>Choose the <strong>Mangrove Genus Present</strong> (required). The <em>AI genus suggestion</em> lists how likely each genus is and marks
                each one <em>Confident</em> or <em>Low confidence</em>. It updates as you edit the areas, but it never picks a genus for you.</li>
            <li>Add <strong>Notes</strong> for the reviewer if needed.</li>
            <li>Click <strong>Save draft</strong> to finish later, or
                <strong>{{ $isExpert ? 'Confirm & publish' : 'Confirm & submit for review' }}</strong> when you're done.</li>
        </ol>

        <h3>Who can see your imagery</h3>
        <dl class="guide-defs">
            <dt>Before approval</dt>
            <dd>Only you, experts, and administrators.</dd>
            <dt>After approval</dt>
            <dd>Everyone. The imagery and its delineation appear on the map, including the public home page map.
                Imagery uploaded by experts is published as soon as it's processed.</dd>
        </dl>
        <div class="guide-warn"><i class="bi bi-exclamation-triangle"></i><strong>The AI can be wrong.</strong> Always check its shapes and genus
            suggestion before you confirm. Upload only imagery you took or have permission to use, and follow CAAP drone rules.</div>
        <div class="guide-actions">
            <a class="guide-link" href="{{ route('drone-imagery.page') }}"><i class="bi bi-cloud-arrow-up"></i> Go to Drone Upload</a>
            <a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a>
        </div>
    </section>

    <section class="guide-section" id="tiles">
        {!! $head('tiles') !!}
        <p class="guide-summary"><strong>In short:</strong> cut a GeoTIFF into equal squares (tiles), pick the ones with mangroves, and download them
            as a ZIP, for example to build a Roboflow training dataset. Nothing here goes on the map or to reviewers, and only you can see it.</p>

        <ol class="guide-steps">
            <li>Open <strong>Tile Explorer</strong> from the sidebar.</li>
            <li>Drop a GeoTIFF in the box (.tif / .tiff, 8-bit RGB with coordinates, up to 1 GB).</li>
            <li>Choose a <strong>Tile size</strong> from 256 to 1280 pixels (640 by default), then click <strong>Upload and make tiles</strong>.</li>
            <li>Wait for the tiles. The AI marks the mangrove areas and shows the mangrove percentage on every tile. Empty tiles are skipped.</li>
            <li>Find tiles with the filters (<em>All</em>, <em>Mangrove</em>, <em>No mangrove</em>), the sort order, or the search box (for example
                <kbd>r004</kbd> or <kbd>c012</kbd>). <strong>Show mangrove areas</strong> turns the outlines on or off.</li>
            <li>Click a tile to preview it. Use <kbd>&larr;</kbd> <kbd>&rarr;</kbd> to move between tiles, <kbd>Space</kbd> to select, and
                <kbd>Esc</kbd> to close.</li>
            <li>Tick the tiles you want, or click <strong>Select mangrove tiles</strong>. Choose a format (<em>GeoTIFF + JPEG</em>,
                <em>GeoTIFF only</em>, or <em>JPEG only (Roboflow)</em>), then click <strong>Download selected</strong>.</li>
        </ol>
        <div class="guide-tip"><i class="bi bi-grid"></i><strong>Need a different size?</strong> Open the GeoTIFF from <em>Your GeoTIFFs</em> and pick
            another tile size next to its name. You don't have to upload it again.</div>
        <div class="guide-actions">
            <a class="guide-link" href="{{ route('tile-explorer.page') }}"><i class="bi bi-grid-3x3-gap"></i> Open Tile Explorer</a>
            <a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a>
        </div>
    </section>

    {{-- ===== Experts ===== --}}
    @if ($showExpert)
    <section class="guide-section" id="review">
        {!! $head('review') !!}
        <p class="guide-summary"><strong>In short:</strong> open a pending delineation, compare it with the satellite map, then approve it, or reject it with clear feedback.</p>

        <ol class="guide-steps">
            <li>You get a notification <i class="bi bi-bell"></i> when a resident submits a delineation. Open it, or find pending areas on the
                <strong>Map</strong> or <strong>Delineate</strong> page.</li>
            <li>Click the area to see its genera, notes, location, and who submitted it.</li>
            <li>Compare the boundary with the satellite base map and, if you can, with what you know from the field.</li>
            <li>Click <strong>Approve</strong> if it's accurate. Otherwise click <strong>Reject</strong> and explain what the resident should fix
                (at least 10 characters).</li>
        </ol>
        <div class="guide-tip"><i class="bi bi-chat-left-text"></i><strong>Write useful feedback:</strong> say exactly what's wrong, for example
            "The north edge includes the fishpond. Please trace along the tree line."</div>

        <h3>Removing a delineation</h3>
        <ul>
            <li><strong>Approved or pending:</strong> click <i class="bi bi-trash"></i> <strong>Remove delineation</strong> at any time.
                The owner is notified.</li>
            <li><strong>Rejected:</strong> the resident has 7 days to revise it. The panel shows the date, and you can remove it after that.</li>
        </ul>
        <p style="margin-top:10px;">You can also draw your own delineations on <strong>Delineate</strong> and correct the boundaries of resident
            records. Your own delineations are approved immediately, and every boundary change is saved in the revision history.</p>
        <div class="guide-actions"><a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a></div>
    </section>

    <section class="guide-section" id="observations">
        {!! $head('observations') !!}
        <p class="guide-summary"><strong>In short:</strong> when mangroves really grow or shrink, edit the boundary and save it as an
            <em>Observed change</em>. It becomes that year's entry in the <em>Changes over time</em> timeline.</p>

        <ol class="guide-steps">
            <li>On the <strong>Map</strong> or <strong>Delineate</strong> page, select the area and click
                <i class="bi bi-bounding-box-circles"></i> <strong>Edit Delineation</strong>.</li>
            <li>Move, add, or delete points so the boundary matches the mangroves as they are now. While editing, the panel shows only the
                edit controls; if the genera changed, pick them in the panel before clicking Edit Delineation.</li>
            <li>Under <strong>Record this change as</strong>, choose one:
                <ul>
                    <li><strong>Correction</strong>: you're only fixing how the area was drawn. It's saved, but it doesn't appear in the timeline.</li>
                    <li><strong>Observed change</strong>: the mangroves really changed. Set the <strong>Date observed</strong> (the survey or image
                        date, not today) and how you saw it: field survey, drone imagery, or satellite imagery.</li>
                </ul>
            </li>
            <li>Click <strong>Save Changes</strong>. The hectares are calculated from the new boundary.</li>
        </ol>
        <div class="guide-tip"><i class="bi bi-info-circle"></i>The first observed change on an area also saves the original boundary as its starting
            year, so the timeline shows a before and after. A second change in the same year replaces the first.</div>
        <div class="guide-warn"><i class="bi bi-exclamation-triangle"></i>Choose <strong>Correction</strong> when tidying a resident's tracing.
            An observed change would show mangrove gain or loss that never happened.</div>
        <div class="guide-actions"><a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a></div>
    </section>

    <section class="guide-section" id="recommendations">
        {!! $head('recommendations') !!}
        <p class="guide-summary"><strong>In short:</strong> mark a site, rate how suitable it is for planting, and say which genus to plant.</p>

        <ol class="guide-steps">
            <li>Open <strong>Planting Recommendation</strong> from the sidebar.</li>
            <li>Click <i class="bi bi-pentagon"></i> <strong>Draw area</strong> to outline a new site, or <i class="bi bi-hand-index"></i>
                <strong>Select area</strong> to pick an approved delineation.</li>
            <li>Give it a <strong>Name</strong> and choose the <strong>Planting suitability</strong>: Highly, Moderately, or Marginally suitable,
                or Not suitable.</li>
            <li>Select the <strong>Recommended mangrove genus</strong>. It's required unless the site is Not suitable.</li>
            <li>Optionally, add <strong>Recommendations</strong> (planting method, spacing, season, propagule source, protection) and
                <strong>Site assessment notes</strong> (substrate, tidal flooding, salinity, threats).</li>
            <li>Click <strong>Save recommendation</strong>. Only the expert who wrote it can edit or delete it.</li>
        </ol>
        <div class="guide-tip"><i class="bi bi-bounding-box-circles"></i><strong>Change a saved site's outline:</strong> open it and click
            <strong>Edit area</strong>. The panel shows only the edit controls until you Save or Cancel.</div>
        <div class="guide-warn"><i class="bi bi-exclamation-triangle"></i>Recommendations are advice, not permits. Remind communities to coordinate
            with DENR (CENRO/PENRO) and the LGU before planting.</div>
        <div class="guide-actions">
            <a class="guide-link" href="{{ route('expert.recommendations.index') }}"><i class="bi bi-tree"></i> Open Planting Recommendation</a>
            <a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a>
        </div>
    </section>
    @endif

    {{-- ===== Admins ===== --}}
    @if ($isAdmin)
    <section class="guide-section" id="admin">
        {!! $head('admin') !!}
        <p class="guide-summary"><strong>In short:</strong> approve expert applications, look after map layers, keep the AI models up to date, and act on feedback.</p>

        <dl class="guide-defs">
            <dt><i class="bi bi-people"></i> Users</dt>
            <dd>Pending expert applications appear at the top. Read the organization and qualifications, then approve or reject them. A rejection
                reason is optional and is shared with the applicant. Use the view, edit, and delete actions to change a user's details or role,
                or reset their password.</dd>
            <dt><i class="bi bi-layers"></i> Map Layers</dt>
            <dd><strong>Delineations:</strong> preview, rename, edit notes, <em>Hide from map</em> (with an optional reason sent to the owner), restore,
                or delete. <strong>Drone imagery:</strong> preview, rename, <em>Retry processing</em> after a failure, run the AI again, or delete.</dd>
            <dt><i class="bi bi-cpu"></i> AI Models</dt>
            <dd>Click <strong>Upload Model</strong> and choose the type: <em>Mangrove Segmentation</em> (a U-Net <code>.pth</code> that finds
                mangrove areas) or <em>Mangrove Genus Classification</em> (a YOLO <code>.pt</code> that suggests the genus and finds canopies).
                Each model is checked before use, and the newest active model of each type runs on new imagery.</dd>
            <dt><i class="bi bi-chat-dots"></i> Feedback</dt>
            <dd>Read problem reports and ideas from residents and experts, including any screenshot and the page they came from.</dd>
            <dt><i class="bi bi-clock-history"></i> Activity Logs</dt>
            <dd>Filter by date to see who did what and when, to investigate problems or misuse.</dd>
        </dl>
        <div class="guide-tip"><i class="bi bi-eye-slash"></i>When a record is doubtful, use <strong>Hide from map</strong> rather than delete it.
            Hiding can be undone, but deleting can't.</div>
        <div class="guide-actions">
            <a class="guide-link" href="{{ route('admin.users') }}"><i class="bi bi-people"></i> Users</a>
            <a class="guide-link" href="{{ route('admin.map-layers.index') }}"><i class="bi bi-layers"></i> Map Layers</a>
            <a class="guide-link" href="{{ route('admin.models') }}"><i class="bi bi-cpu"></i> AI Models</a>
            <a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a>
        </div>
    </section>
    @endif

    {{-- ===== Everyone ===== --}}
    <section class="guide-section" id="account">
        {!! $head('account') !!}
        <dl class="guide-defs">
            <dt><i class="bi bi-person"></i> My Profile</dt>
            <dd>Update your name, contact number, and photo, or change your password.</dd>
            <dt><i class="bi bi-bell"></i> Notifications</dt>
            <dd>Tell you when a delineation is approved, rejected, or removed, when an expert application is decided, and when there is work
                to review. A red badge shows how many are unread.</dd>
            <dt><i class="bi bi-moon-stars"></i> Dark mode</dt>
            <dd>Click the theme button in the header to switch between light and dark. MangroveMap remembers your choice.</dd>
            @unless ($isAdmin)
                <dt><i class="bi bi-megaphone"></i> Send Feedback</dt>
                <dd>Found a problem or have an idea? Click <strong>Send Feedback</strong> in the sidebar and add a screenshot if it helps.
                    The page you're on is attached automatically.</dd>
            @endunless
            <dt><i class="bi bi-key"></i> Forgot password</dt>
            <dd>Use <em>Forgot password</em> on the login page to get a reset link by email.</dd>
        </dl>
        <div class="guide-warn"><i class="bi bi-box-arrow-right"></i>Always log out on shared or public computers.</div>
        <div class="guide-actions"><a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a></div>
    </section>

    <section class="guide-section guide-faq" id="tips">
        {!! $head('tips') !!}
        <details>
            <summary>The map is blank or slow to load.</summary>
            <p>Check your internet connection and refresh the page. Base maps load from online services, so a weak signal in coastal areas
                slows them down. The Street base map is lighter.</p>
        </details>
        <details>
            <summary>I can't finish drawing my area.</summary>
            <p>Click the first point again, or double-click, to close the shape. An area needs at least three points. Press <kbd>Esc</kbd> to start over.</p>
        </details>
        <details>
            <summary>My drone upload stopped halfway.</summary>
            <p>Choose the same files again on the same device. The upload continues from where it stopped.</p>
        </details>
        <details>
            <summary>My drone imagery failed or is stuck processing.</summary>
            <p>Make sure the file is a georeferenced GeoTIFF (.tif/.tiff) exported as an RGB orthomosaic, not a raw photo. Large surveys can take
                a long time. If it shows <em>Processing failed</em>, click <em>Edit &amp; retry</em> or ask an administrator.</p>
        </details>
        <details>
            <summary>The AI marked areas that aren't mangroves, or suggested the wrong genus.</summary>
            <p>That's normal: the AI is only a starting point. Remove the wrong areas or canopies in the review editor, draw any it missed,
                and choose the genus you actually saw.</p>
        </details>
        <details>
            <summary>The location shown for my area looks wrong.</summary>
            <p>The barangay and municipality are worked out from where the shape sits and can be off near borders. Mention the correct location in your notes.</p>
        </details>
        <details>
            <summary>I didn't get the verification or password email.</summary>
            <p>Check your spam or junk folder. On the verification page, click resend. You can ask for a new link a few times per minute.</p>
        </details>
        <details>
            <summary>Who can see what I submit?</summary>
            <p>Pending and rejected delineations and drone imagery are visible only to you, experts, and administrators. Once approved, they
                appear on the public map with your name. Tile Explorer uploads are only ever visible to you. See the
                <a href="{{ route('terms') }}">Terms and Conditions</a> for details.</p>
        </details>
        <div class="guide-actions"><a class="guide-top" href="#guide-top"><i class="bi bi-arrow-up"></i> Back to top</a></div>
    </section>

    <p class="guide-footer">
        @unless ($isAdmin)Still stuck? Use <strong>Send Feedback</strong> in the sidebar to reach the administrators. &middot;@endunless
        <a href="{{ route('terms') }}">Terms and Conditions</a></p>
</div>
@endsection

@section('scripts')
<script>
    // Guide cards: ripple + press on click, smooth scroll to the section, then the section glows.
    (() => {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function arrive(section) {
            section.classList.remove('is-arriving');
            void section.offsetWidth; // restart the animation when the same card is clicked again
            section.classList.add('is-arriving');
            clearTimeout(section._arriveTimer);
            section._arriveTimer = setTimeout(() => section.classList.remove('is-arriving'), 1700);
        }

        function goTo(hash) {
            const target = document.getElementById(hash.slice(1));
            if (!target) return false;
            history.replaceState(null, '', hash);
            target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            if (target.classList.contains('guide-section')) {
                // Glow once the scroll has (roughly) settled, so the user sees it.
                setTimeout(() => arrive(target), reduceMotion ? 0 : 450);
            }
            return true;
        }

        document.querySelectorAll('.guide-task').forEach(card => {
            card.addEventListener('click', event => {
                if (event.ctrlKey || event.metaKey || event.shiftKey) return;
                event.preventDefault();

                if (!reduceMotion) {
                    const rect = card.getBoundingClientRect();
                    const ripple = document.createElement('span');
                    ripple.className = 'guide-ripple';
                    // Keyboard clicks have no pointer position: ripple from the centre.
                    const x = event.clientX ? event.clientX - rect.left : rect.width / 2;
                    const y = event.clientY ? event.clientY - rect.top : rect.height / 2;
                    ripple.style.setProperty('--rx', x + 'px');
                    ripple.style.setProperty('--ry', y + 'px');
                    card.appendChild(ripple);
                    ripple.addEventListener('animationend', () => ripple.remove());

                    card.classList.add('is-pressed');
                    setTimeout(() => card.classList.remove('is-pressed'), 220);
                }

                // Let the press show briefly before the page moves.
                setTimeout(() => goTo(card.getAttribute('href')), reduceMotion ? 0 : 160);
            });
        });

        document.querySelectorAll('.guide-top').forEach(link => {
            link.addEventListener('click', event => {
                if (goTo(link.getAttribute('href'))) event.preventDefault();
            });
        });

        // Opening the guide with a #section link also highlights that section.
        if (location.hash) {
            const section = document.getElementById(location.hash.slice(1));
            if (section && section.classList.contains('guide-section')) setTimeout(() => arrive(section), 300);
        }
    })();
</script>
@endsection
