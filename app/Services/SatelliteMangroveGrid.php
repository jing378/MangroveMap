<?php

namespace App\Services;

use App\Support\ExtentOverlap;

/**
 * The Global Mangrove Watch pixels behind the map's "Mangrove extent by year" (config map.mangrove_history.path,
 * built by scripts/gmw/build_mangrove_history.py), read on the server to check mapped areas against the
 * satellite: how much of an area the satellite also saw as mangrove in a given year.
 *
 * The file stores the pixels as runs: 16-byte records of uint16 row, col, length, municipality and two uint32
 * bit sets of the years (little endian), on a grid given by west/north/pixel (degrees, about 30 m).
 */
class SatelliteMangroveGrid
{
    /** Scanlines per grid row: each 30 m pixel row is sampled at 10 heights (3 m apart). */
    private const LINES_PER_ROW = 10;

    private ?array $history = null;

    /** @var array<int, array<int, array{0: int, 1: int, 2: int, 3: int}>>|null runs by row: [col, length, low bits, high bits] */
    private ?array $rows = null;

    private bool $loaded = false;

    public function available(): bool
    {
        return $this->load() !== null;
    }

    /** @return array{source: string, source_url: ?string, license: ?string, years: array<int, int>}|null */
    public function meta(): ?array
    {
        $history = $this->load();

        return $history === null ? null : [
            'source' => (string) ($history['source'] ?? 'Global Mangrove Watch'),
            'source_url' => $history['source_url'] ?? null,
            'license' => $history['license'] ?? null,
            'years' => array_values($history['years']),
        ];
    }

    /** The satellite year used for $year: the same one, else the nearest year the file has. */
    public function nearestYear(int $year): ?int
    {
        $years = $this->load()['years'] ?? null;
        if (! is_array($years) || $years === []) {
            return null;
        }

        return max(min($year, (int) end($years)), (int) reset($years));
    }

    /**
     * How much of the shapes ([lng, lat] rings, see ExtentOverlap) the satellite saw as mangrove in $year.
     *
     * @return array{year: int, area_ha: float, satellite_ha: float, agreement: ?float}|null agreement is a 0-1 share
     *                                                                                       of the area; null without the file or without any area.
     */
    public function agreement(array $shapes, int $year): ?array
    {
        $year = $this->nearestYear($year);
        $history = $year === null ? null : $this->history($shapes);
        if ($history === null) {
            return null;
        }
        $satelliteHa = $history['hectares'][array_search($year, $history['years'], true)];

        return [
            'year' => $year,
            'area_ha' => $history['area_ha'],
            'satellite_ha' => $satelliteHa,
            'agreement' => $history['area_ha'] > 0 ? round(min(1, $satelliteHa / $history['area_ha']), 4) : null,
        ];
    }

    /**
     * Hectares the satellite saw as mangrove inside the shapes in every year it has (one pass over the pixels),
     * for an area's "Changes over time" ("Previous data") and for agreement().
     *
     * @return array{years: array<int, int>, hectares: array<int, float>, area_ha: float}|null null without the
     *                                                                                         file or without any area
     */
    public function history(array $shapes): ?array
    {
        $history = $this->load();
        $edges = ExtentOverlap::edges($shapes);
        $bounds = ExtentOverlap::yBounds($edges);
        if ($history === null || $bounds === null) {
            return null;
        }

        $grid = $history['grid'];
        $west = (float) $grid['west'];
        $north = (float) $grid['north'];
        $pixel = (float) $grid['pixel'];
        $yearCount = count($history['years']);
        $step = $pixel / self::LINES_PER_ROW;
        [$minLat, $maxLat] = $bounds;

        $areaM2 = 0.0;
        $yearM2 = array_fill(0, $yearCount, 0.0);
        $rows = $this->rows();
        // Lines on the grid's sub-row centres, so each line sits wholly in one pixel row.
        $first = (int) floor(($north - $maxLat) / $step);
        $last = (int) ceil(($north - $minLat) / $step);
        for ($line = $first; $line <= $last; $line++) {
            $lat = $north - ($line + 0.5) * $step;
            if ($lat < $minLat || $lat > $maxLat) {
                continue;
            }
            $polygon = ExtentOverlap::intervalsAt($edges, $lat);
            if ($polygon === []) {
                continue;
            }
            // Degrees of longitude and latitude to square metres at this latitude.
            $scale = cos(deg2rad($lat)) * ExtentOverlap::METRES_PER_DEGREE * $step * ExtentOverlap::METRES_PER_DEGREE;
            $areaM2 += ExtentOverlap::length($polygon) * $scale;

            // Runs in a row never overlap, so each run's share of the area adds to the years it was mangrove.
            foreach ($line < 0 ? [] : ($rows[intdiv($line, self::LINES_PER_ROW)] ?? []) as [$col, $length, $low, $high]) {
                $inside = ExtentOverlap::intersectionLength($polygon, [[$west + $col * $pixel, $west + ($col + $length) * $pixel]]);
                if ($inside <= 0) {
                    continue;
                }
                $m2 = $inside * $scale;
                for ($bit = 0; $bit < $yearCount; $bit++) {
                    if ((($bit < 32 ? $low >> $bit : $high >> ($bit - 32)) & 1) === 1) {
                        $yearM2[$bit] += $m2;
                    }
                }
            }
        }

        return [
            'years' => array_map('intval', array_values($history['years'])),
            'hectares' => array_map(fn (float $m2) => round($m2 / 10000, 4), $yearM2),
            'area_ha' => round($areaM2 / 10000, 4),
        ];
    }

    private function load(): ?array
    {
        if ($this->loaded) {
            return $this->history;
        }
        $this->loaded = true;

        $path = config('map.mangrove_history.path');
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }
        $history = json_decode((string) file_get_contents($path), true);
        if (! is_array($history)
            || ! is_array($history['years'] ?? null) || $history['years'] === []
            || ! is_array($history['grid'] ?? null) || ! isset($history['grid']['west'], $history['grid']['north'], $history['grid']['pixel'])
            || ! is_string($history['runs'] ?? null)) {
            return null;
        }

        return $this->history = $history;
    }

    /** @return array<int, array<int, array{0: int, 1: int, 2: int, 3: int}>> */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $bytes = base64_decode((string) ($this->history['runs'] ?? ''), true) ?: '';
        $rows = [];
        for ($at = 0; $at + 16 <= strlen($bytes); $at += 16) {
            $record = unpack('vrow/vcol/vlength/vplace/Vlow/Vhigh', $bytes, $at);
            $rows[$record['row']][] = [$record['col'], $record['length'], $record['low'], $record['high']];
        }

        return $this->rows = $rows;
    }
}
