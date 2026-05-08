<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('auth/login',           [AuthController::class, 'login'])->middleware('throttle:login');;
Route::post('register/send-otp',    [RegisterController::class, 'sendOtp'])->middleware('throttle:otp');
Route::post('register/verify-otp',  [RegisterController::class, 'verifyOtp']);
Route::post('register',             [RegisterController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {

    // Auth — semua role
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    // ========================
    // KASIR & ADMIN
    // ========================
    Route::middleware('cashier')->group(function () {

        // Ganti password kasir
        Route::patch('users/change-password',          [UserController::class, 'changePassword']);

        // Produk — kasir hanya bisa lihat
        Route::get('products',                         [ProductController::class, 'index']);
        Route::get('products/by-category',             [ProductController::class, 'byCategory']);
        Route::get('products/{product}',               [ProductController::class, 'show']);

        // Kategori — kasir hanya bisa lihat
        Route::get('categories',                       [CategoryController::class, 'index']);
        Route::get('categories/{category}',            [CategoryController::class, 'show']);

        // Transaksi — kasir bisa input dan lihat
        Route::post('transactions',                    [TransactionController::class, 'store']);
        Route::get('transactions/{transaction}',       [TransactionController::class, 'show']);

        // Riwayat transaksi kasir — hanya 1 minggu ke belakang
        Route::get('transactions/cashier/history',     [TransactionController::class, 'cashierHistory']);

        // Hutang — kasir bisa lihat dan proses bayar
        Route::get('debts',                            [DebtController::class, 'index']);
        Route::get('debts/{debt}',                     [DebtController::class, 'show']);
        Route::post('debts/{debt}/pay',                [DebtController::class, 'pay']);
        Route::patch('debts/{debt}',                   [DebtController::class, 'update']);

        // Void Transaksi
        Route::post('transactions/{transaction}/void', [TransactionController::class, 'void']);
    });

    // ========================
    // ADMIN ONLY
    // ========================
    Route::middleware('admin')->group(function () {

        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword']);

        // Produk — admin bisa full CRUD
        Route::post('products',                          [ProductController::class, 'store']);
        Route::put('products/{product}',                 [ProductController::class, 'update']);
        Route::patch('products/{product}',               [ProductController::class, 'update']);
        Route::delete('products/{product}',              [ProductController::class, 'destroy']);
        Route::patch('products/{product}/stock',         [ProductController::class, 'updateStock']);

        // Kategori — admin bisa full CRUD
        Route::post('categories',                        [CategoryController::class, 'store']);
        Route::put('categories/{category}',              [CategoryController::class, 'update']);
        Route::patch('categories/{category}',            [CategoryController::class, 'update']);
        Route::delete('categories/{category}',           [CategoryController::class, 'destroy']);
        Route::post('categories/{category}/merge',       [CategoryController::class, 'merge']);

        // Transaksi — admin bisa lihat semua dan hapus
        Route::get('transactions',                       [TransactionController::class, 'index']);
        Route::delete('transactions/{transaction}',      [TransactionController::class, 'destroy']);

        // Laporan — admin only
        Route::prefix('reports')->group(function () {
            Route::get('daily',               [ReportController::class, 'daily']);
            Route::get('weekly',              [ReportController::class, 'weekly']);
            Route::get('monthly',             [ReportController::class, 'monthly']);
            Route::get('range',               [ReportController::class, 'range']);
            Route::get('debts',               [ReportController::class, 'debtSummary']);
            Route::get('top-products',        [ReportController::class, 'topProducts']);
            Route::get('chart',               [ReportController::class, 'chartData']);
            Route::get('category-breakdown',  [ReportController::class, 'categoryBreakdown']);
        });

        // Void Transaksi
        Route::post('transactions/{transaction}/void', [TransactionController::class, 'void']);
    });
});
