# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

MangroveMap is a Laravel 13 (PHP 8.3) app for mapping mangrove areas (Southern Leyte, PH). Users draw delineation polygons on a Leaflet map, experts review them, and drone orthomosaics are tiled with GDAL and segmented with a PyTorch U-Net. It runs locally under Laragon on Windows, with MySQL in `.env`. Tests use in-memory SQLite.

## Commands

```powershell
composer dev                      # serve + queue:listen + pail logs + vite, concurrently
npm run dev / npm run build       # Vite + Tailwind 4
composer test                     # config:clear, then php artisan test
php artisan test --filter=DroneImageryUploadTest          # single test class
php artisan test --filter=test_method_name                # single test method
php artisan test tests/Unit/BoundaryResolverTest.php      # single file
vendor/bin/pint                   # format (Laravel Pint)

# Drone imagery needs its own worker (composer dev's queue:listen does NOT cover it)
php artisan queue:work drone_imagery --queue=drone-imagery --timeout=7200 --tries=1
# queue:work keeps the code it started with: restart this worker after changing jobs/services it runs

php artisan boundaries:import --path=<geojson>   # load barangay boundaries into admin_boundaries
php artisan delineations:repair-features --dry-run
php artisan delineations:resolve-locations --dry-run [--id=N]   # fill in barangays for areas not matched yet
php artisan map:sync-place-labels                # re-download OpenStreetMap place names for the satellite map labels
```

ML environment: `ml/.venv` (from `ml/requirements.txt`). `config/ml.php` automatically uses `ml/.venv/Scripts/python.exe` if it exists. Otherwise set `ML_PYTHON`.

## Architecture

### Roles and routing
`users.role` is `end_user` (resident), `expert`, or `admin`. Route groups in `routes/web.php` use the `end_user`, `expert`, and `admin` middleware aliases (`app/Http/Middleware`). `User::homeRoute()` decides where each role lands after login or verification. Shared map/delineate pages (`EndUserController::map/delineate`) serve all authenticated users. Experts get their own variants under `/expert/*` in `ExpertController`.

### Delineation lifecycle
`Delineation` stores GeoJSON in the `features` JSON column. Review state is tracked by the `is_approved`/`is_rejected` flags (there is no status enum). The flow is:
1. A resident submits a delineation. `DelineationSubmittedForReview` notifies experts.
2. An expert approves or rejects it. A resident can revise a rejected record (`delineations.revise`), and revisions are kept in `DelineationRevision`. The resident has `Delineation::REJECTED_REMOVAL_GRACE_DAYS` (7) from `rejected_at` to do so. After that an expert may delete it. Experts can delete approved and pending delineations at any time (`expert.delineations.destroy` → `ExpertController::destroyDelineation`; `DelineationRemoved` notifies the owner unless it is the expert's own). The rejection notice, map panel and dashboard show the deadline (`removable_after` in the map payload).
3. Experts can also add yearly observations and planting recommendations (`PlantingRecommendation`). There is no separate observation form. When an expert saves a boundary edit (`delineations.boundary.update`) with `record_as=observation`, `DelineationObservationRecorder` creates or updates that year's `DelineationAnnualObservation`, with hectares computed from the polygon, genera taken from the features, and a boundary revision. The first observation also stores a baseline year from the original boundary. A `record_as=correction` edit (the default) only adds a `DelineationRevision`. `DelineationAnnualObservationController::index` is read-only and feeds the map's "Changes over time" timeline.

Deleting a delineation also removes its review notifications (a model `deleting` hook). Map queries are capped by `Delineation::MAP_QUERY_LIMIT*`.

`BoundaryResolver` reverse-geocodes polygons to barangay/municipality using MySQL spatial functions against `admin_boundaries`. On SQLite, or when the table is empty, it skips the lookup and does nothing. Tests therefore run without spatial data.

### Drone imagery pipeline
1. The Drone Upload page uploads through `DroneImageryUploadController` in chunks: `start` creates a session in `drone-imagery/incoming/{uuid}`, the browser PUTs 8 MB slices (`gdal.upload_chunk_mb`) four at a time (`gdal.upload_parallel`) with retries, each written straight to its offset in a `.part` file, and `complete` moves the parts into place. The session id is kept in localStorage, so picking the same files again resumes. Abandoned sessions are removed after 24 h (`drone-imagery:prune-uploads`, scheduled daily). `DroneImageryController::store` (one multipart request) still works. Both create a `DroneImageryLayer` plus its `DroneImageryFile` rows and call `queueProcessing`.
2. `ProcessDroneImagery` runs on the `drone_imagery` connection and `drone-imagery` queue. It runs GDAL through `GdalCommandRunner`, which writes a `.bat` file that first calls the QGIS `o4w_env.bat` (`GDAL_QGIS_ENV`). This step builds the mosaic and XYZ tiles. The max zoom is chosen automatically from the raster's native resolution (see `config/gdal.php`).
3. If `ML_DRONE_AUTO_SEGMENT` is on, it dispatches `SegmentDroneImagery`. That job resamples the mosaic and calls `MangroveSegmentationService::predictRaster`, which runs `ml/predict.py --raster` tile by tile. `SegmentationFeatureBuilder` then turns the mask into simplified GeoJSON polygons, which are offered as a delineation draft.
4. Review and publication: the owner corrects the mangrove areas in the review editor, then confirms. The first confirm also requires the genus (`delineation_details`). When `FinalizeDroneImagery` has tiled the layer, `DroneImageryDelineationPublisher` turns those areas into a `Delineation` linked by `drone_imagery_layer_id`. A resident's delineation starts pending and experts are notified; an expert's is approved immediately. A layer is `published()` once it is ready and either its owner is an expert or its delineation is approved. Until then, only the owner and experts can see it (`awaitingExpertReview()`). The map shows `auto_show` layers without anyone ticking them.
5. Tiles and segmentation are served only through owner-authorized routes (`drone-imagery.tile`, `.segmentation.show`). Nothing is public via the storage symlink. Users can hide layers (`drone_imagery_layer_hidden_users`).

Tile Explorer (sidebar, `TileExplorerController`): users upload a single GeoTIFF only to cut it into tiles for dataset work (e.g. Roboflow). It never becomes a drone layer, map layer or delineation, and is private to the uploader. The upload reuses the chunked uploader (`DroneImageryUploadController` with `purpose=tiles` and `tile_size`; the browser client is `partials/chunked-upload`). It creates a `TileSource` (`tile-explorer/{id}/source.tif`, kept as uploaded) and a `TileSet` per tile size. `GenerateTiles` (drone imagery worker/queue) validates it (8-bit RGB with a CRS). On the first run per source it detects mangrove areas with `MangroveAreaDetector`, the U-Net via `predictRaster` on a working copy warped to EPSG:3857, saved as `areas.geojson`; if this fails, tiles are still made, unlabelled. It then runs `scripts/gdal/make_tiles.py` on the original file in its own CRS. Per tile that writes a GeoTIFF, a JPEG plus `.wld`/`.aux.xml`, and a thumbnail, named by id `r<row>_c<col>` under `tiles/{size}/`, plus `tiles.json` (mangrove % per tile and an SVG outline). Fully empty tiles are skipped. The ZIP download streams with `maennchen/zipstream-php` (no ext-zip, no temp file) and sets the unencrypted `tile_download_token` cookie so the page knows the download started.

Keep `GDAL_QUEUE_RETRY_AFTER` (7260) above the job timeout (7200). Above that, `GDAL_PROCESS_TIMEOUT` (7000) caps each GDAL command.

### ML integration
`MangroveSegmentationService` shells out to `ml/predict.py`. The script has three modes, `--image`, `--raster`, and `--validate-weights`, and prints JSON as its last stdout line. The weights come from the newest active `AIModel` (`model_type=segmentation`, a `.pth` file that exists on disk). If there is none, it falls back to `ML_MODEL_PATH`. Admins upload new models via `AdminController::uploadModel`, which validates them first. Under Apache on Windows, the service and the script both patch `SystemRoot`/`PATH`. Without that, importing torch fails with WinError 10106. Keep those workarounds.

Genus suggestion: `ml/predict_genus.py` runs an Ultralytics YOLO segmentation model (classes = genera) over the same resampled raster, counting only pixels inside the U-Net mask. `SegmentDroneImagery` stores the result in `segmentation_stats.genus_suggestion` (null when no weights, `{error}` on failure, which never fails the extent detection). The review editor shows it as one clickable list ("AI genus suggestion": a probability for every genus, tagged Confident/Low confidence) and never selects a genus by itself. The job also keeps a per-pixel genus map (`genus_labels.png` + `.json` in the layer's processed folder); when the owner saves edited areas, `updateSegmentation` recounts the suggestion from it (`predict_genus.py --recount`, no model run, under a second). While editing, the review editor also previews it live: 0.8 s after each area change it posts the unsaved areas to `drone-imagery.segmentation.genus-preview`, which recounts without saving. Layers processed before the map existed keep their original suggestion until the AI is re-run. Weights come from the newest active `AIModel` with `model_type=classification` (a `.pt`, uploaded by admins), else `ML_GENUS_MODEL_PATH` (`ml/models/mangrove_classifier.pt`). Settings are in `config/ml.php` under `genus`.

### Frontend
Blade views with Leaflet 1.9.4 from a CDN. Satellite basemaps get Google-hybrid-style labels from `partials/map-place-labels` (`attachPlaceLabels(map, imageryLayers)`): Esri roads plus OpenStreetMap place names from `OsmPlaceLabelService` (Overpass, stored in `storage/app/private/map/`, bbox in `config/map.php`, decluttered client-side by rank). Most map code is inline JS in Blade partials under `resources/views/users/partials/` (`workspace-*`, `map-base-scripts`, `recommendation-scripts`), not in `resources/js`. Layouts: `layouts/enduser`, `layouts/admin`, `layouts/app`.

Dark mode: `partials/theme` (included first in every page `<head>`) sets `<html data-theme="light|dark">` from localStorage or the OS setting, and `partials/theme-toggle` is the header button. View CSS colors are written as `var(--bg-xxxxxx, #xxxxxx)` (prefix `bg`/`fg`/`bd` = role); light mode uses the fallback and the dark values live in the generated `partials/theme-palette`. After adding styles with literal colors, run `node scripts/theme/build-dark-theme.mjs` (`--check` lists views that still need it). It skips `<script>` blocks and `--custom-properties`, so colors in JS-built HTML or page-level CSS variables need a dark value by hand (see the `html[data-theme="dark"]` block in `index.blade.php`).

## Testing notes
- `phpunit.xml` sets SQLite `:memory:`, `QUEUE_CONNECTION=sync`, and array mail/cache/session.
- `ProcessDroneImageryTest` runs real GDAL through the QGIS install. If `gdal.qgis_env` doesn't exist, it skips itself.
- Segmentation tests write segmentation output directly and use `Queue::fake()`, so Python isn't needed.
