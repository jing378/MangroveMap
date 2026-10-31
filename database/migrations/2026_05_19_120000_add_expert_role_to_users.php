<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'end_user', 'expert'])->default('end_user')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'expert')->update(['role' => 'end_user']);

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'end_user'])->default('end_user')->change();
        });
    }
};
