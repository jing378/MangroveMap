<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admins no longer hide delineations from the map (Map Layers keeps only Delete; experts decide what is
 * published). Dropping the columns puts every delineation an admin had hidden back on the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delineations', 'hidden_at')) {
            return;
        }

        Schema::table('delineations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn(['hidden_at', 'hidden_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('delineations', function (Blueprint $table): void {
            $table->timestamp('hidden_at')->nullable()->after('rejection_notes');
            $table->foreignId('hidden_by')->nullable()->after('hidden_at')->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 500)->nullable()->after('hidden_by');
        });
    }
};
