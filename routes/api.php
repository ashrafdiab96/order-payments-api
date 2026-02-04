<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;

Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::put('/orders/{order}', [OrderController::class, 'update']);
