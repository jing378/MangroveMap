<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delineations', function (Blueprint $table) {
            // The drone layer whose mangrove areas this delineation was submitted from. A resident's layer is
            // shared on the map once this delineation is approved; the delineation outlives a deleted layer.
            $table->foreignId('drone_imagery_layer_id')->nullable()->after('user_id')
                ->constrained('drone_imagery_layers')->nullOnDelete();
        });

        Schema::table('drone_imagery_layers', function (Blueprint $table) {
            // { name, genera[], notes } given at "Confirm & submit"; turned into the delineation once the
            // imagery is tiled (FinalizeDroneImagery), then cleared.
            $table->json('delineation_details')->nullable()->after('segmented_at');
        });
    }

    public function down(): void
    {
        Schema::table('delineations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drone_imagery_layer_id');
        });

        Schema::table('drone_imagery_layers', function (Blueprint $table) {
            $table->dropColumn('delineation_details');
        });
    }
};
