<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('profile', ProfileController::class)
    ->middleware('auth:sanctum')
    ->name('api.profile');

Route::prefix('orders')->middleware('auth:sanctum')->name('api.orders.')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::post('/', [OrderController::class, 'store'])->name('store');
    Route::post('{id}/cancel', [OrderController::class, 'destroy'])->name('destroy');
});
