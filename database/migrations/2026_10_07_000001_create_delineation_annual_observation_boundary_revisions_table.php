<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delineation_annual_observation_boundary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_observation_id')
                ->constrained('delineation_annual_observations')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('features');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['annual_observation_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delineation_annual_observation_boundary_revisions');
    }
};