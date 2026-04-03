<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Admin Warung',
            'email'    => 'admin@warung.com',
            'password' => Hash::make('warung123'),
            'role'     => 'admin',
        ]);

        User::create([
            'name'     => 'MaKasir',
            'email'    => 'kasir@warung.com',
            'password' => Hash::make('kasir123'),
            'role'     => 'cashier',
        ]);
    }
}