<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delineation_annual_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delineation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->date('observation_date');
            $table->decimal('coverage_area_ha', 14, 4);
            $table->json('genus_distribution')->nullable();
            $table->string('source');
            $table->timestamps();

            $table->unique(['delineation_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delineation_annual_observations');
    }
};
