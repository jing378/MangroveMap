# Delineation Location Resolution

Locations are assigned by the Laravel server from the geometry and the Southern Leyte barangay boundary dataset. When a shape is completed or edited, the browser requests a preview from the server and displays the result before saving. Save runs the same resolver again, so the browser does not choose the saved location.

The preview uses the authenticated `POST /delineations/location-preview` route. It returns a location status and, when matched, the barangay, municipality, province, dataset version, and overlap details.

## Dataset

The importer reads `storage/app/geo/southern_leyte_barangay_boundaries.geojson`. It expects GeoJSON `MultiPolygon` features with these properties:

- `adm4_name` and `adm4_pcode`: barangay
- `adm3_name` and `adm3_pcode`: municipality
- `adm2_name` and `adm2_pcode`: province
- `version` and `valid_on`: dataset provenance

The supplied dataset identifies itself as version `v03`, valid on `2025-02-13`. GeoJSON coordinates are longitude, latitude; map features are stored latitude, longitude and are converted by `BoundaryResolver`.

## Import

Run the migration and importer after the GeoJSON has been checked:

```powershell
php artisan migrate --force
php artisan boundaries:import --path=storage/app/geo/southern_leyte_barangay_boundaries_qgis_fixed.geojson --accept-qgis-valid
```

To import a repaired export without replacing the original source file:

```powershell
php artisan boundaries:import --path=storage/app/geo/southern_leyte_barangay_boundaries_fixed.geojson
```

The importer validates every polygon and runs in a transaction. Any invalid geometry or missing required field aborts the import and preserves previously imported rows.

The current dataset has five polygons MySQL flags invalid even though QGIS **Check validity** reports zero invalid features. After verifying the file in QGIS, `--accept-qgis-valid` explicitly trusts the QGIS GEOS result for those geometries.

## Matching Rules

- Points are assigned to a containing barangay. A point exactly on a shared boundary is assigned to the lowest barangay code for deterministic results.
- Lines are assigned by greatest overlapping length.
- Areas are assigned by greatest overlapping area.
- All intersecting boundaries with positive overlap are saved in `location_details.matches`; the dominant boundary supplies `location`.
- Features with no match get `location_status: outside_boundaries`. Invalid or zero-length/zero-area geometries are rejected.

Length and area are measured in projected CRS EPSG:32651. Names, codes, overlap percentages, dataset version, and validity date are persisted with the feature in the existing JSON column.

## Geometry Engine Compatibility

MySQL 8.0.39 reports five polygons invalid, while QGIS 3.44.12 Check validity reports all 500 features valid. QGIS Fix geometries leaves those shapes unchanged. The verified QGIS output was imported with the explicit `--accept-qgis-valid` option. Representative MySQL containment and overlap operations succeeded on all five; Tuba-on also passed an area-resolution test.

The imported table contains 500 unique barangay codes from dataset `v03`, valid on `2025-02-13`. A live resolver check matched a known point to Amagusan, Anahawan and a sample area to Tuba-on, Silago.

The assigned barangay is exact relative to this boundary dataset and its version; it does not claim greater accuracy than the source boundaries.
