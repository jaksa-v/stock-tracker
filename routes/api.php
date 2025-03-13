<?php

use App\Http\Controllers\StockController;
use App\Http\Controllers\StockPriceController;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::prefix('/stocks')->group(function () {
    Route::get('/', [StockController::class, 'index']);
    Route::get('/{stock}', [StockController::class, 'show']);
});

Route::prefix('/prices')->group(function () {
    Route::get('/', [StockPriceController::class, 'getAllLatest']);
    Route::get('/{stock}', [StockPriceController::class, 'getSingleLatest']);
    Route::post('/multiple', [StockController::class, 'getMultipleLatest']);
    Route::post('/price-change', [StockController::class, 'calculatePriceChange']);
});
