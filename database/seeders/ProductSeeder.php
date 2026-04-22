<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {

        // Matikan pengecekan foreign key sementara (jika ada relasi seperti category_id)
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        // Kosongkan isi tabel produk
        Product::truncate();

        // Nyalakan kembali pengecekan foreign key
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $products = [
            [
                'name' => 'Indomie Goreng',
                'sku' => 'INDO001',
                'price' => 3000,
                'stock' => 50,
                'min_stock' => 5,
                'description' => 'Mie instan goreng',
                'category' => 'Makanan'
            ],
            [
                'name' => 'Indomie Kuah Ayam Bawang',
                'sku' => 'INDO002',
                'price' => 3000,
                'stock' => 40,
                'min_stock' => 5,
                'description' => 'Mie kuah rasa ayam bawang',
                'category' => 'Makanan'
            ],
            [
                'name' => 'Aqua Botol 600ml',
                'sku' => 'AQ001',
                'price' => 4000,
                'stock' => 30,
                'min_stock' => 10,
                'description' => 'Air mineral',
                'category' => 'Minuman'
            ],
            [
                'name' => 'Teh Botol Sosro',
                'sku' => 'TB001',
                'price' => 5000,
                'stock' => 25,
                'min_stock' => 5,
                'description' => 'Minuman teh botol',
                'category' => 'Minuman'
            ],
            [
                'name' => 'Kopi Kapal Api Sachet',
                'sku' => 'KP001',
                'price' => 2000,
                'stock' => 100,
                'min_stock' => 20,
                'description' => 'Kopi sachet',
                'category' => 'Minuman'
            ],
            [
                'name' => 'Roti Tawar',
                'sku' => 'RT001',
                'price' => 12000,
                'stock' => 10,
                'min_stock' => 3,
                'description' => 'Roti tawar segar',
                'category' => 'Snack'
            ],
            [
                'name' => 'Telur Ayam 1 Kg',
                'sku' => 'TL001',
                'price' => 28000,
                'stock' => 15,
                'min_stock' => 5,
                'description' => 'Telur ayam ras',
                'category' => 'Sembako'
            ],
            [
                'name' => 'Minyak Goreng 1L',
                'sku' => 'MG001',
                'price' => 18000,
                'stock' => 20,
                'min_stock' => 5,
                'description' => 'Minyak goreng kemasan',
                'category' => 'Sembako'
            ],
            [
                'name' => 'Gula Pasir 1 Kg',
                'sku' => 'GP001',
                'price' => 15000,
                'stock' => 18,
                'min_stock' => 5,
                'description' => 'Gula pasir putih',
                'category' => 'Sembako'
            ],
            [
                'name' => 'Garam Dapur',
                'sku' => 'GR001',
                'price' => 3000,
                'stock' => 35,
                'min_stock' => 10,
                'description' => 'Garam halus',
                'category' => 'Sembako'
            ],
        ];

        foreach ($products as $product) {
            // Mencari ID kategori berdasarkan nama secara dinamis di dalam loop
            $category = Category::where('name', $product['category'])->first();

            Product::create([
                'category_id' => $category?->id, // Menggunakan null-safe operator
                'name'        => $product['name'],
                'sku'         => $product['sku'],
                'price'       => $product['price'],
                'stock'       => $product['stock'],
                'min_stock'   => $product['min_stock'],
                'description' => $product['description'],
            ]);
        }
    }
}
