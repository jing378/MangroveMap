<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // When the user was last on the site (TrackUserPresence), for "Last seen" on the admin pages.
            // Unlike last_login_at it also counts users kept signed in by "remember me".
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_seen_at');
        });
    }
};
