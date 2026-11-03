<?php

namespace Tests\Unit;

use App\Services\SegmentationFeatureBuilder;
use PHPUnit\Framework\TestCase;

class SegmentationFeatureBuilderTest extends TestCase
{
    /** Square ring of the given side (metres, approx.) at lng/lat origin, GeoJSON [lng, lat] order. */
    private function square(float $lng, float $lat, float $sideMetres): array
    {
        $dLat = $sideMetres / 110540;
        $dLng = $sideMetres / (111320 * cos(deg2rad($lat)));

        return [[$lng, $lat], [$lng + $dLng, $lat], [$lng + $dLng, $lat + $dLat], [$lng, $lat + $dLat], [$lng, $lat]];
    }

    private function collection(array $geometries): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => array_map(fn ($geometry) => ['type' => 'Feature', 'properties' => [], 'geometry' => $geometry], $geometries),
        ];
    }

    public function test_drops_specks_sorts_by_area_and_swaps_to_lat_lng(): void
    {
        $geoJson = $this->collection([
            ['type' => 'Polygon', 'coordinates' => [$this->square(125.0, 10.0, 10)]],    // ~100 m²
            ['type' => 'Polygon', 'coordinates' => [$this->square(125.01, 10.0, 2)]],    // ~4 m² speck
            ['type' => 'Polygon', 'coordinates' => [$this->square(125.02, 10.0, 30)]],   // ~900 m²
        ]);

        $result = (new SegmentationFeatureBuilder)->build($geoJson, 25, 20, 800);

        $this->assertSame(2, $result['polygon_count']);
        $this->assertCount(2, $result['features']);
        $this->assertEqualsWithDelta(900, $result['features'][0]['area_m2'], 10);
        $this->assertEqualsWithDelta(100, $result['features'][1]['area_m2'], 2);
        $this->assertSame('area', $result['features'][0]['type']);
        $this->assertSame([10.0, 125.02], $result['features'][0]['coords'][0]);
    }

    public function test_keeps_outer_rings_only_and_limits_feature_count(): void
    {
        $outer = $this->square(125.0, 10.0, 40);
        $hole = $this->square(125.0001, 10.0001, 5);
        $geoJson = $this->collection([
            ['type' => 'Polygon', 'coordinates' => [$outer, $hole]],
            ['type' => 'MultiPolygon', 'coordinates' => [
                [$this->square(125.03, 10.0, 20)],
                [$this->square(125.04, 10.0, 15)],
            ]],
        ]);

        $result = (new SegmentationFeatureBuilder)->build($geoJson, 25, 2, 800);

        $this->assertSame(3, $result['polygon_count']);
        $this->assertCount(2, $result['features']);
        $this->assertCount(5, $result['features'][0]['coords']);
    }

    public function test_thins_large_rings_and_keeps_them_closed(): void
    {
        $ring = [];
        for ($i = 0; $i < 1000; $i++) {
            $angle = 2 * M_PI * $i / 1000;
            $ring[] = [125.0 + 0.001 * cos($angle), 10.0 + 0.001 * sin($angle)];
        }
        $ring[] = $ring[0];

        $result = (new SegmentationFeatureBuilder)->build(
            $this->collection([['type' => 'Polygon', 'coordinates' => [$ring]]]),
            25, 20, 100
        );

        $coords = $result['features'][0]['coords'];
        $this->assertCount(100, $coords);
        $this->assertSame($coords[0], end($coords));
    }
}
