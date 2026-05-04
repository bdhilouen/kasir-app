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
        // Transaction details — sering di-join
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('transaction_id');
        });

        // Debts — sering filter by status
        Schema::table('debts', function (Blueprint $table) {
            $table->index(['status', 'customer_name']);
        });

        // Products — sering filter by category
        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            //
        });
    }
};
