<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A drone survey can re-survey an area that is already mapped. Its delineation then points at that area: once
 * approved, its boundary is copied onto the area as that year's observation (DelineationReassessment), and the
 * survey's own record is no longer drawn or counted as an area of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delineations', function (Blueprint $table): void {
            $table->foreignId('reassesses_delineation_id')
                ->nullable()
                ->after('source')
                ->constrained('delineations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delineations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reassesses_delineation_id');
        });
    }
};
