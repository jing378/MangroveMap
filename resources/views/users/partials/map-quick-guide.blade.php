{{--
  Quick guide card for the map pages (Map, Delineate, Planting Recommendation), opened by the "?" button in the
  map controls (partials.map-quick-guide-button). Opens by itself on the first visit of each page; once closed,
  it stays closed (remembered per browser) until the "?" is clicked again.
  Usage: @include('users.partials.map-quick-guide', ['page' => 'map' | 'delineate' | 'recommendations'])
--}}
@php
    $isExpertGuide = auth()->user()?->isExpert();
    $guides = [
        'map' => [
            'title' => 'Using the map',
            'anchor' => 'map',
            'steps' => $isExpertGuide
                ? [
                    '<strong>Click a shape</strong> to see its details.',
                    'Pending resident areas: <strong>Approve</strong> or <strong>Reject</strong> in the panel.',
                    '<i class="bi bi-layers-fill"></i> Switch to <strong>Satellite</strong> to see the canopy.',
                    'Open an area\'s <strong>Changes over time</strong>.',
                ]
                : [
                    '<strong>Click a shape</strong> to see its details.',
                    '<i class="bi bi-layers-fill"></i> Switch to <strong>Satellite</strong> to see the canopy.',
                    '<i class="bi bi-flower1"></i> Show or hide planting recommendations.',
                    'Open an area\'s <strong>Changes over time</strong>.',
                ],
        ],
        'delineate' => [
            'title' => 'Delineating',
            'anchor' => $isExpertGuide ? 'review' : 'delineate',
            'steps' => $isExpertGuide
                ? [
                    '<i class="bi bi-pentagon"></i> <strong>Draw area</strong> to add your own (approved right away).',
                    'Click a pending area, then <strong>Approve</strong> or <strong>Reject</strong>.',
                    '<i class="bi bi-bounding-box-circles"></i> <strong>Edit Delineation</strong> to fix a boundary or record a change.',
                    '<strong>Save</strong> when you\'re done.',
                ]
                : [
                    '<i class="bi bi-pentagon"></i> Click <strong>Draw area</strong> and trace the mangroves.',
                    'Click the first point to finish.',
                    'Choose the <strong>genus</strong> and add notes.',
                    '<strong>Save</strong>. An expert reviews it.',
                ],
        ],
        'recommendations' => [
            'title' => 'Planting recommendations',
            'anchor' => 'recommendations',
            'steps' => [
                '<i class="bi bi-pentagon"></i> <strong>Draw area</strong>, or <i class="bi bi-hand-index"></i> <strong>Select area</strong> for an approved delineation.',
                'Choose the <strong>suitability</strong> and <strong>genus</strong>.',
                'Add notes if needed.',
                '<strong>Save recommendation</strong>.',
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
        const storageKey = `mapGuide.closed.${card.dataset.guidePage}`;

        window.toggleMapGuide = function (open) {
            const show = typeof open === 'boolean' ? open : card.hidden;
            card.hidden = !show;
            document.getElementById('mapGuideBtn')?.setAttribute('aria-expanded', String(show));
            // Closing it remembers the choice, so it doesn't open by itself on the next visit.
            if (!show) {
                try { localStorage.setItem(storageKey, '1'); } catch (e) {}
            }
        };

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !card.hidden) window.toggleMapGuide(false);
        });

        // First visit of this page: open it once.
        let closedBefore = false;
        try { closedBefore = localStorage.getItem(storageKey) === '1'; } catch (e) {}
        if (!closedBefore) window.toggleMapGuide(true);
    })();
</script>
