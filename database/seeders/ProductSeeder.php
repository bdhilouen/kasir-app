<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Indomie Goreng',
                'sku' => 'INDO001',
                'price' => 3000,
                'stock' => 50,
                'min_stock' => 5,
                'description' => 'Mie instan goreng'
            ],
            [
                'name' => 'Indomie Kuah Ayam Bawang',
                'sku' => 'INDO002',
                'price' => 3000,
                'stock' => 40,
                'min_stock' => 5,
                'description' => 'Mie kuah rasa ayam bawang'
            ],
            [
                'name' => 'Aqua Botol 600ml',
                'sku' => 'AQ001',
                'price' => 4000,
                'stock' => 30,
                'min_stock' => 10,
                'description' => 'Air mineral'
            ],
            [
                'name' => 'Teh Botol Sosro',
                'sku' => 'TB001',
                'price' => 5000,
                'stock' => 25,
                'min_stock' => 5,
                'description' => 'Minuman teh botol'
            ],
            [
                'name' => 'Kopi Kapal Api Sachet',
                'sku' => 'KP001',
                'price' => 2000,
                'stock' => 100,
                'min_stock' => 20,
                'description' => 'Kopi sachet'
            ],
            [
                'name' => 'Roti Tawar',
                'sku' => 'RT001',
                'price' => 12000,
                'stock' => 10,
                'min_stock' => 3,
                'description' => 'Roti tawar segar'
            ],
            [
                'name' => 'Telur Ayam 1 Kg',
                'sku' => 'TL001',
                'price' => 28000,
                'stock' => 15,
                'min_stock' => 5,
                'description' => 'Telur ayam ras'
            ],
            [
                'name' => 'Minyak Goreng 1L',
                'sku' => 'MG001',
                'price' => 18000,
                'stock' => 20,
                'min_stock' => 5,
                'description' => 'Minyak goreng kemasan'
            ],
            [
                'name' => 'Gula Pasir 1 Kg',
                'sku' => 'GP001',
                'price' => 15000,
                'stock' => 18,
                'min_stock' => 5,
                'description' => 'Gula pasir putih'
            ],
            [
                'name' => 'Garam Dapur',
                'sku' => 'GR001',
                'price' => 3000,
                'stock' => 35,
                'min_stock' => 10,
                'description' => 'Garam halus'
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}