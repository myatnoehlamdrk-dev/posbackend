<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API route definitions (versioned and legacy)
|--------------------------------------------------------------------------
|
| This file defines `pos_define_api_routes()` rather than registering routes at
| load time, so that `routes/api.php` can invoke the exact same definitions
| twice -- once under `/api/v1` and once unversioned.
|
| Duplicating the list by hand would guarantee the two copies drift apart, and a
| route that exists in only one of them is the most expensive kind of bug to
| find: it works in staging and 404s on a till in production. One definition,
| two mounts, identical behaviour by construction.
|
| Named function rather than a closure because the routes have to be
| re-declarable under two prefixes, and because `php artisan route:cache`
| cannot serialise a closure to run twice.
|
| To add a breaking change, copy this file to `api_v2.php` and register it with
| its own prefix. Anything additive -- a new response field, an optional query
| parameter, a new endpoint -- belongs here and needs no new version.
|
*/

if (! function_exists('pos_define_api_routes')) {
    function pos_define_api_routes(): void
    {
    // The credential endpoints are the ones worth throttling tightly, so they
    // opt out of the broader `api` limiter that `bootstrap/app.php` appends to
    // the whole group. Both emit the standard `Retry-After` header on 429,
    // which is what the Flutter client parses before retrying.
    Route::middleware('throttle:auth')->prefix('auth')->group(function () {
        Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
        // Tightened on top of `throttle:auth` because that limiter is keyed by
        // IP, and IP is the one thing an attacker controls most cheaply. This
        // one is keyed by the email being tried as well, so distributing a
        // guessing run across many hosts still exhausts the per-account budget
        // that `security.login.max_attempts` locks on.
        Route::middleware('throttle:login')->post('/login', [App\Http\Controllers\AuthController::class, 'login']);

        Route::post('/register/send-otp', [App\Http\Controllers\PasswordResetController::class, 'registerSendOtp']);
        Route::post('/register/verify-otp', [App\Http\Controllers\PasswordResetController::class, 'registerVerifyOtp']);

        Route::post('/forgot-password/send-otp', [App\Http\Controllers\PasswordResetController::class, 'sendOtp']);
        Route::post('/forgot-password/verify-otp', [App\Http\Controllers\PasswordResetController::class, 'verifyOtp']);
        Route::post('/forgot-password/reset', [App\Http\Controllers\PasswordResetController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
            // Ends every session for this user, not just the one calling it.
            Route::post('/logout-all', [App\Http\Controllers\AuthController::class, 'logoutAll']);
            // Which devices are currently signed in. `name` is the device label
            // supplied at login, so this is the "which till is live right now"
            // query without a second table to keep in sync.
            Route::get('/sessions', [App\Http\Controllers\AuthController::class, 'sessions']);
            Route::get('/me', [App\Http\Controllers\AuthController::class, 'me']);
            Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'show']);
            Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update']);
            Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'changePassword']);
        });
    });

    // Public: let unauthenticated users search existing shops before registering.
    // Array syntax, not `Route::get($uri, Controller::class, 'method')` -- the
    // two-argument signature silently drops the third argument, leaving Laravel
    // to treat the class itself as an invokable action, which throws at
    // registration time and takes down every route in the app.
    Route::get('shops', [App\Http\Controllers\ShopController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        // Suppliers moved inside this group. It was declared above the group
        // and so inherited no middleware at all, which left the full CRUD
        // resource -- read, update, delete -- open to anyone who knew the URL.
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

        Route::apiResource('feedback', App\Http\Controllers\FeedbackController::class)
            ->only(['index', 'store', 'show', 'destroy']);

        Route::get('customers/analytics', [App\Http\Controllers\CustomerController::class, 'analytics']);
        Route::get('customers/search', [App\Http\Controllers\CustomerController::class, 'search']);
        Route::apiResource('customers', App\Http\Controllers\CustomerController::class);

        // PATCH routes for partial updates (same controller methods as PUT)
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
            Route::get('/monthly-sales', [App\Http\Controllers\DashboardController::class, 'monthlySales']);
            Route::get('/tables/products', [App\Http\Controllers\DashboardController::class, 'productsTable']);
            Route::get('/tables/stock', [App\Http\Controllers\DashboardController::class, 'stockTable']);
            Route::get('/tables/sales', [App\Http\Controllers\DashboardController::class, 'salesTable']);
            Route::get('/tables/bought-products', [App\Http\Controllers\DashboardController::class, 'boughtProductsTable']);
            Route::get('/tables/no-bought-products', [App\Http\Controllers\DashboardController::class, 'noBoughtProductsTable']);
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
        // Ends a user's sessions without touching the account -- the lost
        // handset case, where revoking access would be the wrong remedy.
        Route::delete('/users/{user}/sessions', [App\Http\Controllers\AdminController::class, 'revokeUserSessions']);
        Route::delete('/users/{user}', [App\Http\Controllers\AdminController::class, 'destroyUser']);
    });

    // Moved inside `auth:sanctum`. It used to sit at the end of the file,
    // outside every group, so anyone who knew the URL could spend the shop's
    // ImgBB quota and push arbitrary files through the store's account.
    Route::middleware('auth:sanctum')
        ->post('images', [App\Http\Controllers\ImageController::class, 'store']);
    }
}