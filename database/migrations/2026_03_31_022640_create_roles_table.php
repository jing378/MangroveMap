<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Add role column to users
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'end_user'])->default('end_user')->after('email');
            $table->string('organization')->nullable()->after('role');
            $table->string('phone')->nullable()->after('organization');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'organization', 'phone']);
        });
    }
};