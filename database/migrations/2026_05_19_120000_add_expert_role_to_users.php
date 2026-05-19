<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'end_user', 'expert') NOT NULL DEFAULT 'end_user'");
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'expert')->update(['role' => 'end_user']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'end_user') NOT NULL DEFAULT 'end_user'");
    }
};
