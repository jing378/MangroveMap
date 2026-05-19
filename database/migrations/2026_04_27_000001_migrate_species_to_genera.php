<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if species table exists
        if (Schema::hasTable('species')) {
            // Step 1: Add genus_id column if it doesn't exist (without constraint first)
            if (!Schema::hasColumn('mangrove_data', 'genus_id')) {
                Schema::table('mangrove_data', function (Blueprint $table) {
                    $table->unsignedBigInteger('genus_id')->nullable()->after('coverage_area_km2');
                });
            }

            // Step 2: Copy data from species_id to genus_id
            DB::statement('UPDATE mangrove_data SET genus_id = species_id WHERE species_id IS NOT NULL');

            // Step 3: Rename species table to genera
            Schema::rename('species', 'genera');

            // Step 4: Drop old species_id foreign key constraint
            Schema::table('mangrove_data', function (Blueprint $table) {
                $table->dropForeign(['species_id']);
            });

            // Step 5: Add new genus_id foreign key constraint
            Schema::table('mangrove_data', function (Blueprint $table) {
                $table->foreign('genus_id')->references('id')->on('genera')->onDelete('set null');
            });

            // Step 6: Drop species_id column
            Schema::table('mangrove_data', function (Blueprint $table) {
                $table->dropColumn('species_id');
            });
        }
    }

    public function down(): void
    {
        // Revert back to species table
        if (Schema::hasTable('genera')) {
            // Step 1: Add species_id column back
            Schema::table('mangrove_data', function (Blueprint $table) {
                $table->unsignedBigInteger('species_id')->nullable()->after('coverage_area_km2');
            });

            // Step 2: Copy data from genus_id back to species_id
            DB::statement('UPDATE mangrove_data SET species_id = genus_id WHERE genus_id IS NOT NULL');

            // Step 3: Rename genera table back to species
            Schema::rename('genera', 'species');

            // Step 4: Drop genus_id foreign key
            Schema::table('mangrove_data', function (Blueprint $table) {
                $table->dropForeign(['genus_id']);
            });

            // Step 5: Add species_id foreign key constraint back
            Schema::table('mangrove_data', function (Blueprint $table) {
                $table->foreign('species_id')->references('id')->on('species')->onDelete('set null');
            });

            // Step 6: Drop genus_id column
            Schema::table('mangrove_data', function (Blueprint $table) {
                $table->dropColumn('genus_id');
            });
        }
    }
};
