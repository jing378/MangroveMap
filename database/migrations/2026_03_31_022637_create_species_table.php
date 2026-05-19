<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genera', function (Blueprint $table) {
            $table->id();
            $table->string('common_name');
            $table->string('scientific_name')->unique();
            $table->string('genus');
            $table->string('family');
            $table->text('description')->nullable();
            $table->enum('conservation_status', ['Least Concern', 'Vulnerable', 'Endangered', 'Critically Endangered'])->default('Least Concern');
            $table->text('geographical_distribution')->nullable();
            $table->string('salinity_tolerance')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genera');
    }
};
