<?php

use Illuminate\Http\Request;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('products', ProductController::class);
Route::patch('products/{product}/stock', [ProductController::class, 'updateStock']);

Route::apiResource('transactions', TransactionController::class)
    ->only(['index', 'store', 'show', 'destroy']);

Route::get('debts', [DebtController::class, 'index']);
Route::get('debts/{debt}', [DebtController::class, 'show']);
Route::patch('debts/{debt}', [DebtController::class, 'update']);
Route::post('debts/{debt}/pay', [DebtController::class, 'pay']);

Route::prefix('reports')->group(function () {
    Route::get('daily',        [ReportController::class, 'daily']);
    Route::get('weekly',       [ReportController::class, 'weekly']);
    Route::get('monthly',      [ReportController::class, 'monthly']);
    Route::get('range',        [ReportController::class, 'range']);
    Route::get('debts',        [ReportController::class, 'debtSummary']);
    Route::get('top-products', [ReportController::class, 'topProducts']);
});

// Route::middleware('auth:sanctum')->group(function () {
//     // ... route products yang sebelumnya

//     Route::apiResource('transactions', TransactionController::class)
//          ->only(['index', 'store', 'show', 'destroy']);
// });