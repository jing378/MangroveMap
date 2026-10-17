<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->timestamp('training_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('ai_models')->whereNull('training_date')->update(['training_date' => now()]);

        Schema::table('ai_models', function (Blueprint $table) {
            $table->timestamp('training_date')->nullable(false)->change();
        });
    }
};
