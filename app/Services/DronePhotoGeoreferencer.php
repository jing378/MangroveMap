<?php

namespace App\Services;

use RuntimeException;

/**
 * Places plain pictures (JPG/PNG) on the map so ProcessDroneImagery can mosaic them like GeoTIFFs.
 *
 * - A drone photo taken straight down (DJI): its XMP holds the RTK/GPS position, the height above the
 *   take-off point, the camera's calibrated focal length (pixels) and the gimbal direction. From these
 *   the photo's ground footprint is computed and written as a VRT (EPSG:4326, rotated geotransform) that
 *   reads the JPG itself: no copy is made. Flat ground at take-off height is assumed and lens distortion
 *   is not removed, so a photo lands within a few metres. Only the centre of each photo is kept
 *   (gdal.photo_keep_fraction): it is the least distorted part and drops the dark lens corners, and
 *   overlapping neighbours fill the rest.
 * - A picture uploaded with a world file (.jgw/.pgw/.wld, and optionally .prj or .aux.xml), e.g. a Tile
 *   Explorer JPEG: GDAL reads the world file itself; only a missing CRS is filled in (.prj, else a guess).
 *
 * Photos are placed one by one, not stitched, so neighbouring photos can be a few metres apart. A stitched
 * orthomosaic is still the more accurate upload.
 */
class DronePhotoGeoreferencer
{
    public const PICTURE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /** Files that only describe a picture's location; uploaded with the picture of the same name. */
    public const SIDECAR_EXTENSIONS = ['jgw', 'jpgw', 'jpegw', 'pgw', 'pngw', 'wld', 'prj', 'aux.xml'];

    private const WORLD_FILE_EXTENSIONS = ['jgw', 'jpgw', 'jpegw', 'pgw', 'pngw', 'wld'];

    /** "geotiff", "picture", "sidecar", or null for anything else. */
    public static function kind(string $fileName): ?string
    {
        $name = strtolower($fileName);
        if (preg_match('/\.tiff?$/', $name)) {
            return 'geotiff';
        }
        if (str_ends_with($name, '.aux.xml')) {
            return 'sidecar';
        }
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($extension, self::PICTURE_EXTENSIONS, true)) {
            return 'picture';
        }

        return in_array($extension, self::SIDECAR_EXTENSIONS, true) ? 'sidecar' : null;
    }

    /** The name a picture and its sidecars share: "a" for a.jpg, a.jgw, a.prj and a.jpg.aux.xml. */
    public static function stem(string $fileName): string
    {
        $name = strtolower(basename($fileName));
        if (str_ends_with($name, '.aux.xml')) {
            $name = substr($name, 0, -strlen('.aux.xml'));
            if (in_array(pathinfo($name, PATHINFO_EXTENSION), self::PICTURE_EXTENSIONS, true)) {
                $name = pathinfo($name, PATHINFO_FILENAME);
            }

            return $name;
        }

        return pathinfo($name, PATHINFO_FILENAME);
    }

    /**
     * Where a sidecar is stored next to its picture (stored as "{base}.{pictureExtension}"), using the
     * names GDAL looks for: {base}.jgw / .wld / .prj, and {base}.jpg.aux.xml.
     */
    public static function sidecarPath(string $pictureStoredPath, string $sidecarName): string
    {
        $base = preg_replace('/\.[^.\/]+$/', '', $pictureStoredPath);
        $name = strtolower($sidecarName);

        return str_ends_with($name, '.aux.xml')
            ? $pictureStoredPath.'.aux.xml'
            : $base.'.'.pathinfo($name, PATHINFO_EXTENSION);
    }

    /**
     * A raster GDAL can place for the picture at $path (the picture itself, or a VRT in $workDirectory).
     *
     * @throws RuntimeException with a message for the owner when the picture cannot be placed
     */
    public function georeference(GdalCommandRunner $gdal, string $path, string $originalName, string $workDirectory, int $index): string
    {
        if ($this->hasWorldFile($path)) {
            return $this->withCrs($gdal, $path, $originalName, $workDirectory, $index);
        }

        $camera = $this->readCamera($path);
        if ($camera === null) {
            throw new RuntimeException(
                "{$originalName} has no location: it is not a drone photo with GPS and camera details, and no world file "
                .'(.jgw, .pgw or .wld) was uploaded with it. Upload a georeferenced GeoTIFF instead.'
            );
        }
        if (abs($camera['pitch'] + 90) > 5) {
            throw new RuntimeException(sprintf(
                '%s was taken with the camera tilted (%.0f° instead of straight down), so it cannot be placed on the map. '
                .'Upload only photos taken straight down (-90°).',
                $originalName,
                $camera['pitch'],
            ));
        }

        $vrtPath = $workDirectory.DIRECTORY_SEPARATOR.sprintf('photo-%04d.vrt', $index);
        if (! is_dir($workDirectory) && ! mkdir($workDirectory, 0775, true) && ! is_dir($workDirectory)) {
            throw new RuntimeException('Unable to create the photo processing directory.');
        }
        if (file_put_contents($vrtPath, $this->photoVrt($path, $camera)) === false) {
            throw new RuntimeException("Unable to prepare {$originalName} for the mosaic.");
        }

        // Written out once: gdalwarp reading the VRT would decode the whole JPEG again for every chunk
        // of the mosaic (measured: about twice as slow overall).
        $tifPath = substr($vrtPath, 0, -4).'.tif';
        $gdal->run(['gdal_translate', '-q', '-co', 'TILED=YES', '-co', 'COMPRESS=DEFLATE', $vrtPath, $tifPath], $workDirectory, (int) config('gdal.timeout'));

        return $tifPath;
    }

    /**
     * Camera details of a drone photo taken with a DJI camera, or null when they are missing.
     *
     * @return array{width: int, height: int, lat: float, lng: float, height_m: float, focal_px: float, cx: float, cy: float, pitch: float, heading: float}|null
     */
    public function readCamera(string $path): ?array
    {
        $size = @getimagesize($path);
        $handle = @fopen($path, 'rb');
        if ($size === false || $handle === false) {
            return null;
        }
        $head = (string) fread($handle, 512 * 1024); // the XMP packet sits near the start of the file
        fclose($handle);

        [$width, $height] = $size;
        $value = function (string $key) use ($head): ?float {
            if (preg_match('/drone-dji:'.$key.'="\s*([-+]?[0-9.]+)"/', $head, $match)
                || preg_match('/<drone-dji:'.$key.'>\s*([-+]?[0-9.]+)\s*</', $head, $match)) {
                return (float) $match[1];
            }

            return null;
        };

        $lat = $value('GpsLatitude') ?? $value('Latitude');
        $lng = $value('GpsLongitude') ?? $value('Longitude');
        if (($lat === null || $lng === null) && ($gps = $this->exifGps($path))) {
            [$lat, $lng] = $gps;
        }
        $heightAboveTakeOff = $value('RelativeAltitude');
        $pitch = $value('GimbalPitchDegree');
        $yaw = $value('GimbalYawDegree');
        $roll = $value('GimbalRollDegree') ?? 0.0;
        $focal = $value('CalibratedFocalLength') ?? $this->focalFromExif($path, $width, $height);

        if ($lat === null || $lng === null || $heightAboveTakeOff === null || $pitch === null || $yaw === null
            || $focal === null || $focal <= 0 || $heightAboveTakeOff < 1 || ($lat == 0 && $lng == 0)) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'lat' => $lat,
            'lng' => $lng,
            'height_m' => $heightAboveTakeOff,
            'focal_px' => $focal,
            'cx' => $value('CalibratedOpticalCenterX') ?? $width / 2,
            'cy' => $value('CalibratedOpticalCenterY') ?? $height / 2,
            'pitch' => $pitch,
            // Straight down, DJI reports the gimbal rolled 180° with its yaw turned around: the photo's top edge
            // then faces yaw + 180 (checked against an orthophoto of the same flight).
            'heading' => abs(abs($roll) - 180) < 5 ? $yaw + 180 : $yaw,
        ];
    }

    /** VRT that shows the centre of the photo, scaled to gdal.photo_resolution_m and turned to its footprint. */
    private function photoVrt(string $path, array $camera): string
    {
        $groundPixel = $camera['height_m'] / $camera['focal_px']; // metres per photo pixel on flat ground
        $keep = min(1.0, max(0.3, (float) config('gdal.photo_keep_fraction', 0.8)));
        $outputPixel = max($groundPixel, (float) config('gdal.photo_resolution_m', 0.02));

        $cropWidth = max(1, (int) round($camera['width'] * $keep));
        $cropHeight = max(1, (int) round($camera['height'] * $keep));
        $cropX = intdiv($camera['width'] - $cropWidth, 2);
        $cropY = intdiv($camera['height'] - $cropHeight, 2);
        $outWidth = max(1, (int) round($cropWidth * $groundPixel / $outputPixel));
        $outHeight = max(1, (int) round($cropHeight * $groundPixel / $outputPixel));
        $stepX = $cropWidth / $outWidth;   // photo pixels per output pixel
        $stepY = $cropHeight / $outHeight;

        // Metres east/north of the camera for photo pixel offsets (right, down) from the optical centre.
        $heading = deg2rad($camera['heading']);
        $cos = cos($heading);
        $sin = sin($heading);
        $toEast = fn (float $right, float $down) => $right * $cos - $down * $sin;
        $toNorth = fn (float $right, float $down) => -$right * $sin - $down * $cos;

        // Metres per degree at the photo (WGS 84); exact enough over a photo's few dozen metres.
        $phi = deg2rad($camera['lat']);
        $a = 6378137.0;
        $e2 = 0.00669437999014;
        $w = sqrt(1 - $e2 * sin($phi) ** 2);
        $metresPerDegLat = deg2rad(1) * $a * (1 - $e2) / $w ** 3;
        $metresPerDegLng = deg2rad(1) * $a / $w * cos($phi);

        $originRight = ($cropX - $camera['cx']) * $groundPixel;
        $originDown = ($cropY - $camera['cy']) * $groundPixel;
        $geoTransform = [
            $camera['lng'] + $toEast($originRight, $originDown) / $metresPerDegLng,
            $toEast($stepX * $groundPixel, 0) / $metresPerDegLng,
            $toEast(0, $stepY * $groundPixel) / $metresPerDegLng,
            $camera['lat'] + $toNorth($originRight, $originDown) / $metresPerDegLat,
            $toNorth($stepX * $groundPixel, 0) / $metresPerDegLat,
            $toNorth(0, $stepY * $groundPixel) / $metresPerDegLat,
        ];

        $source = htmlspecialchars($path, ENT_XML1 | ENT_QUOTES);
        $bands = '';
        foreach (['Red', 'Green', 'Blue'] as $number => $colour) {
            $band = $number + 1;
            $bands .= <<<XML
              <VRTRasterBand dataType="Byte" band="{$band}">
                <ColorInterp>{$colour}</ColorInterp>
                <SimpleSource resampling="average">
                  <SourceFilename relativeToVRT="0">{$source}</SourceFilename>
                  <SourceBand>{$band}</SourceBand>
                  <SrcRect xOff="{$cropX}" yOff="{$cropY}" xSize="{$cropWidth}" ySize="{$cropHeight}"/>
                  <DstRect xOff="0" yOff="0" xSize="{$outWidth}" ySize="{$outHeight}"/>
                </SimpleSource>
              </VRTRasterBand>

            XML;
        }
        $transform = implode(', ', array_map(fn (float $number) => sprintf('%.15e', $number), $geoTransform));

        return <<<XML
        <VRTDataset rasterXSize="{$outWidth}" rasterYSize="{$outHeight}">
          <SRS dataAxisToSRSAxisMapping="2,1">EPSG:4326</SRS>
          <GeoTransform>{$transform}</GeoTransform>
        {$bands}</VRTDataset>

        XML;
    }

    private function hasWorldFile(string $path): bool
    {
        $base = preg_replace('/\.[^.\\\\\/]+$/', '', $path);
        foreach (self::WORLD_FILE_EXTENSIONS as $extension) {
            if (is_file("{$base}.{$extension}")) {
                return true;
            }
        }

        return is_file("{$path}.aux.xml");
    }

    /** The picture as GDAL reads it with its world file; a VRT with a CRS added when none came with it. */
    private function withCrs(GdalCommandRunner $gdal, string $path, string $originalName, string $workDirectory, int $index): string
    {
        $info = json_decode($gdal->run(['gdalinfo', '-json', $path], $workDirectory, (int) config('gdal.timeout')), true);
        $transform = $info['geoTransform'] ?? null;
        if (! is_array($transform) || count($transform) !== 6) {
            throw new RuntimeException("The world file uploaded with {$originalName} could not be read.");
        }
        if (! empty($info['coordinateSystem']['wkt'])) {
            return $path;
        }

        $prj = preg_replace('/\.[^.\\\\\/]+$/', '', $path).'.prj';
        if (is_file($prj)) {
            $srs = $prj;
        } else {
            // No .prj: degrees look like longitude/latitude, anything else is taken as UTM zone 51N
            // (Southern Leyte), the CRS drone software exports here.
            [$x, , , $y] = $transform;
            $srs = abs($x) <= 180 && abs($y) <= 90 ? 'EPSG:4326' : 'EPSG:32651';
        }

        $vrtPath = $workDirectory.DIRECTORY_SEPARATOR.sprintf('picture-%04d.vrt', $index);
        $gdal->run(['gdal_translate', '-q', '-of', 'VRT', '-a_srs', $srs, $path, $vrtPath], $workDirectory, (int) config('gdal.timeout'));

        return $vrtPath;
    }

    /** @return array{0: float, 1: float}|null [lat, lng] from the EXIF GPS tags */
    private function exifGps(string $path): ?array
    {
        $exif = $this->exif($path, 'GPS');
        if (! is_array($exif) || ! isset($exif['GPSLatitude'], $exif['GPSLongitude'])) {
            return null;
        }
        $degrees = function (array $parts): float {
            $value = 0.0;
            foreach (array_values($parts) as $i => $part) {
                [$top, $bottom] = array_pad(explode('/', (string) $part), 2, '1');
                $value += ((float) $bottom ? (float) $top / (float) $bottom : 0) / (60 ** $i);
            }

            return $value;
        };
        $lat = $degrees((array) $exif['GPSLatitude']) * (strtoupper($exif['GPSLatitudeRef'] ?? 'N') === 'S' ? -1 : 1);
        $lng = $degrees((array) $exif['GPSLongitude']) * (strtoupper($exif['GPSLongitudeRef'] ?? 'E') === 'W' ? -1 : 1);

        return [$lat, $lng];
    }

    /** EXIF tags of one section, or false; damaged or missing EXIF is not an error (the warning is muted). */
    private function exif(string $path, string $section): array|false
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }
        set_error_handler(fn () => true);
        try {
            return exif_read_data($path, $section);
        } finally {
            restore_error_handler();
        }
    }

    /** Focal length in pixels from the 35 mm equivalent (defined on the 43.27 mm full-frame diagonal). */
    private function focalFromExif(string $path, int $width, int $height): ?float
    {
        $exif = $this->exif($path, 'EXIF');
        $equivalent = is_array($exif) ? (float) ($exif['FocalLengthIn35mmFilm'] ?? 0) : 0.0;

        return $equivalent > 0 ? $equivalent * hypot($width, $height) / 43.27 : null;
    }
}
