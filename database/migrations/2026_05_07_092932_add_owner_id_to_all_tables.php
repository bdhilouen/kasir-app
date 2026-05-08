<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['categories', 'products', 'transactions', 'debts'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('owner_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        foreach (['categories', 'products', 'transactions', 'debts'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['owner_id']);
                $blueprint->dropColumn('owner_id');
            });
        }
    }
};
