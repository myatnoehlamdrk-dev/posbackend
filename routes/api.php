<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Hello from Laravel 12 API!',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
        Route::get('/me', [App\Http\Controllers\AuthController::class, 'me']);
    });
});

Route::apiResource('shops', App\Http\Controllers\ShopController::class);

Route::apiResource('suppliers', App\Http\Controllers\SupplierController::class);
Route::apiResource('packages', App\Http\Controllers\PackageController::class);
Route::apiResource('products', App\Http\Controllers\ProductController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('inventories', App\Http\Controllers\InventoryController::class);
    Route::apiResource('categories', App\Http\Controllers\CategoryController::class);
});

Route::post('images', [App\Http\Controllers\ImageController::class, 'store']);
