<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delineations', function (Blueprint $table) {
            $table->boolean('is_rejected')->default(false)->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('is_rejected');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete()->after('rejected_at');
            $table->text('rejection_notes')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('delineations', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['is_rejected', 'rejected_at', 'rejected_by', 'rejection_notes']);
        });
    }
};
