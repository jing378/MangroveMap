@extends('layouts.enduser')

@section('title', 'Map - MangroveMap')

@section('content')
<div style="height: 100%; display: flex; flex-direction: column;">
    <!-- Map Header -->
    <div style="padding: 24px; border-bottom: 1px solid #e0e8e0; background: #fff;">
        <h1 style="font-size: 24px; font-weight: 700; color: #1a2e1a; margin-bottom: 8px;">Mangrove Distribution Map</h1>
        <p style="color: #7a9a7a; font-size: 13px;">Explore mangrove coverage areas and health status</p>
    </div>

    <!-- Map Container -->
    <div id="map" style="flex: 1; background: #f0f0f0;"></div>
</div>

<!-- Mapbox CSS -->
<link href='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' rel='stylesheet' />

<style>
    #map {
        position: relative;
    }

    .mapboxgl-popup {
        max-width: 300px;
        font-family: 'Manrope', sans-serif;
    }

    .popup-content {
        padding: 12px;
    }

    .popup-content h3 {
        font-size: 14px;
        font-weight: 700;
        color: #1a2e1a;
        margin-bottom: 8px;
    }

    .popup-content p {
        font-size: 12px;
        color: #7a9a7a;
        margin: 4px 0;
    }

    .popup-content .label {
        font-weight: 600;
        color: #1a2e1a;
    }
</style>

<script src='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js'></script>
<script>
    // Initialize map
    mapboxgl.accessToken = '{{ $mapboxToken }}';

    const map = new mapboxgl.Map({
        container: 'map',
        style: 'mapbox://styles/mapbox/light-v11',
        center: [125.00, 10.25],
        zoom: 8
    });

    // Load mangrove data from API
    function loadMangroveData(year = new Date().getFullYear()) {
        fetch(`/api/map/data?year=${year}`)
            .then(response => response.json())
            .then(data => {
                // Remove existing source and layer if they exist
                if (map.getSource('mangrove-data')) {
                    map.removeLayer('mangrove-data');
                    map.removeSource('mangrove-data');
                }

                // Add source
                map.addSource('mangrove-data', {
                    type: 'geojson',
                    data: data
                });

                // Add layer with color based on health status
                map.addLayer({
                    id: 'mangrove-data',
                    type: 'circle',
                    source: 'mangrove-data',
                    paint: {
                        'circle-radius': [
                            'interpolate',
                            ['linear'],
                            ['get', 'coverage'],
                            0, 4,
                            100, 15
                        ],
                        'circle-color': [
                            'match',
                            ['get', 'health'],
                            'healthy', '#16a34a',
                            'degraded', '#dc2626',
                            'recovering', '#f59e0b',
                            '#1e9e62'
                        ],
                        'circle-opacity': 0.7,
                        'circle-stroke-width': 2,
                        'circle-stroke-color': '#fff'
                    }
                });

                // Add popups on click
                map.on('click', 'mangrove-data', function(e) {
                    const properties = e.features[0].properties;
                    const popup = new mapboxgl.Popup()
                        .setLngLat(e.lngLat)
                        .setHTML(`
                            <div class="popup-content">
                                <h3>${properties.region}</h3>
                                <p><span class="label">Coverage:</span> ${properties.coverage} km²</p>
                                <p><span class="label">Health:</span> ${properties.health.charAt(0).toUpperCase() + properties.health.slice(1)}</p>
                                <p><span class="label">Date:</span> ${properties.date}</p>
                            </div>
                        `)
                        .addTo(map);
                });

                // Change cursor to pointer on hover
                map.on('mouseenter', 'mangrove-data', function() {
                    map.getCanvas().style.cursor = 'pointer';
                });

                map.on('mouseleave', 'mangrove-data', function() {
                    map.getCanvas().style.cursor = '';
                });
            })
            .catch(error => console.error('Error loading map data:', error));
    }

    // Load data when map is ready
    map.on('load', function() {
        loadMangroveData();

        // Add year selector if needed
        const yearControl = document.createElement('div');
        yearControl.className = 'mapboxgl-ctrl mapboxgl-ctrl-group';
        yearControl.innerHTML = `
            <select id="yearSelect" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                <option value="2023">2023</option>
                <option value="2024">2024</option>
                <option value="2025">2025</option>
            </select>
        `;
        map.addControl({
            onAdd: function() {
                this._container = yearControl;
                return yearControl;
            },
            onRemove: function() {
                this._container.parentNode.removeChild(this._container);
            }
        });

        document.getElementById('yearSelect').addEventListener('change', function(e) {
            loadMangroveData(e.target.value);
        });

        // Add legend
        const legend = document.createElement('div');
        legend.style.cssText = 'position: absolute; bottom: 20px; left: 20px; background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); font-family: "Manrope", sans-serif; font-size: 12px; z-index: 10;';
        legend.innerHTML = `
            <h4 style="margin: 0 0 12px 0; font-weight: 700; color: #1a2e1a;">Health Status</h4>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #16a34a;"></div>
                <span>Healthy</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #f59e0b;"></div>
                <span>Recovering</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: #dc2626;"></div>
                <span>Degraded</span>
            </div>
        `;
        document.getElementById('map').appendChild(legend);
    });
</script>

@endsection