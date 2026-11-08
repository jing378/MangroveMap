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
            const features = JSON.parse(button.dataset.features || '[]');
            const style = 'rejected' in button.dataset ? rejectedDelineationStyle : delineationStyle;
            fitTo(drawFeatures(features, style).getBounds());
        }

        function previewImagery(button) {
            const b = JSON.parse(button.dataset.bounds || 'null');
            if (!b) return;

            const bounds = L.latLngBounds([b.south, b.west], [b.north, b.east]);
            L.tileLayer(button.dataset.tileUrl, {
                bounds,
                minNativeZoom: Number(button.dataset.zoomMin) || 0,
                maxNativeZoom: Number(button.dataset.zoomMax) || MAP_MAX_ZOOM,
                maxZoom: MAP_MAX_ZOOM,
                updateWhenZooming: false,
                updateWhenIdle: true,
            }).addTo(previewGroup);
            fitTo(bounds);

            if (button.dataset.segmentationUrl) {
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
                    previewLabel.textContent = button.dataset.name + (button.dataset.segmentationUrl ? ' · AI-detected extent in yellow' : '');
                    previewImagery(button);
                } else {
                    previewLabel.textContent = button.dataset.name + ('rejected' in button.dataset ? ' · Rejected, shown in red' : '');
                    previewDelineation(button);
                }

                if (reveal) revealPreviewMap();
        }

        document.querySelectorAll('[data-preview]').forEach(button => {
            button.addEventListener('click', () => showPreview(button));
        });

        // Preview the first previewable row so the map is never empty on load (without scrolling the page).
        const firstPreview = document.querySelector('[data-preview]:not(:disabled)');
        if (firstPreview) showPreview(firstPreview, { reveal: false });

        // The map sits in a card that can change width (sidebar, rotation); keep its tiles filling it.
        window.addEventListener('resize', () => previewMap.invalidateSize());

        // ---- Dialogs ----
        function openDialog(id, action, fill) {
            const dialog = document.getElementById(id);
            const form = dialog.querySelector('form');
            form.action = action;
            fill(form);
            dialog.showModal();
        }

        document.querySelectorAll('[data-edit-delineation]').forEach(button => {
            button.addEventListener('click', () => openDialog('editDelineationDialog', button.dataset.url, () => {
                document.getElementById('editDelineationName').value = button.dataset.name || '';
                document.getElementById('editDelineationNotes').value = button.dataset.notes || '';
            }));
        });

        document.querySelectorAll('[data-hide-delineation]').forEach(button => {
            button.addEventListener('click', () => openDialog('hideDelineationDialog', button.dataset.url, () => {
                document.getElementById('hideDelineationName').textContent = button.dataset.name || '';
                document.getElementById('hideDelineationReason').value = '';
            }));
        });

        document.querySelectorAll('[data-rename-imagery]').forEach(button => {
            button.addEventListener('click', () => openDialog('renameImageryDialog', button.dataset.url, () => {
                document.getElementById('renameImageryName').value = button.dataset.name || '';
            }));
        });

        document.querySelectorAll('[data-close-dialog]').forEach(button => {
            button.addEventListener('click', () => button.closest('dialog').close());
        });

        // Tapping the dimmed area outside a dialog closes it, like the other admin modals.
        document.querySelectorAll('dialog.layer-dialog').forEach(dialog => {
            dialog.addEventListener('click', event => {
                if (event.target === dialog) dialog.close();
            });
        });
        // Forms with data-confirm / data-busy-label are handled by partials.action-feedback.
    })();
</script>
