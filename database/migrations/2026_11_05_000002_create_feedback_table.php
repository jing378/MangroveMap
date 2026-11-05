<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Send Feedback" reports from the sidebar, reviewed by admins on the Feedback page.
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            // Kept when the account is deleted, so the report itself is not lost.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->text('message');
            $table->string('page_url', 2048)->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('status', 32)->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
