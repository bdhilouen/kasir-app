<?php

namespace Database\Seeders;

use App\Models\Debt;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'admin@warung.com')->first()
            ?? User::where('role', 'admin')->first();

        if (! $owner) {
            $this->command->error('Admin owner tidak ditemukan. Jalankan UserSeeder dulu.');

            return;
        }

        $products = Product::where('owner_id', $owner->id)->get();

        if ($products->isEmpty()) {
            $this->command->error('Produk kosong! Jalankan ProductSeeder dulu.');

            return;
        }

        mt_srand(20260507);
        fake()->seed(20260507);

        $startDate = Carbon::create(2026, 1, 1);
        $endDate = Carbon::create(2026, 4, 30);

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
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $qty,
                    'subtotal' => $subtotal,
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

            $invoiceNumber = 'INV-'.$date->format('Ymd').'-'.str_pad($i, 4, '0', STR_PAD_LEFT);

            $transaction = Transaction::updateOrCreate([
                'owner_id' => $owner->id,
                'invoice_number' => $invoiceNumber,
            ], [
                'transaction_date' => $date,
                'customer_name' => fake()->randomElement(['Budi', 'Siti', 'Andi', 'Dewi', 'Walk-in']),
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $change,
                'payment_method' => fake()->randomElement(['cash', 'transfer', 'qris']),
                'status' => $status,
                'is_voided' => false,
            ]);

            $transaction->transactionDetails()->delete();
            $transaction->debt()->delete();

            foreach ($details as $detail) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    ...$detail,
                ]);
            }

            if (in_array($status, ['debt', 'partial'], true)) {
                Debt::create([
                    'owner_id' => $owner->id,
                    'transaction_id' => $transaction->id,
                    'customer_name' => $transaction->customer_name ?? 'Tidak diketahui',
                    'total_debt' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'remaining_debt' => $totalAmount - $paidAmount,
                    'status' => $status === 'debt' ? 'unpaid' : 'partial',
                ]);
            }
        }

        $this->command->info('200 transaksi dummy berhasil dibuat!');
    }
}
