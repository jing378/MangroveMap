<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Which extent model labelled the tiles ("roboflow", "unet", "yolo"); null for uploads labelled before this. */
    public function up(): void
    {
        Schema::table('tile_sources', function (Blueprint $table) {
            $table->string('areas_model', 20)->nullable()->after('areas_error');
        });
    }

    public function down(): void
    {
        Schema::table('tile_sources', function (Blueprint $table) {
            $table->dropColumn('areas_model');
        });
    }
};
