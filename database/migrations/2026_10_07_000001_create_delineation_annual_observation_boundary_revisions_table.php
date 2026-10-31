<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional: an earlier failed run (identifier too long) can leave an
        // empty, half-built table behind. Remove it so this migration can be re-run cleanly.
        if (Schema::hasTable('delineation_annual_observation_boundary_revisions')
            && DB::table('delineation_annual_observation_boundary_revisions')->doesntExist()) {
            Schema::drop('delineation_annual_observation_boundary_revisions');
        }

        Schema::create('delineation_annual_observation_boundary_revisions', function (Blueprint $table) {
            $table->id();
            // Explicit short names: the generated ones exceed MySQL's 64-character identifier limit.
            $table->foreignId('annual_observation_id')
                ->constrained('delineation_annual_observations', 'id', 'daobr_annual_observation_id_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('features');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'id', 'daobr_created_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['annual_observation_id', 'version'], 'daobr_observation_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delineation_annual_observation_boundary_revisions');
    }
};
