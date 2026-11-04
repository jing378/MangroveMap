<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drone_imagery_layers', function (Blueprint $table) {
            // status gains awaiting_review (prepared, waiting for the owner) and finalizing (edits applied, tiling).
            $table->string('preview_path')->nullable()->after('mosaic_path');
            // WGS84 bounds of the unedited mosaic; `bounds` holds the final (cropped/shifted) extent.
            $table->json('preview_bounds')->nullable()->after('preview_path');
            // { mode: remove|keep, polygons: [[[lng, lat], ...], ...], offset: { east_m, north_m } }
            $table->json('edits')->nullable()->after('preview_bounds');
            // The edits the current tiles were built from, to tell the owner about unapplied changes.
            $table->json('applied_edits')->nullable()->after('edits');
            $table->string('final_mosaic_path')->nullable()->after('applied_edits');
            $table->decimal('display_opacity', 3, 2)->default(0.78)->after('zoom_max');
            $table->timestamp('reviewed_at')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('drone_imagery_layers', function (Blueprint $table) {
            $table->dropColumn([
                'preview_path',
                'preview_bounds',
                'edits',
                'applied_edits',
                'final_mosaic_path',
                'display_opacity',
                'reviewed_at',
            ]);
        });
    }
};
