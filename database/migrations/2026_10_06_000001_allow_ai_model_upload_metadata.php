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
            $table->decimal('accuracy', 5, 4)->nullable()->change();
            $table->integer('dataset_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('ai_models')->whereNull('accuracy')->update(['accuracy' => 0]);
        DB::table('ai_models')->whereNull('dataset_size')->update(['dataset_size' => 0]);

        Schema::table('ai_models', function (Blueprint $table) {
            $table->decimal('accuracy', 5, 4)->nullable(false)->change();
            $table->integer('dataset_size')->nullable(false)->change();
        });
    }
};
