<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate([
            'email' => 'admin@warung.com',
        ], [
            'name' => 'Admin Warung',
            'password' => Hash::make('warung123'),
            'role' => 'admin',
            'created_by' => null,
        ]);

        User::updateOrCreate([
            'email' => 'kasir@warung.com',
        ], [
            'name' => 'MaKasir',
            'password' => Hash::make('kasir123'),
            'role' => 'cashier',
            'created_by' => $admin->id,
        ]);
    }
}
