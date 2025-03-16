<?php

use App\Http\Controllers\StockController;
use App\Http\Controllers\StockPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('/stocks')->group(function () {
    Route::get('/', [StockController::class, 'index'])->name('stocks.index');
    Route::get('/{stock}', [StockController::class, 'show'])->name('stocks.show');
});

Route::prefix('/prices')->group(function () {
    Route::get('/', [StockPriceController::class, 'getAllLatest'])->name('prices.latest');
    Route::get('/batch', [StockPriceController::class, 'getMultipleLatest'])->name('prices.batch');
    Route::get('/change', [StockPriceController::class, 'calculatePriceChange'])->name('prices.change');
    Route::get('/{stock}', [StockPriceController::class, 'getSingleLatest'])->name('prices.show');
});
