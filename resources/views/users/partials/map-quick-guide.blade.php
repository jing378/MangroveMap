{{--
  Quick guide card for the map pages (Map, Delineate, Planting Recommendation), opened by the "?" button in the
  map controls (partials.map-quick-guide-button). It opens by itself the first time each of these pages is
  opened in a browser tab (sessionStorage); after that only the "?" opens it.
  Usage: @include('users.partials.map-quick-guide', ['page' => 'map' | 'delineate' | 'recommendations'])
--}}
@php
    $isExpertGuide = auth()->user()?->isExpert();
    // Keep each step to one short line that names the real button (with its icon), so it can be followed on the map.
    $searchStep = '<i class="bi bi-search"></i> <strong>Search</strong> a name, genus or status to jump to an area.';
    $detailsStep = '<strong>Click a shape</strong> for its details. Tap a <strong>genus</strong> to see what it looks like.';
    $layersStep = '<i class="bi bi-layers-fill"></i> <strong>Layers</strong>: Satellite, Street, Topo or <strong>Mangrove 400-ft View</strong>.';
    // The year card: satellite extent when that data is switched on, else this system's own records.
    $yearSliderStep = config('map.mangrove_history.enabled')
        ? '<i class="bi bi-graph-up"></i> See <strong>mangrove extent by year</strong> since 1985.'
        : '<i class="bi bi-graph-up"></i> <strong>Year slider</strong>: what was mapped by each year. Turn on <strong>Compare with previous data</strong> for the satellite record.';
    // The map-controls refresh button (workspace-map): Map and Delineate only.
    $refreshStep = '<i class="bi bi-arrow-repeat"></i> <strong>Refresh</strong> for the latest areas. New ones also appear by themselves.';
    $guides = [
        'map' => [
            'title' => 'Using the map',
            'anchor' => 'map',
            'steps' => $isExpertGuide
                ? [
                    $searchStep,
                    '<strong>Click a shape</strong>. Pending ones: <strong>Approve</strong> or <strong>Reject</strong> in the panel.',
                    '<i class="bi bi-bounding-box-circles"></i> <strong>Edit Delineation</strong> to fix a boundary or record an observed change.',
                    $layersStep,
                    $yearSliderStep,
                    $refreshStep,
                ]
                : [
                    $searchStep,
                    $detailsStep,
                    $layersStep,
                    '<i class="bi bi-flower1"></i> Show or hide <strong>planting recommendations</strong>.',
                    $yearSliderStep,
                    $refreshStep,
                ],
        ],
        'delineate' => [
            'title' => 'Delineating',
            'anchor' => $isExpertGuide ? 'review' : 'delineate',
            'steps' => $isExpertGuide
                ? [
                    '<i class="bi bi-pentagon"></i> <strong>Draw area</strong> or <i class="bi bi-file-earmark-arrow-up"></i> <strong>Import shapefile</strong>. Yours are approved right away.',
                    'Click a pending area, then <strong>Approve</strong> or <strong>Reject</strong> (say what to fix).',
                    '<i class="bi bi-bounding-box-circles"></i> <strong>Edit Delineation</strong>: a <strong>Correction</strong>, or an <strong>Observed change</strong> for that year.',
                    '<strong>Save</strong> when you\'re done.',
                    $refreshStep,
                ]
                : [
                    '<i class="bi bi-pentagon"></i> <strong>Draw area</strong> and click along the mangroves\' edge.',
                    'Click the first point or double-click to finish. <kbd>Esc</kbd> cancels.',
                    'Have a shapefile? <i class="bi bi-file-earmark-arrow-up"></i> <strong>Import shapefile</strong> (a .zip).',
                    'Choose the <strong>genus</strong>, add notes, then <strong>Save delineation</strong>. An expert reviews it.',
                    'Rejected? Open it and <strong>Revise rejected delineation</strong> within 7 days.',
                    $refreshStep,
                ],
        ],
        'recommendations' => [
            'title' => 'Planting recommendations',
            'anchor' => 'recommendations',
            'steps' => [
                '<i class="bi bi-pentagon"></i> <strong>Draw area</strong>, or <i class="bi bi-hand-index"></i> <strong>Select area</strong> for an approved delineation.',
                'Choose the <strong>suitability</strong> and <strong>genus</strong> (not needed if Not suitable).',
                'Add advice and site notes if needed.',
                '<strong>Save recommendation</strong>. Open a saved one and <strong>Edit area</strong> to change its outline.',
            ],
        ],
    ];
    $guide = $guides[$page] ?? $guides['map'];
@endphp

<section class="map-guide-card" id="mapGuideCard" data-guide-page="{{ $page }}" aria-labelledby="mapGuideTitle" hidden>
    <div class="map-guide-head">
        <i class="bi bi-lightbulb" aria-hidden="true"></i>
        <h2 id="mapGuideTitle">Quick guide: {{ $guide['title'] }}</h2>
        <button type="button" class="map-guide-close" onclick="toggleMapGuide(false)" aria-label="Close quick guide"><i class="bi bi-x-lg"></i></button>
    </div>
    <ol>
        @foreach ($guide['steps'] as $step)
            <li>{!! $step !!}</li>
        @endforeach
    </ol>
    <a class="map-guide-more" href="{{ route('guide') }}#{{ $guide['anchor'] }}">Full guide <i class="bi bi-arrow-right"></i></a>
</section>

<style>
    .map-guide-card {
        position: absolute;
        top: 120px;
        left: 58px;
        z-index: 1001;
        width: 270px;
        padding: 12px 14px 12px;
        border-radius: 12px;
        border: 1px solid var(--bd-cfe5d8, #cfe5d8);
        background: var(--bg-ffffff, #fff);
        box-shadow: 0 8px 24px rgba(15, 40, 25, .18);
        font-family: 'Manrope', sans-serif;
    }

    .map-guide-card[hidden] {
        display: none;
    }

    .map-guide-head {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 8px;
        color: var(--fg-1e7a4f, #1e7a4f);
    }

    .map-guide-head h2 {
        flex: 1;
        margin: 0;
        font-size: 13px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .map-guide-close {
        all: unset;
        cursor: pointer;
        display: grid;
        place-items: center;
        width: 26px;
        height: 26px;
        border-radius: 7px;
        color: var(--fg-5b7864, #5b7864);
    }

    .map-guide-close:hover,
    .map-guide-close:focus-visible {
        background: var(--bg-f3faf6, #f3faf6);
    }

    .map-guide-card ol {
        list-style: none;
        counter-reset: mapguide;
        margin: 0;
        padding: 0;
    }

    .map-guide-card li {
        counter-increment: mapguide;
        position: relative;
        padding-left: 27px;
        font-size: 12.5px;
        line-height: 1.45;
        color: var(--fg-2f4a3a, #2f4a3a);
    }

    .map-guide-card li+li {
        margin-top: 7px;
    }

    .map-guide-card li::before {
        content: counter(mapguide);
        position: absolute;
        left: 0;
        top: 0;
        display: grid;
        place-items: center;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #1e9e62;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
    }

    .map-guide-card li strong {
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .map-guide-card li .bi {
        color: var(--fg-1e7a4f, #1e7a4f);
    }

    .map-guide-card kbd {
        padding: 0 4px;
        border: 1px solid var(--bd-cfe5d8, #cfe5d8);
        border-radius: 4px;
        font: inherit;
        font-size: 11px;
        font-weight: 700;
    }

    .map-guide-more {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 10px;
        font-size: 12px;
        font-weight: 800;
        color: var(--fg-1e7a4f, #1e7a4f);
        text-decoration: none;
    }

    .map-guide-more:hover {
        text-decoration: underline;
    }

    #mapGuideBtn[aria-expanded="true"] {
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
    }

    /* Out of the way while a boundary or area is being edited (those pages show only the edit controls). */
    body.is-boundary-editing .map-guide-card,
    body.is-area-editing .map-guide-card {
        display: none;
    }

    @media (max-width: 780px) {
        .map-guide-card {
            left: 56px;
            right: 10px;
            width: auto;
            max-width: 320px;
        }
    }
</style>

<script>
    (function () {
        const card = document.getElementById('mapGuideCard');
        if (!card) return;

        window.toggleMapGuide = function (open) {
            const show = typeof open === 'boolean' ? open : card.hidden;
            card.hidden = !show;
            document.getElementById('mapGuideBtn')?.setAttribute('aria-expanded', String(show));
        };

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !card.hidden) window.toggleMapGuide(false);
        });

        // Open by itself only the first time this page is opened in this browser tab, so it doesn't come back
        // every time the user goes back and forth between pages. A new tab shows it again; "?" opens it any time.
        const seenKey = 'mangrovemap-guide-seen:' + (card.dataset.guidePage || 'map');
        let seen = false;
        try {
            seen = sessionStorage.getItem(seenKey) === '1';
            sessionStorage.setItem(seenKey, '1');
        } catch (e) {
            // Storage blocked (private mode, site data off): fall back to showing it.
        }
        window.toggleMapGuide(!seen);
    })();
</script>
