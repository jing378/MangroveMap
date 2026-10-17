<?php

namespace Tests\Feature;

use App\Http\Controllers\EndUserController;
use PHPUnit\Framework\TestCase;

class DelineationGenusPersistenceTest extends TestCase
{
    public function test_map_payload_preserves_selected_genera_and_location(): void
    {
        $features = [[
            'type' => 'area',
            'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01]],
            'location' => 'North Shore',
            'genera' => ['Rhizophora', 'Avicennia'],
        ]];

        $this->assertSame($features, EndUserController::sanitizeFeaturesForMap($features));
    }
}