<?php

use App\Http\Controllers\StockController;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/stocks', [StockController::class, 'index']);
Route::get('/stocks/{stock}', [StockController::class, 'show']);
