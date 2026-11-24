<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::ADMIN]);
        $staffRole = Role::firstOrCreate(['name' => Role::STAFF]);
        $customerRole = Role::firstOrCreate(['name' => Role::CUSTOMER]);

        User::updateOrCreate(
            ['email' => 'admin@unisell.local'],
            [
                'name' => 'Admin UniSell',
                'password' => Hash::make('Admin12345!'),
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@unisell.local'],
            [
                'name' => 'Staff UniSell',
                'password' => Hash::make('Staff12345!'),
                'role_id' => $staffRole->id,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'customer@unisell.local'],
            [
                'name' => 'Customer Demo',
                'password' => Hash::make('Customer12345!'),
                'role_id' => $customerRole->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
