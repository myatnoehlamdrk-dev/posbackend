<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);

    Route::post('/register/send-otp', [App\Http\Controllers\PasswordResetController::class, 'registerSendOtp']);
    Route::post('/register/verify-otp', [App\Http\Controllers\PasswordResetController::class, 'registerVerifyOtp']);

    Route::post('/forgot-password/send-otp', [App\Http\Controllers\PasswordResetController::class, 'sendOtp']);
    Route::post('/forgot-password/verify-otp', [App\Http\Controllers\PasswordResetController::class, 'verifyOtp']);
    Route::post('/forgot-password/reset', [App\Http\Controllers\PasswordResetController::class, 'resetPassword']);

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
    Route::delete('sales/{sale}/items/{saleItem}', [App\Http\Controllers\SaleController::class, 'destroyItem']);
    Route::get('sales/{sale}/voucher', [App\Http\Controllers\PdfController::class, 'generateVoucher']);
    Route::apiResource('orders', App\Http\Controllers\OrderController::class);
    Route::apiResource('purchase-items', App\Http\Controllers\PurchaseItemController::class);

    Route::get('settings', [App\Http\Controllers\SettingController::class, 'show']);
    Route::put('settings', [App\Http\Controllers\SettingController::class, 'update']);

    Route::get('customers/analytics', [App\Http\Controllers\CustomerController::class, 'analytics']);
    Route::get('customers/search', [App\Http\Controllers\CustomerController::class, 'search']);
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
        Route::get('/all', [App\Http\Controllers\DashboardController::class, 'all']);
        Route::get('/stats', [App\Http\Controllers\DashboardController::class, 'stats']);
        Route::get('/sales-chart', [App\Http\Controllers\DashboardController::class, 'salesChart']);
        Route::get('/top-products', [App\Http\Controllers\DashboardController::class, 'topProducts']);
        Route::get('/recent-sales', [App\Http\Controllers\DashboardController::class, 'recentSales']);
        Route::get('/category-trend', [App\Http\Controllers\DashboardController::class, 'categoryTrend']);
        Route::get('/least-products', [App\Http\Controllers\DashboardController::class, 'leastProducts']);
    });
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\AdminController::class, 'dashboard']);
    Route::get('/dashboard/sales-chart', [App\Http\Controllers\AdminController::class, 'salesChart']);
    Route::get('/dashboard/top-products', [App\Http\Controllers\AdminController::class, 'topProducts']);

    Route::get('/shops', [App\Http\Controllers\AdminController::class, 'shops']);
    Route::put('/shops/{shop}/toggle-active', [App\Http\Controllers\AdminController::class, 'toggleShopActive']);
    Route::delete('/shops/{shop}', [App\Http\Controllers\AdminController::class, 'destroyShop']);

    Route::get('/users', [App\Http\Controllers\AdminController::class, 'users']);
    Route::get('/users/pending', [App\Http\Controllers\AdminController::class, 'pendingUsers']);
    Route::put('/users/{user}/approve', [App\Http\Controllers\AdminController::class, 'approveUser']);
    Route::put('/users/{user}/toggle-active', [App\Http\Controllers\AdminController::class, 'toggleUserActive']);
    Route::delete('/users/{user}', [App\Http\Controllers\AdminController::class, 'destroyUser']);
});

Route::post('images', [App\Http\Controllers\ImageController::class, 'store']);
