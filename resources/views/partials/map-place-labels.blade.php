    {{-- Google-style "hybrid" labels for satellite basemaps. Inlined into a page <script> after Leaflet is loaded.
         Esri World Imagery has no text, so while any of the given imagery layers is on the map this adds:
           1. Esri's road overlay (roads and street names);
           2. OpenStreetMap place names (towns, villages, hamlets, sitios) from GET map.place-labels
              (synced with `php artisan map:sync-place-labels`). Like Google Maps, towns show first and smaller
              places appear as you zoom in; where two would overlap, the more important one is kept;
           3. Esri's place labels only when zoomed out past the OSM names (provinces, countries).
         Street and topographic basemaps already have their own labels, so all of this is removed for them.
         Labels sit above drone imagery and polygons but ignore clicks. --}}
    function attachPlaceLabels(map, imageryLayers) {
      const PLACE_LABELS_URL = @json(route('map.place-labels'));
      // OSM place rank (1 city, 2 town, 3 village, 4 hamlet/quarter, 5 neighbourhood/sitio) => first zoom shown.
      const RANK_MIN_ZOOM = { 1: 9, 2: 10, 3: 13, 4: 15, 5: 16 };
      const ESRI_PLACES_MAX_ZOOM = 9;
      const maxZoom = map.options.maxZoom || 22;

      if (!map.getPane('placeLabels')) {
        const pane = map.createPane('placeLabels');
        pane.style.zIndex = 450;
        pane.style.pointerEvents = 'none';
        const roadPane = map.createPane('placeRoads');
        roadPane.style.zIndex = 440;
        roadPane.style.pointerEvents = 'none';
      }
      if (!document.getElementById('placeLabelStyles')) {
        const style = document.createElement('style');
        style.id = 'placeLabelStyles';
        style.textContent = `
          .osm-place-label { background: none; border: 0; }
          .osm-place-label span {
            display: block; transform: translate(-50%, -50%); white-space: nowrap; color: #fff;
            font-family: Manrope, system-ui, -apple-system, 'Segoe UI', sans-serif;
            /* A thin dark halo keeps the text readable on imagery without making it look heavy. */
            text-shadow: 0 0 2px rgba(0, 0, 0, 0.85), 0 0 3px rgba(0, 0, 0, 0.5);
          }
          .osm-place-label.rank-1 span, .osm-place-label.rank-2 span { font-size: 14px; font-weight: 500; }
          .osm-place-label.rank-3 span { font-size: 10px; font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; }
          .osm-place-label.rank-4 span, .osm-place-label.rank-5 span {
            font-size: 9.5px; font-weight: 400; letter-spacing: 0.05em; text-transform: uppercase; color: rgba(255, 255, 255, 0.92);
          }`;
        document.head.appendChild(style);
      }

      const roads = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', {
        pane: 'placeRoads',
        maxZoom,
        maxNativeZoom: 19,
      });
      const esriPlaces = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        pane: 'placeLabels',
        maxZoom: ESRI_PLACES_MAX_ZOOM,
        attribution: 'Roads &copy; Esri; place names &copy; OpenStreetMap contributors',
      });

      const placeGroup = L.layerGroup();
      const placeMarkers = new Map();
      let places = [];

      // One request per page, shared by every map that shows labels.
      window.__placeLabelsRequest = window.__placeLabelsRequest || fetch(PLACE_LABELS_URL, { headers: { Accept: 'application/json' } })
        .then(response => (response.ok ? response.json() : { places: [] }))
        .catch(() => ({ places: [] }));
      window.__placeLabelsRequest.then(data => {
        places = Array.isArray(data?.places) ? data.places : [];
        placeOsmLabels();
      });

      const imageryShown = () => imageryLayers.some(layer => map.hasLayer(layer));

      function placeMarker(place) {
        let marker = placeMarkers.get(place);
        if (!marker) {
          const text = document.createElement('span');
          text.textContent = place.name;
          marker = L.marker([place.lat, place.lng], {
            pane: 'placeLabels',
            interactive: false,
            keyboard: false,
            icon: L.divIcon({ className: `osm-place-label rank-${place.rank}`, html: text.outerHTML, iconSize: [0, 0] }),
          });
          placeMarkers.set(place, marker);
        }
        return marker;
      }

      // Show the names that fit. Places arrive most important first; one that would overlap an already
      // placed label is skipped, so more names appear as you zoom in.
      function placeOsmLabels() {
        // Some maps attach labels before their first setView/fitBounds; placement waits for whenReady.
        if (!map._loaded) return;
        const zoom = map.getZoom();
        if (!imageryShown() || !places.length) {
          placeGroup.clearLayers();
          return;
        }
        const bounds = map.getBounds().pad(0.25);
        const placed = [];
        const visible = new Set();
        for (const place of places) {
          if (zoom < (RANK_MIN_ZOOM[place.rank] ?? 16)) continue;
          if (!bounds.contains([place.lat, place.lng])) continue;
          const point = map.latLngToContainerPoint([place.lat, place.lng]);
          // Rough text width per character at each label size (used only to stop labels overlapping).
          const charWidth = place.rank <= 2 ? 8.2 : (place.rank === 3 ? 6.9 : 6.5);
          const halfWidth = (place.name.length * charWidth + 12) / 2;
          const box = { left: point.x - halfWidth, right: point.x + halfWidth, top: point.y - 12, bottom: point.y + 12 };
          if (placed.some(other => box.left < other.right && box.right > other.left && box.top < other.bottom && box.bottom > other.top)) continue;
          placed.push(box);
          visible.add(placeMarker(place));
        }
        placeGroup.eachLayer(marker => {
          if (!visible.has(marker)) placeGroup.removeLayer(marker);
        });
        visible.forEach(marker => {
          if (!placeGroup.hasLayer(marker)) placeGroup.addLayer(marker);
        });
      }

      const overlays = [roads, esriPlaces, placeGroup];
      const sync = () => {
        const show = imageryShown();
        overlays.forEach(layer => {
          if (show && !map.hasLayer(layer)) layer.addTo(map);
          if (!show && map.hasLayer(layer)) map.removeLayer(layer);
        });
        placeOsmLabels();
      };
      imageryLayers.forEach(layer => layer.on('add remove', sync));
      map.on('zoomend moveend', placeOsmLabels);
      map.whenReady(placeOsmLabels);
      sync();
      return esriPlaces;
    }
