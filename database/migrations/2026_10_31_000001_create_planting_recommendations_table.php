<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planting_recommendations', function (Blueprint $table) {
            $table->id();
            // Expert who authored the recommendation.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Delineation the area was selected from, if any; the geometry is copied into `features`.
            $table->foreignId('delineation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('features');
            $table->string('suitability', 32);
            $table->json('recommended_genera');
            $table->text('recommendation');
            $table->text('site_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planting_recommendations');
    }
};
