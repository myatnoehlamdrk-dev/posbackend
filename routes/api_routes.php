<?php

use Illuminate\Support\Facades\Route;

if (! function_exists('pos_define_api_routes')) {
    function pos_define_api_routes(): void
    {
        Route::middleware('throttle:auth')->prefix('auth')->group(function () {
            Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
            Route::middleware('throttle:login')->post('/login', [App\Http\Controllers\AuthController::class, 'login']);
            Route::post('/register/send-otp', [App\Http\Controllers\EmailVerificationController::class, 'sendOtp']);
            Route::post('/register/verify-otp', [App\Http\Controllers\EmailVerificationController::class, 'verifyOtp']);
            Route::post('/forgot-password/send-otp', [App\Http\Controllers\PasswordResetController::class, 'sendOtp']);
            Route::post('/forgot-password/verify-otp', [App\Http\Controllers\PasswordResetController::class, 'verifyOtp']);
            Route::post('/forgot-password/reset', [App\Http\Controllers\PasswordResetController::class, 'resetPassword']);

            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
                Route::post('/logout-all', [App\Http\Controllers\AuthController::class, 'logoutAll']);
                Route::get('/sessions', [App\Http\Controllers\AuthController::class, 'sessions']);
                Route::get('/me', [App\Http\Controllers\AuthController::class, 'me']);
                Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'show']);
                Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update']);
                Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'changePassword']);
            });
        });

        Route::get('shops', [App\Http\Controllers\ShopController::class, 'index']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::apiResource('suppliers', App\Http\Controllers\SupplierController::class);
            Route::apiResource('shops', App\Http\Controllers\ShopController::class)->except(['index']);
            Route::patch('shops/{shop}', [App\Http\Controllers\ShopController::class, 'update']);
            Route::apiResource('packages', App\Http\Controllers\PackageController::class);
            Route::get('products/search', [App\Http\Controllers\ProductController::class, 'search']);
            Route::get('products/latest', [App\Http\Controllers\ProductController::class, 'latest']);
            Route::apiResource('products', App\Http\Controllers\ProductController::class);
            Route::apiResource('inventories', App\Http\Controllers\InventoryController::class);
            Route::get('categories/with-products', [App\Http\Controllers\CategoryController::class, 'withProducts']);
            Route::apiResource('categories', App\Http\Controllers\CategoryController::class);
            Route::apiResource('sales', App\Http\Controllers\SaleController::class);
            Route::delete('sales/{sale}/items/{saleItem}', [App\Http\Controllers\SaleController::class, 'destroyItem']);
            Route::get('sales/{sale}/voucher', [App\Http\Controllers\PdfController::class, 'generateVoucher']);
            Route::apiResource('orders', App\Http\Controllers\OrderController::class);
            Route::post('orders/{order}/items', [App\Http\Controllers\OrderController::class, 'addItems']);
            Route::apiResource('purchase-items', App\Http\Controllers\PurchaseItemController::class);
            Route::get('settings', [App\Http\Controllers\SettingController::class, 'show']);
            Route::put('settings', [App\Http\Controllers\SettingController::class, 'update']);
            Route::apiResource('feedback', App\Http\Controllers\FeedbackController::class)->only(['index', 'store', 'show', 'destroy']);
            Route::post('notifications/daily-report/check', [App\Http\Controllers\DailyStockReportController::class, 'check']);
            Route::get('customers/analytics', [App\Http\Controllers\CustomerController::class, 'analytics']);
            Route::get('customers/search', [App\Http\Controllers\CustomerController::class, 'search']);
            Route::apiResource('customers', App\Http\Controllers\CustomerController::class);
            Route::patch('products/{product}', [App\Http\Controllers\ProductController::class, 'update']);
            Route::patch('categories/{category}', [App\Http\Controllers\CategoryController::class, 'update']);
            Route::patch('packages/{package}', [App\Http\Controllers\PackageController::class, 'update']);
            Route::patch('sales/{sale}', [App\Http\Controllers\SaleController::class, 'update']);
            Route::patch('orders/{order}', [App\Http\Controllers\OrderController::class, 'update']);
            Route::patch('customers/{customer}', [App\Http\Controllers\CustomerController::class, 'update']);
            Route::patch('inventories/{inventory}', [App\Http\Controllers\InventoryController::class, 'update']);
            Route::patch('settings', [App\Http\Controllers\SettingController::class, 'update']);
            Route::patch('auth/profile', [App\Http\Controllers\ProfileController::class, 'update']);
            Route::prefix('stock-alerts')->group(function () {
                Route::get('/', [App\Http\Controllers\StockAlertController::class, 'index']);
                Route::get('/triggered', [App\Http\Controllers\StockAlertController::class, 'triggered']);
                Route::post('/check', [App\Http\Controllers\StockAlertController::class, 'check']);
                Route::post('/products/{product}', [App\Http\Controllers\StockAlertController::class, 'store']);
                Route::delete('/products/{product}', [App\Http\Controllers\StockAlertController::class, 'destroy']);
            });
            Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index']);
            Route::get('/dashboard/all', [App\Http\Controllers\DashboardController::class, 'all']);
            Route::get('/dashboard/sales-chart', [App\Http\Controllers\DashboardController::class, 'salesChart']);
            Route::get('/dashboard/top-products', [App\Http\Controllers\DashboardController::class, 'topProducts']);
            Route::get('/dashboard/last-month-sales', [App\Http\Controllers\DashboardController::class, 'lastMonthSales']);
            Route::get('/dashboard/top-category', [App\Http\Controllers\DashboardController::class, 'topCategory']);
            Route::get('/dashboard/top-supplier', [App\Http\Controllers\DashboardController::class, 'topSupplier']);
            Route::get('/dashboard/expiring-stocks', [App\Http\Controllers\DashboardController::class, 'expiringStocks']);
            Route::get('/dashboard/low-stocks', [App\Http\Controllers\DashboardController::class, 'lowStocks']);
            Route::get('/dashboard/monthly-sales', [App\Http\Controllers\DashboardController::class, 'monthlySales']);
            Route::get('/dashboard/tables/products', [App\Http\Controllers\DashboardController::class, 'productsTable']);
            Route::get('/dashboard/tables/stock', [App\Http\Controllers\DashboardController::class, 'stockTable']);
            Route::get('/dashboard/tables/sales', [App\Http\Controllers\DashboardController::class, 'salesTable']);
            Route::get('/dashboard/tables/bought-products', [App\Http\Controllers\DashboardController::class, 'boughtProductsTable']);
            Route::get('/dashboard/tables/no-bought-products', [App\Http\Controllers\DashboardController::class, 'noBoughtProductsTable']);
            Route::prefix('fcm-tokens')->group(function () {
                Route::post('/', [App\Http\Controllers\Api\FcmTokenController::class, 'register']);
                Route::delete('/', [App\Http\Controllers\Api\FcmTokenController::class, 'unregister']);
            });
        });

        Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
            Route::get('/dashboard', [App\Http\Controllers\AdminController::class, 'dashboard']);
            Route::prefix('fcm-tokens')->group(function () {
                Route::post('/', [App\Http\Controllers\Api\FcmTokenController::class, 'register']);
                Route::delete('/', [App\Http\Controllers\Api\FcmTokenController::class, 'unregister']);
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
            Route::put('/users/{user}/role', [App\Http\Controllers\AdminController::class, 'updateUserRole']);
            Route::delete('/users/{user}/sessions', [App\Http\Controllers\AdminController::class, 'revokeUserSessions']);
            Route::delete('/users/{user}', [App\Http\Controllers\AdminController::class, 'destroyUser']);
        });

        Route::middleware('auth:sanctum')
            ->post('images', [App\Http\Controllers\ImageController::class, 'store']);
    }
}
