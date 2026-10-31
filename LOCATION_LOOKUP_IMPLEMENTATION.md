# Location Lookup Implementation

## Overview

Delineation locations are resolved by Laravel from the Southern Leyte barangay boundary dataset. While drawing, the browser posts geometry to the authenticated location-preview endpoint and displays its response. Save runs the same resolver again; client-side reverse geocoding and approximate bounding-box fallbacks do not determine saved locations.

## Components

- `database/migrations/2026_10_18_000000_create_admin_boundaries_table.php` creates the indexed spatial lookup table. MySQL stores `MULTIPOLYGON` geometries in SRID 4326; non-MySQL test databases get a text column.
- `app/Console/Commands/ImportAdminBoundaries.php` imports the GeoJSON in one transaction and validates its CRS, Southern Leyte province code, required fields, geometry type, and polygon validity.
- `app/Services/BoundaryResolver.php` converts map coordinates from `[latitude, longitude]` to GeoJSON `[longitude, latitude]`, then resolves the feature against the imported boundaries.
- `app/Http/Controllers/EndUserController.php`, `app/Http/Controllers/ExpertController.php`, and `app/Http/Controllers/DelineationAnnualObservationController.php` call the resolver before saving feature geometry.
- `resources/views/users/partials/workspace-scripts.blade.php` displays the server result and no longer submits a browser-generated location as authoritative.

## Resolution Rules

- Points match the barangay containing the point. Points on a shared boundary use the lowest barangay code as a deterministic tie-break.
- Lines are ranked by intersecting length; areas are ranked by intersecting area. Measurements use projected CRS EPSG:32651.
- Positive-overlap matches are stored in each feature's `location_details.matches`; the dominant match supplies the display `location` and administrative codes.
- A feature outside the loaded boundaries receives `location_status: outside_boundaries`. If the database is not MySQL or the boundary table is empty, it is saved with `location_status: pending` and no guessed label.
- Invalid or zero-length/zero-area feature geometries are rejected.

## Importing the Boundaries

The source file is `storage/app/geo/southern_leyte_barangay_boundaries.geojson`. The importer expects GeoJSON `MultiPolygon` features with `adm4_*`, `adm3_*`, `adm2_*`, `version`, and optional `valid_on` properties.

After repairing the source geometry in QGIS, run:

```powershell
php artisan migrate --force
php artisan boundaries:import --path=storage/app/geo/southern_leyte_barangay_boundaries_fixed.geojson
```

If QGIS Check validity reports zero invalid features but MySQL's `ST_IsValid` disagrees, explicitly trust the verified QGIS result:

```powershell
php artisan boundaries:import --path=storage/app/geo/southern_leyte_barangay_boundaries_qgis_fixed.geojson --accept-qgis-valid
```

QGIS steps: run **Check validity** on the file and verify 500 valid, zero invalid, and zero error features. Export the verified QGIS output as GeoJSON in EPSG:4326. Keep the original dataset unchanged. The import is transactional; failed imports do not replace previously imported boundaries.

## Current Setup Status

- The migration has been applied to the configured local MySQL 8.0.39 database.
- GeoJSON axis-order handling and the MySQL spatial operations were smoke-tested. The resolver was also tested against temporary adjacent polygons inside a rolled-back transaction.
- MySQL 8.0.39 flags five records as invalid, but QGIS 3.44.12 GEOS Check validity reports all 500 features valid. The QGIS repair leaves those five geometries unchanged. Representative point/area queries work in MySQL, including a transactional area lookup for Tuba-on: `PH0806402007` (Catmon, Bontoc), `PH0806406018` (Poblacion, Liloan), `PH0806415005` (Esperanza, San Ricardo), `PH0806416014` (Tuba-on, Silago), and `PH0806416015` (Tubod, Silago).
- Imported `southern_leyte_barangay_boundaries_qgis_fixed.geojson` with `--accept-qgis-valid`. The table contains 500 rows and 500 unique barangay codes from dataset `v03`, valid on `2025-02-13`.
- Live resolver checks matched a known point to Amagusan, Anahawan and a sample area to Tuba-on, Silago.

## Verification

- `php artisan test tests/Unit` passes.
- PHP syntax checks pass for the changed PHP files, and `php artisan view:cache` succeeds.
- The full `php artisan test` suite has six failures because an existing migration issues MySQL-specific `ALTER TABLE ... MODIFY COLUMN` syntax against the SQLite test database. This is separate from the location resolver.
