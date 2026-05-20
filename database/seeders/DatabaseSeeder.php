<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'organization' => 'MangroveMap Admin',
                'phone' => '09123456789',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Resident',
                'password' => Hash::make('password'),
                'role' => 'end_user',
                'organization' => 'Demo Organization',
                'phone' => '09987654321',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'expert@example.com'],
            [
                'name' => 'Expert User',
                'password' => Hash::make('password'),
                'role' => 'expert',
                'organization' => 'MangroveMap Review',
                'phone' => '09112233445',
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            GenusSeeder::class,
            MangroveDataSeeder::class,
            DelineationSeeder::class,
            AnalysisSeeder::class,
            AIModelSeeder::class,
            UserActivitySeeder::class,
        ]);
    }
}
