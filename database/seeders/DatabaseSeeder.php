<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create default roles
        $admin = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'is_admin' => true]
        );

        $kasambahay = Role::firstOrCreate(
            ['slug' => 'kasambahay'],
            ['name' => 'Kasambahay', 'is_admin' => false]
        );

        $boy = Role::firstOrCreate(
            ['slug' => 'boy'],
            ['name' => 'Boy', 'is_admin' => false]
        );

        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@checklist-imus.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'plain_password' => 'admin123',
                'role_id' => $admin->id,
                'is_active' => true,
            ]
        );

        // Create kasambahay user
        User::firstOrCreate(
            ['email' => 'kasambahay@checklist-imus.com'],
            [
                'name' => 'Kasambahay',
                'password' => Hash::make('staff123'),
                'plain_password' => 'staff123',
                'role_id' => $kasambahay->id,
                'is_active' => true,
            ]
        );

        // Create boy user
        User::firstOrCreate(
            ['email' => 'boy@checklist-imus.com'],
            [
                'name' => 'Boy',
                'password' => Hash::make('staff123'),
                'plain_password' => 'staff123',
                'role_id' => $boy->id,
                'is_active' => true,
            ]
        );
    }
}
