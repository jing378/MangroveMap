<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('mangrove_data_id')->nullable();
            $table->enum('analysis_type', ['classification', 'change_detection', 'damage_assessment']);
            $table->string('image_url')->nullable();
            $table->string('species_detected')->nullable();
            $table->decimal('classification_confidence', 5, 4)->nullable();
            $table->boolean('detected_damage')->default(false);
            $table->text('recommendations')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('results')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            $table->foreign('mangrove_data_id')
                ->references('id')
                ->on('mangrove_data')
                ->onDelete('set null');
            
            // Indexes
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis');
    }
};