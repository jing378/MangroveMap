<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mangrove_data', function (Blueprint $table) {
            $table->id();
            $table->string('region');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('coverage_area_km2', 15, 4);
            $table->foreignId('genus_id')->nullable()->constrained('genera')->onDelete('set null');
            $table->enum('health_status', ['healthy', 'degraded', 'recovering'])->default('healthy');
            $table->integer('degradation_level')->nullable(); // 0-100
            $table->string('satellite_image_url')->nullable();
            $table->timestamp('observation_date')->useCurrent();
            $table->string('data_source')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->timestamps();

            $table->index('region');
            $table->index('observation_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mangrove_data');
    }
};
