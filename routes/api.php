<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/low-stock', [ProductController::class, 'lowStock']);

Route::post('/orders', [OrderController::class, 'store']);

Route::get('/customers/{email}', [CustomerController::class, 'show'])->where('email', '[^/]+');
Route::get('/customers/{email}/orders', [CustomerController::class, 'orders'])->where('email', '[^/]+');
