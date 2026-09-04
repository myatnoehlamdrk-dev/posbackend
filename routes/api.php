<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
        Route::get('/me', [App\Http\Controllers\AuthController::class, 'me']);
        Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'show']);
        Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update']);
        Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'changePassword']);
    });
});

Route::apiResource('shops', App\Http\Controllers\ShopController::class);

Route::apiResource('suppliers', App\Http\Controllers\SupplierController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('packages', App\Http\Controllers\PackageController::class);
    Route::get('products/search', [App\Http\Controllers\ProductController::class, 'search']);
    Route::apiResource('products', App\Http\Controllers\ProductController::class);
    Route::apiResource('inventories', App\Http\Controllers\InventoryController::class);
    Route::apiResource('categories', App\Http\Controllers\CategoryController::class);
    Route::apiResource('sales', App\Http\Controllers\SaleController::class);
    Route::apiResource('orders', App\Http\Controllers\OrderController::class);
    Route::apiResource('purchase-items', App\Http\Controllers\PurchaseItemController::class);

    Route::get('settings', [App\Http\Controllers\SettingController::class, 'show']);
    Route::put('settings', [App\Http\Controllers\SettingController::class, 'update']);

    Route::apiResource('customers', App\Http\Controllers\CustomerController::class);

    Route::prefix('stock-alerts')->group(function () {
        Route::get('/', [App\Http\Controllers\StockAlertController::class, 'index']);
        Route::get('/triggered', [App\Http\Controllers\StockAlertController::class, 'triggered']);
        Route::post('/check', [App\Http\Controllers\StockAlertController::class, 'check']);
        Route::post('/{product}', [App\Http\Controllers\StockAlertController::class, 'store']);
        Route::delete('/{product}', [App\Http\Controllers\StockAlertController::class, 'destroy']);
    });

    Route::prefix('audit')->group(function () {
        Route::get('/', [App\Http\Controllers\AuditController::class, 'index']);
        Route::get('/recent', [App\Http\Controllers\AuditController::class, 'recent']);
        Route::get('/sale/{sale}', [App\Http\Controllers\AuditController::class, 'forSale']);
        Route::get('/order/{order}', [App\Http\Controllers\AuditController::class, 'forOrder']);
    });

    Route::prefix('export')->group(function () {
        Route::get('/sales', [App\Http\Controllers\ExportController::class, 'sales']);
        Route::get('/orders', [App\Http\Controllers\ExportController::class, 'orders']);
    });

    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [App\Http\Controllers\DashboardController::class, 'stats']);
        Route::get('/sales-chart', [App\Http\Controllers\DashboardController::class, 'salesChart']);
        Route::get('/top-products', [App\Http\Controllers\DashboardController::class, 'topProducts']);
        Route::get('/recent-sales', [App\Http\Controllers\DashboardController::class, 'recentSales']);
    });
});

Route::post('images', [App\Http\Controllers\ImageController::class, 'store']);
