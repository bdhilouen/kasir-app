<?php

use Illuminate\Http\Request;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('products', ProductController::class);
Route::patch('products/{product}/stock', [ProductController::class, 'updateStock']);

Route::middleware('auth:sanctum')->group(function () {
    // ... route products yang sebelumnya

    Route::apiResource('transactions', TransactionController::class)
         ->only(['index', 'store', 'show', 'destroy']);
});