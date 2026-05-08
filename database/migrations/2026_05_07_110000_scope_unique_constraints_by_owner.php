<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_unique');
            $table->dropUnique('categories_slug_unique');

            $table->unique(['owner_id', 'name'], 'categories_owner_id_name_unique');
            $table->unique(['owner_id', 'slug'], 'categories_owner_id_slug_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_sku_unique');

            $table->unique(['owner_id', 'sku'], 'products_owner_id_sku_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_invoice_number_unique');

            $table->unique(['owner_id', 'invoice_number'], 'transactions_owner_id_invoice_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_owner_id_invoice_number_unique');

            $table->unique('invoice_number');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_owner_id_sku_unique');

            $table->unique('sku');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_owner_id_slug_unique');
            $table->dropUnique('categories_owner_id_name_unique');

            $table->unique('slug');
            $table->unique('name');
        });
    }
};
