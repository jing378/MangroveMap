<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isMySql = DB::connection()->getDriverName() === 'mysql';

        Schema::create('admin_boundaries', function (Blueprint $table) use ($isMySql) {
            $table->id();
            $table->string('adm4_pcode', 20)->unique();
            $table->string('adm4_name');
            $table->string('adm3_pcode', 20);
            $table->string('adm3_name');
            $table->string('adm2_pcode', 20);
            $table->string('adm2_name');
            $table->string('dataset_version', 32);
            $table->date('dataset_valid_on')->nullable();

            if ($isMySql) {
                $table->geometry('geom', 'MULTIPOLYGON', 4326);
                $table->spatialIndex('geom');
            } else {
                $table->longText('geom');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_boundaries');
    }
};
