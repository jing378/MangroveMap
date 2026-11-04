@php
    $currentUser = auth()->user();
    $isAdmin = $currentUser->isAdmin();
    $isExpert = $currentUser->isExpert();
    // Admins see every section so they can help residents and experts.
    $showResident = $currentUser->isResident() || $isAdmin;
    $showExpert = $isExpert || $isAdmin;
    $mapRoute = $isExpert ? route('expert.map') : route('map');
    $delineateRoute = $isExpert ? route('expert.delineate') : route('delineate');

    $sections = array_filter([
        'getting-started' => 'Getting started',
        'map' => 'Using the map',
        'delineate' => $showResident ? 'Submitting a delineation' : null,
        'drone' => 'Drone imagery upload',
        'review' => $showExpert ? 'Reviewing delineations' : null,
        'observations' => $showExpert ? 'Changes over time' : null,
        'recommendations' => $showExpert ? 'Planting recommendations' : null,
        'admin' => $isAdmin ? 'Administration' : null,
        'account' => 'Account and notifications',
        'tips' => 'Tips and troubleshooting',
    ]);
@endphp

@extends($isAdmin ? 'layouts.admin' : 'layouts.enduser')

@section('title', 'Help & Guide - MangroveMap')

@section('styles')
<style>
    .guide-page {
        max-width: 920px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .guide-hero {
        background: linear-gradient(135deg, #0b3a2b 0%, #0f3a45 100%);
        color: #fff;
        border-radius: 18px;
        padding: 26px 28px;
        margin-bottom: 20px;
    }

    .guide-hero h1 {
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .guide-hero p {
        margin-top: 6px;
        font-size: 14px;
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

    .guide-toc {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 20px;
    }

    .guide-toc a {
        padding: 7px 12px;
        border-radius: 999px;
        border: 1px solid var(--bd-d4dfd4, #d4dfd4);
        background: var(--bg-ffffff, #fff);
        color: var(--fg-365a45, #365a45);
        font-size: 12.5px;
        font-weight: 700;
        text-decoration: none;
    }

    .guide-toc a:hover {
        border-color: #1e9e62;
        color: var(--fg-1e9e62, #1e9e62);
    }

    .guide-section {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 16px;
        padding: 22px 24px;
        margin-bottom: 16px;
        scroll-margin-top: 12px;
    }

    .guide-section h2 {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 4px;
    }

    .guide-section h2 i {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .guide-section h3 {
        font-size: 14.5px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        margin: 16px 0 6px;
    }

    .guide-section p,
    .guide-section li {
        font-size: 14px;
        color: var(--fg-2f4a3a, #2f4a3a);
    }

    .guide-lead {
        color: var(--fg-5b7864, #5b7864) !important;
        margin-bottom: 8px;
    }

    .guide-section ol,
    .guide-section ul {
        padding-left: 22px;
    }

    .guide-section li+li {
        margin-top: 4px;
    }

    .guide-badge {
        display: inline-block;
        margin-left: auto;
        padding: 3px 9px;
        border-radius: 999px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e7a4f, #1e7a4f);
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .guide-tip,
    .guide-warn {
        margin-top: 12px;
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

    .guide-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 12px;
        padding: 8px 14px;
        border-radius: 10px;
        background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
    }

    .guide-link+.guide-link {
        margin-left: 6px;
    }

    .guide-status {
        display: grid;
        grid-template-columns: max-content 1fr;
        gap: 6px 14px;
        margin-top: 8px;
        font-size: 14px;
    }

    .guide-status dt {
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .guide-status dd {
        color: var(--fg-2f4a3a, #2f4a3a);
    }

    .guide-faq details {
        border-top: 1px solid var(--bd-eef2ee, #eef2ee);
        padding: 10px 0;
    }

    .guide-faq details:first-of-type {
        border-top: none;
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

    @media (max-width: 640px) {
        .guide-hero {
            padding: 20px;
        }

        .guide-section {
            padding: 18px 16px;
        }

        .guide-status {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="guide-page">
    <header class="guide-hero">
        <h1>Help &amp; User Guide</h1>
        <p>Step-by-step instructions and tips for mapping mangroves, uploading drone imagery, and working with expert reviews.</p>
        <span class="guide-role"><i class="bi bi-person-badge"></i>
            Showing the guide for: {{ $currentUser->roleLabel() }}
        </span>
    </header>

    <nav class="guide-toc" aria-label="Guide sections">
        @foreach ($sections as $id => $label)
            <a href="#{{ $id }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- ===== Everyone ===== --}}
    <section class="guide-section" id="getting-started">
        <h2><i class="bi bi-rocket-takeoff"></i> Getting started</h2>
        <p class="guide-lead">MangroveMap helps map mangrove areas in Southern Leyte, check them with experts, and plan planting.</p>
        <ul>
            <li><strong>Residents</strong> draw mangrove areas, upload drone imagery, and send them to experts for review.</li>
            <li><strong>Experts</strong> approve or reject submissions, record yearly measurements, and give planting recommendations.</li>
            <li><strong>Administrators</strong> manage users, map layers, AI models, and activity logs.</li>
        </ul>
        <h3>Your first steps</h3>
        <ol>
            <li>Verify your email using the link sent after you registered. You can't use mapping features until you do.</li>
            <li>Open <strong>My Profile</strong> to check your name, contact number, and photo.</li>
            <li>Open the <strong>Map</strong> to explore existing mangrove areas before drawing your own.</li>
        </ol>
        <div class="guide-tip"><strong>Want to become an expert?</strong> Check "Apply for an expert account" when you register and describe your
            qualifications. An administrator reviews each application, and you'll get a notification when it's decided.</div>
    </section>

    <section class="guide-section" id="map">
        <h2><i class="bi bi-map"></i> Using the map</h2>
        <ul>
            <li><strong>Move and zoom:</strong> drag to pan, scroll or pinch to zoom.</li>
            <li><strong>Base map:</strong> use the layer control to switch between <em>Satellite</em>, <em>Street</em>, and <em>Topo</em>.
                Satellite is best for spotting mangrove canopy.</li>
            <li><strong>Find an area:</strong> type a name, genus, or status into the search box at the top of the map to jump to a saved area.</li>
            <li><strong>Area details:</strong> click any shape to open the side panel. It shows the area in hectares, the barangay and
                municipality, genera present, who drew it, its review status, and any planting recommendation.</li>
            <li><strong>Planting recommendations:</strong> use the <i class="bi bi-flower1"></i> button to show or hide the recommendation layer.</li>
            <li><strong>Changes over time:</strong> when an area has yearly observations, the <em>Changes over time</em> timeline shows how its
                boundary and coverage changed.</li>
            <li><strong>On a phone:</strong> the side panel opens as a sheet at the bottom. Drag it up for details, or tap outside it to close.</li>
        </ul>
        <a class="guide-link" href="{{ $mapRoute }}"><i class="bi bi-map"></i> Open the map</a>
    </section>

    {{-- ===== Residents ===== --}}
    @if ($showResident)
    <section class="guide-section" id="delineate">
        <h2><i class="bi bi-pencil-square"></i> Submitting a delineation <span class="guide-badge">Residents</span></h2>
        <p class="guide-lead">A delineation is a shape you draw around a mangrove area so experts can verify it and add it to the map.</p>
        <h3>Draw and save an area</h3>
        <ol>
            <li>Open <strong>Delineate</strong> from the sidebar and zoom in on the mangrove area. Use the Satellite base map.</li>
            <li>Click <i class="bi bi-pentagon"></i> <strong>Draw area</strong>.</li>
            <li>Click around the edge of the mangroves to add points. Click the starting point or double-click to finish. Press <kbd>Esc</kbd> to cancel.</li>
            <li>Use <i class="bi bi-arrow-counterclockwise"></i> Undo and <i class="bi bi-arrow-clockwise"></i> Redo to fix mistakes.</li>
            <li>In the side panel, pick the <strong>Mangrove Genus Present</strong> (for example Rhizophora/Bakawan or Avicennia/Bungalon),
                or type another genus and press <kbd>Enter</kbd>.</li>
            <li>Add <strong>Notes</strong>, for example what you saw, when you visited, and the condition of the trees.</li>
            <li>Click <strong>Save delineation</strong>. The location is filled in automatically, and experts are notified.</li>
        </ol>
        <h3>What the review status means</h3>
        <dl class="guide-status">
            <dt>Pending</dt>
            <dd>Waiting for an expert. You can still edit the boundary of your own pending delineation.</dd>
            <dt>Approved</dt>
            <dd>Verified by an expert. It now appears on the public map with your name as the contributor.</dd>
            <dt>Rejected</dt>
            <dd>The expert explained what to fix. Open it, read the <em>Expert feedback</em>, click
                <strong>Revise rejected delineation</strong>, adjust it, then click <strong>Resubmit for review</strong>.</dd>
        </dl>
        <h3>Editing a boundary</h3>
        <ul>
            <li>Select the area, then click <i class="bi bi-bounding-box-circles"></i> <strong>Edit Delineation</strong>.</li>
            <li>Drag a point to move it. Drag or click a faded midpoint to add a point.</li>
            <li>Double-click or right-click a point to delete it, or select it and press <kbd>Delete</kbd>.</li>
            <li>Click <strong>Save Changes</strong> or <strong>Cancel</strong>.</li>
        </ul>
        <div class="guide-tip"><strong>Tips for an accepted delineation:</strong> trace only the mangrove canopy, not the open water, fishponds, or
            roads. Zoom in close for edges. Draw one shape per continuous patch, and always add the genera you saw.</div>
        <a class="guide-link" href="{{ $delineateRoute }}"><i class="bi bi-pencil-square"></i> Start delineating</a>
    </section>
    @endif

    <section class="guide-section" id="drone">
        <h2><i class="bi bi-cloud-arrow-up"></i> Drone imagery upload</h2>
        <p class="guide-lead">Upload drone orthomosaics, and the AI will detect mangrove areas for you to check.</p>
        <ol>
            <li>Open <strong>Drone Upload</strong> from the sidebar.</li>
            <li>Enter a <strong>Survey name</strong>, for example "Brgy. San Isidro, March 2026".</li>
            <li>Drop your files into the box or click to choose them. Use georeferenced <strong>.tif / .tiff</strong> files
                (8-bit RGB orthomosaics). You can upload several tiles of the same survey at once.</li>
            <li>Click <strong>Upload and process</strong>. The list on the right shows progress: the preview is prepared, then the AI detects mangroves.
                Large files can take a while, and you can leave the page and come back.</li>
            <li>When it's ready, open the imagery to review it. Green shapes are the areas the AI found.
                <ul>
                    <li><i class="bi bi-pentagon"></i> <strong>Draw area</strong> adds mangroves the AI missed.</li>
                    <li><i class="bi bi-pencil"></i> <strong>Edit</strong> lets you drag corners. Double-click a corner to delete it.</li>
                    <li>Click an area to remove it if it isn't mangrove, for example a coconut farm or shadows.</li>
                    <li>Adjust <strong>Opacity</strong> to compare the imagery with the base map.</li>
                </ul>
            </li>
            <li>Click <strong>Save draft</strong> to continue later, or <strong>Confirm &amp; add to map</strong> when you're done.
                Confirmed areas are sent for expert review like any other delineation.</li>
        </ol>
        <div class="guide-warn"><strong>The AI can be wrong.</strong> Always check its shapes before you confirm them. Upload only imagery you took
            or have permission to use, and follow CAAP drone rules. Your imagery stays private to you and the administrators.</div>
        <a class="guide-link" href="{{ route('drone-imagery.page') }}"><i class="bi bi-cloud-arrow-up"></i> Go to Drone Upload</a>
    </section>

    {{-- ===== Experts ===== --}}
    @if ($showExpert)
    <section class="guide-section" id="review">
        <h2><i class="bi bi-patch-check"></i> Reviewing delineations <span class="guide-badge">Experts</span></h2>
        <ol>
            <li>You get a notification <i class="bi bi-bell"></i> when a resident submits a delineation. Open it, or find pending areas on the
                <strong>Map</strong> or <strong>Delineate</strong> page.</li>
            <li>Click the area to see its genera, notes, location, and who submitted it.</li>
            <li>Compare the boundary with the satellite base map and, if possible, what you know from the field.</li>
            <li>Click <strong>Approve</strong> if it's accurate. Click <strong>Reject</strong> if it isn't, and explain what the resident should fix
                (at least 10 characters).</li>
        </ol>
        <div class="guide-tip"><strong>Write useful feedback:</strong> say exactly what's wrong, such as "The north edge includes the fishpond.
            Please trace along the tree line." Clear notes help residents fix their work quickly.</div>
        <p style="margin-top:12px;">You can also draw your own delineations on <strong>Delineate</strong> and correct boundaries of resident records.
            Every boundary change is saved in the revision history.</p>
    </section>

    <section class="guide-section" id="observations">
        <h2><i class="bi bi-calendar3"></i> Recording changes over time <span class="guide-badge">Experts</span></h2>
        <p class="guide-lead">When mangroves grow or shrink, update the boundary on the map. The edit becomes that year's observation in
            <em>Changes over time</em> and the coverage trend.</p>
        <ol>
            <li>On the <strong>Map</strong> or <strong>Delineate</strong> page, select the zone and click
                <i class="bi bi-bounding-box-circles"></i> <strong>Edit Delineation</strong>.</li>
            <li>Move, add, or delete points so the boundary matches the mangroves as they are now. Update the genera in the panel if they changed.</li>
            <li>Under <strong>Record this change as</strong>, choose:
                <ul>
                    <li><strong>Correction</strong> if you're only fixing how the area was drawn. It's saved, but it isn't added to the timeline.</li>
                    <li><strong>Observed change</strong> if the mangroves really changed. Then set the <strong>Date observed</strong> (the survey or
                        image date, not today's date) and how you saw it: field survey, drone imagery, or satellite imagery.</li>
                </ul>
            </li>
            <li>Click <strong>Save Changes</strong>. The hectares are calculated from the new boundary, and the genera come from the panel.</li>
        </ol>
        <div class="guide-tip">The first observed change on a zone also saves the original boundary as its starting year, so the timeline
            shows a before and after. If you record two changes in the same year, the later one replaces the earlier one.</div>
        <div class="guide-warn">Choose <strong>Correction</strong> when tidying a resident's tracing. Saving it as an observed change would show
            mangrove gain or loss that never happened.</div>
    </section>

    <section class="guide-section" id="recommendations">
        <h2><i class="bi bi-tree"></i> Planting recommendations <span class="guide-badge">Experts</span></h2>
        <ol>
            <li>Open <strong>Planting Recommendation</strong> from the sidebar.</li>
            <li>Click <i class="bi bi-pentagon"></i> <strong>Draw area</strong> to outline a new site, or <i class="bi bi-hand-index"></i>
                <strong>Select area</strong> to pick an approved delineation.</li>
            <li>Give it a <strong>Name</strong> and choose the <strong>Planting suitability</strong>: Highly, Moderately, or Marginally suitable,
                or Not suitable.</li>
            <li>Select the <strong>Recommended mangrove genus</strong>. This is required unless the site is Not suitable.</li>
            <li>Optionally, add <strong>Recommendations</strong> (planting method, spacing, season, propagule source, protection) and
                <strong>Site assessment notes</strong> (substrate, tidal flooding, salinity, threats).</li>
            <li>Click <strong>Save recommendation</strong>. Only the expert who wrote a recommendation can edit or delete it.</li>
        </ol>
        <div class="guide-warn">Recommendations are advice, not permits. Remind communities to coordinate with DENR (CENRO/PENRO) and the LGU before planting.</div>
        <a class="guide-link" href="{{ route('expert.recommendations.index') }}"><i class="bi bi-tree"></i> Open Planting Recommendation</a>
    </section>
    @endif

    {{-- ===== Admins ===== --}}
    @if ($isAdmin)
    <section class="guide-section" id="admin">
        <h2><i class="bi bi-shield-lock"></i> Administration <span class="guide-badge">Admins</span></h2>
        <h3>Users and expert applications</h3>
        <ul>
            <li>Pending expert applications appear at the top of <strong>Users</strong>. Read the organization and qualifications, then
                <strong>Approve</strong> or reject them. A rejection reason is optional and is shared with the applicant.</li>
            <li>Use the view, edit, and delete actions to update a user's details or role, or to reset their password.</li>
        </ul>
        <h3>Map layers</h3>
        <ul>
            <li><strong>Delineations:</strong> preview, edit the name and notes, <em>Hide from map</em> (with an optional reason sent to the owner),
                restore, or delete.</li>
            <li><strong>Drone imagery:</strong> preview, rename, <em>Retry processing</em> after a failure, run or re-run AI segmentation, or delete.</li>
        </ul>
        <h3>Model training</h3>
        <ul>
            <li>Click <strong>Upload Model</strong> to add a new segmentation model (<code>.pth</code>). It's checked before it becomes active,
                and the newest valid model is used for new AI detections.</li>
        </ul>
        <h3>Activity logs</h3>
        <ul>
            <li>Filter by date to see who did what and when. Use this to investigate problems or misuse.</li>
        </ul>
        <div class="guide-tip">Prefer <strong>Hide from map</strong> over deleting when a record is doubtful. Hiding can be undone, but deleting can't.</div>
        <a class="guide-link" href="{{ route('admin.users') }}"><i class="bi bi-people"></i> Users</a>
        <a class="guide-link" href="{{ route('admin.map-layers.index') }}"><i class="bi bi-layers"></i> Map Layers</a>
    </section>
    @endif

    {{-- ===== Everyone ===== --}}
    <section class="guide-section" id="account">
        <h2><i class="bi bi-person-gear"></i> Account and notifications</h2>
        <ul>
            <li><strong>My Profile</strong> lets you update your name, contact number, and photo, and change your password.</li>
            <li><strong>Notifications</strong> <i class="bi bi-bell"></i> tell you when a delineation is approved or rejected, when an expert
                application is decided, and when there is new work to review. A red badge shows how many are unread.</li>
            <li>Forgot your password? Use <em>Forgot password</em> on the login page to get a reset link by email.</li>
            <li>Always log out on shared or public computers.</li>
        </ul>
    </section>

    <section class="guide-section guide-faq" id="tips">
        <h2><i class="bi bi-lightbulb"></i> Tips and troubleshooting</h2>
        <details>
            <summary>The map is blank or slow to load.</summary>
            <p>Check your internet connection and refresh the page. Base maps load from online services, so weak signal in coastal areas can
                slow them down. Try the Street base map, which is lighter.</p>
        </details>
        <details>
            <summary>I can't finish drawing my area.</summary>
            <p>Click the first point again, or double-click to close the shape. An area needs at least three points. Press <kbd>Esc</kbd> to start over.</p>
        </details>
        <details>
            <summary>My drone upload failed or is stuck processing.</summary>
            <p>Make sure the file is a georeferenced GeoTIFF (.tif/.tiff) exported from your mapping software as an RGB orthomosaic, not a raw
                photo. Large surveys can take a long time. If it shows <em>Processing failed</em>, use <em>Edit &amp; retry</em> or ask an administrator.</p>
        </details>
        <details>
            <summary>The AI marked areas that aren't mangroves.</summary>
            <p>That's normal. The AI is a starting point. Click those areas to remove them in the review editor, and draw any it missed before
                you confirm.</p>
        </details>
        <details>
            <summary>The location shown for my area looks wrong.</summary>
            <p>The barangay and municipality are worked out automatically from where the shape sits, and can be approximate near borders.
                Mention the correct location in your notes.</p>
        </details>
        <details>
            <summary>I didn't get the verification or password email.</summary>
            <p>Check your spam or junk folder. On the verification page, click resend. You can request a new link a few times per minute.</p>
        </details>
        <details>
            <summary>Who can see what I submit?</summary>
            <p>Pending and rejected delineations are visible only to you, experts, and administrators. Approved delineations are public on the
                home page map with your name. Drone imagery stays private. See the <a href="{{ route('terms') }}">Terms and Conditions</a> for details.</p>
        </details>
    </section>

    <p class="guide-footer">Still stuck? Contact an administrator through the system. &middot; <a href="{{ route('terms') }}">Terms and Conditions</a></p>
</div>
@endsection
