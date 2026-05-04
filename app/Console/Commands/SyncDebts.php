<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\Transaction;
use Illuminate\Console\Command;

class SyncDebts extends Command
{
    protected $signature   = 'debts:sync';
    protected $description = 'Sinkronisasi hutang dari transaksi partial/debt yang belum punya record di tabel debts';

    public function handle(): void
    {
        // Ambil transaksi partial/debt yang belum ada di tabel debts
        $transactions = Transaction::with('debt')
            ->whereIn('status', ['partial', 'debt'])
            ->where('is_voided', false)
            ->whereDoesntHave('debt')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('Semua transaksi sudah tersinkronisasi.');
            return;
        }

        $this->info("Ditemukan {$transactions->count()} transaksi yang belum punya record hutang.");

        $count = 0;
        foreach ($transactions as $transaction) {
            $remaining = $transaction->total_amount - $transaction->paid_amount;

            Debt::create([
                'transaction_id' => $transaction->id,
                'customer_name'  => $transaction->customer_name ?? 'Tidak diketahui',
                'total_debt'     => $transaction->total_amount,
                'paid_amount'    => $transaction->paid_amount,
                'remaining_debt' => $remaining,
                'status'         => $transaction->status === 'debt' ? 'unpaid' : 'partial',
            ]);

            $this->line("✓ {$transaction->invoice_number} — sisa {$remaining}");
            $count++;
        }

        $this->info("Selesai. {$count} record hutang berhasil dibuat.");
    }
}