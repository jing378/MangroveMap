"""Builds the map's "Mangrove extent by year" dataset from Global Mangrove Watch (GMW) rasters.

GMW v4.1 publishes 1x1 degree GeoTIFF tiles with one band per year (1985-2025, 1 = mangrove, about 30 m
pixels, EPSG:4326). This keeps only the pixels inside the province (the barangay boundaries, grown about
1 km seaward because mangroves fringe the coast outside the land polygons), and writes one JSON file:

  years              [1985, ..., 2025]
  total_ha           mapped hectares per year for the whole province
  municipalities     name, bounds and hectares per year for each municipality
  grid               west/north/pixel of the raster grid the runs refer to
  runs               base64 of 16-byte records, one per horizontal run of pixels with the same history:
                     uint16 row, uint16 col, uint16 length, uint16 municipality (1-based),
                     uint32 years 0-31 as bits, uint32 years 32+ as bits (little endian)

Needs GDAL and NumPy, so run it with the QGIS Python (see config/gdal.php), for example:

  call "C:\\Program Files\\QGIS 3.44.12\\bin\\o4w_env.bat"
  python scripts/gmw/build_mangrove_history.py --tiles <folder of GMW_*_mng_ext.tif> ^
      --boundaries <barangays.geojson with adm3_pcode/adm3_name> --out resources/data/mangrove-history.json

Data: Bunting et al. (2026), Global Mangrove Watch v4.1, doi:10.5281/zenodo.21346456 (CC BY 4.0).
"""
import argparse
import base64
import glob
import json
import math
import os

import numpy as np
from osgeo import gdal, ogr, osr

gdal.UseExceptions()

FIRST_YEAR = 1985
SEAWARD_PIXELS = 36  # about 1 km at 30 m pixels


def pixel_area_ha(lat_deg, pixel_deg):
    """Area of one pixel at this latitude on the WGS84 ellipsoid, in hectares."""
    a, e2 = 6378137.0, 0.00669437999014
    lat = math.radians(lat_deg)
    w = math.sqrt(1 - e2 * math.sin(lat) ** 2)
    meridional = a * (1 - e2) / w ** 3
    normal = a / w
    step = math.radians(pixel_deg)
    return (meridional * step) * (normal * math.cos(lat) * step) / 10000.0


def grow(ids, steps):
    """Spreads each municipality id into neighbouring empty cells, one pixel per step."""
    for _ in range(steps):
        empty = ids == 0
        if not empty.any():
            break
        padded = np.pad(ids, 1)
        best = np.zeros_like(ids)
        for dy, dx in ((0, 1), (2, 1), (1, 0), (1, 2)):
            neighbour = padded[dy:dy + ids.shape[0], dx:dx + ids.shape[1]]
            best = np.where(best == 0, neighbour, best)
        ids = np.where(empty, best, ids)
    return ids


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--tiles', required=True)
    parser.add_argument('--boundaries', required=True)
    parser.add_argument('--out', required=True)
    parser.add_argument('--province', default='Southern Leyte')
    args = parser.parse_args()

    tiles = sorted(glob.glob(os.path.join(args.tiles, 'GMW_*_mng_ext.tif')))
    if not tiles:
        raise SystemExit('No GMW_*_mng_ext.tif tiles in ' + args.tiles)
    mosaic = gdal.BuildVRT('/vsimem/gmw.vrt', tiles)
    west0, pixel, _, north0, _, _ = mosaic.GetGeoTransform()
    bands = mosaic.RasterCount
    years = [FIRST_YEAR + i for i in range(bands)]

    boundaries = ogr.Open(args.boundaries)
    layer = boundaries.GetLayer()
    names = {}
    for feature in layer:
        names[feature.GetField('adm3_pcode')] = feature.GetField('adm3_name')
    layer.ResetReading()
    codes = sorted(names, key=lambda code: names[code])

    # Window of the mosaic that covers the province plus the seaward margin, snapped to the pixel grid.
    min_x, max_x, min_y, max_y = layer.GetExtent()
    margin = (SEAWARD_PIXELS + 2) * pixel
    col0 = max(0, int(math.floor((min_x - margin - west0) / pixel)))
    row0 = max(0, int(math.floor((north0 - (max_y + margin)) / pixel)))
    col1 = min(mosaic.RasterXSize, int(math.ceil((max_x + margin - west0) / pixel)))
    row1 = min(mosaic.RasterYSize, int(math.ceil((north0 - (min_y - margin)) / pixel)))
    width, height = col1 - col0, row1 - row0
    west, north = west0 + col0 * pixel, north0 - row0 * pixel

    stack = mosaic.ReadAsArray(col0, row0, width, height).astype(bool)  # (years, rows, cols)

    # Municipality id per pixel (1-based, 0 = outside the province).
    srs = osr.SpatialReference()
    srs.ImportFromEPSG(4326)
    srs.SetAxisMappingStrategy(osr.OAMS_TRADITIONAL_GIS_ORDER)
    target = gdal.GetDriverByName('MEM').Create('', width, height, 1, gdal.GDT_Byte)
    target.SetGeoTransform((west, pixel, 0, north, 0, -pixel))
    target.SetProjection(srs.ExportToWkt())
    memory = gdal.GetDriverByName('MEM').Create('', 0, 0, 0, gdal.GDT_Unknown)
    numbered = memory.CreateLayer('m', srs, ogr.wkbMultiPolygon)
    numbered.CreateField(ogr.FieldDefn('id', ogr.OFTInteger))
    for feature in layer:
        copy = ogr.Feature(numbered.GetLayerDefn())
        copy.SetGeometry(feature.GetGeometryRef())
        copy.SetField('id', codes.index(feature.GetField('adm3_pcode')) + 1)
        numbered.CreateFeature(copy)
    gdal.RasterizeLayer(target, [1], numbered, options=['ATTRIBUTE=id', 'ALL_TOUCHED=TRUE'])
    ids = grow(target.ReadAsArray().astype(np.uint8), SEAWARD_PIXELS)

    stack &= (ids > 0)[None, :, :]
    row_ha = np.array([pixel_area_ha(north - (r + 0.5) * pixel, pixel) for r in range(height)])

    total_ha = [round(float((stack[i].sum(axis=1) * row_ha).sum()), 1) for i in range(bands)]
    municipalities = []
    for index, code in enumerate(codes, start=1):
        inside = ids == index
        ever = stack.any(axis=0) & inside
        entry = {'id': index, 'name': names[code], 'pcode': code, 'bounds': None,
                 'ha': [round(float(((stack[i] & inside).sum(axis=1) * row_ha).sum()), 1) for i in range(bands)]}
        if ever.any():
            rows, cols = np.nonzero(ever)
            entry['bounds'] = [round(north - (int(rows.max()) + 1) * pixel, 5), round(west + int(cols.min()) * pixel, 5),
                               round(north - int(rows.min()) * pixel, 5), round(west + (int(cols.max()) + 1) * pixel, 5)]
        municipalities.append(entry)

    # Years as bits: bit i of `low` is year i (0-31), bit i of `high` is year 32 + i.
    low = np.zeros((height, width), dtype=np.uint32)
    high = np.zeros((height, width), dtype=np.uint32)
    for i in range(bands):
        if i < 32:
            low |= stack[i].astype(np.uint32) << np.uint32(i)
        else:
            high |= stack[i].astype(np.uint32) << np.uint32(i - 32)

    records = bytearray()
    run_count = 0
    pixel_count = 0
    record = np.dtype([('row', '<u2'), ('col', '<u2'), ('len', '<u2'), ('muni', '<u2'), ('low', '<u4'), ('high', '<u4')])
    for row in np.nonzero((low | high).any(axis=1))[0]:
        cols = np.nonzero(low[row] | high[row])[0]
        start = 0
        for k in range(1, len(cols) + 1):
            a, b = cols[start], cols[k - 1]
            ends = (k == len(cols) or cols[k] != b + 1 or low[row, cols[k]] != low[row, a]
                    or high[row, cols[k]] != high[row, a] or ids[row, cols[k]] != ids[row, a])
            if ends:
                records += np.array([(row, a, k - start, ids[row, a], low[row, a], high[row, a])], dtype=record).tobytes()
                run_count += 1
                pixel_count += k - start
                start = k

    result = {
        'source': 'Global Mangrove Watch v4.1 (Bunting et al., 2026)',
        'source_url': 'https://doi.org/10.5281/zenodo.21346456',
        'license': 'CC BY 4.0',
        'province': args.province,
        'resolution_m': round(pixel * 111320),
        'years': years,
        'total_ha': total_ha,
        'municipalities': municipalities,
        'grid': {'west': round(west, 9), 'north': round(north, 9), 'pixel': pixel, 'width': width, 'height': height},
        'run_count': run_count,
        'runs': base64.b64encode(bytes(records)).decode('ascii'),
    }
    os.makedirs(os.path.dirname(os.path.abspath(args.out)), exist_ok=True)
    with open(args.out, 'w', encoding='utf-8') as handle:
        json.dump(result, handle, separators=(',', ':'))
    print(json.dumps({'years': [years[0], years[-1]], 'pixels': pixel_count, 'runs': run_count,
                      'first_ha': total_ha[0], 'last_ha': total_ha[-1], 'bytes': os.path.getsize(args.out)}))


if __name__ == '__main__':
    main()
