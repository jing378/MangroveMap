<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the user finished or skipped the welcome walkthrough (partials/onboarding-tour). Null = it opens by
 * itself on their next page. Kept on the account, so it does not come back on another device or after logout.
 * Existing users stay null: everyone sees the walkthrough once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarded_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('onboarded_at');
        });
    }
};
