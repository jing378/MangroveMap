<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tile Explorer: GeoTIFFs uploaded only to be cut into tiles (never on the map or reviewed).
        Schema::create('tile_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('original_name');
            $table->string('stored_path');
            $table->unsignedBigInteger('file_size');
            // Read from the file on the first tiling run.
            $table->string('crs', 120)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // AI mangrove areas used to label tiles: null (not run yet), ready, failed.
            $table->string('areas_status', 20)->nullable();
            $table->text('areas_error')->nullable();
            $table->timestamps();
        });

        // The source cut at one tile size; the tile list itself is tiles.json next to the tile files.
        Schema::create('tile_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tile_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tile_size');
            $table->string('status', 20)->default('queued'); // queued, detecting, processing, ready, failed
            $table->unsignedInteger('tile_count')->default(0);
            $table->unsignedInteger('mangrove_tile_count')->default(0);
            $table->unsignedSmallInteger('columns')->nullable();
            $table->unsignedSmallInteger('rows')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['tile_source_id', 'tile_size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tile_sets');
        Schema::dropIfExists('tile_sources');
    }
};
