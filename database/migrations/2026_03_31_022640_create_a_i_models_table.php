<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('model_type', ['segmentation', 'classification', 'change_detection']);
            $table->string('version');
            $table->decimal('accuracy', 5, 4);
            $table->timestamp('training_date')->useCurrent();
            $table->integer('dataset_size');
            $table->enum('status', ['training', 'completed', 'failed'])->default('training');
            $table->text('description')->nullable();
            $table->string('model_path')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};