<?php

namespace App\Console\Commands;

use App\Models\Delineation;
use App\Services\BoundaryResolver;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-run the barangay lookup for saved areas without a matched location ("outside boundaries" or
 * "pending"), e.g. after importing boundaries or after coastal areas started matching the nearest
 * barangay (BoundaryResolver::COASTAL_TOLERANCE_METRES). Matched areas are left as they are.
 */
class ResolveDelineationLocations extends Command
{
    protected $signature = 'delineations:resolve-locations
        {--id=* : Only these delineation ids}
        {--dry-run : List the changes without saving them}';

    protected $description = 'Resolve the barangay of delineation areas that have no matched location yet';

    public function handle(BoundaryResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        $ids = array_filter(array_map('intval', (array) $this->option('id')));

        Delineation::query()
            ->select(['id', 'name', 'features'])
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('id')
            ->chunkById(50, function ($delineations) use ($resolver, $dryRun, &$updated) {
                foreach ($delineations as $delineation) {
                    $features = $delineation->features ?? [];
                    $unresolved = array_filter(
                        $features,
                        fn ($feature) => is_array($feature) && ($feature['location_status'] ?? null) !== 'matched'
                    );
                    if ($unresolved === []) {
                        continue;
                    }

                    try {
                        $resolved = $resolver->resolveFeatures(array_intersect_key($features, $unresolved));
                    } catch (Throwable $exception) {
                        $this->warn("#{$delineation->id} {$delineation->name}: skipped ({$exception->getMessage()})");

                        continue;
                    }

                    $changed = 0;
                    foreach (array_keys($unresolved) as $position => $index) {
                        $feature = $resolved[$position];
                        if (($feature['location_status'] ?? null) !== 'matched') {
                            continue;
                        }
                        $features[$index] = [
                            ...$features[$index],
                            'location' => $feature['location'],
                            'location_status' => $feature['location_status'],
                            'location_details' => $feature['location_details'],
                        ];
                        $changed++;
                    }
                    if ($changed === 0) {
                        continue;
                    }

                    $locations = collect($features)->pluck('location')->filter()->unique()->implode('; ');
                    $this->line("#{$delineation->id} {$delineation->name}: {$changed} area(s) → {$locations}");
                    if (! $dryRun) {
                        // Only the location fields change: no revision, review state untouched.
                        $delineation->forceFill(['features' => $features])->saveQuietly();
                    }
                    $updated++;
                }
            });

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} delineation(s).");

        return self::SUCCESS;
    }
}
