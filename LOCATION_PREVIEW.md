# Live Location Preview

## Behavior

The map previews a delineation's location before it is saved. It sends the selected feature's geometry to Laravel, which runs the same `BoundaryResolver` used by the save handlers. The browser displays the server result; it does not reverse-geocode or classify the location itself.

The preview runs when a feature is selected after drawing and after a vertex drag ends. For a line/area outline, finish and close the shape first; an incomplete sketch does not have a preview yet. Point features preview immediately after placement.

## Request Flow

1. `resources/views/users/partials/workspace-scripts.blade.php` posts `{ feature: { type, coords } }` to `POST /delineations/location-preview` with the CSRF token.
2. The authenticated, throttled route in `routes/web.php` calls `EndUserController::previewDelineationLocation`.
3. The controller validates the feature and calls `BoundaryResolver`.
4. The JSON response includes the resolved feature. The map displays its location/status and fills the zone name only if the name field is still blank.
5. Save resolves the geometry again, so the saved result remains authoritative even if the preview was stale or unavailable.

## Display States

- `matched`: show the barangay, municipality, and province.
- `outside_boundaries`: show that the feature is outside the loaded barangay boundaries.
- `pending`: the boundary table is empty or the database cannot run the spatial lookup.
- Request/validation failure: show `Location preview unavailable`; the user can still save if the save request is valid.

When selecting another feature, outstanding preview responses are ignored so they cannot overwrite the current feature's display.

## Prerequisites

The MySQL `admin_boundaries` table must be populated. It currently contains 500 Southern Leyte barangays from dataset `v03`, valid on `2025-02-13`. See [LOCATION_LOOKUP_IMPLEMENTATION.md](LOCATION_LOOKUP_IMPLEMENTATION.md) for importing and verifying the boundary data.

## Verification

- `php artisan route:list --name=delineations.location-preview` should list the preview route.
- `php artisan test tests/Unit` exercises the resolver's coordinate conversion and polygon handling.
- A known point at latitude `10.26302569`, longitude `125.24250919` previews as `Amagusan, Anahawan, Southern Leyte` when the boundary table is loaded.
