<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Hello from Laravel 12 API!',
    ]);
});


    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);

    Route::middleware('auth.token')->group(function () {
        Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
        Route::get('/me', [App\Http\Controllers\AuthController::class, 'me']);
    });


Route::middleware('auth.token')->apiResource('shops', App\Http\Controllers\ShopController::class);
