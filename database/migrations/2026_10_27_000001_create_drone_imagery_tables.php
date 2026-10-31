<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drone_imagery_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('queued');
            $table->string('source_type', 20);
            $table->text('source_crs')->nullable();
            $table->json('bounds')->nullable();
            $table->json('metadata')->nullable();
            $table->string('mosaic_path')->nullable();
            $table->string('tiles_path')->nullable();
            $table->unsignedTinyInteger('zoom_min')->default(0);
            $table->unsignedTinyInteger('zoom_max')->default(22);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('drone_imagery_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drone_imagery_layer_id')
                ->constrained('drone_imagery_layers')
                ->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['drone_imagery_layer_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drone_imagery_files');
        Schema::dropIfExists('drone_imagery_layers');
    }
};
