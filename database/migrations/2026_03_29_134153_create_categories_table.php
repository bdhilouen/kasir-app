<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 110)->unique(); // url-friendly name
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Tambah kolom category_id ke products
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')
                  ->nullable()                    // nullable biar produk lama tidak error
                  ->constrained('categories')
                  ->onDelete('set null')           // kalau kategori dihapus, produk tidak ikut terhapus
                  ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('categories');
    }
};