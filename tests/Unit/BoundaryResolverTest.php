<?php

namespace Tests\Unit;

use App\Services\BoundaryResolver;
use PHPUnit\Framework\TestCase;

class BoundaryResolverTest extends TestCase
{
    public function test_point_coordinates_are_converted_from_latitude_longitude_to_geojson_order(): void
    {
        $geometry = (new BoundaryResolver())->geometryForFeature([
            'type' => 'point',
            'coords' => [10.26302569, 125.24250919],
        ]);

        $this->assertSame([
            'type' => 'Point',
            'coordinates' => [125.24250919, 10.26302569],
        ], $geometry);
    }

    public function test_area_coordinates_are_closed_and_reordered_for_geojson(): void
    {
        $geometry = (new BoundaryResolver())->geometryForFeature([
            'type' => 'area',
            'coords' => [[10.0, 125.0], [10.0, 125.1], [10.1, 125.1]],
        ]);

        $this->assertSame('Polygon', $geometry['type']);
        $this->assertSame([
            [125.0, 10.0],
            [125.1, 10.0],
            [125.1, 10.1],
            [125.0, 10.0],
        ], $geometry['coordinates'][0]);
    }
}
