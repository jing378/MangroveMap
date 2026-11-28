# MangroveMap API reference

MangroveMap's pages call a set of JSON endpoints to save delineations, review them, upload drone imagery, refresh the map and show live notifications. This document lists those endpoints: what each one does, who may call it, what it expects and what it returns.

All routes are defined in `routes/web.php`. There is no separate `/api` prefix and no token API: the endpoints are meant for MangroveMap's own pages.

## Conventions

| Topic | How it works |
|---|---|
| Authentication | The Laravel session cookie from logging in. Almost every endpoint needs a logged-in user with a verified email. |
| CSRF | Every `POST`, `PUT` and `DELETE` sends the `X-CSRF-TOKEN` header, read from `<meta name="csrf-token">`. |
| Format | Send `Content-Type: application/json` (or `multipart/form-data` for file uploads) and `Accept: application/json`. |
| Methods | `GET` reads, `POST` creates or runs an action, `PUT` updates, `DELETE` removes. |
| Roles | `resident` (`end_user`), `expert`, `admin`. Expert routes start with `/expert`, admin routes with `/admin`. Roles are checked on the server for every request. |
| Coordinates | `[latitude, longitude]` pairs in WGS 84, for example `[10.39, 124.98]`. |

### Status codes

| Code | Meaning |
|---|---|
| `200` | OK |
| `201` | Created |
| `202` | Accepted: the work was queued and runs in the background |
| `401` | Not logged in, or the session expired |
| `403` | Logged in, but this role or user may not do this |
| `404` | Not found, or not visible to this user |
| `409` | Not allowed in the record's current state, for example revising a delineation that isn't rejected |
| `419` | The CSRF token expired. Reload the page. |
| `422` | Validation failed. The body is `{"message": "...", "errors": {"field": ["..."]}}`. |
| `429` | Too many requests. See the rate limits below. |
| `503` | A background service (the queue or processing) is unavailable |

Error bodies always include a human-readable `message`.

### Rate limits

Limits are per user (or per IP for guests), per minute, and each endpoint counts on its own (the route name is the throttle prefix, e.g. `throttle:20,1,drone-imagery.uploads.complete`; a plain `throttle:N,1` would share one counter with every other route). Going over returns `429` with a `message` and a `Retry-After` header (JSON requests), or sends a form back with that message as an error. The chunked uploader waits for `Retry-After` and tries again.

| Endpoint | Limit |
|---|---|
| `GET /notifications/poll` | 30 |
| `GET /map/delineations` | 120 |
| `POST /delineations/location-preview` | 60 |
| `PUT /drone-imagery/uploads/{id}/files/{f}/chunks/{c}` | 3000 |
| `POST /drone-imagery/uploads`, `.../complete` | 20 |
| `POST /drone-imagery`, `.../segmentation`, `.../confirm` | 10 |
| `POST /drone-imagery/{id}/segmentation/genus-preview` | 120 |
| `PUT /drone-imagery/{id}/segmentation`, `/edits`, `/canopies` | 60 |
| `POST /push-subscriptions`, `DELETE /push-subscriptions` | 20 |
| `POST /feedback` | 10 |
| `POST /onboarding/complete` | 20 |
| `GET /register/check-email` | 30 |
| `POST /profile/expert-application` | 5 |
| `GET /map/mangrove-history`, `GET /map/place-labels` | 60 |
| `GET /dashboard/field-conditions` | 30 |
| `POST /login` | 30 per IP across all emails, plus 5 wrong passwords per email and IP (then a 1-minute wait) |
| `POST /register` | 10 per IP |
| `POST /forgot-password` | 5 per IP (and one reset email per address per 60 seconds) |
| `POST /reset-password` | 10 per IP |
| `POST /tile-explorer/sources/{id}/sets`, `.../download` | 20 |
| `POST /tile-explorer/sets/{id}/drone-imagery` | 10 |
| `POST /tile-explorer/sources/{id}/detect` | 10 |

## Map data

### `GET /map/delineations`
The delineations the map draws for the current user. The map calls it after every save, edit, review or removal, from the refresh button, and on live updates.

- **Who:** any logged-in user.
- **Returns `200`:**
  ```json
  {
    "delineations": [ /* the user's own, newest first, at most 80 */ ],
    "approvedDelineationsForMap": [ /* approved delineations of others, at most 150 */ ],
    "residentDelineationsForMap": [ /* experts only: residents' delineations, pending first, at most 150 */ ]
  }
  ```
  Each delineation includes `id`, `name`, `notes`, `features`, `is_approved`, `is_rejected`, `rejection_notes`, `user_name`, `approved_by_name`, `municipality`, `planting_recommendations`, `last_revised_by_name`, `last_revised_at`, `removable_after`, `reassesses` (`{id, name}` of the area a drone re-survey reassesses, else `null`) and `genus_canopy` (areas drawn from a drone survey whose genus classification is ready: `{genera: [{genus, percent}], recognised_percent, canopy_count}`, each genus's share of the canopy the AI recognised, largest first; else `null`). Approved re-surveys are left out: their boundary is on the area they updated.

### `GET /delineations/{delineation}/annual-observations`
The "Changes over time" timeline for one delineation: one entry per observed year, with `year`, `coverageAreaHa`, `source` and the boundary `features`.

`previousData` is the earlier satellite record of the same place: `{source, sourceUrl, license, areaHa, years: [{year, mangroveHa}]}`, the hectares Global Mangrove Watch saw as mangrove inside the area's current boundary in each year from 1985 (`areaHa` is the boundary's own area). `null` when the satellite data isn't installed.

`mapped` is MangroveMap's own figure for the area, which the timeline draws after the previous data as a separate series: `{since, areaHa, approved}`, the year it was added, its current boundary's hectares, and whether it is expert-approved.

### `GET /map/place-labels`
OpenStreetMap place names for the satellite map labels: `{"places": [...]}`. Cached by browsers for 24 hours.

### `GET /map/mangrove-history`
Satellite mangrove extent by year (Global Mangrove Watch). Gzipped when the browser accepts it, cached for 24 hours. Returns `404` when the data isn't installed.

### `GET /dashboard/field-conditions?lat=&lng=`
Weather forecast for the dashboard's Field Conditions card (resident, expert and admin dashboards). Returns an HTML fragment, not JSON. `lat` and `lng` are optional; both or neither must be sent.

## Delineations

A delineation's `features` is an array of up to 20 shapes:

```json
{ "type": "area", "coords": [[10.39, 124.98], [10.39, 124.99], [10.40, 124.99]], "genera": ["Rhizophora"] }
```

`type` is `point`, `line` or `area`. `genera` lists the mangrove genera present.

### `POST /delineations`: submit a delineation (resident)
- **Body:** `name` (required), `notes` (required), `features` (required, 1–20), `genera` (optional, used for features without their own).
- **Returns `200`:** `{"message": "Delineation saved and submitted for expert review.", "delineation": {...}}`. Experts are notified.

### `POST /expert/delineations`: add a delineation (expert)
Same body as above; `name` and `notes` are optional. The delineation is approved immediately.

### `POST /delineations/location-preview`
Looks up the barangay and municipality of a shape before it is saved.
- **Body:** `{"feature": {"type": "area", "coords": [...]}}`
- **Returns `200`:** `{"feature": {..., "location": "...", "location_status": "matched" | "pending" | "outside_boundaries"}}`. `pending` means the barangay boundaries aren't loaded yet.

### `PUT /delineations/{delineation}/boundary`: edit a boundary
- **Who:** the owner while it is pending, or an expert.
- **Body:** `features` (required). Experts may add `record_as`: `correction` (default) or `observation`. An observation also needs `observation_date` (2000-01-01 to today) and `observation_source` (`field_survey`, `drone_imagery` or `satellite_imagery`). It creates or updates that year's observation.
- **Errors:** `403` if not allowed, `422` for invalid shapes.

### `PUT /delineations/{delineation}/revision`: revise a rejected delineation
- **Who:** the owner, while it is rejected. Seven days after the rejection an expert may remove it, so revise before then.
- **Body:** `name`, `notes`, `features` (all required).
- **Errors:** `403` if not the owner, `409` if it isn't rejected.
- The delineation goes back to pending and experts are notified.

### `DELETE /delineations/{delineation}`: delete (resident)
- **Who:** the owner. Approved delineations can't be deleted (`422`).

### `POST /expert/delineations/{delineation}/approve`
- **Who:** expert. **Body:** `planting_recommendation` (optional, up to 5000 characters).
- **Returns `200`:** `{"message": "...", "unreadCount": 0, "reassessed_delineation_id": null}`. The owner is notified. For a drone re-survey, its boundary is copied onto the area it reassesses (a revision plus that year's observation) and `reassessed_delineation_id` is that area's id.

### `POST /expert/delineations/{delineation}/reject`
- **Who:** expert. **Body:** `rejection_notes` (required, 10–2000 characters).
- **Returns `200`:** `{"message": "...", "removable_after": "2026-11-24T..."}`. The owner is notified and has 7 days to revise.

### `DELETE /expert/delineations/{delineation}`
- **Who:** expert. Approved and pending delineations can be removed at any time. Rejected ones only after the 7-day revision window; before that the response is `422` with the date.
- The owner is notified unless it is the expert's own.

## Planting recommendations (experts)

### `POST /expert/recommendations` · `PUT /expert/recommendations/{recommendation}`
- **Body:**
  - `name` (required)
  - `features`: 1–20 areas, each with at least 3 points
  - `suitability`: `highly_suitable`, `moderately_suitable`, `marginally_suitable` or `not_suitable`
  - `recommended_genera`: required unless `not_suitable`
  - `recommendation` and `site_notes` (optional, up to 5000 characters)
  - `delineation_id` (optional; must be an approved, visible delineation)
- **Returns:** `201` on create, `200` on update, with the saved recommendation.

### `DELETE /expert/recommendations/{recommendation}`
Returns `{"message": "Planting recommendation deleted."}`.

## Drone imagery

Large GeoTIFFs are uploaded in pieces so that a dropped connection doesn't restart the whole upload.

### Chunked upload
1. **`POST /drone-imagery/uploads`**: start.
   - **Body:**
     - `files`: `[{"name": "survey.tif", "size": 123456789}]`, up to 1 GB each. For `tiles`: one `.tif`/`.tiff`. For `drone`, either:
       - GeoTIFFs (`.tif`/`.tiff`, up to `gdal.max_upload_files`), or
       - drone photos (`.jpg`/`.jpeg`/`.png`, up to `gdal.max_upload_photos`, 500), each optionally with location files of the same name (`.jgw`, `.jpgw`, `.pgw`, `.pngw`, `.wld`, `.prj`, `<photo>.jpg.aux.xml`).

       Mixing GeoTIFFs and photos, or a location file without its photo, returns `422` (`errors.files`).
     - `name` (optional)
     - `purpose`: `drone` (default) or `tiles`
     - `tile_size`: required for `tiles`; one of 256, 512, 640, 1024, 1280
   - **Returns `201`:** `{"upload_id": "...", "chunk_size": 8388608}`
2. **`PUT /drone-imagery/uploads/{uploadId}/files/{fileIndex}/chunks/{chunkIndex}`**: send one piece as the raw request body.
   - Sending the same piece again simply replaces it, so failed pieces can be retried.
   - **Returns:** `{"received": true}`, or `422` if the piece arrived damaged.
3. **`GET /drone-imagery/uploads/{uploadId}`**: which pieces have arrived (used to resume).
4. **`POST /drone-imagery/uploads/{uploadId}/complete`**: assemble the files and queue processing.
   - **Returns `202`:** the new layer (`id`, `name`, `status`, `files`). Processing continues in the background. Responds with `503` if it couldn't be queued.
5. **`DELETE /drone-imagery/uploads/{uploadId}`**: cancel. Returns `{"cancelled": true}`.

Location files are stored next to their photo and are not counted as layer files (`file_count` counts photos). The layer list (`GET /drone-imagery`) gives each layer a `source_format`: `photos` or `geotiff`.

`POST /drone-imagery` (one multipart request with `files[]`) still works for small GeoTIFF uploads (GeoTIFFs only).

### Layers
| Method and URL | Purpose |
|---|---|
| `GET /drone-imagery` | The user's layers and the published layers they can see |
| `GET /drone-imagery/{id}` | One layer, with processing status and review details |
| `GET /drone-imagery/{id}/segmentation` | The AI-detected mangrove areas (GeoJSON) |
| `POST /drone-imagery/{id}/segmentation` | Run the AI delineation again (owner). The genus classification follows automatically when it is done |
| `POST /drone-imagery/{id}/classification` | Run only the genus classification again (owner); the areas stay. `202` with `{"classification_status": "queued"}`, `409` while either AI job runs, before the areas exist or without a genus model. Throttled 10/min |
| `PUT /drone-imagery/{id}/segmentation` | Save edited areas. Body: `areas`, `offset` (`east_m`, `north_m`). Allowed while the genus is still being classified. Returns the areas' `location` and `reassessment_candidates` (approved areas they overlap: `id`, `name`, `location`, `area_ha`, `overlap_percent`, `mapped_by`, `approved_year`) until the delineation is submitted |
| `POST /drone-imagery/{id}/segmentation/genus-preview` | Recount the AI genus suggestion for unsaved areas, without saving. Returns `{"genus_suggestion": {...}}`; `409` until the genus classification is ready |
| `GET /drone-imagery/{id}/canopies` · `PUT` | The tree canopies the genus classification found, and removing wrong ones |

The AI runs in two separate jobs, each with its own status in the layer payloads: the delineation (`segmentation_status`, `segmentation_error`, `segmented_at`) and the genus classification (`classification_status`, `classification_error`, `classified_at`). Each status is `null` (not run), `queued`, `processing`, `ready` or `failed`. `segmentation_stats` also carries the classification's `genus_suggestion`, `canopy_count` and `canopies_by_genus`.
| `PUT /drone-imagery/{id}/edits` | Save review edits |
| `POST /drone-imagery/{id}/confirm` | Confirm the review. The first confirm also needs `delineation` (`name`, `genera`, `notes`, and optionally `reassesses_delineation_id`: one of the `reassessment_candidates`, to submit it as a re-survey of that area; `422` when the areas no longer overlap it). Creates the delineation. |
| `DELETE /drone-imagery/{id}` | Delete your own layer together with the delineation submitted from it, whatever its review state (`{"action": "deleted", "deleted_delineations": 0\|1}`; a resident's sends experts `DelineationWithdrawn`), or hide someone else's (`{"action": "hidden"}`) |
| `GET /drone-imagery/{id}/tiles/{z}/{x}/{y}` | Map tile (PNG), only for users allowed to see the layer |
| `GET /imagery/{id}/tiles/{z}/{x}/{y}` | Public map tile, for published layers only |

## Tile Explorer

| Method and URL | Purpose |
|---|---|
| `GET /tile-explorer/sets/{id}` | A tile set's tiles, with mangrove % per tile |
| `POST /tile-explorer/sources/{id}/sets` | Cut the GeoTIFF at another `tile_size`. Returns `202` while working, `200` if it already exists. |
| `POST /tile-explorer/sets/{id}/download` | The chosen tiles as GeoTIFFs: one tile is sent as its `.tif`, two or more as a ZIP holding only the `.tif` files (streamed). Body: `ids`, optional `token` (set as the `tile_download_token` cookie when the file starts). |
| `POST /tile-explorer/sets/{id}/drone-imagery` | Send the chosen tiles to Drone Imagery: their GeoTIFFs are copied into a new drone layer (one batch, the tiles stay) and queued like a Drone Upload. Body: `ids` (at most `gdal.max_upload_files`), optional `name` (defaults to the GeoTIFF's name). Returns `202` with the layer (`id`, `name`, `status`, `source_type`, `file_count`, `drone_upload_url`), `409` if the set isn't ready. |
| `GET /tile-explorer/sets/{id}/tiles/{tileId}/{variant}` | One tile image or thumbnail |
| `POST /tile-explorer/sources/{id}/detect` | Detect the mangroves again with the current extent model (`ML_DRONE_EXTENT_MODEL`) and remake every tile size of the GeoTIFF with the new labels, one after another. No body. Returns `202` with the GeoTIFF (`areas_status`/`areas_model` cleared, every `tile_sets[].status` `queued`), `409` while tiles are being made, `422` when it has no tiles yet. Each GeoTIFF in the page data and `GET /tile-explorer/sets/{id}` (`source`) has `areas_model` (`roboflow`, `unet`, `yolo`, or null for uploads labelled before it was recorded) and `areas_model_label`. |
| `DELETE /tile-explorer/sources/{id}` | Delete the GeoTIFF and its tiles. Returns `409` while tiles are still being made. |

## Notifications

### `GET /notifications/poll?since={ISO 8601 time}`
The live check. Every open page calls it every 20 seconds while visible.
- **Returns `200`:**
  ```json
  {
    "unread": 3,
    "fresh": [
      {
        "id": "uuid",
        "type": "delineation_submitted",
        "title": "Delineation submitted for review",
        "message": "...",
        "icon": "bi-hourglass-split",
        "url": "/expert/delineate?delineation=22",
        "read": false
      }
    ],
    "now": "2026-11-17T15:36:00+08:00"
  }
  ```
  `fresh` lists notifications created since `since`, at most 5. Without `since` it is empty. Send the returned `now` as the next `since`.

### Other notification endpoints
| Method and URL | Purpose |
|---|---|
| `PUT /notifications/{id}/read` | Mark one as read. Returns `{"success": true, "unreadCount": 2}`. |
| `POST /notifications/read-all` | Mark all as read |
| `DELETE /notifications/{id}` | Delete one |
| `DELETE /notifications` | Delete all of the user's notifications, read and unread. Returns `{"success": true, "deleted": 12, "unreadCount": 0}` |

### Device notifications (Web Push)
| Method and URL | Purpose |
|---|---|
| `POST /push-subscriptions` | Turn on notifications for this browser. Body: the browser's `PushSubscription` (`endpoint` on `https://`, `keys.p256dh`, `keys.auth`) and `contentEncoding` (`aes128gcm` or `aesgcm`). Returns `503` if the server has no push keys. |
| `DELETE /push-subscriptions` | Turn them off. Body: `endpoint`. |

## Other

| Method and URL | Who | Purpose |
|---|---|---|
| `POST /feedback` | Residents and experts (admins get `403`) | Send feedback. Multipart: `type` (`bug`, `suggestion`, `question` or `other`), `message` (5–5000 characters), `page_url`, `screenshot` (image, up to 5 MB). Returns `201`. |
| `POST /onboarding/complete` | Any verified user | The welcome walkthrough was finished or skipped: sets `users.onboarded_at` (once), so it stops opening by itself. No body. Returns `{onboarded_at}`. |
| `GET /register/check-email?email=` | Guests | Whether an email address is already registered (sign-up form) |
| `PUT /profile` | Any user | Update the profile. Returns JSON when asked with `Accept: application/json`. |
| `GET /up` | Anyone | Health check: `200` when the app is running |

Admin pages (`/admin/...`) use normal form submissions that redirect back with a message, except `PUT /admin/users/{user}`, which also answers in JSON for the edit window.
