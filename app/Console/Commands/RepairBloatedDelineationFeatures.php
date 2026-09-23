<?php

namespace App\Console\Commands;

use App\Http\Controllers\EndUserController;
use App\Models\Delineation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairBloatedDelineationFeatures extends Command
{
    protected $signature = 'delineations:repair-features {--dry-run : List affected rows without updating}';

    protected $description = 'Shrink delineation features JSON bloated by accidental full-map saves';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $ids = DB::table('delineations')
            ->whereRaw('JSON_LENGTH(features) > 20')
            ->orderByDesc('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No bloated delineation feature arrays found.');

            return self::SUCCESS;
        }

        $this->warn('Found '.$ids->count().' delineation(s) with more than 20 features.');

        foreach ($ids as $id) {
            $length = (int) DB::table('delineations')->where('id', $id)->value(DB::raw('JSON_LENGTH(features)'));

            if ($dryRun) {
                $this->line("  [dry-run] id={$id} feature_count≈{$length}");

                continue;
            }

            @ini_set('memory_limit', '512M');

            $delineation = Delineation::query()->find($id);
            if (! $delineation) {
                continue;
            }

            $clean = EndUserController::sanitizeFeaturesForMap($delineation->features);
            $delineation->update(['features' => $clean]);
            $this->info("  id={$id}: {$length} → ".count($clean).' feature(s)');
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Repair complete.');

        return self::SUCCESS;
    }
}
