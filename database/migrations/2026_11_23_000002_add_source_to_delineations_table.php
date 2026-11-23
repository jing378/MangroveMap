<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a delineation came from: "manual" (hand-drawn on the map) or "drone" (a drone survey's AI-drawn areas,
 * corrected and confirmed by the owner). Kept on the record so it survives the drone layer being deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delineations', function (Blueprint $table): void {
            $table->string('source', 16)->default('manual')->after('drone_imagery_layer_id')->index();
        });

        // Existing records: linked to a drone layer, or still carrying the publisher's default notes from one
        // whose layer has since been deleted.
        DB::table('delineations')
            ->whereNotNull('drone_imagery_layer_id')
            ->orWhere('notes', 'like', 'Mangrove areas from drone survey "%')
            ->update(['source' => 'drone']);
    }

    public function down(): void
    {
        Schema::table('delineations', function (Blueprint $table): void {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
