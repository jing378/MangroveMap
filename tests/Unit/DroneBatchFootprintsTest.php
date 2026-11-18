<?php

namespace Tests\Unit;

use App\Services\BoundaryResolver;
use App\Services\DroneBatchFootprints;
use PHPUnit\Framework\TestCase;

class DroneBatchFootprintsTest extends TestCase
{
    private function box(float $west, float $east, float $south = 10.0, float $north = 10.01): array
    {
        return compact('west', 'south', 'east', 'north');
    }

    private function footprints(): DroneBatchFootprints
    {
        return new DroneBatchFootprints(new BoundaryResolver);
    }

    public function test_adjacent_and_overlapping_files_form_one_group(): void
    {
        $groups = $this->footprints()->group([
            'a.tif' => $this->box(124.000, 124.010),
            'b.tif' => $this->box(124.008, 124.020),
            'c.tif' => $this->box(124.020, 124.030),
        ], 1000);

        $this->assertCount(1, $groups);
    }

    public function test_files_chained_through_a_neighbour_stay_together(): void
    {
        // a and c are ~2 km apart, but b sits between them within 1 km of each.
        $groups = $this->footprints()->group([
            'a.tif' => $this->box(124.000, 124.001),
            'b.tif' => $this->box(124.009, 124.010),
            'c.tif' => $this->box(124.018, 124.019),
        ], 1000);

        $this->assertCount(1, $groups);
    }

    public function test_files_from_different_towns_are_split_largest_group_first(): void
    {
        $groups = $this->footprints()->group([
            'bontoc.tif' => $this->box(124.110, 124.120),
            'macrohon_1.tif' => $this->box(124.000, 124.010),
            'macrohon_2.tif' => $this->box(124.010, 124.020),
        ], 1000);

        $this->assertSame([['macrohon_1.tif', 'macrohon_2.tif'], ['bontoc.tif']], $groups);
    }

    public function test_gap_is_measured_in_ground_metres(): void
    {
        // 0.01° of longitude at 10°N is ~1096 m.
        $gap = $this->footprints()->gapMetres($this->box(124.00, 124.01), $this->box(124.02, 124.03));

        $this->assertEqualsWithDelta(1096, $gap, 5);
    }
}
