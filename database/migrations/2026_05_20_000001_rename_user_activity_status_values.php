<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_activities')->where('status', 'warning')->update(['status' => 'pending']);
        DB::table('user_activities')->where('status', 'danger')->update(['status' => 'rejected']);
    }

    public function down(): void
    {
        DB::table('user_activities')->where('status', 'pending')->update(['status' => 'warning']);
        DB::table('user_activities')->where('status', 'rejected')->update(['status' => 'danger']);
    }
};
