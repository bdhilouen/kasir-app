<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_voided')->default(false)->after('status');
            $table->timestamp('voided_at')->nullable()->after('is_voided');
            $table->string('void_reason', 255)->nullable()->after('voided_at');
            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['is_voided', 'voided_at', 'void_reason', 'voided_by']);
        });
    }
};
