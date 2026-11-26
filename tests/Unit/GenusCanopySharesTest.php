<?php

namespace Tests\Unit;

use App\Models\DroneImageryLayer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenusCanopySharesTest extends TestCase
{
    private function layer(?string $status, mixed $stats): DroneImageryLayer
    {
        return (new DroneImageryLayer)->forceFill([
            'classification_status' => $status,
            'classification_stats' => $stats,
        ]);
    }

    #[Test]
    public function it_lists_each_genus_share_of_the_recognised_canopy_largest_first(): void
    {
        $layer = $this->layer('ready', [
            'canopy_count' => 30,
            'genus_suggestion' => [
                'labeled_percent' => 78.1,
                'genera' => [
                    ['genus' => 'avicennia', 'share_percent' => 30.94],
                    ['genus' => 'Sonneratia', 'share_percent' => 67.16],
                    ['genus' => 'Rhizophora', 'share_percent' => 1.9],
                    ['genus' => 'Nypa', 'share_percent' => 0],
                ],
            ],
        ]);

        $this->assertSame([
            'genera' => [
                ['genus' => 'Sonneratia', 'percent' => 67.2],
                ['genus' => 'Avicennia', 'percent' => 30.9],
                ['genus' => 'Rhizophora', 'percent' => 1.9],
            ],
            'recognised_percent' => 78.1,
            'canopy_count' => 30,
        ], $layer->genusCanopyShares());
    }

    #[Test]
    public function it_has_nothing_until_the_classification_is_ready_with_a_genus(): void
    {
        $genera = ['genus_suggestion' => ['genera' => [['genus' => 'Rhizophora', 'share_percent' => 100]]]];

        $this->assertNull($this->layer('processing', $genera)->genusCanopyShares());
        $this->assertNull($this->layer(null, null)->genusCanopyShares());
        $this->assertNull($this->layer('ready', ['genus_suggestion' => ['error' => 'Model failed']])->genusCanopyShares());
        $this->assertNull($this->layer('ready', ['genus_suggestion' => ['genera' => []]])->genusCanopyShares());
    }
}
