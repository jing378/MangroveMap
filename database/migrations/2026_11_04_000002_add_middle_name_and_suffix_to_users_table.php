<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('suffix', 20)->nullable()->after('last_name');
        });

        // Pull a suffix ("Jr.", "III") or middle initial out of names that already contain one.
        // Other accounts keep the first/last split they already have.
        DB::table('users')->select(['id', 'name'])->orderBy('id')->each(function ($user) {
            $parts = User::parseName((string) $user->name);

            if ($parts['suffix'] !== null || $parts['middle_name'] !== null) {
                DB::table('users')->where('id', $user->id)->update($parts);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'suffix']);
        });
    }
};
