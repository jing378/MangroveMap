<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delineations', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            $table->index(['is_approved', 'approved_at']);
            $table->index(['is_rejected', 'rejected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delineations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_approved', 'approved_at']);
            $table->dropIndex(['is_rejected', 'rejected_at']);
        });
    }
};
