<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Public — hanya login
Route::post('auth/login', [AuthController::class, 'login']);

// Protected — semua butuh token
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::patch('products/{product}/stock', [ProductController::class, 'updateStock']);

    // Categories
    Route::apiResource('categories', CategoryController::class);
    Route::post('categories/{category}/merge', [CategoryController::class, 'merge']);

    // Transactions
    Route::apiResource('transactions', TransactionController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    // Debts
    Route::get('debts',              [DebtController::class, 'index']);
    Route::get('debts/{debt}',       [DebtController::class, 'show']);
    Route::patch('debts/{debt}',     [DebtController::class, 'update']);
    Route::post('debts/{debt}/pay',  [DebtController::class, 'pay']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('daily',               [ReportController::class, 'daily']);
        Route::get('weekly',              [ReportController::class, 'weekly']);
        Route::get('monthly',             [ReportController::class, 'monthly']);
        Route::get('range',               [ReportController::class, 'range']);
        Route::get('debts',               [ReportController::class, 'debtSummary']);
        Route::get('top-products',        [ReportController::class, 'topProducts']);
        Route::get('chart',               [ReportController::class, 'chartData']);        // baru
        Route::get('category-breakdown',  [ReportController::class, 'categoryBreakdown']); // baru
    });
});
