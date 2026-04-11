<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@checklist-imus.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // Create staff user
        User::firstOrCreate(
            ['email' => 'staff@checklist-imus.com'],
            [
                'name' => 'Staff User',
                'password' => Hash::make('staff123'),
                'role' => 'staff',
                'is_active' => true,
            ]
        );
    }
}
