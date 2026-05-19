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
        // Create Admin Account
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'organization' => 'MangroveMap Admin',
            'phone' => '+1234567890',
        ]);

        // Create Demo Resident Account
        User::factory()->create([
            'name' => 'Demo Resident',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
            'role' => 'end_user',
            'organization' => 'Demo Organization',
            'phone' => '+0987654321',
        ]);

        // Create Expert Account
        User::factory()->create([
            'name' => 'Expert User',
            'email' => 'expert@example.com',
            'password' => Hash::make('password'),
            'role' => 'expert',
            'organization' => 'MangroveMap Review',
            'phone' => '+1122334455',
            'email_verified_at' => now(),
        ]);
    }
}
