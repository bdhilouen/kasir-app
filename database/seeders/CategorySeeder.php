<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Makanan',
                'description' => 'Berbagai jenis produk makanan.'
            ],
            [
                'name' => 'Minuman',
                'description' => 'Berbagai jenis minuman kemasan.'
            ],
            [
                'name' => 'Sembako',
                'description' => 'Kebutuhan pokok sehari-hari.'
            ],
            [
                'name' => 'Snack',
                'description' => 'Makanan ringan dan cemilan.'
            ],
            [
                'name' => 'Kebutuhan Rumah Tangga',
                'description' => 'Produk untuk kebutuhan rumah tangga.'
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

    }
}