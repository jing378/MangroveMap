<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planting_recommendations', function (Blueprint $table) {
            $table->text('recommendation')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('planting_recommendations')
            ->whereNull('recommendation')
            ->update(['recommendation' => '']);

        Schema::table('planting_recommendations', function (Blueprint $table) {
            $table->text('recommendation')->nullable(false)->change();
        });
    }
};
