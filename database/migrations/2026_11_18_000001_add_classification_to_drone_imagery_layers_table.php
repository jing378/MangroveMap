<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Genus classification gets its own status and results (ClassifyDroneImagery), separate from the AI
 * delineation (SegmentDroneImagery). Results stored with the delineation before the split are copied over;
 * the copies left in segmentation_stats are ignored from now on.
 */
return new class extends Migration
{
    private const GENUS_KEYS = ['genus_suggestion', 'canopy_count', 'canopies_by_genus'];

    public function up(): void
    {
        Schema::table('drone_imagery_layers', function (Blueprint $table) {
            // null = never run (or no genus model); queued | processing | ready | failed
            $table->string('classification_status', 20)->nullable()->after('segmented_at');
            $table->json('classification_stats')->nullable()->after('classification_status');
            $table->text('classification_error')->nullable()->after('classification_stats');
            $table->timestamp('classified_at')->nullable()->after('classification_error');
        });

        DB::table('drone_imagery_layers')
            ->where('segmentation_status', 'ready')
            ->whereNotNull('segmentation_stats')
            ->orderBy('id')
            ->select(['id', 'segmentation_stats', 'segmented_at'])
            ->chunkById(100, function ($layers) {
                foreach ($layers as $layer) {
                    $stats = json_decode((string) $layer->segmentation_stats, true);
                    if (! is_array($stats) || ! array_key_exists('genus_suggestion', $stats) || $stats['genus_suggestion'] === null) {
                        continue;
                    }

                    $classification = array_intersect_key($stats, array_flip(self::GENUS_KEYS));
                    $failed = is_array($stats['genus_suggestion']) && isset($stats['genus_suggestion']['error']);
                    DB::table('drone_imagery_layers')->where('id', $layer->id)->update([
                        'classification_status' => $failed ? 'failed' : 'ready',
                        'classification_stats' => json_encode($classification, JSON_UNESCAPED_SLASHES),
                        'classification_error' => $failed ? mb_substr((string) $stats['genus_suggestion']['error'], 0, 2000) : null,
                        'classified_at' => $layer->segmented_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('drone_imagery_layers', function (Blueprint $table) {
            $table->dropColumn([
                'classification_status',
                'classification_stats',
                'classification_error',
                'classified_at',
            ]);
        });
    }
};
