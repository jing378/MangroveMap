<?php

namespace Tests\Unit;

use App\Support\ExtentOverlap;
use PHPUnit\Framework\TestCase;

class ExtentOverlapTest extends TestCase
{
    private const LAT = 10.0;

    private const LNG = 124.0;

    /** A square ring in [lng, lat], $size metres wide, its south-west corner $east/$north metres from the origin. */
    private function square(float $east, float $north, float $size): array
    {
        $lat = fn (float $m) => self::LAT + $m / ExtentOverlap::METRES_PER_DEGREE;
        $lng = fn (float $m) => self::LNG + $m / (ExtentOverlap::METRES_PER_DEGREE * cos(deg2rad(self::LAT)));

        return [
            [$lng($east), $lat($north)],
            [$lng($east + $size), $lat($north)],
            [$lng($east + $size), $lat($north + $size)],
            [$lng($east), $lat($north + $size)],
        ];
    }

    public function test_half_overlapping_squares_score_one_third_iou(): void
    {
        // 100 m × 100 m squares (1 ha each), shifted by 50 m: overlap 0.5 ha, union 1.5 ha.
        $scores = ExtentOverlap::compare([[$this->square(0, 0, 100)]], [[$this->square(50, 0, 100)]]);

        $this->assertEqualsWithDelta(1.0, $scores['predicted_ha'], 0.002);
        $this->assertEqualsWithDelta(1.0, $scores['reference_ha'], 0.002);
        $this->assertEqualsWithDelta(0.5, $scores['overlap_ha'], 0.002);
        $this->assertEqualsWithDelta(1 / 3, $scores['iou'], 0.002);
        $this->assertEqualsWithDelta(0.5, $scores['precision'], 0.002);
        $this->assertEqualsWithDelta(0.5, $scores['recall'], 0.002);
        $this->assertEqualsWithDelta(0.5, $scores['f1'], 0.002);
    }

    public function test_identical_areas_match_perfectly_and_disjoint_ones_not_at_all(): void
    {
        $same = ExtentOverlap::compare([[$this->square(0, 0, 40)]], [[$this->square(0, 0, 40)]]);
        $this->assertEqualsWithDelta(1.0, $same['iou'], 0.001);

        $apart = ExtentOverlap::compare([[$this->square(0, 0, 40)]], [[$this->square(200, 0, 40)]]);
        $this->assertSame(0.0, $apart['iou']);
        $this->assertSame(0.0, $apart['recall']);
    }

    public function test_overlapping_shapes_count_once_and_holes_are_left_out(): void
    {
        // Two overlapping 100 m squares on one side: their union is 1.5 ha, not 2.
        $union = ExtentOverlap::compare([[$this->square(0, 0, 100)], [$this->square(50, 0, 100)]], []);
        $this->assertEqualsWithDelta(1.5, $union['predicted_ha'], 0.003);

        // A 100 m square with a 50 m hole: 0.75 ha.
        $holed = ExtentOverlap::compare([[$this->square(0, 0, 100), $this->square(25, 25, 50)]], []);
        $this->assertEqualsWithDelta(0.75, $holed['predicted_ha'], 0.003);
    }

    public function test_reads_stored_features_and_geojson(): void
    {
        $ring = $this->square(0, 0, 100);
        // Stored features are Leaflet [lat, lng]; only polygon ("area") features count.
        $features = [
            ['type' => 'area', 'coords' => array_map(fn ($p) => [$p[1], $p[0]], $ring)],
            ['type' => 'marker', 'coords' => [[10, 124]]],
        ];
        $fromFeatures = ExtentOverlap::shapesFromFeatures($features);
        $this->assertCount(1, $fromFeatures);

        $geoJson = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[...$ring, $ring[0]]]]],
        ]];
        $fromGeoJson = ExtentOverlap::shapesFromGeoJson($geoJson);

        $scores = ExtentOverlap::compare($fromFeatures, $fromGeoJson);
        $this->assertEqualsWithDelta(1.0, $scores['iou'], 0.001);
        $this->assertEqualsWithDelta(1.0, $scores['reference_ha'], 0.002);
    }
}
