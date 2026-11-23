<?php

namespace Tests\Feature;

use App\Services\BoundaryResolver;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Needs the app (validation messages), unlike tests/Unit/BoundaryResolverTest. */
class BoundaryResolverGeometryTest extends TestCase
{
    public function test_an_area_of_two_distinct_points_is_refused_before_it_reaches_mysql(): void
    {
        // A, B, A: three positions but a flat shape, seen while an area was still being drawn. MySQL's
        // ST_GeomFromGeoJSON failed on it with a 500; it is now a clear validation error.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('An area needs at least 3 different points.');

        (new BoundaryResolver)->geometryForFeature([
            'type' => 'area',
            'coords' => [[10.344564452483073, 124.97545603662732], [10.344557522509357, 124.97546073049308], [10.344564452483073, 124.97545603662732]],
        ]);
    }
}
