<?php

namespace App\Console\Commands;

use App\Services\OsmPlaceLabelService;
use Illuminate\Console\Command;

class SyncMapPlaceLabels extends Command
{
    protected $signature = 'map:sync-place-labels';

    protected $description = 'Download OpenStreetMap place names (towns, villages, hamlets) for the satellite map labels';

    public function handle(OsmPlaceLabelService $labels): int
    {
        try {
            $count = $labels->sync();
        } catch (\Throwable $e) {
            $this->error('Could not download place names: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Stored {$count} OpenStreetMap place names.");

        return self::SUCCESS;
    }
}
