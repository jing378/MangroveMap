# MangroveMap

A web system for mapping and monitoring mangrove areas in **Southern Leyte, Philippines**. Residents draw mangrove areas on a map or upload drone images, experts review and approve them, and AI models help find mangrove extent and suggest the genus present.

## Features

- **Interactive map (Leaflet):** satellite basemap with place labels, approved mangrove areas, drone imagery layers, and a "Changes over time" timeline.
- **Delineation:** draw mangrove area polygons, tag the genus, and add notes. The barangay and municipality are detected automatically from official boundaries.
- **Expert review:** experts approve or reject submissions. Residents can revise a rejected one within 7 days, and every revision is kept.
- **Yearly observations and planting recommendations** for each mangrove area, recorded by experts.
- **Drone imagery pipeline:** upload GeoTIFF orthomosaics. The system mosaics and tiles them with GDAL, then:
  - a **U-Net** segmentation model detects the mangrove extent (or, optionally, a hosted **Roboflow Workflow** with an RF-DETR segmentation model);
  - a **YOLO** model (or, optionally, a hosted **Roboflow Workflow** with an RF-DETR instance segmentation model) pinpoints the canopies and suggests which genus is present.

  The owner corrects the detected areas, and the result becomes a delineation that goes through expert review.
- **Roles:** resident, expert (applies with credentials, approved by an admin), and admin (users, AI models, map layers, feedback, activity log).
- Email notifications, dark mode, and installable as an app (PWA).

## Tech stack

| Part | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3, MySQL 8 (spatial functions) |
| Frontend | Blade, Tailwind CSS 4, Vite, Leaflet 1.9 |
| Imagery processing | GDAL (via QGIS on Windows, `gdal-bin` on Linux), Laravel queues |
| AI | Python, PyTorch, segmentation-models-pytorch (U-Net), Ultralytics YOLO |

## Requirements

- PHP 8.3, Composer, Node.js 20+
- MySQL 8
- GDAL: on Windows install [QGIS](https://qgis.org) (provides GDAL); on Linux run `sudo apt install gdal-bin python3-gdal`
- Python 3.12 for the AI models
- The model weights (`.pth` / `.pt`). They are **not in this repository** because of their size. Get them from the team and place them in `ml/models/`, or upload them on the admin's AI Models page.

## Setup

```bash
git clone https://github.com/jing378/MangroveMap.git
cd MangroveMap
composer setup                      # install deps, create .env, generate key, migrate, build assets
```

1. Edit `.env` to point at your MySQL database (`DB_CONNECTION=mysql`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), then:
   ```bash
   php artisan migrate --seed
   ```
2. Load the barangay boundaries and map labels:
   ```bash
   php artisan boundaries:import --path=storage/app/geo/southern_leyte_barangay_boundaries.geojson
   php artisan map:sync-place-labels
   ```
3. Set up the AI environment:
   ```bash
   python -m venv ml/.venv
   ml/.venv/Scripts/pip install -r ml/requirements.txt      # Linux: ml/.venv/bin/pip
   ```
   To let the hosted Roboflow Workflow draw the mangrove areas instead of the U-Net, set `ML_DRONE_EXTENT_MODEL=roboflow` and `ROBOFLOW_API_KEY` (from app.roboflow.com/settings/api) in `.env`. If Roboflow fails, the U-Net is used. Check the connection with a live call on one image:
   ```bash
   ml/.venv/Scripts/python ml/smoke_roboflow.py path/to/tile.jpg
   ```
   Likewise `ML_GENUS_MODEL=roboflow` lets the hosted canopy Workflow (`mangrove-canopy-3-rfdetr-seg-small-t1…`, set by `ROBOFLOW_GENUS_WORKFLOW`) find the canopies and genus instead of the YOLO weights, which take over if it fails. Live check:
   ```bash
   ml/.venv/Scripts/python ml/smoke_roboflow_genus.py path/to/tile.jpg
   ```
4. GDAL: on Windows, set `GDAL_QGIS_ENV` in `.env` if your QGIS version differs from the default in `config/gdal.php`. On Linux, set `GDAL_PYTHON` if `python3` is not the Python with GDAL.
5. Email: fill in the `MAIL_*` settings (an SMTP account) so verification and review emails can be sent.

## Running

```bash
composer dev
```
This starts the web server, the default queue worker, the log viewer, and Vite.

Drone imagery runs on **its own queue** and needs a second worker in another terminal:
```bash
php artisan queue:work drone_imagery --queue=drone-imagery --timeout=7200 --tries=1
```
Restart this worker after changing any job or service code. It keeps running the code it started with.

### Seeded accounts (local development only)

`php artisan migrate --seed` creates one admin, one expert, and one resident account for testing (see `database/seeders/DatabaseSeeder.php`). Ask the team for the login details.

Change their passwords, or remove the accounts, on any copy that is reachable from the internet.

## Testing

```bash
composer test
```
Tests run on an in-memory SQLite database. **Always use `composer test`**: it clears the config cache first. With a cached config, Laravel ignores `phpunit.xml`, and `tests/TestCase.php` refuses to run so your real database is not wiped. The drone imagery tests use your local GDAL/QGIS install and skip themselves if it is not found.

Format code with `vendor/bin/pint`.

## Useful commands

| Command | Purpose |
|---|---|
| `php artisan delineations:resolve-locations --dry-run` | Fill in barangays for areas not matched yet |
| `php artisan delineations:repair-features --dry-run` | Check and repair stored delineation shapes |
| `php artisan map:sync-place-labels` | Re-download OpenStreetMap place names for the map labels |

## Project structure

```
app/Http/Controllers     Resident, expert, admin, and drone imagery controllers
app/Jobs                 Drone imagery processing, segmentation, and finalizing (queued)
app/Services             GDAL runner, AI segmentation service, boundary lookup
ml/                      predict.py (U-Net), predict_genus.py (YOLO or the Roboflow canopy Workflow), predict_roboflow.py (Roboflow client + extent Workflow), model weights
resources/views          Blade pages; most map code lives in resources/views/users/partials
database/migrations      Schema
tests/                   Feature and unit tests
```

See [CLAUDE.md](CLAUDE.md) for a detailed description of the architecture.

## Team

Capstone project. Team members:

- _Name_: _Role_
- _Name_: _Role_
- _Name_: _Role_

Adviser: _Name_
