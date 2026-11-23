<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders an admin sent (AdminReminderSender): what about, to how many people, with the admin's note.
 * The newest one per kind and target keeps the same reminder from going out more than once a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_reminders', function (Blueprint $table): void {
            $table->id();
            // pending_reviews | stale_drafts (groups, no target) | delineation | drone_layer (one record)
            $table->string('kind', 32);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients')->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['kind', 'target_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_reminders');
    }
};
