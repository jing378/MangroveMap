<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // null = never applied; pending / approved / rejected otherwise.
            $table->string('expert_application_status', 20)->nullable()->index()->after('role');
            $table->text('expert_qualifications')->nullable()->after('expert_application_status');
            $table->timestamp('expert_applied_at')->nullable()->after('expert_qualifications');
            $table->timestamp('expert_reviewed_at')->nullable()->after('expert_applied_at');
            $table->foreignId('expert_reviewed_by')->nullable()->after('expert_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('expert_rejection_notes')->nullable()->after('expert_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('expert_reviewed_by');
            $table->dropColumn([
                'expert_application_status',
                'expert_qualifications',
                'expert_applied_at',
                'expert_reviewed_at',
                'expert_rejection_notes',
            ]);
        });
    }
};
