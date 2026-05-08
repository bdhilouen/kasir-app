<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'admin@warung.com')->first()
            ?? User::where('role', 'admin')->first();

        if (! $owner) {
            $this->command->error('Admin owner tidak ditemukan. Jalankan UserSeeder dulu.');

            return;
        }

        $categories = [
            [
                'name' => 'Makanan',
                'description' => 'Berbagai jenis produk makanan.',
            ],
            [
                'name' => 'Minuman',
                'description' => 'Berbagai jenis minuman kemasan.',
            ],
            [
                'name' => 'Sembako',
                'description' => 'Kebutuhan pokok sehari-hari.',
            ],
            [
                'name' => 'Snack',
                'description' => 'Makanan ringan dan cemilan.',
            ],
            [
                'name' => 'Kebutuhan Rumah Tangga',
                'description' => 'Produk untuk kebutuhan rumah tangga.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate([
                'owner_id' => $owner->id,
                'name' => $category['name'],
            ], [
                'description' => $category['description'],
            ]);
        }
    }
}
