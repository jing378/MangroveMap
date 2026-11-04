<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delineation_annual_observations', function (Blueprint $table) {
            // Genus names selected on the boundary when an expert records an observed change.
            $table->json('genera_present')->nullable()->after('genus_distribution');
        });
    }

    public function down(): void
    {
        Schema::table('delineation_annual_observations', function (Blueprint $table) {
            $table->dropColumn('genera_present');
        });
    }
};
