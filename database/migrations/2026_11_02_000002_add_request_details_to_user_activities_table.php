<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activities', function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('subject_id');
            $table->string('user_agent', 512)->nullable()->after('ip_address');
            // The activity log page filters by module/status and always sorts by newest.
            $table->index(['module', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table): void {
            $table->dropIndex(['module', 'created_at']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
