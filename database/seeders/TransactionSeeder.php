<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        if ($products->isEmpty()) {
            $this->command->error('Produk kosong! Jalankan ProductSeeder dulu.');
            return;
        }

        $startDate = Carbon::create(2026, 1, 1);
        $endDate   = Carbon::create(2026, 4, 30);

        for ($i = 1; $i <= 200; $i++) {

            $date = Carbon::createFromTimestamp(
                rand($startDate->timestamp, $endDate->timestamp)
            );

            $date->setTime(rand(7, 22), rand(0, 59));
            
            $itemsCount = rand(1, 5);

            $totalAmount = 0;
            $details = [];

            for ($j = 0; $j < $itemsCount; $j++) {
                $product = $products->random();
                $qty = rand(1, 3);

                $subtotal = $product->price * $qty;
                $totalAmount += $subtotal;

                $details[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $product->price,
                    'quantity'     => $qty,
                    'subtotal'     => $subtotal,
                ];
            }

            // Payment logic
            $paidAmount = rand(0, $totalAmount + 10000);

            if ($paidAmount >= $totalAmount) {
                $status = 'paid';
                $change = $paidAmount - $totalAmount;
            } elseif ($paidAmount == 0) {
                $status = 'debt';
                $change = 0;
            } else {
                $status = 'partial';
                $change = 0;
            }

            $transaction = Transaction::create([
                'invoice_number'  => 'INV-' . $date->format('Ymd') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'transaction_date'=> $date,
                'customer_name'   => fake()->randomElement(['Budi', 'Siti', 'Andi', 'Dewi', 'Walk-in']),
                'total_amount'    => $totalAmount,
                'paid_amount'     => $paidAmount,
                'change_amount'   => $change,
                'payment_method'  => fake()->randomElement(['cash', 'transfer', 'qris']),
                'status'          => $status,
                'is_voided'       => false,
            ]);

            foreach ($details as $detail) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    ...$detail
                ]);
            }
        }

        $this->command->info('200 transaksi dummy berhasil dibuat!');
    }
}