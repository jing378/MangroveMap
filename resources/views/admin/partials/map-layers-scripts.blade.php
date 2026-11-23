<script>
    (function() {
        const MAP_MAX_ZOOM = 22;
        // Southern Leyte, where the project's mangrove surveys are.
        const DEFAULT_VIEW = [10.25, 125.1];

        const previewMap = L.map('layerPreviewMap', { maxZoom: MAP_MAX_ZOOM }).setView(DEFAULT_VIEW, 9);
        // Satellite basemap. Not called previewImagery: that name is the drone-layer preview function
        // below, and declaring both is a syntax error that stops this whole script (no map at all).
        const basemapImagery = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: MAP_MAX_ZOOM,
            maxNativeZoom: 18,
            attribution: 'Imagery &copy; Esri',
        }).addTo(previewMap);
        @include('partials.map-place-labels')
        attachPlaceLabels(previewMap, [basemapImagery]);

        const previewGroup = L.layerGroup().addTo(previewMap);
        const previewLabel = document.getElementById('previewLabel');
        const delineationStyle = { color: '#22c55e', weight: 2, fillOpacity: 0.25 };
        // Rejected delineations in red, so they are not mistaken for published mangrove areas.
        const rejectedDelineationStyle = { color: '#ef4444', weight: 2, fillOpacity: 0.25 };
        const aiExtentStyle = { color: '#facc15', weight: 2, fillOpacity: 0.12, dashArray: '4 4' };

        // Delineation features use {type: point|line|area, coords: [lat, lng] | [[lat, lng], ...]}.
        function featureLayer(feature, style) {
            if (feature.type === 'point') {
                return L.circleMarker(feature.coords, { ...style, radius: 7, fillOpacity: 0.8 });
            }
            if (feature.type === 'line') {
                return L.polyline(feature.coords, style);
            }
            return L.polygon(feature.coords, style);
        }

        function drawFeatures(features, style) {
            const group = L.featureGroup((features || []).map(feature => featureLayer(feature, style)));
            group.addTo(previewGroup);
            return group;
        }

        function fitTo(bounds) {
            if (bounds && bounds.isValid()) {
                previewMap.fitBounds(bounds, { padding: [30, 30], maxZoom: 18 });
            }
        }

        function previewDelineation(button) {
            // Drawn from drone imagery: that imagery first, so the outline sits on top of it.
            drawImagery(button);
            const features = JSON.parse(button.dataset.features || '[]');
            const style = 'rejected' in button.dataset ? rejectedDelineationStyle : delineationStyle;
            fitTo(drawFeatures(features, style).getBounds());
        }

        function previewImagery(button) {
            const bounds = drawImagery(button);
            if (!bounds) return;
            fitTo(bounds);

            // Its submitted delineation on top of the imagery; before the owner confirms there is none yet,
            // so the AI-detected extent is shown instead.
            if (button.dataset.features) {
                const style = 'rejected' in button.dataset ? rejectedDelineationStyle : delineationStyle;
                drawFeatures(JSON.parse(button.dataset.features), style);
            } else if (button.dataset.segmentationUrl) {
                fetch(button.dataset.segmentationUrl, { headers: { Accept: 'application/json' } })
                    .then(response => response.ok ? response.json() : null)
                    .then(result => {
                        if (result && button.classList.contains('is-previewing')) {
                            drawFeatures(result.features, aiExtentStyle);
                        }
                    })
                    .catch(error => console.warn('AI extent load failed:', error));
            }
        }

        // A drone layer's map tiles, or its review image while it awaits the owner's review. Returns its bounds.
        function drawImagery(button) {
            const b = JSON.parse(button.dataset.bounds || 'null');
            if (!b || !(button.dataset.tileUrl || button.dataset.previewUrl)) return null;

            const bounds = L.latLngBounds([b.south, b.west], [b.north, b.east]);
            if (button.dataset.tileUrl) {
                L.tileLayer(button.dataset.tileUrl, {
                    bounds,
                    minNativeZoom: Number(button.dataset.zoomMin) || 0,
                    maxNativeZoom: Number(button.dataset.zoomMax) || MAP_MAX_ZOOM,
                    maxZoom: MAP_MAX_ZOOM,
                    updateWhenZooming: false,
                    updateWhenIdle: true,
                }).addTo(previewGroup);
            } else if (button.dataset.previewUrl) {
                // Awaiting the owner's review: no map tiles yet, only the review image.
                L.imageOverlay(button.dataset.previewUrl, bounds).addTo(previewGroup);
            }

            return bounds;
        }

        const previewMapEl = document.getElementById('layerPreviewMap');

        // Bring the map into view after a row's preview button, but only when it is off screen
        // (on phones the list is below the map), and never for the automatic first preview.
        function revealPreviewMap() {
            const rect = previewMapEl.getBoundingClientRect();
            const visible = rect.top >= 0 && rect.bottom <= window.innerHeight;
            if (!visible) {
                previewMapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        function showPreview(button, { reveal = true } = {}) {
                previewGroup.clearLayers();
                document.querySelectorAll('.layer-row.previewing').forEach(row => row.classList.remove('previewing'));
                document.querySelectorAll('[data-preview].is-previewing').forEach(b => b.classList.remove('is-previewing'));

                button.classList.add('is-previewing');
                button.closest('.layer-row')?.classList.add('previewing');

                if (button.dataset.kind === 'imagery') {
                    previewLabel.textContent = button.dataset.name
                        + (button.dataset.previewUrl ? ' · awaiting owner review (preview image)' : '')
                        + (button.dataset.features
                            ? ' · with its delineation (' + button.dataset.delineationStatus + ('rejected' in button.dataset ? ', in red)' : ')')
                            : (button.dataset.segmentationUrl ? ' · AI-detected extent in yellow' : ''));
                    previewImagery(button);
                } else {
                    previewLabel.textContent = button.dataset.name
                        + ('rejected' in button.dataset ? ' · Rejected, shown in red' : '')
                        + (button.dataset.tileUrl ? ' · on its drone imagery' : '')
                        + (button.dataset.previewUrl ? ' · on its drone imagery (awaiting owner review)' : '');
                    previewDelineation(button);
                }

                if (reveal) revealPreviewMap();
        }

        // What a click previews: a preview button, or a row carrying the preview data itself (delineations;
        // the row's other controls, links and Delete, keep their own action).
        function previewSource(target) {
            const source = target.closest('[data-preview]');
            if (!source || source.disabled) return null;
            if (source.matches('tr') && target.closest('a, button, form, input, select, textarea, label')) return null;
            return source;
        }

        // Delegated: the list is replaced in place when switching tabs, filtering or paging (see below).
        document.addEventListener('click', event => {
            const source = previewSource(event.target);
            if (source) showPreview(source);
        });

        // Rows that preview are focusable; Enter or Space previews them too.
        document.addEventListener('keydown', event => {
            if ((event.key !== 'Enter' && event.key !== ' ') || !event.target.matches('tr[data-preview]')) return;
            event.preventDefault();
            showPreview(event.target);
        });

        // Preview the first previewable row so the map is never empty (without scrolling the page).
        function previewFirstRow() {
            const firstPreview = document.querySelector('#layerList [data-preview]:not(:disabled)');
            if (firstPreview) {
                showPreview(firstPreview, { reveal: false });
            } else {
                previewGroup.clearLayers();
                previewLabel.textContent = 'Select a layer below to preview it';
            }
        }
        previewFirstRow();

        // The map sits in a card that can change width (sidebar, rotation); keep its tiles filling it.
        window.addEventListener('resize', () => previewMap.invalidateSize());

        // ---- Expand ----
        // The preview card fills the window (imagery, outline and label), keeping the same view; Esc or the
        // button again puts it back. A CSS overlay rather than the Fullscreen API, which iPhones do not support.
        const previewSection = previewMapEl.closest('.preview-section');
        // Where the card sits in the page: while expanded it is moved to <body>, so no page wrapper (scroll
        // container, stacking context) can clip it or keep it under the header, then put back here.
        const previewHome = document.createComment('preview-section');
        previewSection.before(previewHome);
        let expandButton = null;

        function setExpanded(expanded) {
            if (expanded) {
                document.body.appendChild(previewSection);
            } else {
                previewHome.after(previewSection);
            }
            previewSection.classList.toggle('is-expanded', expanded);
            document.documentElement.classList.toggle('preview-expanded', expanded);
            expandButton.innerHTML = `<i class="bi ${expanded ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'}" aria-hidden="true"></i>`;
            expandButton.title = expanded ? 'Back to normal size (Esc)' : 'Expand map';
            expandButton.setAttribute('aria-label', expandButton.title);
            expandButton.setAttribute('aria-pressed', String(expanded));
            previewMap.invalidateSize();
            // Once more after the browser has laid out the new size, so the newly visible tiles load.
            requestAnimationFrame(() => previewMap.invalidateSize());
        }

        const ExpandControl = L.Control.extend({
            options: { position: 'topleft' },
            onAdd() {
                const bar = L.DomUtil.create('div', 'leaflet-bar');
                expandButton = L.DomUtil.create('a', 'preview-expand', bar);
                expandButton.href = '#';
                expandButton.setAttribute('role', 'button');
                L.DomEvent.disableClickPropagation(bar);
                L.DomEvent.on(expandButton, 'click', event => {
                    L.DomEvent.preventDefault(event);
                    setExpanded(!previewSection.classList.contains('is-expanded'));
                });
                return bar;
            },
        });
        new ExpandControl().addTo(previewMap);
        setExpanded(false);

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && previewSection.classList.contains('is-expanded')) {
                setExpanded(false);
            }
        });

        // Forms with data-confirm / data-busy-label are handled by partials.action-feedback.

        // ---- Tabs, filters and pages without a reload ----
        // The page is fetched in the background and only the list card is swapped, so the preview map, the
        // stat cards and the scroll position stay put. The address bar follows (back/forward work). Anything
        // unexpected (an error, a redirect elsewhere) falls back to a normal page load.
        const listId = 'layerList';
        let loadController = null;

        // Same host and page. The protocol is ignored and then set to the page's: links Laravel builds can come
        // out as http:// behind the https tunnel, which would otherwise not match (or be blocked when fetched).
        function isListUrl(url) {
            return url.host === location.host && url.pathname === location.pathname;
        }

        function listUrl(href) {
            const url = new URL(href, location.href);
            url.protocol = location.protocol;
            return url;
        }

        async function loadList(url, { push = true } = {}) {
            const list = document.getElementById(listId);
            loadController?.abort();
            loadController = new AbortController();
            list.classList.add('is-loading');
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'text/html' },
                    credentials: 'same-origin',
                    signal: loadController.signal,
                });
                const responseUrl = new URL(response.url);
                if (!response.ok || !isListUrl(responseUrl)) throw new Error('fallback');

                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                const fresh = doc.getElementById(listId);
                if (!fresh) throw new Error('fallback');

                list.replaceWith(fresh);
                if (push) history.pushState({ mapLayersList: true }, '', url.href);
                previewFirstRow();
            } catch (error) {
                if (error.name === 'AbortError') return;
                location.href = url.href;
            } finally {
                document.getElementById(listId)?.classList.remove('is-loading');
            }
        }

        document.addEventListener('click', event => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target.closest(`#${listId} a[href]`);
            if (!link || link.hasAttribute('download') || link.target === '_blank') return;
            const url = listUrl(link.href);
            if (!isListUrl(url)) return;
            event.preventDefault();
            loadList(url);
        });

        document.addEventListener('submit', event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.closest(`#${listId}`) || (form.method || 'get').toLowerCase() !== 'get') return;
            const url = listUrl(form.action || location.href);
            if (!isListUrl(url)) return;
            event.preventDefault();
            url.search = new URLSearchParams(new FormData(form)).toString();
            // Leave out empty fields, like a normal submit of this filter would show them.
            [...url.searchParams.entries()].filter(([, value]) => value === '').forEach(([key]) => url.searchParams.delete(key));
            loadList(url);
        });

        window.addEventListener('popstate', () => loadList(listUrl(location.href), { push: false }));
    })();
</script>
