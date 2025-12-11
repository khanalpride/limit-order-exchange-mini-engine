<?php

use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('profile', ProfileController::class)
    ->middleware('auth:sanctum')
    ->name('api.profile');
