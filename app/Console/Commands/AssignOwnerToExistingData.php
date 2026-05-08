<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignOwnerToExistingData extends Command
{
    protected $signature   = 'owner:assign';
    protected $description = 'Assign owner_id ke data lama yang belum punya owner';

    public function handle(): void
    {
        $firstAdmin = User::where('role', 'admin')->orderBy('id')->first();

        if (!$firstAdmin) {
            $this->error('Tidak ada user admin ditemukan.');
            return;
        }

        $tables = ['categories', 'products', 'transactions', 'debts'];

        foreach ($tables as $table) {
            $count = DB::table($table)
                ->whereNull('owner_id')
                ->update(['owner_id' => $firstAdmin->id]);

            $this->line("✓ {$table}: {$count} rows diassign ke {$firstAdmin->email}");
        }

        $this->info('Selesai.');
    }
}