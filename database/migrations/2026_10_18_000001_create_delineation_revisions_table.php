<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delineation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delineation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['delineation_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delineation_revisions');
    }
};