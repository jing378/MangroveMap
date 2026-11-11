"""Tile Explorer: cut a georeferenced raster into equal square tiles.

Run with GDAL's Python (QGIS's on Windows via GdalCommandRunner, python3-gdal on Linux):

    python make_tiles.py --raster mosaic.tif --out DIR --tile-size 640 [--areas areas.geojson]

For every tile that contains imagery it writes
    DIR/tif/<id>.tif       GeoTIFF (RGBA, CRS + geotransform of the source, DEFLATE)
    DIR/jpg/<id>.jpg       JPEG (RGB) + <id>.wld world file + <id>.jpg.aux.xml (CRS), e.g. for Roboflow
    DIR/thumb/<id>.jpg     small JPEG for the grid
where <id> is r<row>_c<col>; the download names (<prefix>_<id>) are listed in tiles.json.
Edge tiles are padded to the full size (transparent in the GeoTIFF, black in the JPEGs), so all tiles match.
Tiles with no imagery at all (outside the orthomosaic footprint) are skipped.

With --areas (mangrove polygons, GeoJSON in EPSG:4326) each tile also gets its mangrove coverage
(% of the tile's imagery pixels) and an SVG path of the areas clipped to the tile, in tile pixels.

Progress goes to DIR/progress.json while running; the tile list to DIR/tiles.json at the end.
The last stdout line is a JSON summary.
"""

import argparse
import json
import math
import os
import sys
import time

import numpy as np
from osgeo import gdal, ogr, osr

gdal.UseExceptions()
ogr.UseExceptions()


def write_json(path, payload):
    temporary = path + ".tmp"
    with open(temporary, "w", encoding="utf-8") as handle:
        json.dump(payload, handle, separators=(",", ":"))
    os.replace(temporary, path)


def load_areas(path, target_srs):
    """Mangrove polygons transformed to the raster's CRS, in an in-memory OGR layer (for rasterizing)."""
    source = ogr.Open(path)
    source_layer = source.GetLayer(0)
    wgs84 = osr.SpatialReference()
    wgs84.ImportFromEPSG(4326)
    wgs84.SetAxisMappingStrategy(osr.OAMS_TRADITIONAL_GIS_ORDER)
    transform = osr.CoordinateTransformation(wgs84, target_srs)

    # GDAL 3.11+ does vectors in "MEM" ("Memory" is deprecated); older GDAL only has "Memory".
    driver = ogr.GetDriverByName("MEM") or ogr.GetDriverByName("Memory")
    memory = driver.CreateDataSource("areas")
    layer = memory.CreateLayer("areas", srs=target_srs, geom_type=ogr.wkbMultiPolygon)
    geometries = []
    for feature in source_layer:
        geometry = feature.GetGeometryRef()
        if geometry is None:
            continue
        geometry = geometry.Clone()
        geometry.Transform(transform)
        if not geometry.IsValid():
            try:
                geometry = geometry.MakeValid()
            except Exception:  # GDAL without GEOS: keep as is
                pass
        if geometry is None or geometry.IsEmpty():
            continue
        out = ogr.Feature(layer.GetLayerDefn())
        out.SetGeometry(geometry)
        layer.CreateFeature(out)
        geometries.append(geometry)
    return memory, layer, geometries


def svg_path(geometry, origin_x, origin_y, pixel_w, pixel_h):
    """Polygon rings of $geometry as an SVG path in tile pixel coordinates (draw with fill-rule: evenodd)."""
    parts = []

    def ring_path(ring):
        points = ring.GetPoints() or []
        if len(points) < 3:
            return
        pixels = [((x - origin_x) / pixel_w, (y - origin_y) / pixel_h) for x, y, *_ in points]
        # Specks and pin-holes of a few pixels only clutter the outline (they still count in the coverage).
        area = abs(sum(x0 * y1 - x1 * y0 for (x0, y0), (x1, y1) in zip(pixels, pixels[1:] + pixels[:1]))) / 2
        if area < 150:
            return
        parts.append("M" + "L".join("%g %g" % (round(x, 1), round(y, 1)) for x, y in pixels) + "Z")

    def walk(g):
        kind = ogr.GT_Flatten(g.GetGeometryType())
        if kind == ogr.wkbPolygon:
            for index in range(g.GetGeometryCount()):
                ring_path(g.GetGeometryRef(index))
        elif kind in (ogr.wkbMultiPolygon, ogr.wkbGeometryCollection):
            for index in range(g.GetGeometryCount()):
                walk(g.GetGeometryRef(index))

    walk(geometry)
    return "".join(parts)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--raster", required=True)
    parser.add_argument("--out", required=True)
    parser.add_argument("--tile-size", type=int, default=640)
    parser.add_argument("--thumb-size", type=int, default=256)
    parser.add_argument("--areas")
    parser.add_argument("--prefix", default="tile")
    parser.add_argument("--jpeg-quality", type=int, default=92)
    args = parser.parse_args()

    size = args.tile_size
    source = gdal.Open(args.raster)
    width, height = source.RasterXSize, source.RasterYSize
    gt = source.GetGeoTransform()
    srs = source.GetSpatialRef()
    if srs is None:
        raise RuntimeError("The raster has no coordinate system.")
    srs.SetAxisMappingStrategy(osr.OAMS_TRADITIONAL_GIS_ORDER)
    projection = source.GetProjection()

    alpha_index = None
    color_indexes = []
    for index in range(1, source.RasterCount + 1):
        if source.GetRasterBand(index).GetColorInterpretation() == gdal.GCI_AlphaBand:
            alpha_index = index
        elif len(color_indexes) < 3:
            color_indexes.append(index)
    if len(color_indexes) < 3:
        raise RuntimeError("Tile Explorer needs RGB imagery (at least 3 bands).")
    mask_band = source.GetRasterBand(alpha_index) if alpha_index else source.GetRasterBand(1).GetMaskBand()

    for folder in ("tif", "jpg", "thumb"):
        os.makedirs(os.path.join(args.out, folder), exist_ok=True)

    area_source = area_layer = None
    geometries = []
    if args.areas and os.path.exists(args.areas):
        area_source, area_layer, geometries = load_areas(args.areas, srs)
    has_areas = area_layer is not None

    wgs84 = osr.SpatialReference()
    wgs84.ImportFromEPSG(4326)
    wgs84.SetAxisMappingStrategy(osr.OAMS_TRADITIONAL_GIS_ORDER)
    to_wgs84 = osr.CoordinateTransformation(srs, wgs84)

    columns = math.ceil(width / size)
    rows = math.ceil(height / size)
    total = columns * rows
    mem_driver = gdal.GetDriverByName("MEM")
    tiff_driver = gdal.GetDriverByName("GTiff")
    progress_path = os.path.join(args.out, "progress.json")
    digits = max(3, len(str(max(rows, columns))))
    tiles = []
    done = 0
    last_report = 0.0

    for row in range(rows):
        for column in range(columns):
            done += 1
            if time.monotonic() - last_report > 1.0:
                write_json(progress_path, {"done": done, "total": total})
                last_report = time.monotonic()

            x_off, y_off = column * size, row * size
            w, h = min(size, width - x_off), min(size, height - y_off)
            valid = mask_band.ReadAsArray(x_off, y_off, w, h) > 0
            valid_pixels = int(valid.sum())
            if valid_pixels == 0:
                continue

            name = "%s_r%0*d_c%0*d" % (args.prefix, digits, row, digits, column)
            tile_id = "r%0*d_c%0*d" % (digits, row, digits, column)
            origin_x = gt[0] + x_off * gt[1] + y_off * gt[2]
            origin_y = gt[3] + x_off * gt[4] + y_off * gt[5]
            tile_gt = (origin_x, gt[1], gt[2], origin_y, gt[4], gt[5])

            tile = mem_driver.Create("", size, size, 4, gdal.GDT_Byte)
            tile.SetGeoTransform(tile_gt)
            tile.SetProjection(projection)
            for out_index, source_index in enumerate(color_indexes, start=1):
                data = np.zeros((size, size), dtype=np.uint8)
                data[:h, :w] = source.GetRasterBand(source_index).ReadAsArray(x_off, y_off, w, h)
                data[:h, :w][~valid] = 0
                tile.GetRasterBand(out_index).WriteArray(data)
                tile.GetRasterBand(out_index).SetColorInterpretation(
                    (gdal.GCI_RedBand, gdal.GCI_GreenBand, gdal.GCI_BlueBand)[out_index - 1])
            alpha = np.zeros((size, size), dtype=np.uint8)
            alpha[:h, :w][valid] = 255
            tile.GetRasterBand(4).WriteArray(alpha)
            tile.GetRasterBand(4).SetColorInterpretation(gdal.GCI_AlphaBand)

            tiff_driver.CreateCopy(os.path.join(args.out, "tif", tile_id + ".tif"), tile,
                                   options=["COMPRESS=DEFLATE", "PREDICTOR=2", "TILED=YES"])
            gdal.Translate(os.path.join(args.out, "jpg", tile_id + ".jpg"), tile, format="JPEG", bandList=[1, 2, 3],
                           creationOptions=["QUALITY=%d" % args.jpeg_quality, "WORLDFILE=YES"])
            gdal.SetConfigOption("GDAL_PAM_ENABLED", "NO")
            try:
                gdal.Translate(os.path.join(args.out, "thumb", tile_id + ".jpg"), tile, format="JPEG",
                               bandList=[1, 2, 3], width=args.thumb_size, height=args.thumb_size,
                               resampleAlg="average", creationOptions=["QUALITY=80"])
            finally:
                gdal.SetConfigOption("GDAL_PAM_ENABLED", None)

            corner_x = [origin_x, origin_x + size * gt[1] + size * gt[2]]
            corner_y = [origin_y, origin_y + size * gt[4] + size * gt[5]]
            min_x, max_x, min_y, max_y = min(corner_x), max(corner_x), min(corner_y), max(corner_y)
            lons, lats = [], []
            for x, y in ((min_x, min_y), (min_x, max_y), (max_x, min_y), (max_x, max_y)):
                lon, lat, *_ = to_wgs84.TransformPoint(x, y)
                lons.append(lon)
                lats.append(lat)

            mangrove = None
            outline = ""
            if has_areas:
                area_layer.SetSpatialFilterRect(min_x, min_y, max_x, max_y)
                burned = mem_driver.Create("", size, size, 1, gdal.GDT_Byte)
                burned.SetGeoTransform(tile_gt)
                burned.SetProjection(projection)
                gdal.RasterizeLayer(burned, [1], area_layer, burn_values=[1])
                inside = burned.GetRasterBand(1).ReadAsArray()[:h, :w] > 0
                mangrove = round(float((inside & valid).sum()) * 100.0 / valid_pixels, 1)
                burned = None

                if mangrove > 0:
                    box = ogr.CreateGeometryFromWkt(
                        "POLYGON((%r %r,%r %r,%r %r,%r %r,%r %r))"
                        % (min_x, min_y, max_x, min_y, max_x, max_y, min_x, max_y, min_x, min_y))
                    pieces = []
                    for geometry in geometries:
                        if not geometry.Intersects(box):
                            continue
                        clipped = geometry.Intersection(box)
                        if clipped is not None and not clipped.IsEmpty():
                            pieces.append(svg_path(clipped.SimplifyPreserveTopology(abs(gt[1]) * 2),
                                                   origin_x, origin_y, gt[1], gt[5]))
                    outline = "".join(pieces)
                area_layer.SetSpatialFilter(None)

            tiles.append({
                "id": tile_id,
                "name": name,
                "row": row,
                "col": column,
                "valid": round(valid_pixels * 100.0 / (size * size), 1),
                "mangrove": mangrove,
                "bounds": [round(min(lons), 7), round(min(lats), 7), round(max(lons), 7), round(max(lats), 7)],
                "outline": outline,
            })
            tile = None

    pixel_m = abs(gt[1])
    authority = srs.GetAuthorityName(None)
    code = srs.GetAuthorityCode(None)
    manifest = {
        "tile_size": size,
        "columns": columns,
        "rows": rows,
        "raster_width": width,
        "raster_height": height,
        "crs": "%s:%s" % (authority, code) if authority and code else srs.ExportToProj4(),
        "pixel_size": pixel_m,
        "has_areas": has_areas,
        "tiles": tiles,
    }
    write_json(os.path.join(args.out, "tiles.json"), manifest)
    write_json(progress_path, {"done": total, "total": total})
    print(json.dumps({
        "tile_count": len(tiles),
        "columns": columns,
        "rows": rows,
        "skipped_empty": total - len(tiles),
    }))


if __name__ == "__main__":
    try:
        main()
    except Exception as exception:  # noqa: BLE001 - reported to the job as JSON
        print(json.dumps({"error": str(exception)}))
        sys.exit(1)
