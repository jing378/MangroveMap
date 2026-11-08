<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Proof of expertise uploaded with the application, kept on the private local disk.
            $table->string('expert_certificate_path')->nullable()->after('expert_qualifications');
            $table->string('expert_certificate_name')->nullable()->after('expert_certificate_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['expert_certificate_path', 'expert_certificate_name']);
        });
    }
};
