{{-- Dashboard "Field Conditions" weather card, shown on the hero banner of the resident/expert and admin dashboards.
     The forecast (users.partials.field-conditions, FieldWeatherService) is fetched after the page so a slow weather service never delays it.
     The hero must be a flex row (.dash-hero with .hero-content). --}}
<style>
    /* Field conditions (weather), shown directly on the hero banner (dark green in both themes) */
    .field-card {
        flex: 0 0 min(440px, 42%);
        min-width: 0;
        position: relative;
        z-index: 1;
        padding-left: 28px;
        border-left: 1px solid rgba(255, 255, 255, 0.18);
        color: #fff;
    }

    @media (max-width: 1024px) {
        .dash-hero {
            flex-direction: column;
            align-items: stretch;
            gap: 20px;
        }

        .field-card {
            flex-basis: auto;
            padding-left: 0;
            padding-top: 18px;
            border-left: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
        }
    }

    .field-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 4px 12px;
        margin-bottom: 10px;
    }

    .field-head .chart-title {
        color: #fff;
        font-size: 14px;
    }

    .field-loading,
    .field-empty {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 64px;
        font-size: 13px;
        color: #b8d9c6;
    }

    .field-empty .bi {
        font-size: 22px;
    }

    .field-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.25);
        color: #a3e6be;
        border-top-color: currentColor;
        border-radius: 50%;
        animation: fieldSpin 0.8s linear infinite;
    }

    @keyframes fieldSpin {
        to { transform: rotate(360deg); }
    }

    .field-body {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .field-now {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 14px;
    }

    .field-now-icon {
        font-size: 40px;
        color: #f5c56b;
        line-height: 1;
    }

    .field-now-temp {
        font-size: 26px;
        font-weight: 800;
        color: #fff;
        line-height: 1.1;
    }

    .field-now-desc {
        font-size: 13px;
        font-weight: 600;
        color: #e2f2e8;
    }

    .field-now-meta {
        font-size: 11px;
        color: #a3c9b3;
        margin-top: 2px;
    }

    .field-verdict {
        flex: 1 1 200px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid;
        font-size: 12px;
        line-height: 1.4;
    }

    .field-verdict .bi {
        font-size: 18px;
        margin-top: 1px;
    }

    .field-verdict strong {
        display: block;
        font-size: 13px;
    }

    .field-verdict.is-good { background: rgba(110, 231, 160, 0.12); border-color: rgba(110, 231, 160, 0.35); color: #b9f2d0; }
    .field-verdict.is-caution { background: rgba(245, 197, 107, 0.14); border-color: rgba(245, 197, 107, 0.4); color: #fbdea4; }
    .field-verdict.is-avoid { background: rgba(255, 138, 118, 0.14); border-color: rgba(255, 138, 118, 0.4); color: #ffc4b8; }

    .field-foot {
        margin-top: 12px;
        font-size: 11px;
        color: #93b9a3;
    }

    .field-foot a {
        color: inherit;
        text-decoration: underline;
    }

    .field-foot-hint {
        font-style: italic;
    }

    .field-head .chart-title {
        font-weight: 700;
    }

    .field-head .chart-title > .bi { margin-right: 6px; }

</style>

<!-- Field conditions: weather forecast for surveys and planting, loaded after the page -->
<section class="field-card" id="fieldConditions" data-url="{{ route('dashboard.field-conditions') }}" aria-labelledby="fieldConditionsTitle" aria-busy="true">
    <div class="field-head">
        <div class="chart-title" id="fieldConditionsTitle"><i class="bi bi-cloud-sun me-2"></i> Field Conditions</div>
    </div>
    <div class="field-content" aria-live="polite">
        <div class="field-loading"><span class="field-spinner" aria-hidden="true"></span> Loading the forecast...</div>
    </div>
</section>

<script>
    // Field conditions: the forecast is fetched after the page so a slow weather service never delays it.
    (() => {
        const card = document.getElementById('fieldConditions');
        if (!card) return;
        const content = card.querySelector('.field-content');
        // The user's last area (rounded to ~1 km) is remembered in this browser only, so the card opens on it
        // right away; the live position then refreshes it if the user has moved.
        const STORAGE_KEY = 'mangrovemap.fieldWeatherSpot';
        const round = value => Math.round(value * 100) / 100;
        const readSpot = () => {
            try {
                const spot = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                return spot && Number.isFinite(spot.lat) && Number.isFinite(spot.lng) ? spot : null;
            } catch (e) {
                return null;
            }
        };
        const saveSpot = spot => {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(spot)); } catch (e) { /* storage unavailable */ }
        };
        const sameSpot = (a, b) => !!a && !!b && a.lat === b.lat && a.lng === b.lng;

        let shownSpot;
        let requestedSpot;
        let requestId = 0;

        const load = spot => {
            const id = ++requestId;
            requestedSpot = spot;
            const url = new URL(card.dataset.url, window.location.href);
            if (spot) {
                url.searchParams.set('lat', spot.lat);
                url.searchParams.set('lng', spot.lng);
            }
            card.setAttribute('aria-busy', 'true');

            return fetch(url.pathname + url.search, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                })
                .then(response => response.ok ? response.text() : Promise.reject(response.status))
                .then(html => {
                    if (id !== requestId) return;
                    content.innerHTML = html;
                    shownSpot = spot;
                })
                .catch(() => {
                    if (id !== requestId || shownSpot !== undefined) return; // keep a forecast that is already shown
                    content.innerHTML = '<div class="field-empty"><i class="bi bi-cloud-slash"></i><span>The weather forecast could not be loaded. Check PAGASA before going out to the field.</span></div>';
                })
                .finally(() => { if (id === requestId) card.removeAttribute('aria-busy'); });
        };

        load(readSpot());

        if ('geolocation' in navigator && window.isSecureContext) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    const spot = { lat: round(position.coords.latitude), lng: round(position.coords.longitude) };
                    saveSpot(spot);
                    if (!sameSpot(spot, requestedSpot)) load(spot);
                },
                error => {
                    // Location access turned off: forget the remembered area and show the default place.
                    if (error.code === error.PERMISSION_DENIED && requestedSpot) {
                        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* storage unavailable */ }
                        load(null);
                    }
                },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 15 * 60 * 1000 }
            );
        }
    })();

</script>
