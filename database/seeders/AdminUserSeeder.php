<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'System Administrator',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@slack.app',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);
    }
}
