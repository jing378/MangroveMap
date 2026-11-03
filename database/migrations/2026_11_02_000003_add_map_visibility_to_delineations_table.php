<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delineations', function (Blueprint $table): void {
            // Set when an admin takes an approved delineation off the shared/public maps.
            $table->timestamp('hidden_at')->nullable()->after('rejection_notes');
            $table->foreignId('hidden_by')->nullable()->after('hidden_at')->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 500)->nullable()->after('hidden_by');
        });
    }

    public function down(): void
    {
        Schema::table('delineations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn(['hidden_at', 'hidden_reason']);
        });
    }
};
