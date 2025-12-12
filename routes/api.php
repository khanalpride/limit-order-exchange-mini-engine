<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('profile', ProfileController::class)
    ->middleware('auth:sanctum')
    ->name('api.profile');

Route::get('orders', [OrderController::class, 'index'])
    ->middleware('auth:sanctum')
    ->name('api.orders');
